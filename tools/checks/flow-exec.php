<?php

/**
 * DEVELOPMENT ONLY — run ONE guarded INSERT against a flow's private database.
 *
 *   php tools/checks/flow-exec.php <db-path> "<insert ...>"
 *
 * WHY THIS IS A SEPARATE FILE FROM flow-query.php
 * ----------------------------------------------
 * `flow-query.php` accepts SELECT only, on purpose: assertions must not be able
 * to mutate the fixture, or a broken test can "pass" by rewriting what it is
 * measuring. This file is the one narrow exception, for FIXTURE SETUP that no
 * product route can perform yet.
 *
 * It is deliberately not a general SQL runner: it accepts INSERT only, and only
 * against a database inside `.arena/flows/`, so a mistyped path can never touch
 * the shared or production database.
 *
 * The one current use: attaching a cost centre to a posted journal line, so the
 * "used cost centre is deactivated, not deleted" guard can be proved before any
 * route posts a tagged entry.
 */

$dbPath = $argv[1] ?? null;
$sql = $argv[2] ?? null;

if ($dbPath === null || $sql === null) {
    fwrite(STDERR, "usage: flow-exec.php <db-path> \"<insert ...>\"\n");
    exit(2);
}

if (! is_file($dbPath)) {
    fwrite(STDERR, "no database at {$dbPath}\n");
    exit(2);
}

$resolved = realpath($dbPath);
$sandbox = realpath(dirname(__DIR__, 2) . '/.arena/flows');

if ($resolved === false || $sandbox === false || ! str_starts_with($resolved, $sandbox . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "refusing to write outside .arena/flows\n");
    exit(2);
}

if (! preg_match('/^\s*insert\b/i', $sql)) {
    fwrite(STDERR, "only INSERT is allowed\n");
    exit(2);
}

try {
    $pdo = new PDO('sqlite:' . $resolved, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);

    $affected = $pdo->exec($sql);
    echo "OK {$affected}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'SQL failed: ' . $e->getMessage() . "\n");
    exit(1);
}
