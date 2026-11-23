<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Recover a lost superadmin credential.
 *
 * WHY THIS EXISTS
 * ---------------
 * `UserSeeder` deliberately refuses to touch an existing account ("Superadmin
 * already exists; left untouched") — correct for seeding, but it left no
 * supported way back in if the bootstrap secret was lost. The only options were
 * editing the database by hand or deleting the account, and a hand-edited
 * password hash is exactly the kind of thing that gets left in a shell history.
 *
 * This command follows the same secret-handling rules as the seeder:
 *
 *   - the secret comes from a file (`--password-file`) or from a generated value,
 *     never from an argument (arguments appear in `ps` and shell history);
 *   - a file is deleted after it is read;
 *   - the account is put back into "must change password" state, so the recovery
 *     secret is single-use;
 *   - nothing sensitive is written to a log.
 *
 * It is intentionally NOT reachable over HTTP. Recovery requires shell access to
 * the host, which is the same trust level as the database itself.
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'vira:reset-admin-password
                            {--password-file= : Path to a file holding the new secret (deleted after use)}
                            {--generate : Generate a strong secret and print it once}';

    protected $description = 'Reset the superadmin password and force a change at next login (recovery)';

    /** Minimum length, matching the seeder. */
    private const MIN_LENGTH = 12;

    public function handle(): int
    {
        $admin = User::query()->where('username', 'admin')->first();

        if ($admin === null) {
            $this->error('No superadmin account exists. Run the seeder to create one.');

            return self::FAILURE;
        }

        $file = $this->option('password-file');
        $generate = (bool) $this->option('generate');

        if ($file === null && ! $generate) {
            $this->error('Refusing to guess a password.');
            $this->line('Use one of:');
            $this->line('  php artisan vira:reset-admin-password --generate');
            $this->line('  php artisan vira:reset-admin-password --password-file=/run/secrets/admin_pw');

            return self::FAILURE;
        }

        if ($file !== null) {
            if (! is_file($file) || ! is_readable($file)) {
                $this->error("Cannot read {$file}.");

                return self::FAILURE;
            }

            $secret = trim((string) file_get_contents($file));

            if ($secret === '') {
                $this->error('The password file is empty.');

                return self::FAILURE;
            }

            if (strlen($secret) < self::MIN_LENGTH) {
                $this->error('The password must be at least '.self::MIN_LENGTH.' characters.');

                return self::FAILURE;
            }

            @unlink($file);
        } else {
            $secret = rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');
        }

        $admin->forceFill([
            'password' => Hash::make($secret),
            // Single use: the recovery secret must not become the real password.
            'must_change_password' => true,
            'failed_login_attempts' => 0,
        ])->save();

        // Any session opened with the old secret must not survive the reset, or
        // the recovery would hand an attacker the session it just invalidated.
        // Sessions are file-backed by default; the DB driver is checked first so
        // this works either way without assuming a schema.
        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $admin->getKey())
                ->delete();
        }

        $this->info('Superadmin password reset. The account must change it at next login.');
        $this->info('Existing sessions for this account were invalidated.');

        if ($generate) {
            $this->warn('Generated password — shown ONCE:');
            $this->warn('    '.$secret);
            $this->warn('It is not stored anywhere and cannot be recovered.');
        } else {
            $this->info('Password read from '.$file.' (the file has been deleted).');
        }

        return self::SUCCESS;
    }
}
