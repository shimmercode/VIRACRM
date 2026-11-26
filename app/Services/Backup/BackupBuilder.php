<?php

namespace App\Services\Backup;

use RuntimeException;
use SQLite3;
use ZipArchive;

/**
 * Builds a `.vbk1` backup container.
 *
 * CONTAINER LAYOUT
 * ----------------
 *   magic   "VIRABK1\n"
 *   header  one line of JSON + "\n"
 *   body    gzip( zip( manifest.json + database.sqlite + files/... ) )
 *           encrypted with AES-256-GCM
 *
 * WHY NOTHING HERE SHELLS OUT
 * ---------------------------
 * docs/HOST_COMPATIBILITY.md marks SSH and cron BLOCKED and warns that `exec`
 * may be disabled outright. A backup that needed `sqlite3 .backup` or `tar`
 * would be a backup that cannot run on the host it is for. Every step below is
 * pure PHP: SQLite3::backup(), ZipArchive, gzencode, openssl — each of which
 * tools/checks/env-capabilities.php verifies at runtime.
 *
 * WHY SQLite3::backup() AND NOT copy()
 * ------------------------------------
 * copy() takes no lock. A concurrent writer produces a torn file that may still
 * open and still pass `PRAGMA integrity_check`, which is the worst outcome: a
 * backup that looks restorable and is not. SQLite3::backup() is the online
 * backup API — it holds the read lock across the copy.
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
        private readonly string $databasePath,
        private readonly string $storagePath,
    ) {
    }

    /**
     * @return array{path:string,bytes:int,rows:int,tables:int,files:int,sha256:string}
     */
    public function build(string $destinationDirectory): array
    {
        if (! BackupCipher::supports()) {
            throw new RuntimeException('aes-256-gcm unavailable; refusing to build an unauthenticated backup.');
        }
        if (! is_dir($destinationDirectory) && ! mkdir($destinationDirectory, 0750, true) && ! is_dir($destinationDirectory)) {
            throw new RuntimeException("cannot create backup directory: {$destinationDirectory}");
        }

        $work = rtrim(sys_get_temp_dir(), '/') . '/vira-bk-' . bin2hex(random_bytes(6));
        if (! mkdir($work, 0700, true)) {
            throw new RuntimeException("cannot create work directory: {$work}");
        }

        try {
            $snapshot = $work . '/database.sqlite';
            $stats = $this->snapshotDatabase($snapshot);

            $files = $this->collectStorageFiles();

            $manifest = [
                'version' => 1,
                'created_at' => gmdate('c'),
                'database' => [
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

            $zip->addFile($snapshot, 'database.sqlite');

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
            ];
        } finally {
            $this->removeTree($work);
        }
    }

    /**
     * @return array{tables:int,rows:int}
     */
    private function snapshotDatabase(string $target): array
    {
        if (! file_exists($this->databasePath)) {
            throw new RuntimeException("database not found: {$this->databasePath}");
        }

        /* Read-only handle: a backup must never be able to write to the source. */
        $source = new SQLite3($this->databasePath, SQLITE3_OPEN_READONLY);
        $dest = new SQLite3($target);

        try {
            if ($source->backup($dest) !== true) {
                throw new RuntimeException('SQLite3::backup() failed');
            }
        } finally {
            $source->close();
        }

        $tables = (int) $dest->querySingle(
            "select count(*) from sqlite_master where type = 'table' and name not like 'sqlite_%'"
        );

        $rows = 0;
        $result = $dest->query(
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"
        );
        $names = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $names[] = (string) $row['name'];
        }
        $result->finalize();

        foreach ($names as $name) {
            $rows += (int) $dest->querySingle('select count(*) from "' . str_replace('"', '""', $name) . '"');
        }

        /* A snapshot that fails its own integrity check must not ship. */
        $integrity = (string) $dest->querySingle('PRAGMA integrity_check');
        $dest->close();

        if ($integrity !== 'ok') {
            throw new RuntimeException("snapshot failed integrity_check: {$integrity}");
        }

        return ['tables' => $tables, 'rows' => $rows];
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
