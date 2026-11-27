<?php
/**
 * Authorization check: create a user with a restricted role and verify the
 * permission helpers return the right answers. This tests the *policy layer*;
 * the HTTP-level refusal is tested separately by authz-flow.mjs, which proves
 * middleware actually runs.
 */
require __DIR__ . '/../../vendor/autoload.php';

/*
 * ISOLATION — this script writes fixtures (it creates `viewer1` and rewrites
 * that account's password), so it must never run against the shared database.
 *
 * For most of this project's life it did exactly that. It went unnoticed
 * because the runner's isolation check compared `select count(*)` over seven
 * tables, and rewriting one column of one existing row leaves every row count
 * identical. The bug was only found by fingerprinting the database *content*
 * before and after a regression run (tools/checks/db-fingerprint.php).
 *
 * It is the same mechanism policies.php already uses, and the same one the
 * browser flows use: copy the database into a private directory and point
 * DB_DATABASE at the copy BEFORE bootstrap, so Laravel's immutable Dotenv
 * never sees the shared path. The copy starts from the shared database, so the
 * seeded `viewer` role this script needs is present, and nothing it does here
 * can reach a real account.
 */
$flowRoot = getenv('VIRA_FLOW_ROOT') ?: (dirname(__DIR__, 2) . '/.arena/flows');
$privateDir = rtrim($flowRoot, '/') . '/authz';

if (! is_dir($privateDir) && ! mkdir($privateDir, 0o775, true) && ! is_dir($privateDir)) {
    fwrite(STDERR, "cannot create {$privateDir}\n");
    exit(2);
}

$sharedDb = getenv('VIRA_BASE_DB') ?: (dirname(__DIR__, 2) . '/database/database.sqlite');
$privateDb = $privateDir . '/database.sqlite';

if (! copy($sharedDb, $privateDb)) {
    fwrite(STDERR, "cannot copy database to {$privateDb}\n");
    exit(2);
}

// A stale WAL/SHM beside the shared database would make the copy inconsistent.
foreach (['-wal', '-shm'] as $suffix) {
    @unlink($privateDb . $suffix);
}

$_ENV['DB_DATABASE'] = $privateDb;
$_SERVER['DB_DATABASE'] = $privateDb;
putenv('DB_DATABASE=' . $privateDb);

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Belt and braces: if the override somehow did not take, rewriting a real
// account's password is the one thing this script must not do.
if (realpath((string) config('database.connections.sqlite.database')) === realpath($sharedDb)) {
    fwrite(STDERR, "refusing to run: still pointed at the shared database\n");
    exit(2);
}

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Generated, never printed, and never the same twice. This script only exercises
// the policy layer (permission helpers) — it never authenticates over HTTP — so
// no human needs to know this value. A fixed password in a committed file is a
// credential regardless of its name; see docs/phase-1/22-bootstrap-credential-policy.md.
$pw = rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');

$viewer = User::query()->firstOrNew(['username' => 'viewer1']);
$viewer->fill([
    'name' => 'کاربر مشاهده‌گر',
    'username' => 'viewer1',
    'password' => Hash::make($pw),
    'is_superadmin' => false,
    'is_active' => true,
    'must_change_password' => false,
]);
$viewer->save();

$role = Role::query()->where('code', 'viewer')->firstOrFail();
$viewer->roles()->sync([$role->id]);
$viewer->unsetRelation('roles');
$viewer = $viewer->fresh();

$expectations = [
    ['customers.view', true],
    ['contracts.view', true],
    ['projects.view', true],
    ['notes.view', true],
    ['customers.create', false],
    ['customers.update', false],
    ['customers.delete', false],
    ['contracts.create', false],
    ['contracts.update', false],
    ['contracts.view_financials', false],
    ['credentials.reveal', false],
    ['payroll.view', false],
    ['settings.manage', false],
    ['users.manage', false],
];

$fail = 0;
foreach ($expectations as [$perm, $expected]) {
    $actual = $viewer->hasPermission($perm);
    $ok = $actual === $expected;
    if (! $ok) { $fail++; }
    printf("%-4s %-26s expected=%-5s actual=%s\n",
        $ok ? 'PASS' : 'FAIL', $perm, var_export($expected, true), var_export($actual, true));
}

// A blocked user must hold no permission at all, regardless of roles.
$viewer->forceFill(['is_active' => false])->save();
$blockedOk = $viewer->fresh()->hasPermission('customers.view') === false
    && $viewer->fresh()->isBlocked() === true;
printf("%-4s %-26s\n", $blockedOk ? 'PASS' : 'FAIL', 'deactivated user denied');
if (! $blockedOk) { $fail++; }
$viewer->forceFill(['is_active' => true])->save();

// Superadmin bypasses the grid.
$admin = User::query()->where('username', 'admin')->firstOrFail();
$adminOk = $admin->hasPermission('payroll.view') && $admin->hasPermission('settings.manage');
printf("%-4s %-26s\n", $adminOk ? 'PASS' : 'FAIL', 'superadmin bypass');
if (! $adminOk) { $fail++; }

echo "\n" . ($fail === 0 ? 'ALL AUTHZ CHECKS PASS' : "$fail FAILURES") . "\n";
exit($fail === 0 ? 0 : 1);
