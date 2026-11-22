<?php
/**
 * VIRA CRM — seed runner.
 *
 * There is no `artisan` in this environment (no real Composer install), so the
 * seeders are invoked through the container directly. The code path is the
 * same one a future `php artisan db:seed` would take: DatabaseSeeder::run().
 *
 * Usage:
 *   php tools/seed.php           # seed baseline data
 *   php tools/seed.php --demo    # baseline + demo data (NEVER on production)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\ConnectionResolverInterface;

$demo = in_array('--demo', $argv, true);

// Re-bind the seeder command output so seeders can write progress lines.
$seeder = $app->make(Database\Seeders\DatabaseSeeder::class);

echo "Seeding baseline data…\n";
try {
    $seeder->run();
} catch (Throwable $e) {
    echo 'FAIL seed: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

if ($demo) {
    echo "\nSeeding DEMO data…\n";
    try {
        (new Database\Seeders\DemoDataSeeder())->run();
    } catch (Throwable $e) {
        echo 'FAIL demo seed: ' . $e->getMessage() . "\n";
        exit(1);
    }
}

$counts = [
    'users' => App\Models\User::query()->count(),
    'roles' => App\Models\Role::query()->count(),
    'permissions' => App\Models\Permission::query()->count(),
    'contract_types' => App\Models\ContractType::query()->count(),
    'settings' => App\Models\Setting::query()->count(),
];

echo "\nRow counts:\n";
foreach ($counts as $table => $n) {
    echo '  ' . str_pad($table, 16) . $n . "\n";
}

echo "\nOK\n";
