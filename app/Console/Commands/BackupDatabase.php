<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupCipher;
use App\Services\Backup\RetentionPolicy;
use App\Services\Security\AuditRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Take an encrypted, authenticated backup of the database and stored files.
 *
 * WHAT IT DOES
 * ------------
 * Writes one `.vbk1` container to storage/app/backups containing a SQLite online
 * snapshot, the storage files, and a manifest — gzipped and sealed with
 * AES-256-GCM. See docs/BACKUP_ARCHITECTURE.md for the format and for why GCM
 * rather than the CBC this replaced.
 *
 * WHY IT IS A CLI COMMAND AND NOT A ROUTE
 * ---------------------------------------
 * A backup contains every credential in the system. An HTTP endpoint that
 * produces one is an endpoint that can be made to produce one, and the
 * authentication for it becomes the single control standing between an attacker
 * and every password in the product. Shell access is already the same trust
 * level as the database itself, so requiring it adds no burden and removes an
 * attack surface. docs/HOST_COMPATIBILITY.md marks cron and SSH BLOCKED on the
 * target host; the honest consequence is that scheduling is unresolved
 * (docs/BACKUP_ARCHITECTURE.md §8), not that this should be exposed over HTTP to
 * work around it.
 *
 * FAILURE BEHAVIOUR
 * -----------------
 * Output is written to a `.partial` file and renamed only on success, so a
 * container with a final name is always a complete one. On failure the partial
 * is removed, nothing old is pruned, and a `backup_failed` audit row records the
 * reason.
 */
final class BackupDatabase extends Command
{
    protected $signature = 'vira:backup
                            {--path= : override the destination directory}
                            {--no-prune : keep every previous container}';

    protected $description = 'Create an encrypted AES-256-GCM backup of the database and stored files';

    public function handle(): int
    {
        $key = (string) (config('vira.backup_key') ?? '');

        if ($key === '') {
            $this->error('VIRA_BACKUP_KEY is not set. Refusing to write an unencrypted backup.');
            $this->line('Generate one with: php artisan vira:backup-key');

            return self::FAILURE;
        }

        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== 32) {
            $this->error('VIRA_BACKUP_KEY must be base64-encoded 32 bytes.');
            $this->line('Generate one with: php artisan vira:backup-key');

            return self::FAILURE;
        }

        if (! BackupCipher::supports()) {
            $this->error('aes-256-gcm is unavailable in this PHP/OpenSSL build.');
            $this->line('Refusing to write a backup that cannot be authenticated. See docs/BACKUP_ARCHITECTURE.md §1.');

            return self::FAILURE;
        }

        $destination = (string) ($this->option('path')
            ?: storage_path('app/' . config('vira.backup_path', 'backups')));

        $databasePath = (string) config('database.connections.sqlite.database');

        $this->line('database : ' . $databasePath);
        $this->line('storage  : ' . storage_path('app'));
        $this->line('target   : ' . $destination);

        try {
            $builder = new \App\Services\Backup\BackupBuilder(
                new BackupCipher($decoded),
                $databasePath,
                storage_path('app'),
            );

            $result = $builder->build($destination);
        } catch (\Throwable $e) {
            $this->audit('backup_failed', 'بکاپ ناموفق: ' . $e->getMessage());

            $this->error('backup FAILED: ' . $e->getMessage());
            $this->line('No previous backup was deleted.');

            return self::FAILURE;
        }

        /* Audit FIRST, cosmetics second.
         *
         * The first draft of this command printed a formatted table before
         * recording the event, and on a PHP build without ext-intl the table
         * renderer threw — so a backup had been written with no audit row and no
         * pruning. A backup whose security-relevant side effects sit downstream
         * of a formatting call is a backup that can quietly go unrecorded. Order
         * matters more than the pretty output. */
        $this->audit(
            'backup',
            sprintf(
                'بکاپ موفق: %s (%s بایت، %d جدول، %d ردیف، %d فایل)',
                basename((string) $result['path']),
                number_format((int) $result['bytes']),
                (int) $result['tables'],
                (int) $result['rows'],
                (int) $result['files'],
            ),
        );

        $this->info('backup written');
        $this->line('  file   : ' . basename((string) $result['path']));
        $this->line('  bytes  : ' . number_format((int) $result['bytes']));
        $this->line('  tables : ' . (int) $result['tables']);
        $this->line('  rows   : ' . (int) $result['rows']);
        $this->line('  files  : ' . (int) $result['files']);
        $this->line('  sha256 : ' . (string) $result['sha256']);

        if (! $this->option('no-prune')) {
            $removed = $this->prune($destination, (string) $result['path']);
            $this->line('  pruned : ' . $removed . ' old container(s)');
        }

        $this->newLine();
        $this->warn('VIRA_BACKUP_KEY is the only way to read this file. Store it');
        $this->warn('separately from the backup — a backup that travels with its');
        $this->warn('own key is not an encrypted backup.');

        return self::SUCCESS;
    }

    /**
     * Age-class retention: keep the newest container per period, per class.
     *
     * Runs only after a successful backup. Pruning first would mean a failed
     * backup could be the reason the last good one was deleted — which is
     * precisely when a backup is needed.
     *
     * The selection arithmetic lives in RetentionPolicy, which is pure and
     * unit-tested; this method only does the filesystem work around it.
     */
    private function prune(string $directory, string $keep): int
    {
        $files = [];

        foreach (glob(rtrim($directory, '/') . '/*.vbk1') ?: [] as $path) {
            $files[] = ['path' => $path, 'mtime' => (int) (filemtime($path) ?: 0)];
        }

        if ($files === []) {
            return 0;
        }

        $decision = RetentionPolicy::select($files, (array) config('vira.backup_retention', []));

        $removed = 0;

        foreach ($decision['remove'] as $path) {
            /* The container just written is never a candidate, whatever its
             * mtime or how the periods happen to fall. */
            if ($path === $keep) {
                continue;
            }
            if (@unlink($path)) {
                @unlink($path . '.sha256');
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Record an event without letting the recording become the failure.
     *
     * The audit table lives in the same database a backup reads and a restore
     * replaces. If that insert throws, it must not mask the real error — the
     * operator needs to know why the backup failed, not read a secondary
     * exception from the thing recording it. So every event also goes to the log
     * file, which does not depend on the database being healthy.
     */
    private function audit(string $event, string $description): void
    {
        Log::info('vira backup: ' . $event . ' — ' . $description);

        try {
            AuditRecorder::record($event, null, $description);
        } catch (\Throwable $e) {
            Log::error('could not record ' . $event . ' in the audit table: ' . $e->getMessage());
            $this->warn('note: the ' . $event . ' event could not be written to the audit table');
            $this->warn('      (' . $e->getMessage() . ') — it is in the log file instead.');
        }
    }
}
