<?php

namespace App\Services\Backup;

use App\Services\Backup\Snapshot\DatabaseSnapshotSource;
use RuntimeException;
use ZipArchive;

/**
 * Builds a `.vbk1` backup container.
 *
 * CONTAINER LAYOUT
 * ----------------
 *   magic   "VIRABK1\n"
 *   header  one line of JSON + "\n"
 *   body    gzip( zip( manifest.json + <database member> + files/... ) )
 *           encrypted with AES-256-GCM
 *
 * The database member is `database.sqlite` for a SQLite source and
 * `database.sql` for a MySQL dump. Which one it is, and therefore which
 * restorer may open the container, is recorded in the manifest under
 * `database.format` and mirrored in the header. A restore that is handed the
 * wrong kind refuses instead of installing something the target cannot read.
 *
 * WHY THE SOURCE IS AN OBJECT AND NOT A PATH
 * ------------------------------------------
 * This class used to take a `$databasePath` string and call `SQLite3::backup()`
 * on it, with the path supplied from the SQLite config key regardless of which
 * driver was active. Taking a `DatabaseSnapshotSource` instead means the
 * decision "how is this database backed up" is made once, explicitly, from the
 * live connection — and a driver with no strategy cannot reach this code at
 * all. See Snapshot\SnapshotSourceFactory.
 *
 * WHAT "SUCCESS" MEANS HERE
 * -------------------------
 * The container is written, then RE-OPENED and verified through the same gates
 * a restore uses, and only then renamed to its final name. A build that
 * produces a file the restorer would reject is a failure, not a backup.
 */
final class BackupBuilder
{
    private const MAGIC = "VIRABK1\n";

    /**
     * Ceiling on the uncompressed payload. GCM here encrypts the whole payload
     * in one call, so the payload must fit in memory; with memory_limit at 256M
     * a 64M ceiling leaves room for the gzip copy and PHP overhead.
     *
     * This is a refusal, not a silent truncation: a backup that quietly dropped
     * the tail of the database would restore as a smaller database and nobody
     * would notice until a customer record was missing.
     */
    private const MAX_PAYLOAD_BYTES = 64 * 1024 * 1024;

    public function __construct(
        private readonly BackupCipher $cipher,
        private readonly DatabaseSnapshotSource $source,
        private readonly string $storagePath,
    ) {
    }

