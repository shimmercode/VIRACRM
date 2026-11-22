<?php
/**
 * DEVELOPMENT ONLY — put the database into a known state for the flow tests:
 * a superadmin with a fixed password and must_change_password=false, plus a
 * viewer account holding only the `viewer` role.
 *
 * This file must never be run against a real installation.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$adminPassword = 'DevOnly-Passw0rd-2026';
$viewerPassword = 'DevOnly-Viewer-2026x';

// ── superadmin ────────────────────────────────────────────────────────────
$admin = User::query()->firstOrNew(['username' => 'admin']);
$admin->fill([
    'name' => 'مدیر سامانه',
    'username' => 'admin',
    'password' => Hash::make($adminPassword),
    'is_superadmin' => true,
    'is_active' => true,
    // The forced-change path is exercised separately by authed-flow.mjs, so it
    // is switched off here to keep these runs independent of ordering.
    'must_change_password' => false,
]);
$admin->save();

$manager = Role::query()->where('code', 'manager')->first();
if ($manager) {
    $admin->roles()->syncWithoutDetaching([$manager->id]);
}

// ── viewer ────────────────────────────────────────────────────────────────
$viewer = User::query()->firstOrNew(['username' => 'viewer1']);
$viewer->fill([
    'name' => 'کاربر مشاهده‌گر',
    'username' => 'viewer1',
    'password' => Hash::make($viewerPassword),
    'is_superadmin' => false,
    'is_active' => true,
    'must_change_password' => false,
]);
$viewer->save();

$viewerRole = Role::query()->where('code', 'viewer')->first();
if ($viewerRole) {
    $viewer->roles()->sync([$viewerRole->id]);
}

echo "admin: admin / {$adminPassword}\n";
echo "viewer: viewer1 / {$viewerPassword}\n";
echo "platform: " . (App\Models\Permission::query()->count()) . " permissions, "
    . App\Models\Role::query()->count() . " roles\n";
