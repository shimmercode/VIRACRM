<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * REQ-10.01 — the superadmin account.
 *
 * NO PASSWORD IS STORED IN THIS REPOSITORY. The account is created in one of
 * three ways, in this order of precedence:
 *
 *   1. SEED_ADMIN_PASSWORD_FILE — a path to a file holding the secret. This is
 *      the recommended path: the secret never appears in an environment
 *      variable (which is readable from /proc/<pid>/environ and inherited by
 *      every child process), never appears in a shell history file, and can be
 *      created by a secret manager immediately before the install.
 *
 *   2. SEED_ADMIN_PASSWORD — an environment variable. Convenient for CI and
 *      containers where the variable is injected by the orchestrator.
 *
 *   3. Nothing set — a cryptographically random password is generated and
 *      printed ONCE to the console. Suitable for an interactive install where
 *      the operator is watching the terminal and can copy it.
 *
 * In every case `must_change_password` is true, so the bootstrap secret is
 * single-use: the first successful login is forced straight into the
 * password-change screen.
 *
 * WHY IT DOES NOT USE getenv() ALONE
 * ----------------------------------
 * The development sandbox runs PHP under WASM, which does NOT inherit the
 * parent process environment: `getenv()` returns false even when the variable
 * is exported. The earlier version of this seeder called getenv() only, so an
 * operator who set SEED_ADMIN_PASSWORD silently got a random password instead
 * and had no way to know. Both carriers are now consulted, plus the file form.
 */
class UserSeeder extends Seeder
{
    /** Minimum length for an operator-supplied bootstrap secret. */
    private const MIN_LENGTH = 12;

    public function run(): void
    {
        $existing = User::query()->where('username', 'admin')->first();

        if ($existing !== null) {
            $this->command?->warn('Superadmin already exists; left untouched.');

            return;
        }

        [$secret, $source] = $this->resolveSecret();

        $admin = User::create([
            'name' => 'مدیر سامانه',
            'username' => 'admin',
            'email' => null,
            'password' => Hash::make($secret),
            'is_superadmin' => true,
            'is_active' => true,
            // Single use: the first login must change it.
            'must_change_password' => true,
        ]);

        // Give the superadmin the manager role as well, so clearing the
        // superadmin flag later does not leave the account with no rights.
        $manager = Role::query()->where('code', 'manager')->first();

        if ($manager !== null) {
            $admin->roles()->syncWithoutDetaching([$manager->id]);
        }

        $this->command?->info('Superadmin created: admin (password from: ' . $source . ')');

        if ($source === 'generated') {
            $this->command?->warn('Generated password — shown ONCE, change it on first login:');
            $this->command?->warn('    ' . $secret);
            $this->command?->warn('Copy it now; it is not stored anywhere and cannot be recovered.');
        } else {
            $this->command?->info('The password you supplied is required to be changed at first login.');
        }
    }

    /**
     * Find the bootstrap secret, or generate one.
     *
     * @return array{0: string, 1: string} the secret and where it came from
     */
    private function resolveSecret(): array
    {
        $file = $this->readEnv('SEED_ADMIN_PASSWORD_FILE');

        if ($file !== null) {
            if (! is_file($file)) {
                throw new \RuntimeException(
                    "SEED_ADMIN_PASSWORD_FILE is set but {$file} is not readable."
                );
            }

            $secret = trim((string) file_get_contents($file));

            if ($secret === '') {
                throw new \RuntimeException('SEED_ADMIN_PASSWORD_FILE is empty.');
            }

            $this->assertStrong($secret, 'SEED_ADMIN_PASSWORD_FILE');

            // Remove the secret from disk as soon as it has been consumed, so a
            // leftover file cannot become a permanent credential.
            @unlink($file);

            return [$secret, 'SEED_ADMIN_PASSWORD_FILE'];
        }

        $inline = $this->readEnv('SEED_ADMIN_PASSWORD');

        if ($inline !== null) {
            $this->assertStrong($inline, 'SEED_ADMIN_PASSWORD');

            return [$inline, 'SEED_ADMIN_PASSWORD'];
        }

        // 18 random bytes -> 24 base64url characters: ~144 bits of entropy.
        return [rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '='), 'generated'];
    }

    /**
     * Read a variable from either carrier.
     *
     * $_ENV first: under WASM that is the only one populated, and on a normal
     * host Laravel's Dotenv fills it from .env. getenv() is kept as the last
     * resort for plain-CLI hosts that inject real environment variables.
     */
    private function readEnv(string $key): ?string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    private function assertStrong(string $secret, string $source): void
    {
        if (strlen($secret) < self::MIN_LENGTH) {
            throw new \RuntimeException(
                "{$source} must be at least " . self::MIN_LENGTH . ' characters; refusing to create a weak superadmin.'
            );
        }
    }
}
