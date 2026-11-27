<?php
/**
 * Read-only logical fingerprint of a SQLite database.
 *
 * WHY THIS EXISTS
 * ───────────────
 * The regression runner used to prove "the shared database was untouched" by
 * comparing `select count(*)` over seven hardcoded tables. That check passed
 * while `authz.php` rewrote `users.password` for `viewer1` on every single run:
 * one column of one existing row leaves every row count identical, so a
 * count-only comparison is structurally blind to it.
 *
 * So this fingerprints *content*, not shape: every row of every table is fed
 * into one SHA-256. A changed byte anywhere moves the digest.
 *
 * It also reports a per-table digest, so when the whole-database digest moves
 * the runner can name the table instead of just failing.
 *
 * SAFETY
 * ──────
 * Opens the file through SQLite's URI form with `mode=ro`, and only ever runs
 * SELECT. It cannot modify the database even if the caller passes the shared
 * path, which is exactly why it is allowed to.
 *
 * The URI form is not cosmetic. `PDO::ATTR_OPEN_FLAGS` is not exposed by this
 * PHP build (`Undefined constant PDO::ATTR_OPEN_FLAGS`), and the plain
 * `sqlite:<path>` DSN was verified to accept a CREATE TABLE. The URI form was
 * verified to refuse it with "attempt to write a readonly database".
 *
 * USAGE
 *   php tools/checks/db-fingerprint.php <database.sqlite> [--json]
 */

if ($argc < 2) {
    fwrite(STDERR, "usage: php db-fingerprint.php <database.sqlite> [--json]\n");
    exit(2);
}

$path = $argv[1];
$wantJson = in_array('--json', array_slice($argv, 2), true);

$resolved = realpath($path);
if ($resolved === false || ! is_file($resolved)) {
    fwrite(STDERR, "not a file: {$path}\n");
    exit(2);
}

// mode=ro is enforced by SQLite itself, not by the discipline of this file.
$pdo = new PDO('sqlite:file:' . $resolved . '?mode=ro', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Table order is fixed by name so two runs over identical content always hash
// identically — sqlite_master's own order is an implementation detail.
$tables = [];
foreach ($pdo->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name") as $row) {
    $tables[] = $row['name'];
}

$totalRows = 0;
$whole = hash_init('sha256');
$perTable = [];

foreach ($tables as $table) {
    $perTableDigest = hash_init('sha256');
    $rows = 0;

    // Order by rowid so the digest does not depend on physical row order.
    // WITHOUT ROWID tables throw; fall back to an unordered read for those.
    try {
        $stmt = $pdo->query('select * from "' . str_replace('"', '""', $table) . '" order by rowid');
    } catch (PDOException) {
        $stmt = $pdo->query('select * from "' . str_replace('"', '""', $table) . '"');
    }

    foreach ($stmt as $row) {
        // Fetch returns each column twice (numeric and named keys); keep the
        // numeric half only, or every value would be hashed twice.
        $values = [];
        foreach ($row as $key => $value) {
            if (is_int($key)) {
                $values[] = is_null($value) ? 'NULL' : (string) $value;
            }
        }

        $encoded = implode("\x1f", $values) . "\x1e";
        hash_update($perTableDigest, $encoded);
        $rows++;
    }

    $digest = hash_final($perTableDigest);
    $perTable[$table] = $digest;

    hash_update($whole, 'TABLE ' . $table . ' ' . $rows . ' ' . $digest . "\n");
    $totalRows += $rows;
}

$wholeDigest = hash_final($whole);

if ($wantJson) {
    echo json_encode([
        'tables' => count($tables),
        'rows' => $totalRows,
        'sha256' => $wholeDigest,
        'perTable' => $perTable,
    ]), "\n";
    exit(0);
}

printf("tables=%d rows=%d sha256=%s\n", count($tables), $totalRows, $wholeDigest);
exit(0);
