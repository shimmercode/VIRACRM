<?php

namespace App\Services\Backup\Snapshot;

/**
 * One database driver's backup strategy.
 *
 * WHY THIS INTERFACE EXISTS
 * ------------------------
 * The backup engine used to be a single code path that called `SQLite3::backup()`
 * and took its source from `database.connections.sqlite.database` — a config
 * key, not the connection the application was actually using. On the SQLite
 * deployment the two coincide and the backup is correct. On MySQL they cannot
 * coincide, and the command did not notice: it read a schema name as if it were
 * a filesystem path and carried on.
 *
 * The fix is not a special case inside the builder. It is to make "where does a
 * backup come from, and how is it taken" an explicit, named strategy chosen from
 * the live connection — so that a driver nobody has implemented cannot fall
 * through to a strategy meant for a different one.
 *
 * CONTRACT
 * --------
 * assertReady() is called BEFORE any directory is created or any file is
 * written. A strategy that cannot run must throw here, which is what makes
 * "fail closed" a property of the ordering rather than a promise in a comment.
 */
interface DatabaseSnapshotSource
{
    /** The Laravel driver name this strategy serves: `sqlite`, `mysql`, `mariadb`. */
    public function driver(): string;

    /** Logical format recorded in the manifest: `sqlite-file` or `mysqldump`. */
    public function format(): string;

    /** Archive member the snapshot is stored under inside the container. */
    public function member(): string;

    /** One line for the operator: what will be read, and how. */
    public function describe(): string;

    /**
     * Prove the strategy can run. Called before anything is created.
     *
     * @throws SnapshotUnavailable when a required utility is missing, the
     *         runtime forbids spawning it, or the source cannot be read.
     */
    public function assertReady(): void;

    /**
     * Take the snapshot into $targetFile.
     *
     * The returned counts come from the SOURCE database, read independently of
     * the artefact, so a caller can tell "the dump contains the data" from "a
     * file was created".
     *
     * @return array{tables:int, rows:int, table_list:list<string>}
     *
     * @throws SnapshotUnavailable
     */
    public function snapshot(string $targetFile): array;
}
