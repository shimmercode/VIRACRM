<?php

namespace App\Services\Backup\Snapshot;

use SQLite3;

/**
 * SQLite: an online snapshot via the native backup API.
 *
 * WHY SQLite3::backup() AND NOT copy()
 * ------------------------------------
 * copy() takes no lock. A concurrent writer produces a torn file that may still
 * open and still pass `PRAGMA integrity_check`, which is the worst outcome: a
 * backup that looks restorable and is not. SQLite3::backup() is the online
 * backup API — it holds the read lock across the copy.
 *
 * This is the strategy that has always run in this project. It is unchanged
 * behaviour, moved behind the driver interface so that it can no longer be used
 * for a database it does not apply to.
 */
final class SqliteSnapshotSource implements DatabaseSnapshotSource
{
    public function __construct(private readonly string $databasePath)
    {
    }

    public function driver(): string
    {
        return 'sqlite';
    }

    public function format(): string
    {
        return 'sqlite-file';
    }

    public function member(): string
    {
        return 'database.sqlite';
    }

    public function describe(): string
    {
        return 'sqlite file ' . $this->databasePath . ' (online backup API)';
    }

    public function assertReady(): void
    {
        if (! extension_loaded('sqlite3')) {
            throw new SnapshotUnavailable('ext-sqlite3 is not loaded; cannot snapshot a SQLite database.');
        }

        if ($this->databasePath === '') {
            throw new SnapshotUnavailable('database.connections.sqlite.database is empty.');
        }

        /* `:memory:` is a legitimate Laravel setting and an impossible backup
         * source: there is no file to read. Say so rather than letting it fall
         * through to "database not found". */
        if ($this->databasePath === ':memory:') {
            throw new SnapshotUnavailable(
                'the SQLite database is :memory: — there is no file to back up.'
            );
        }

        if (! is_file($this->databasePath)) {
            throw new SnapshotUnavailable("database not found: {$this->databasePath}");
        }

        if (! is_readable($this->databasePath)) {
            throw new SnapshotUnavailable("database is not readable: {$this->databasePath}");
        }
    }

    public function snapshot(string $targetFile): array
    {
        $this->assertReady();

        /* Read-only handle: a backup must never be able to write to the source. */
        $source = new SQLite3($this->databasePath, SQLITE3_OPEN_READONLY);
        $dest = new SQLite3($targetFile);

        try {
            if ($source->backup($dest) !== true) {
                throw new SnapshotUnavailable('SQLite3::backup() failed');
            }
        } finally {
            $source->close();
        }

        $names = $this->tableNames($dest);

        $rows = 0;
        foreach ($names as $name) {
            $rows += (int) $dest->querySingle('select count(*) from "' . str_replace('"', '""', $name) . '"');
        }

        /* A snapshot that fails its own integrity check must not ship. */
        $integrity = (string) $dest->querySingle('PRAGMA integrity_check');
        $dest->close();

        if ($integrity !== 'ok') {
            throw new SnapshotUnavailable("snapshot failed integrity_check: {$integrity}");
        }

        return ['tables' => count($names), 'rows' => $rows, 'table_list' => $names];
    }

    /** @return list<string> */
    private function tableNames(SQLite3 $db): array
    {
        $names = [];
        $result = $db->query(
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
        );

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $names[] = (string) $row['name'];
        }

        $result->finalize();

        return $names;
    }
}
