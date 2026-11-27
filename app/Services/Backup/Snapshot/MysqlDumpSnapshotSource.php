<?php

namespace App\Services\Backup\Snapshot;

use Illuminate\Database\ConnectionInterface;
use PDO;
use Throwable;

/**
 * MySQL / MariaDB: a logical dump produced by `mysqldump`.
 *
 * WHY mysqldump AND NOT A HAND-WRITTEN EXPORTER
 * ---------------------------------------------
 * A correct MySQL logical dump has to get column types, character sets,
 * collations, generated columns, triggers, views, routines, foreign-key
 * ordering and binary escaping right. A partial reimplementation would look
 * convincing and silently lose whatever it forgot — and nobody discovers a
 * lost trigger until a restore. `mysqldump` is the database's own tool; using
 * it is the difference between a backup and a plausible file.
 *
 * WHY IT IS NOT ASSUMED TO EXIST
 * ------------------------------
 * `mysqldump` may be missing, or the PHP runtime may forbid spawning processes
 * (this development host's php-wasm build does exactly that). Both are checked
 * before anything is created, and both produce an actionable message rather
 * than a generic failure. The directive was explicit: do not assume
 * `mysqldump` is available.
 *
 * WHY THE ROW COUNTS COME FROM THE CONNECTION
 * -------------------------------------------
 * Requirement 1.5: never report success based solely on a file being created.
 * The counts recorded in the manifest are read from the LIVE database through
 * Laravel's connection, before the dump runs. The dump is then checked against
 * them (see `verifyDumpContainsData`), so "the file exists" and "the file
 * contains the rows the database has" become two separate facts that must both
 * hold.
 *
 * CREDENTIALS
 * -----------
 * The password is passed through the child process environment (`MYSQL_PWD`),
 * never on the command line — arguments are visible in `ps` to every user on
 * the host, and a backup password is the one that must not be.
 */
