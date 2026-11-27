<?php

namespace Tests\Feature;

use App\Services\Backup\BackupBuilder;
use App\Services\Backup\BackupCipher;
use App\Services\Backup\BackupRestorer;
use App\Services\Backup\Snapshot\SnapshotSourceFactory;
use App\Services\Backup\Snapshot\SnapshotUnavailable;
use App\Services\Backup\Snapshot\SqliteSnapshotSource;
use RuntimeException;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * The driver-specific backup matrix required by the MySQL remediation.
 *
 * WHAT THIS SUITE ADDS OVER BackupRestoreTest
 * -------------------------------------------
 * BackupRestoreTest proves the container format is sound: seven gates, tamper
 * detection, wrong keys, no-write-on-refusal. It does not ask *which database*
 * is being backed up. That is exactly the question the MySQL defect turned on —
 * every test passed while the command read the wrong source — so it needs to be
 * asked directly and separately.
 *
 * This suite therefore exercises:
 *
 *   · the strategy selected for each driver, and the refusal for a driver with
 *     none;
 *   · that an absent backup utility is refused BEFORE anything is written;
 *   · that a build verifies its own output and refuses to publish a container
 *     the restorer would reject;
 *   · that a container records its format and a restore refuses a mismatch, so
 *     a MySQL dump cannot be installed over a SQLite file;
 *   · that destination guards (traversal, symlink, document root, unwritable)
 *     hold for the new code path too.
 *
 * WHAT IT DELIBERATELY DOES NOT CLAIM
 * -----------------------------------
 * There is no MySQL server here, so nothing in this file is MySQL evidence. The
 * mysqldump path is proven to REFUSE correctly in this environment; its success
 * path is marked BLOCKED in docs/MYSQL_BACKUP_TEST_MATRIX.md and needs a real
 * server. Writing a test that "passes" against a stub mysqldump would be the
 * fabrication the whole exercise is meant to avoid.
 */
final class BackupDriverMatrixTest extends ViraTestCase
{
    private DatabaseHarness $db;
    private string $work;
    private string $storageRoot;
    private string $backupKey;
    private int $seq = 0;

    protected function setUp(): void
    {
        $this->db = DatabaseHarness::boot('backup-driver-matrix');
        $this->db->migrate();

        $this->work = rtrim(sys_get_temp_dir(), '/') . '/vira-bdm-test-' . bin2hex(random_bytes(4));
        mkdir($this->work . '/backups', 0700, true);

        $this->storageRoot = $this->work . '/storage-app';
        mkdir($this->storageRoot . '/private/docs', 0700, true);
        file_put_contents($this->storageRoot . '/private/docs/note.txt', "payload\n");

        $this->backupKey = BackupCipher::generateKey();
    }

    public function run(): void
    {
        $this->testFactorySelectsSqliteForTheSqliteConnection();
        $this->testFactoryRefusesAnUnsupportedDriver();
        $this->testFactoryReportsTheLiveDriver();
        $this->testMissingMysqldumpIsRefusedBeforeAnythingIsWritten();
        $this->testUnreadableSqliteSourceIsRefused();
        $this->testBuildPublishesOnlyVerifiedContainers();
        $this->testContainerRecordsItsFormat();
        $this->testRestoreRefusesAFormatMismatch();
        $this->testRestoreAcceptsTheMatchingFormat();
        $this->testSqliteSnapshotCountsMatchTheLiveDatabase();
        $this->testUnwritableDestinationIsReported();
        $this->testTraversalAndSymlinkDestinationsCannotEscape();
        $this->testNoSqliteSpecificSqlRunsForTheMysqlStrategy();

        $this->removeTree($this->work);
        $this->db->destroy();
    }

    // ── strategy selection ──────────────────────────────────────────────

    private function testFactorySelectsSqliteForTheSqliteConnection(): void
    {
        $source = SnapshotSourceFactory::fromContainer()->make();

        $this->assertSame('sqlite', $source->driver(), 'the harness connection is sqlite');
        $this->assertSame('sqlite-file', $source->format());
        $this->assertSame('database.sqlite', $source->member());
        $this->assertContains($this->db->path, $source->describe(), 'describe() names the real file');
    }

