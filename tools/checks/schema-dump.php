<?php

/**
 * DEVELOPMENT ONLY — print the result of ONE read-only SQLite query as TSV.
 *
 *   php tools/checks/schema-dump.php <db-path> "<sql>"
 *
 * Used by tools/generate-schema-doc.mjs. Read-only: it opens the connection with
 * SQLITE_OPEN_READONLY so a generator can never alter the database it documents.
 */

$dbPath = $argv[1] ?? null;
$sql = $argv[2] ?? null;

if ($dbPath === null || $sql === null || ! is_file($dbPath)) {
    fwrite(STDERR, "usage: schema-dump.php <db-path> \"<sql>\"\n");
    exit(2);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
    ]);

    $stmt = $pdo->query($sql);
    foreach ($stmt as $row) {
        $cells = [];
        foreach ($row as $k => $v) {
            if (is_int($k)) {
                $cells[] = $v === null ? '' : (string) $v;
            }
        }
        echo implode("\t", $cells), "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'SQL failed: ' . $e->getMessage() . "\n");
    exit(1);
}
