<?php

/**
 * DEVELOPMENT ONLY — run one artisan command against a scratch database.
 *
 *   php tools/checks/artisan-probe.php <db-path> [KEY=VALUE ...] -- <command> [args...]
 *
 * Written for the Phase 4 backup scenario, which needs the real `vira:backup`
 * and `vira:restore` commands rather than a re-implementation: a test that
 * exercises a copy of the backup code proves nothing about the code that ships.
 *
 * Same refusal rule as migration-probe.php. The database path must resolve
 * inside .arena/ or /tmp/, and `database/database.sqlite` is refused outright —
 * the shared development database is the one file these checks must never
 * modify. Path segments are resolved one at a time so `.arena/../database/...`
 * cannot slip past a substring test.
 *
 * KEY=VALUE arguments before `--` are installed into $_ENV/$_SERVER before
 * bootstrap, because Laravel's Dotenv is immutable and will not overwrite a key
 * that is already present. That is how a throwaway backup key is supplied
 * without writing one into .env or the repository.
 */

$dbPath = $argv[1] ?? null;

if ($dbPath === null) {
    fwrite(STDERR, "usage: artisan-probe.php <db-path> [KEY=VALUE ...] -- <command> [args...]\n");
    exit(2);
}

$rest = array_slice($argv, 2);
$sep = array_search('--', $rest, true);

if ($sep === false) {
    fwrite(STDERR, "no `-- <command>` given\n");
    exit(2);
}

$envPairs = array_slice($rest, 0, $sep);
$command = array_slice($rest, $sep + 1);

if ($command === []) {
    fwrite(STDERR, "empty command\n");
    exit(2);
}

$root = dirname(__DIR__, 2);

/** Resolve `.` and `..` one segment at a time from the working directory. */
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
$shared = $resolve($root . '/database/database.sqlite');

if ($resolved === $shared) {
    fwrite(STDERR, "refusing to run a command against the shared database\n");
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
    fwrite(STDERR, "refusing to run against {$dbPath} ({$resolved}): only .arena/ or /tmp/ paths are allowed\n");
    exit(3);
}

if (! is_file($resolved)) {
    fwrite(STDERR, "no database at {$resolved}\n");
    exit(2);
}

foreach ($envPairs as $pair) {
    $eq = strpos($pair, '=');

    if ($eq === false) {
        fwrite(STDERR, "not a KEY=VALUE pair: {$pair}\n");
        exit(2);
    }

    $key = substr($pair, 0, $eq);
    $value = substr($pair, $eq + 1);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

$_ENV['DB_DATABASE'] = $resolved;
$_SERVER['DB_DATABASE'] = $resolved;
putenv('DB_DATABASE=' . $resolved);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

/*
 * The WASM build has no ext-intl, so Symfony's console formatter fatals inside
 * its own error rendering and the real message never reaches the output. Catch
 * and print it here instead.
 */
/*
 * Kernel::call() reads numeric keys as positional arguments, so `--path=x` in a
 * plain list becomes "the argument 0 does not exist". Options have to be keyed.
 */
$params = [];
foreach (array_slice($command, 1) as $token) {
    if (str_starts_with($token, '--')) {
        $eq = strpos($token, '=');
        $params[$eq === false ? $token : substr($token, 0, $eq)] = $eq === false ? true : substr($token, $eq + 1);

        continue;
    }

    /*
     * `name=value` becomes a NAMED argument. Symfony's ArrayInput will not
     * accept a required argument by position through Kernel::call — it reports
     * "Not enough arguments (missing: file)" — so the name has to travel with
     * the value. A bare token is still positional, for commands that want one.
     */
    $eq = strpos($token, '=');

    if ($eq !== false) {
        $params[substr($token, 0, $eq)] = substr($token, $eq + 1);

        continue;
    }

    $params[] = $token;
}

try {
    $exit = $kernel->call($command[0], $params);

    echo $kernel->output();
    echo "\n[exit {$exit}]\n";

    // Confirm the override actually took effect; a command that silently ran
    // against .env's database would look identical from the outside.
    echo '[db ' . $resolved . "]\n";

    exit($exit === 0 ? 0 : 1);
} catch (\Throwable $e) {
    echo 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage() . "\n";

    exit(4);
}