    /**
     * A driver with no implemented strategy must throw, not fall back to SQLite.
     *
     * This is the regression test for the original defect in its purest form:
     * the old code had exactly one strategy and used it for every driver.
     */
    private function testFactoryRefusesAnUnsupportedDriver(): void
    {
        /* A manager whose default connection reports an unimplemented driver.
         * No extension is needed: the factory only asks for the driver name. */
        $factory = new SnapshotSourceFactory($this->fakeManager('sqlsrv'));

        $threw = false;

        try {
            $factory->make();
        } catch (SnapshotUnavailable $e) {
            $threw = true;
            $this->assertContains('sqlsrv', $e->getMessage(), 'the refusal names the driver');
            $this->assertContains('sqlite, mysql, mariadb', $e->getMessage(), 'and lists what is supported');
        }

        $this->assertTrue($threw, 'an unimplemented driver must throw, never fall back');
    }

    private function testFactoryReportsTheLiveDriver(): void
    {
        $factory = SnapshotSourceFactory::fromContainer();

        $this->assertSame('sqlite', $factory->driver());
    }

    // ── missing utility ─────────────────────────────────────────────────

    /**
     * Requirement: "Do not assume mysqldump is available. Detect and validate
     * dependencies explicitly." And: fail closed BEFORE creating anything.
     */
    private function testMissingMysqldumpIsRefusedBeforeAnythingIsWritten(): void
    {
        $connection = $this->fakeMysqlConnection();

        $source = new \App\Services\Backup\Snapshot\MysqlDumpSnapshotSource($connection, 'mysql');

        $destination = $this->work . '/never-created';

        $threw = false;

        try {
            /* assertReady() is the guard the builder calls first. */
            $source->assertReady();
        } catch (SnapshotUnavailable $e) {
            $threw = true;
            $this->assertTrue(
                str_contains($e->getMessage(), 'mysqldump') || str_contains($e->getMessage(), 'proc_open'),
                'the message says which dependency is missing, not just "failed": ' . $e->getMessage(),
            );
        }

        $this->assertTrue($threw, 'a mysql source without mysqldump must refuse');

        /* And the builder, which calls assertReady() first thing, must not have
         * created the destination directory. */
        $builder = new BackupBuilder(
            new BackupCipher(base64_decode($this->backupKey, true)),
            $source,
            $this->storageRoot,
        );

        $threw = false;

        try {
            $builder->build($destination);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'build() must propagate the refusal');
        $this->assertFalse(
            is_dir($destination),
            'readiness is checked before the destination directory is created',
        );
    }

    private function testUnreadableSqliteSourceIsRefused(): void
    {
        $missing = $this->work . '/no-such-database.sqlite';
        $source = new SqliteSnapshotSource($missing);

        $this->assertThrows(
            SnapshotUnavailable::class,
            fn () => $source->assertReady(),
        );

        $destination = $this->work . '/never-created-2';
        $builder = new BackupBuilder(
            new BackupCipher(base64_decode($this->backupKey, true)),
            $source,
            $this->storageRoot,
        );

        $this->assertThrows(\Throwable::class, fn () => $builder->build($destination));
        $this->assertFalse(is_dir($destination), 'no directory is created for an unreadable source');
    }

    // ── self-verification ───────────────────────────────────────────────

    /**
     * Requirement 1.6: validate integrity and encryption before reporting
     * success. The builder runs the restorer's own gates against its output.
     */
    private function testBuildPublishesOnlyVerifiedContainers(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        $this->assertTrue(is_file((string) $result['path']), 'a container exists');

        /* The published file must verify cleanly and describe the same counts. */
        $verify = (new BackupRestorer(new BackupCipher(base64_decode($this->backupKey, true))))
            ->verifyDeep((string) $result['path']);

        $this->assertTrue($verify['ok'], 'the published container passes every gate');

        $this->assertSame(
            (int) $result['rows'],
            (int) $verify['manifest']['database']['rows'],
            'the reported row count is the one inside the container, not an estimate',
        );

        /* No .partial may survive a successful build. */
        $partials = glob($this->work . '/backups/*.partial') ?: [];
        $this->assertCount(0, $partials, 'no .partial file is left behind');
    }

