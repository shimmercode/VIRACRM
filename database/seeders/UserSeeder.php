<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * REQ-10.01 — the superadmin account.
 *
 * NO PASSWORD IS STORED IN THIS REPOSITORY (§10 of the constraints, and basic
 * hygiene). The account is created in one of two ways:
 *
 *   1. SEED_ADMIN_PASSWORD is set in the environment — used for a controlled
 *      install where the operator supplies the secret.
 *   2. No variable is set — a cryptographically random password is generated
 *      and printed ONCE to the console. It is never written to the database in
 *      plaintext, to a log file, or to git.
 *
 * In both cases `must_change_password` is true, so the first login is forced
 * into the password-change screen and the bootstrap secret stops being a
 * long-lived credential.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $existing = User::query()->where('username', 'admin')->first();

        if ($existing !== null) {
            $this->command?->warn('Superadmin already exists; left untouched.');

            return;
        }

        $provided = getenv('SEED_ADMIN_PASSWORD');
        $generated = false;

        if (! is_string($provided) || $provided === '') {
            // 24 bytes -> ~32 base64 chars. Enough entropy, still typeable.
            $provided = rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');
            $generated = true;
        }

        $admin = User::create([
            'name' => 'مدیر سامانه',
            'username' => 'admin',
            'email' => null,
            'password' => Hash::make($provided),
            'is_superadmin' => true,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        // Give the superadmin the manager role as well, so removing the
        // superadmin flag later does not leave the account with no rights.
        $manager = Role::query()->where('code', 'manager')->first();
        if ($manager !== null) {
            $admin->roles()->syncWithoutDetaching([$manager->id]);
        }

        $this->command?->info('Superadmin created: admin');

        if ($generated) {
            $this->command?->warn('Generated password (shown once, change on first login): ' . $provided);
        } else {
            $this->command?->info('Password taken from SEED_ADMIN_PASSWORD.');
        }
    }
}