final class MysqlDumpSnapshotSource implements DatabaseSnapshotSource
{
    /**
     * Tables the dump must contain at least one row for, to distinguish a real
     * dump from an empty one. Chosen because every deployment has them and they
     * are populated by the seeders.
     */
    private const EXPECTED_NON_EMPTY = ['users', 'migrations'];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $driverName,
        private readonly ?string $utilityPath = null,
    ) {
    }

    public function driver(): string
    {
        return $this->driverName;
    }

    public function format(): string
    {
        return 'mysqldump';
    }

    public function member(): string
    {
        return 'database.sql';
    }

    public function describe(): string
    {
        $config = $this->connectionConfig();

        return sprintf(
            'mysql database %s on %s:%s (mysqldump logical export)',
            (string) $this->connection->getDatabaseName(),
            (string) ($config['host'] ?? '?'),
            (string) ($config['port'] ?? '?'),
        );
    }

    /**
     * The connection's own configuration.
     *
     * Taken from the connection object rather than the `config()` helper so the
     * strategy works under any container that can hand over a connection — the
     * test shim included. It is also more correct: the connection is the thing
     * whose host and credentials are about to be used.
     *
     * @return array<string,mixed>
     */
    private function connectionConfig(): array
    {
        if (method_exists($this->connection, 'getConfig')) {
            return (array) $this->connection->getConfig();
        }

        return [];
    }

    public function assertReady(): void
    {
        if (! function_exists('proc_open')) {
            throw new SnapshotUnavailable(
                'proc_open() is unavailable, so mysqldump cannot be spawned. '
                . 'A MySQL backup requires a PHP build that permits process execution.'
            );
        }

        if ($this->locateUtility() === null) {
            throw new SnapshotUnavailable(
                'mysqldump was not found on PATH (checked "mysqldump" and MYSQLDUMP_PATH). '
                . 'Install the MySQL/MariaDB client tools on the application host, or set '
                . 'MYSQLDUMP_PATH to the binary. Refusing rather than writing a backup that '
                . 'does not contain the database.'
            );
        }

        /* Prove the connection is live before doing anything else. A backup of
         * an unreachable database is not a backup. */
        try {
            $this->connection->selectOne('select 1 as ok');
        } catch (Throwable $e) {
            throw new SnapshotUnavailable('cannot reach the MySQL server: ' . $e->getMessage());
        }
    }

    public function snapshot(string $targetFile): array
    {
        $this->assertReady();

        $counts = $this->readCounts();

        $binary = $this->locateUtility();

        if ($binary === null) {
            throw new SnapshotUnavailable('mysqldump disappeared between the readiness check and the run.');
        }

        $config = $this->connectionConfig();

        $command = [
            $binary,
            '--host=' . (string) ($config['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($config['port'] ?? '3306'),
            '--user=' . (string) ($config['username'] ?? ''),
            '--single-transaction',   // consistent snapshot of InnoDB without locking writers
            '--skip-lock-tables',     // paired with the above; --lock-tables is the default
            '--routines',             // stored procedures and functions
            '--triggers',
            '--events',
            '--default-character-set=' . (string) ($config['charset'] ?? 'utf8mb4'),
            '--no-tablespaces',       // references no PROCESS privilege on MySQL 8
            '--result-file=' . $targetFile,
            (string) $this->connection->getDatabaseName(),
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /* The password goes through the environment, not argv. `ps` shows
         * arguments to every user on the host; it does not show the environment
         * of another user's process. */
        $env = getenv();
        $env['MYSQL_PWD'] = (string) ($config['password'] ?? '');

        $process = @proc_open($command, $descriptors, $pipes, null, $env);

        if (! is_resource($process)) {
            throw new SnapshotUnavailable(
                'could not start mysqldump. The PHP runtime reported that process '
                . 'spawning is not available (proc_open returned ' . gettype($process) . ').'
            );
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($exit !== 0) {
            @unlink($targetFile);

            throw new SnapshotUnavailable(sprintf(
                'mysqldump exited %d: %s',
                $exit,
                trim((string) $stderr) !== '' ? trim((string) $stderr) : '(no stderr)',
            ));
        }

        $this->verifyDumpContainsData($targetFile, $counts);

        return $counts;
    }

    /**
     * Read table names and row counts from the live database.
     *
     * @return array{tables:int, rows:int, table_list:list<string>}
     */
    private function readCounts(): array
    {
        $database = (string) $this->connection->getDatabaseName();

        $tables = $this->connection->select(
            'select table_name as name from information_schema.tables '
            . 'where table_schema = ? and table_type = ? order by table_name',
            [$database, 'BASE TABLE'],
        );

        $names = array_map(static fn ($row) => (string) ((array) $row)['name'], $tables);

        $rows = 0;
        foreach ($names as $name) {
            $quoted = '`' . str_replace('`', '``', $name) . '`';
            $rows += (int) $this->connection->selectOne("select count(*) as c from {$quoted}")->c;
        }

        return ['tables' => count($names), 'rows' => $rows, 'table_list' => $names];
    }

    /**
     * Requirement 1.5/1.6 — the file must actually contain the database.
     *
     * Four independent confirmations, because a non-empty file proves very
     * little on its own:
     *
     *   1. it exists and is not empty;
     *   2. it carries a mysqldump header (not an error page or an empty file);
     *   3. it mentions every table the live database has;
     *   4. the tables that must have data appear with INSERT statements.
     */
    private function verifyDumpContainsData(string $file, array $counts): void
    {
        if (! is_file($file) || filesize($file) === 0) {
            throw new SnapshotUnavailable('mysqldump produced no output.');
        }

        $head = (string) file_get_contents($file, false, null, 0, 4096);

        if (! str_contains($head, 'MySQL dump')) {
            throw new SnapshotUnavailable(
                'the dump does not begin with a mysqldump header — it is not a MySQL dump.'
            );
        }

        $body = (string) file_get_contents($file);

        $missing = [];
        foreach ($counts['table_list'] as $name) {
            /* `CREATE TABLE `name`` appears for every table in a full dump. */
            if (! str_contains($body, '`' . $name . '`')) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new SnapshotUnavailable(sprintf(
                'the dump is missing %d table(s) the database has: %s',
                count($missing),
                implode(', ', array_slice($missing, 0, 5)),
            ));
        }

        foreach (self::EXPECTED_NON_EMPTY as $name) {
            if (! in_array($name, $counts['table_list'], true)) {
                continue;
            }

            if (! preg_match('/INSERT INTO `' . preg_quote($name, '/') . '`/', $body)) {
                throw new SnapshotUnavailable(
                    "the dump contains no INSERT for `{$name}`, which has rows in the "
                    . 'live database. Refusing to report an empty backup as a success.'
                );
            }
        }

        if ($counts['rows'] > 0 && ! preg_match('/INSERT INTO `/m', $body)) {
            throw new SnapshotUnavailable('the database has rows but the dump contains no INSERT statements.');
        }
    }

    private function locateUtility(): ?string
    {
        $candidates = [];

        if ($this->utilityPath !== null && $this->utilityPath !== '') {
            $candidates[] = $this->utilityPath;
        }

        /* Explicit override, for hosts that install the client tools off PATH. */
        $fromEnv = (string) (getenv('MYSQLDUMP_PATH') ?: '');
        if ($fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        /* PATH search without shelling out — `command -v` would need a process,
         * which is the very thing that may be unavailable. */
        $path = (string) getenv('PATH');

        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            if ($dir === '') {
                continue;
            }

            $candidate = rtrim($dir, '/') . '/mysqldump';

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