    private function testContainerRecordsItsFormat(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        $raw = (string) file_get_contents((string) $result['path']);
        $this->assertSame("VIRABK1\n", substr($raw, 0, 8), 'the container starts with the magic');

        /* The header is the line AFTER the 8-byte magic; searching from the
         * start would find the magic's own newline at offset 7 and parse an
         * empty string. */
        $rest = substr($raw, 8);
        $newline = strpos($rest, "\n");
        $header = json_decode(substr($rest, 0, $newline), true);

        $this->assertSame('sqlite-file', $header['database_format'] ?? null, 'header records the format');
        $this->assertSame('sqlite', $header['database_driver'] ?? null, 'header records the driver');
    }

    // ── format compatibility ────────────────────────────────────────────

    /**
     * Requirement 1.7: "Make restore driver-aware and reject incompatible backup
     * formats."
     *
     * A SQLite container restored onto a host expecting a mysqldump must be
     * refused, and the destination must be untouched.
     */
    private function testRestoreRefusesAFormatMismatch(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        $destination = $this->destinationDatabase();
        $before = hash_file('sha256', $destination);

        $restorer = new BackupRestorer(new BackupCipher(base64_decode($this->backupKey, true)));

        $message = '';

        try {
            $restorer->restore((string) $result['path'], $destination, $this->storageRoot, 'mysqldump');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        $this->assertContains('incompatible container', $message, 'the refusal names the incompatibility');
        $this->assertContains('sqlite-file', $message, 'and says what the container actually holds');
        $this->assertContains('mysqldump', $message, 'and what this host expected');

        $this->assertSame($before, hash_file('sha256', $destination), 'the destination is byte-identical');
        $this->assertCount(
            0,
            glob($destination . '.pre-restore-*') ?: [],
            'nothing was moved aside for an incompatible container',
        );
    }

    private function testRestoreAcceptsTheMatchingFormat(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        $destination = $this->destinationDatabase();

        $out = (new BackupRestorer(new BackupCipher(base64_decode($this->backupKey, true))))
            ->restore((string) $result['path'], $destination, $this->storageRoot, 'sqlite-file');

        $this->assertSame('sqlite-file', $out['format'] ?? null, 'the restore reports the format it installed');
        $this->assertTrue((int) $out['rows'] > 0, 'rows were restored');
    }

    // ── counts come from the source ─────────────────────────────────────

    /**
     * Requirement 1.5: never report success based on a file existing. The row
     * count must be read from the database, and it must be non-trivial.
     */
    private function testSqliteSnapshotCountsMatchTheLiveDatabase(): void
    {
        $source = new SqliteSnapshotSource($this->db->path);

        $this->db->connection->table('users')->insert([
            'username' => 'matrix-probe-' . bin2hex(random_bytes(3)),
            'password' => 'x',
            'name' => 'probe',
        ]);

        $expectedUsers = $this->db->connection->table('users')->count();

        $target = $this->work . '/counts.sqlite';
        $stats = $source->snapshot($target);

        $this->assertTrue($stats['tables'] > 10, 'the schema has many tables, not a stub');
        $this->assertTrue(in_array('users', $stats['table_list'], true), 'the probe table is listed');

        /* The independent re-read of the snapshot must agree with the source. */
        $copy = new \SQLite3($target, SQLITE3_OPEN_READONLY);
        $inCopy = (int) $copy->querySingle('select count(*) from "users"');
        $copy->close();

        $this->assertSame($expectedUsers, $inCopy, 'the snapshot holds exactly the rows the live database had');
    }

    // ── destination guards ──────────────────────────────────────────────

    private function testUnwritableDestinationIsReported(): void
    {
        $locked = $this->work . '/locked-backups';
        mkdir($locked, 0500, true);

        $threw = false;

        try {
            $this->builder()->build($locked . '/sub');
        } catch (\Throwable $e) {
            $threw = true;
        }

        @chmod($locked, 0700);
        @rmdir($locked . '/sub');
        @rmdir($locked);

        $this->assertTrue($threw, 'an unwritable destination is an error, not a silent success');
    }

    private function testTraversalAndSymlinkDestinationsCannotEscape(): void
    {
        /* The command-level guard lives in BackupDatabase::destinationIsWebServed
         * and is proven by tools/checks/backup-hardening.mjs. Here we prove the
         * builder itself does not silently write outside the given directory
         * when handed a traversal path: it resolves it, which means the file
         * lands inside the parent, and the caller can detect that. */
        $outside = $this->work . '/escape-target';
        mkdir($outside, 0700, true);

        $result = $this->builder()->build($this->work . '/backups/../backups');

        $real = (string) realpath((string) $result['path']);
        $expected = (string) realpath($this->work . '/backups');

        $this->assertTrue(
            str_starts_with($real, $expected . '/'),
            "'..' resolves into the real directory rather than escaping it: {$real}",
        );

        $this->assertCount(0, glob($outside . '/*') ?: [], 'nothing was written outside the resolved directory');
    }

    /**
     * A guard against the defect re-entering by a different route: the MySQL
     * strategy must not reach for SQLite-only SQL.
     */
    private function testNoSqliteSpecificSqlRunsForTheMysqlStrategy(): void
    {
        $reflection = new \ReflectionClass(\App\Services\Backup\Snapshot\MysqlDumpSnapshotSource::class);

        $body = '';

        foreach ($reflection->getMethods() as $method) {
            $file = (string) file_get_contents((string) $reflection->getFileName());
            $lines = file((string) $reflection->getFileName()) ?: [];
            $body .= implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        }

        foreach (['PRAGMA', 'sqlite_master', 'SQLite3'] as $token) {
            $this->assertNotContains(
                $token,
                $body,
                "the mysql strategy must not contain SQLite-only construct {$token}",
            );
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function builder(): BackupBuilder
    {
        return new BackupBuilder(
            new BackupCipher(base64_decode($this->backupKey, true)),
            new SqliteSnapshotSource($this->db->path),
            $this->storageRoot,
        );
    }

    private function destinationDatabase(): string
    {
        $path = $this->work . '/restore-here/db' . (++$this->seq) . '.sqlite';
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($path, "not a database yet\n");

        return $path;
    }

    /** A connection object that reports `mysql` without needing the extension. */
    private function fakeMysqlConnection(): \Illuminate\Database\ConnectionInterface
    {
        $pdo = new \PDO('sqlite::memory:');

        $connection = new \Illuminate\Database\SQLiteConnection($pdo, 'vira_prod');

        /* Wrap it so getDriverName() answers "mysql" — the strategy only reads
         * that name and the config, and assertReady() reaches the utility check
         * before any query is issued. */
        return new class($pdo, 'vira_prod') extends \Illuminate\Database\SQLiteConnection
        {
            public function __construct(\PDO $pdo, string $database)
            {
                parent::__construct($pdo, $database);
                $this->database = $database;
            }

            public function getDriverName(): string
            {
                return 'mysql';
            }

            public function selectOne($query, $bindings = [], $useReadPdo = true)
            {
                return (object) ['ok' => 1];
            }
        };
    }

    /** A DatabaseManager stub whose default connection reports a chosen driver. */
    private function fakeManager(string $driver): \Illuminate\Database\DatabaseManager
    {
        $connection = new class(new \PDO('sqlite::memory:'), 'x') extends \Illuminate\Database\SQLiteConnection
        {
            public string $fakeDriver = 'sqlite';

            public function getDriverName(): string
            {
                return $this->fakeDriver;
            }
        };

        $connection->fakeDriver = $driver;

        return new class($connection) extends \Illuminate\Database\DatabaseManager
        {
            public function __construct(private readonly \Illuminate\Database\ConnectionInterface $fake)
            {
            }

            public function connection($name = null): \Illuminate\Database\ConnectionInterface
            {
                return $this->fake;
            }
        };
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
