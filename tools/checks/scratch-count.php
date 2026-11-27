<?php

/**
 * DEVELOPMENT ONLY — read row counts out of a scratch database.
 *
 *   php tools/checks/scratch-count.php <db-path> <table> [<table> ...]
 *
 * Written for the Phase 4 backup scenario, which has to reconcile the row counts
 * of a RESTORED database against the source. The flow harness can only read the
 * flow's own database, and re-implementing the backup or restore code to get at
 * the counts would prove nothing about the code that ships.
 *
 * Read-only by construction: it opens the file and runs COUNT(*), and nothing
 * else. Same refusal rule as the other probes — the path must resolve inside
 * .arena/ or /tmp/, segments are resolved one at a time, and the shared
 * development database is refused outright.
 */

$dbPath = $argv[1] ?? null;
$tables = array_slice($argv, 2);

if ($dbPath === null || $tables === []) {
    fwrite(STDERR, "usage: scratch-count.php <db-path> <table> [<table> ...]\n");
    exit(2);
}

$root = dirname(__DIR__, 2);

$resolve = static function (string $path): string {
    $absolute = $path !== '' && $path[0] === '/' ? $path : getcwd() . '/' . $path;
    $out = [];

    foreach (explode('/', $absolute) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($out);

            continue;
        }

        $out[] = $segment;
    }

    return '/' . implode('/', $out);
};

$resolved = $resolve($dbPath);

if ($resolved === $resolve($root . '/database/database.sqlite')) {
    fwrite(STDERR, "refusing to open the shared database\n");
    exit(3);
}

$allowed = false;
foreach ([$resolve($root . '/.arena'), '/tmp'] as $prefix) {
    if (str_starts_with($resolved, $prefix . '/')) {
        $allowed = true;
        break;
    }
}

if (! $allowed) {
    fwrite(STDERR, "refusing to open {$dbPath} ({$resolved}): only .arena/ or /tmp/ paths are allowed\n");
    exit(3);
}

if (! is_file($resolved)) {
    fwrite(STDERR, "no database at {$resolved}\n");
    exit(2);
}

$pdo = new PDO('sqlite:' . $resolved, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // Read-only intent: no write happens in this script, and opening immutable
    // would fail on a live WAL database.
    PDO::ATTR_TIMEOUT => 5,
]);

foreach ($tables as $table) {
    // Table names cannot be bound as parameters, so validate the identifier
    // rather than interpolating whatever was passed.
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
        echo "{$table}\tBAD_IDENTIFIER\n";

        continue;
    }

    try {
        $count = $pdo->query("select count(*) from \"{$table}\"")->fetchColumn();
        echo "{$table}\t{$count}\n";
    } catch (\Throwable $e) {
        echo "{$table}\tERROR\n";
    }
}
