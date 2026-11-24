<?php
/**
 * VIRA CRM — review environment data control.
 *
 * Runs the ReviewDemoSeeder against an explicitly named SQLite file and refuses
 * to run against anything that is not the review database. There is no artisan
 * in this environment, so this is the entry point; the seeder itself is a
 * normal Laravel seeder and would run unchanged under `php artisan db:seed`.
 *
 * Usage:
 *   node docs/tools/php-cli.mjs tools/review-seed.php seed  <db-path>
 *   node docs/tools/php-cli.mjs tools/review-seed.php reset <db-path>
 *   node docs/tools/php-cli.mjs tools/review-seed.php status <db-path>
 *
 * SAFETY
 *   • Refuses if the review environment is disabled.
 *   • Refuses if the database file is not under the review data directory.
 *   • Never touches the default application database.
 *   • Prints counts only — no credential, no personal-looking value.
 */

require __DIR__ . '/../vendor/autoload.php';

$command = $argv[1] ?? 'status';
$dbPath = $argv[2] ?? null;

if ($dbPath === null) {
    fwrite(STDERR, "usage: review-seed.php <seed|reset|status> <db-path>\n");
    exit(2);
}

$dbPath = realpath($dbPath) ?: $dbPath;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── guard 1: the review environment must be switched on ──────────────────
if (! config('review.enabled')) {
    fwrite(STDERR, "REFUSED: VIRA_REVIEW_ENABLED is false. Seeding is a review-only operation.\n");
    exit(3);
}

// ── guard 2: must be a file, must be inside the review data directory ────
$allowedRoot = realpath(base_path('database/review')) ?: base_path('database/review');
$resolved = realpath($dbPath) ?: $dbPath;

if (! is_file($resolved)) {
    fwrite(STDERR, "REFUSED: database file does not exist: {$resolved}\n");
    exit(3);
}

if (! str_starts_with($resolved, $allowedRoot)) {
    fwrite(STDERR, "REFUSED: {$resolved} is outside the review data directory ({$allowedRoot}).\n");
    fwrite(STDERR, "         The review seeder may only write to its own database copy.\n");
    exit(3);
}

// ── point the default connection at the review copy ──────────────────────
config(['database.connections.sqlite.database' => $resolved]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::reconnect('sqlite');

$seeder = new Database\Seeders\ReviewDemoSeeder();

switch ($command) {
    case 'seed':
        $counts = $seeder->seed();
        echo "SEEDED\n";
        foreach ($counts as $table => $n) {
            echo '  ' . str_pad($table, 16) . $n . "\n";
        }
        echo "\nDemo accounts (review database only):\n";
        foreach (config('review.demo_users') as $u) {
            echo '  ' . str_pad($u['username'], 18) . $u['role']
                . ($u['superadmin'] ? ' + superadmin' : '') . "\n";
        }
        echo '  password ............ ' . Database\Seeders\ReviewDemoSeeder::DEMO_PASSWORD . "\n";
        break;

    case 'reset':
        $deleted = $seeder->reset();
        echo "RESET\n";
        foreach ($deleted as $table => $n) {
            echo '  ' . str_pad($table, 30) . $n . "\n";
        }
        break;

    case 'status':
        $tables = ['users', 'customers', 'contracts', 'projects', 'employees',
                   'project_assignments', 'work_items', 'notes'];
        echo "STATUS  {$resolved}\n";
        foreach ($tables as $t) {
            echo '  ' . str_pad($t, 22)
                . Illuminate\Support\Facades\DB::table($t)->count() . "\n";
        }
        break;

    default:
        fwrite(STDERR, "unknown command: {$command}\n");
        exit(2);
}
