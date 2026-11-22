<?php
/**
 * DEVELOPMENT ONLY — put the local database into a known state for the browser
 * checks: a superadmin with must_change_password=false, plus a viewer account
 * holding only the `viewer` role.
 *
 * NO PASSWORD IS STORED IN THIS FILE. Earlier versions hardcoded
 * 'DevOnly-Passw0rd-2026', which violates the project rule that no credential
 * may live in the repository — a fixed password in a committed file is a
 * credential, whether or not it is called "dev only".
 *
 * The password is now generated at run time and written to a file outside the
 * repository (default `.arena/admin-password`, which is git-ignored, mode 0600).
 * It is printed only when VIRA_SHOW_SECRET=1 is set, which is useful on a
 * throwaway sandbox and wrong everywhere else, so the default is silence.
 *
 * The flow suites do not need this file at all: each one clones the database and
 * sets its own password inside its own copy (see lib/flow-harness.mjs and
 * lib/browser-flow.mjs). This script exists only for ad-hoc manual browsing.
 *
 * This file must never be run against a real installation.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Generate a password that is never derived from a constant in this file. */
$generate = static fn (): string => rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');

$adminPassword = $generate();
$viewerPassword = $generate();

// Where the secret goes. Outside the repo by default; git-ignored if inside it.
$secretDir = getenv('VIRA_SECRET_DIR') ?: (dirname(__DIR__, 2) . '/.arena');
if (! is_dir($secretDir)) {
    mkdir($secretDir, 0700, true);
}
$secretFile = $secretDir . '/admin-password';

// ── superadmin ────────────────────────────────────────────────────────────
$admin = User::query()->firstOrNew(['username' => 'admin']);
$admin->fill([
    'name' => 'مدیر سامانه',
    'username' => 'admin',
    'password' => Hash::make($adminPassword),
    'is_superadmin' => true,
    'is_active' => true,
    // The forced-change path is exercised separately by authed-flow.mjs against
    // its own user, so it is switched off here to keep manual browsing simple.
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

file_put_contents($secretFile, $adminPassword . "\n");
@chmod($secretFile, 0600);

echo "admin: admin (password written to {$secretFile}, mode 0600)\n";
echo "viewer: viewer1 (password not stored; regenerate with this script)\n";
echo "platform: " . App\Models\Permission::query()->count() . " permissions, "
    . Role::query()->count() . " roles\n";

if (getenv('VIRA_SHOW_SECRET') === '1') {
    echo "\nVIRA_SHOW_SECRET=1 — printing for an interactive sandbox session:\n";
    echo "  admin   / {$adminPassword}\n";
    echo "  viewer1 / {$viewerPassword}\n";
} else {
    echo "Set VIRA_SHOW_SECRET=1 to print them; by default the secret is not echoed.\n";
}