    /**
     * @return array{path:string,bytes:int,rows:int,tables:int,files:int,sha256:string,format:string,driver:string}
     */
    public function build(string $destinationDirectory): array
    {
        if (! BackupCipher::supports()) {
            throw new RuntimeException('aes-256-gcm unavailable; refusing to build an unauthenticated backup.');
        }

        /* Readiness first — before a directory is created or a byte written.
         * A strategy that cannot run (missing mysqldump, unsupported driver,
         * proc_open disabled, unreadable database) must fail here so that
         * nothing downstream can be mistaken for progress. */
        $this->source->assertReady();

        /* @-suppressed deliberately: a destination that cannot be created is an
         * expected, tested outcome (an unwritable or read-only target), and the
         * refusal below is what the operator needs to see — not a PHP warning
         * above it. The exception carries the real information. */
        if (! is_dir($destinationDirectory) && ! @mkdir($destinationDirectory, 0750, true) && ! is_dir($destinationDirectory)) {
            throw new RuntimeException("cannot create backup directory: {$destinationDirectory}");
        }

        $work = rtrim(sys_get_temp_dir(), '/') . '/vira-bk-' . bin2hex(random_bytes(6));
        if (! mkdir($work, 0700, true)) {
            throw new RuntimeException("cannot create work directory: {$work}");
        }

        try {
            $snapshotMember = $this->source->member();
            $snapshot = $work . '/' . $snapshotMember;
            $stats = $this->source->snapshot($snapshot);

            $files = $this->collectStorageFiles();

            $manifest = [
                'version' => 2,
                'created_at' => gmdate('c'),
                'database' => [
                    'format' => $this->source->format(),
                    'driver' => $this->source->driver(),
                    'source' => $this->source->describe(),
                    'member' => $snapshotMember,
                    'sha256' => hash_file('sha256', $snapshot),
                    'bytes' => filesize($snapshot),
                    'tables' => $stats['tables'],
                    'rows' => $stats['rows'],
                ],
                'files' => [],
            ];

            $zipPath = $work . '/payload.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('cannot create payload archive');
            }

            $zip->addFile($snapshot, $snapshotMember);

            foreach ($files as $absolute => $relative) {
                $zip->addFile($absolute, 'files/' . $relative);
                $manifest['files'][$relative] = [
                    'sha256' => hash_file('sha256', $absolute),
                    'bytes' => filesize($absolute),
                ];
            }

            /* The manifest goes in last so it describes exactly what was added.
             *
             * Encoded ONCE and reused for both the archive member and the
             * checksum recorded in the header. Encoding it a second time for the
             * hash — without these flags — yields different bytes, and then every
             * container fails gate 5 on restore. Not a theoretical hazard: that
             * is what this file did before BackupRestoreTest caught it. */
            $manifestJson = (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('manifest.json', $manifestJson);
            $zip->close();

            $zipBytes = (string) file_get_contents($zipPath);
            if (strlen($zipBytes) > self::MAX_PAYLOAD_BYTES) {
                throw new RuntimeException(sprintf(
                    'payload is %s bytes, above the %s byte ceiling. Refusing rather than '
                    . 'running out of memory mid-encrypt; see docs/BACKUP_ARCHITECTURE.md §9.',
                    number_format(strlen($zipBytes)),
                    number_format(self::MAX_PAYLOAD_BYTES),
                ));
            }

            $gz = gzencode($zipBytes, 6);
            if ($gz === false) {
                throw new RuntimeException('gzip failed');
            }

            $envelope = $this->cipher->encrypt($gz);
            $manifestSha = hash('sha256', $manifestJson);

            $header = [
                'cipher' => $envelope['cipher'],
                'kdf' => $envelope['kdf'],
                'salt' => $envelope['salt'],
                'iv' => $envelope['iv'],
                'tag' => $envelope['tag'],
                'manifest_sha256' => $manifestSha,
                'payload_bytes' => strlen($gz),
                'created_at' => $manifest['created_at'],
                /* Mirrored here so a restorer can reject an incompatible
                 * container from the header alone, without decrypting. */
                'database_format' => $manifest['database']['format'],
                'database_driver' => $manifest['database']['driver'],
                'contents' => [
                    'database_bytes' => $manifest['database']['bytes'],
                    'database_tables' => $manifest['database']['tables'],
                    'database_rows' => $manifest['database']['rows'],
                    'files' => count($manifest['files']),
                ],
            ];

            $name = 'vira-backup-' . gmdate('Y-m-d-His') . '-' . bin2hex(random_bytes(3)) . '.vbk1';

            /* Write to .partial and rename only on success. A half-written file
             * with the final name would be indistinguishable from a good backup
             * to anything that lists the directory. */
            $partial = rtrim($destinationDirectory, '/') . '/' . $name . '.partial';
            $final = rtrim($destinationDirectory, '/') . '/' . $name;

            $body = self::MAGIC
                . json_encode($header, JSON_UNESCAPED_SLASHES) . "\n"
                . $envelope['ciphertext'];

            if (file_put_contents($partial, $body) === false) {
                throw new RuntimeException("cannot write {$partial}");
            }

            /* ── verify what was written, before it is called a backup ──────
             *
             * Requirement 1.6: validate integrity and encryption BEFORE
             * reporting success. The container is handed to the same restorer a
             * recovery would use. If the restorer would reject it, so do we —
             * while the .partial name still makes the file unusable, so a bad
             * container can never sit in the directory looking restorable.
             */
            $verification = (new BackupRestorer($this->cipher))->verifyDeep($partial);

            if (! $verification['ok']) {
                @unlink($partial);

                throw new RuntimeException(sprintf(
                    'the container was written but FAILED verification at gate %d (%s). '
                    . 'Nothing was kept; this is not a backup.',
                    (int) $verification['gate'],
                    (string) $verification['error'],
                ));
            }

            /* And the verified file must describe the same database we read. */
            $verifiedRows = (int) ($verification['manifest']['database']['rows'] ?? -1);
            $verifiedFormat = (string) ($verification['manifest']['database']['format'] ?? '');

            if ($verifiedFormat !== $this->source->format() || $verifiedRows !== (int) $stats['rows']) {
                @unlink($partial);

                throw new RuntimeException(sprintf(
                    'verification disagrees with what was read: expected %s/%d rows, '
                    . 'the container holds %s/%d. Refusing to report this as a backup.',
                    $this->source->format(),
                    (int) $stats['rows'],
                    $verifiedFormat,
                    $verifiedRows,
                ));
            }

            $sha = hash_file('sha256', $partial);
            if (! rename($partial, $final)) {
                @unlink($partial);
                throw new RuntimeException("cannot promote {$partial} to {$final}");
            }

            /* Sidecar checksum, so an off-host copy can be verified without the
             * backup key. */
            file_put_contents($final . '.sha256', $sha . '  ' . $name . "\n");

            return [
                'path' => $final,
                'bytes' => filesize($final),
                'rows' => $stats['rows'],
                'tables' => $stats['tables'],
                'files' => count($manifest['files']),
                'sha256' => $sha,
                'format' => $this->source->format(),
                'driver' => $this->source->driver(),
            ];
        } finally {
            $this->removeTree($work);
        }
    }

    /**
     * Everything under storage/app/private and storage/app/public, skipping
     * backups themselves — a backup containing the previous backup would grow
     * without bound.
     *
     * @return array<string,string> absolute => relative
     */
    private function collectStorageFiles(): array
    {
        $found = [];

        foreach (['private', 'public'] as $sub) {
            $root = rtrim($this->storagePath, '/') . '/' . $sub;
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $absolute = $file->getPathname();
                $relative = ltrim(str_replace($root, '', $absolute), '/\\');
                $found[$absolute] = $sub . '/' . $relative;
            }
        }

        ksort($found);

        return $found;
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
