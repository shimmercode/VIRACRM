<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupCipher;
use App\Services\Backup\BackupRestorer;
use App\Services\Security\AuditRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Restore from a `.vbk1` container — but only after proving it is good.
 *
 * THE ORDER IS THE SAFETY PROPERTY
 * --------------------------------
 * `--verify` runs the seven gates and writes nothing. A restore runs the same
 * gates first and refuses before touching the destination if any of them fails.
 * That ordering is requirement 1.7 of the security decisions: a restore must not
 * modify the target database without validating the file.
 *
 * The destination database is moved aside to `<path>.pre-restore-<timestamp>`
 * rather than overwritten, so a failure partway through still leaves the
 * previous database on disk. If the install then fails, it is moved back
 * automatically.
 *
 * WHY `--force` IS REQUIRED TO WRITE
 * ----------------------------------
 * A restore destroys the current database. Requiring an explicit flag means the
 * destructive path cannot be reached by typing the command to see what it does,
 * and `--verify` is the default because that is the thing you actually want to
 * do to an unfamiliar file.
 */
final class RestoreBackup extends Command
{
    protected $signature = 'vira:restore
                            {file : path to a .vbk1 container}
                            {--verify : validate only; write nothing (default behaviour without --force)}
                            {--force : actually restore; required to modify anything}';

    protected $description = 'Verify and restore an encrypted backup container';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $key = (string) (config('vira.backup_key') ?? '');

        if (! is_readable($file)) {
            $this->error("cannot read {$file}");

            return self::FAILURE;
        }

        if ($key === '') {
            $this->error('VIRA_BACKUP_KEY is not set; the container cannot be decrypted.');

            return self::FAILURE;
        }

        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== 32) {
            $this->error('VIRA_BACKUP_KEY must be base64-encoded 32 bytes.');

            return self::FAILURE;
        }

        $restorer = new BackupRestorer(new BackupCipher($decoded));

        $this->line('verifying ' . $file);
        $deep = $restorer->verifyDeep($file);

        if (! $deep['ok']) {
            $this->audit(
                'restore_failed',
                sprintf('بازیابی رد شد در دروازهٔ %d: %s', (int) $deep['gate'], (string) $deep['error']),
            );

            $this->error(sprintf('REFUSED at gate %d: %s', (int) $deep['gate'], (string) $deep['error']));
            /*
             * Precise wording, deliberately.
             *
             * The earlier text here was "The destination was not modified", and
             * that is not quite true: refusing the restore installs no data from
             * the container, but recording the refusal appends one audit row to
             * the application database — which, during a restore attempt, is the
             * destination. Measured: table list and every non-audit row are
             * unchanged, the file's checksum is not. Saying "not modified" would
             * be the kind of overclaim this whole exercise is trying to remove.
             */
            $this->warn('No data from the container was installed.');
            $this->warn('One restore_failed row was appended to the audit log.');

            return self::FAILURE;
        }

        $manifest = (array) $deep['manifest'];
        $db = (array) ($manifest['database'] ?? []);

        $this->info('verification passed — all seven gates');
        $this->line('  created : ' . (string) ($manifest['created_at'] ?? '?'));
        $this->line('  tables  : ' . (int) ($db['tables'] ?? 0));
        $this->line('  rows    : ' . (int) ($db['rows'] ?? 0));
        $this->line('  files   : ' . count((array) ($manifest['files'] ?? [])));

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Nothing was written. Re-run with --force to restore.');
            $this->warn('The current database will be moved aside, not overwritten.');

            return self::SUCCESS;
        }

        /*
         * Where the restore lands, and what format it expects, both come from
         * the live connection. A SQLite deployment gets its file replaced; a
         * MySQL deployment cannot have its server overwritten by copying a file,
         * so it refuses until a mysql-client restore path exists — rather than
         * copying a dump over a path that is not a database.
         */
        $connection = app(\Illuminate\Database\DatabaseManager::class)->connection();
        $driver = (string) $connection->getDriverName();

        $targetFormat = match ($driver) {
            'sqlite' => 'sqlite-file',
            'mysql', 'mariadb' => 'mysqldump',
            default => throw new \RuntimeException(
                "no restore strategy is implemented for the \"{$driver}\" driver."
            ),
        };

        $databasePath = (string) $connection->getDatabaseName();

        $this->line('target driver : ' . $driver);
        $this->line('target format : ' . $targetFormat);

        if ($targetFormat === 'mysqldump') {
            $this->error('restoring into a MySQL/MariaDB server is not implemented.');
            $this->line('A restore there must be performed with the mysql client');
            $this->line('(mysql --user=... --password=... <database> < the extracted .sql),');
            $this->line('which this command cannot do safely without a live server to verify');
            $this->line('against. Nothing was written.');
            $this->warn('The container is verified and intact; the extraction is manual.');

            $this->audit(
                'restore_failed',
                'بازیابی MySQL پشتیبانی نمی‌شود؛ فایل تأیید شد ولی چیزی نوشته نشد.',
            );

            return self::FAILURE;
        }

        $this->warn('This replaces ' . $databasePath);

        /*
         * Confirm interactively, but do not block a scripted run.
         *
         * `--force` is already an explicit assertion of intent; demanding a
         * second "yes" from a terminal that has no one at it makes the command
         * unusable exactly where it is needed — an unattended recovery. When
         * there IS a human attached, they are still asked.
         */
        if ($this->input->isInteractive() && ! $this->confirm('Proceed?', false)) {
            $this->line('aborted; nothing written.');

            return self::SUCCESS;
        }

        try {
            $result = $restorer->restore($file, $databasePath, storage_path('app'), $targetFormat);
        } catch (\Throwable $e) {
            $this->audit('restore_failed', 'بازیابی ناموفق: ' . $e->getMessage());

            $this->error('restore FAILED: ' . $e->getMessage());
            $this->warn('The previous database was left in place or moved back.');

            return self::FAILURE;
        }

        /* Audit before cosmetics — see the same note in BackupDatabase. A
         * renderer must never be able to swallow the record of a restore. */
        $this->audit(
            'restore',
            sprintf(
                'بازیابی موفق از %s (%d جدول، %d ردیف، %d فایل)',
                basename($file),
                (int) $result['tables'],
                (int) $result['rows'],
                (int) $result['files'],
            ),
        );

        $this->info('restore complete');
        $this->line('  database       : ' . (string) $result['database']);
        $this->line('  tables         : ' . (int) $result['tables']);
        $this->line('  rows           : ' . (int) $result['rows']);
        $this->line('  files restored : ' . (int) $result['files']);
        $this->line('  format         : ' . (string) ($result['format'] ?? 'sqlite-file'));

        return self::SUCCESS;
    }

    /**
     * Record an event without letting the recording become the failure.
     *
     * The audit table lives in the same database a restore is about to replace,
     * and a corrupt database is one of the most likely reasons to run a restore
     * at all. If that insert throws, it must not mask the real error: the
     * operator needs to know why the restore failed, not read a secondary
     * exception from the thing recording it. So every event also goes to the log
     * file, which does not depend on the database being healthy.
     */
    private function audit(string $event, string $description): void
    {
        Log::info('vira restore: ' . $event . ' — ' . $description);

        try {
            AuditRecorder::record($event, null, $description);
        } catch (\Throwable $e) {
            Log::error('could not record ' . $event . ' in the audit table: ' . $e->getMessage());
            $this->warn('note: the ' . $event . ' event could not be written to the audit table');
            $this->warn('      (' . $e->getMessage() . ') — it is in the log file instead.');
        }
    }
}
