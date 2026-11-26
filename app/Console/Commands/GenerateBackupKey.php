<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupCipher;
use Illuminate\Console\Command;

/**
 * Print a new VIRA_BACKUP_KEY.
 *
 * Separate from the credential key generator on purpose: the two secrets must
 * not be the same value, and having one command that could be mistaken for the
 * other is how that happens. See config/vira.php for why they are independent.
 *
 * The key is printed once and stored nowhere. It is deliberately NOT written to
 * .env by this command — a command that silently edits environment files can put
 * a key somewhere it was not meant to be, and the operator should place it
 * consciously.
 */
final class GenerateBackupKey extends Command
{
    protected $signature = 'vira:backup-key';

    protected $description = 'Generate a new VIRA_BACKUP_KEY (base64, 32 bytes)';

    public function handle(): int
    {
        if (! BackupCipher::supports()) {
            $this->warn('aes-256-gcm is unavailable in this build; backups will be refused at runtime.');
        }

        $this->newLine();
        $this->line('Add this to .env:');
        $this->newLine();
        $this->info('VIRA_BACKUP_KEY=' . BackupCipher::generateKey());
        $this->newLine();
        $this->warn('This key is the only way to read the backups it protects.');
        $this->warn('Store it separately from the backup files themselves — and');
        $this->warn('do NOT reuse VIRA_CREDENTIAL_KEY here; they are independent');
        $this->warn('secrets by design (config/vira.php).');

        return self::SUCCESS;
    }
}
