<?php

/**
 * DEVELOPMENT ONLY — run one artisan command against a throwaway database.
 *
 * Used to prove a migration's up, down and re-up without ever pointing at
 * database/database.sqlite. The database path is a required argument; there is
 * no default, so a missing argument cannot silently fall back to the shared
 * file.
 *
 *   php tools/checks/migration-probe.php <db-path> <artisan args...>
 *
 * It refuses to run against the shared development database, against any path
 * that already exists outside a scratch directory, and against anything that is
 * not a plain file path.
 */

if ($argc < 3) {
    fwrite(STDERR, "usage: migration-probe.php <db-path> <artisan args...>\n");
    exit(2);
}

$dbPath = $argv[1];
$artisan = array_slice($argv, 2);

$real = realpath($dbPath);
$root = dirname(__DIR__, 2);
$shared = $root . '/database/database.sqlite';

// Hard refusal rather than a warning: this harness exists so a schema change can
// be exercised without risking the one database the product develops against.
if ($real !== false && ($real === $shared || realpath($shared) === $real)) {
    fwrite(STDERR, "refusing to run a migration against the shared database\n");
    exit(3);
}

/*
 * Scratch directories only. The target may not exist yet — a fresh migration
 * database is the whole point — so resolve the parent directory and compare that
 * instead of string-matching the raw argument, which fails on a relative path.
 */
$absolute = str_starts_with($dbPath, '/') ? $dbPath : getcwd() . '/' . $dbPath;

// Resolve . and .. segment by segment. A blanket str_replace on '/.' would eat
// the dot in '/.arena/' — which is exactly the directory this is meant to allow.
$segments = [];
foreach (explode('/', $absolute) as $segment) {
    if ($segment === '' || $segment === '.') {
        continue;
    }
    if ($segment === '..') {
        array_pop($segments);

        continue;
    }
    $segments[] = $segment;
}
$absolute = '/' . implode('/', $segments);

$scratch = false;
foreach ([$root . '/.arena/', '/tmp/'] as $prefix) {
    if (str_starts_with($absolute, $prefix)) {
        $scratch = true;

        break;
    }
}

if (! $scratch) {
    fwrite(STDERR, "refusing to run against {$dbPath} ({$absolute}): only .arena/ or /tmp/ paths are allowed\n");
    exit(3);
}

// Only migration-related commands. This is not a general artisan runner.
$allowed = ['migrate', 'migrate:rollback', 'migrate:status', 'migrate:fresh', 'migrate:reset'];
if (! in_array($artisan[0] ?? '', $allowed, true)) {
    fwrite(STDERR, "only these commands are allowed: " . implode(', ', $allowed) . "\n");
    exit(2);
}

putenv('DB_DATABASE=' . $dbPath);
$_ENV['DB_DATABASE'] = $dbPath;
$_SERVER['DB_DATABASE'] = $dbPath;
putenv('DB_CONNECTION=sqlite');
$_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_CONNECTION'] = 'sqlite';

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

// Confirm the override actually took effect before anything can write.
$configured = (string) config('database.connections.sqlite.database');
if ($configured !== $dbPath) {
    fwrite(STDERR, "DB_DATABASE override did not take: config says {$configured}\n");
    exit(4);
}

$command = $artisan[0];
$params = ['--force' => true];

// Roll back exactly one step: this harness exists to exercise one migration's
// up and down, not to unwind the whole history.
if ($command === 'migrate:rollback') {
    $params['--step'] = 1;
}

/*
 * Catch it here rather than letting the console render it: php-wasm has no
 * ext-intl, and Symfony's console Helper::width() fatals on "Class Normalizer
 * not found" while formatting the message — which hides the actual SQL error
 * behind an environment crash.
 */
try {
    $exit = $kernel->call($command, $params);
    echo $kernel->output();
} catch (Throwable $e) {
    echo 'MIGRATION FAILED: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    $exit = 1;
}

exit($exit);
