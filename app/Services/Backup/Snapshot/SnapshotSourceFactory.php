<?php

namespace App\Services\Backup\Snapshot;

use Illuminate\Database\DatabaseManager;

/**
 * Choose the backup strategy from the connection the application is ACTUALLY
 * using, and refuse to guess when there isn't one.
 *
 * THE BUG THIS REPLACES
 * ---------------------
 * The previous implementation read
 * `config('database.connections.sqlite.database')` directly. On the SQLite
 * deployment that is the same file the application reads, so it worked and
 * every test passed. Under `DB_CONNECTION=mysql` that key resolves to the
 * schema NAME (`vira_prod`), the command treated it as a filesystem path, and
 * it neither backed up the live database nor refused — it simply produced
 * whatever it could. A backup system that is wrong only on the deployment that
 * matters is worse than one that is obviously missing.
 *
 * So the source is resolved from `$db->connection()` — the manager's default,
 * which respects `DB_CONNECTION` and any runtime `setDefaultConnection()` — not
 * from a config key naming a specific driver.
 *
 * FAILING CLOSED
 * --------------
 * An unimplemented driver throws here, before any directory is created, any
 * file is written, or any success is reported. The message names the driver and
 * says what would be needed, because "backup failed" is not actionable.
 */
final class SnapshotSourceFactory
{
    public function __construct(private readonly DatabaseManager $manager)
    {
    }

    /**
     * Build one from the container's own manager.
     *
     * `DatabaseManager` takes the application in its constructor and is not
     * auto-resolvable, so `app(SnapshotSourceFactory::class)` throws
     * BindingResolutionException. A named constructor keeps the dependency
     * explicit and gives one place to change if the container binding ever
     * moves.
     */
    public static function fromContainer(): self
    {
        /* `db` is a real binding in the running application. In the project's
         * test shim only the facade's container has it, so fall back to the
         * facade's manager rather than making the harness reach into private
         * container wiring. Both paths end at the same DatabaseManager. */
        try {
            $manager = app('db');

            if ($manager instanceof DatabaseManager) {
                return new self($manager);
            }
        } catch (\Throwable) {
            /* fall through */
        }

        /* `DB::getDatabaseManager()` is NOT a facade accessor — it resolves
         * through __callStatic to the CONNECTION and throws. The manager is the
         * facade's resolved root instance. */
        $root = \Illuminate\Support\Facades\DB::getFacadeRoot();

        if ($root instanceof DatabaseManager) {
            return new self($root);
        }

        throw new SnapshotUnavailable(
            'cannot resolve the database manager from the container; '
            . 'no backup source can be chosen.'
        );
    }

    public function make(): DatabaseSnapshotSource
    {
        $connection = $this->manager->connection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => new SqliteSnapshotSource(
                (string) $connection->getDatabaseName(),
            ),

            /* Laravel reports MariaDB as its own driver name on recent versions
             * and as `mysql` on older ones; both use mysqldump, so both map to
             * the same strategy. */
            'mysql', 'mariadb' => new MysqlDumpSnapshotSource(
                $connection,
                $driver,
            ),

            default => throw new SnapshotUnavailable(sprintf(
                'no backup strategy is implemented for the "%s" driver. Refusing to '
                . 'write a backup that would not contain this database. Supported '
                . 'drivers: sqlite, mysql, mariadb.',
                $driver,
            )),
        };
    }

    /** The driver that will be used, without constructing a strategy. */
    public function driver(): string
    {
        return (string) $this->manager->connection()->getDriverName();
    }
}
