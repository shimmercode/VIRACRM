<?php
/**
 * DEVELOPMENT ONLY — set a known password on the bootstrap admin so the
 * end-to-end flow tests can authenticate. Never run this against a real
 * installation; the production path is the seeder, which generates a random
 * password the operator must change on first login.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$password = $argv[1] ?? null;

if ($password === null || strlen($password) < 12) {
    fwrite(STDERR, "usage: reset-admin.php <password of at least 12 chars>\n");
    exit(2);
}

$user = App\Models\User::query()->where('username', 'admin')->first();

if ($user === null) {
    fwrite(STDERR, "no admin user; run tools/seed.php first\n");
    exit(2);
}

$user->forceFill([
    'password' => Illuminate\Support\Facades\Hash::make($password),
    'must_change_password' => true,
    'is_active' => true,
    'is_superadmin' => true,
])->save();

echo "admin password reset; must_change_password=true\n";
