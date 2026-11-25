<?php

/**
 * VIRA CRM — run migrations against SQLite (development / testing).
 *
 * Boots a minimal Laravel container with the Capsule manager so migrations run
 * through the real Schema builder, exactly as Laravel would run them. MySQL is
 * unavailable in this environment, so SQLite is used for tests; the migration
 * files themselves contain no SQLite-specific SQL and are portable.
 *
 * Usage:
 *   php tools/migrate.php --fresh           # drop and rebuild database/database.sqlite
 *   php tools/migrate.php                   # run pending migrations
 *   php tools/migrate.php --db=path/to.sqlite  # target another SQLite file
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$dbPath = $root . '/database/database.sqlite';

// `--db=path` targets another SQLite file (the review database, a flow copy).
// Without it the tool would only ever migrate the default database, and every
// other environment would silently keep running on an old schema.
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbPath = substr($arg, 5);

        if (! str_starts_with($dbPath, '/')) {
            $dbPath = $root . '/' . $dbPath;
        }
    }
}

$fresh = in_array('--fresh', $argv, true);

if ($fresh && file_exists($dbPath)) {
    unlink($dbPath);
}

@mkdir(dirname($dbPath), 0777, true);
if (! file_exists($dbPath)) {
    touch($dbPath);
}

$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => $dbPath,
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Migrations use the Schema facade, and Illuminate\Support\Facades\Facade needs
// a container it can resolve 'db' and 'db.schema' from. Wire a real container
// rather than stubbing the facade, so the migrations run through Laravel's own
// code path (which is the whole point of running them here).
$container = new Illuminate\Container\Container();
$container->instance('config', new Illuminate\Config\Repository([
    'database' => [
        'default' => 'sqlite',
        'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $dbPath]],
        'migrations' => 'migrations',
    ],
    'app' => ['timezone' => 'Asia/Tehran'],
]));

$conn = $capsule->getConnection();
$container->instance('db', $capsule->getDatabaseManager());
$container->bind('db.connection', fn () => $conn);
$container->bind('db.schema', fn () => $conn->getSchemaBuilder());
$container->bind('db.transactions', fn () => new Illuminate\Database\DatabaseTransactionsManager());
$container->instance(Illuminate\Database\ConnectionInterface::class, $conn);
$container->instance(Illuminate\Database\Connection::class, $conn);

Illuminate\Support\Facades\Facade::setFacadeApplication($container);
Illuminate\Support\Facades\Schema::clearResolvedInstances();

$conn->getPdo()->exec('PRAGMA foreign_keys = ON');

$files = glob($root . '/database/migrations/*.php');
sort($files);

$applied = 0;
$failed = 0;

foreach ($files as $file) {
    $name = basename($file, '.php');

    try {
        $migration = require $file;
        $migration->up();
        $applied++;
        echo "  ok    {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL  {$name}\n";
        echo '        ' . get_class($e) . ': ' . $e->getMessage() . "\n";
        echo '        at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    }
}

$tables = array_map(
    fn ($row) => $row->name,
    $conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
);
$indexes = $conn->select("SELECT name FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%'");

echo "\n" . str_repeat('─', 64) . "\n";
echo 'migrations: ' . count($files) . " ({$applied} ok, {$failed} failed)\n";
echo 'tables:     ' . count($tables) . "\n";
echo '  ' . implode(', ', $tables) . "\n";
echo 'indexes:    ' . count($indexes) . "\n";

exit($failed > 0 ? 1 : 0);
