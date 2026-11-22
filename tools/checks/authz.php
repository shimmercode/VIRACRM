<?php
/**
 * Authorization check: create a user with a restricted role and verify the
 * permission helpers return the right answers. This tests the *policy layer*;
 * the HTTP-level refusal is tested separately by authz-flow.mjs, which proves
 * middleware actually runs.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$pw = 'DevOnly-Viewer-2026x';

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
