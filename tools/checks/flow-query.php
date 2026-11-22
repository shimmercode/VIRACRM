<?php

/**
 * DEVELOPMENT ONLY — run one read-only SQL query against a flow's database.
 *
 *   php tools/checks/flow-query.php <db-path> "SELECT count(*) FROM customers"
 *
 * Prints the first column of the first row. Deliberately dumb and deliberately
 * separate from the application: the flows sometimes need to assert on stored
 * state (for example "the customer really was deleted, not just hidden"), and
 * booting Laravel for that would apply the very code under test.
 *
 * Only SELECT is accepted, so a typo cannot mutate the fixture.
 */

$dbPath = $argv[1] ?? null;
$sql = $argv[2] ?? null;

if ($dbPath === null || $sql === null) {
    fwrite(STDERR, "usage: flow-query.php <db-path> \"<select ...>\"\n");
    exit(2);
}

if (! is_file($dbPath)) {
    fwrite(STDERR, "no database at {$dbPath}\n");
    exit(2);
}

if (! preg_match('/^\s*select\b/i', $sql)) {
    fwrite(STDERR, "only SELECT is allowed\n");
    exit(2);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);

    $value = $pdo->query($sql)->fetchColumn();

    echo $value === null ? 'NULL' : (string) $value;
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'query failed: ' . $e->getMessage() . "\n");
    exit(1);
}
