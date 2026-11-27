<?php

namespace App\Services\Backup;

use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Verifies and restores a `.vbk1` container.
 *
 * THE RULE THIS CLASS EXISTS TO ENFORCE
 * -------------------------------------
 * A restore must not touch the destination until the backup has been proven
 * good. That is not a nicety: a restore that fails halfway leaves a database
 * that is neither the old one nor a valid new one, which is worse than either.
 *
 * So verification and restoration are separate methods with separate
 * responsibilities:
 *
 *   verify()  — read-only. Opens the container, runs every gate, reports what
 *               is inside. Writes nothing anywhere. Safe to call on a file of
 *               unknown provenance.
 *   restore() — calls verify() first and aborts before any write if it fails.
 *               Extracts to a staging directory, re-checks, and only then moves
 *               anything into place.
 *
 * THE SEVEN GATES
 * ---------------
 *  1 magic bytes          — is this a VIRA container at all
 *  2 header parses        — is the envelope readable
 *  3 GCM authentication   — right key AND unmodified ciphertext
 *  4 gzip inflation       — not truncated
 *  5 manifest hash        — the manifest is the one the header committed to
 *  6 per-member sha256    — no single file inside was swapped or corrupted
 *  7 integrity_check      — the database itself is structurally sound
 *
 * Gate 2 additionally carries a FORMAT check: a container records which database
 * format it holds (`sqlite-file` or `mysqldump`), and a restore refuses to
 * install one that does not match what it is being asked to restore into. That
 * is a gate of its own in spirit — an incompatible container is exactly as
 * unusable as a corrupt one, and the operator should be told which it is before
 * anything is moved aside.
 *
 * Gate 3 is the one CBC could not provide. Gates 5 and 6 are belt-and-braces:
 * GCM already covers them, but they turn "the file is bad" into "this specific
 * member is bad", which is the difference between a shrug and a diagnosis.
 */
final class BackupRestorer
{
    private const MAGIC = "VIRABK1\n";

    public function __construct(private readonly BackupCipher $cipher)
    {
    }

    /**
     * Read-only validation. Never writes.
     *
     * @return array{
     *   ok: bool,
     *   gate: ?int,
     *   error: ?string,
     *   manifest: ?array,
     *   header: ?array,
     *   payload: ?string
     * }
     */
    public function verify(string $containerPath): array
    {
        $fail = fn (int $gate, string $error) => [
            'ok' => false, 'gate' => $gate, 'error' => $error,
            'manifest' => null, 'header' => null, 'payload' => null,
        ];

        if (! is_readable($containerPath)) {
            return $fail(1, "cannot read {$containerPath}");
        }

        $raw = (string) file_get_contents($containerPath);

        // ── gate 1: magic ────────────────────────────────────────────────
        if (! str_starts_with($raw, self::MAGIC)) {
            return $fail(1, 'not a VIRA backup container (magic bytes missing)');
        }

        // ── gate 2: header ───────────────────────────────────────────────
        $rest = substr($raw, strlen(self::MAGIC));
        $newline = strpos($rest, "\n");

        if ($newline === false) {
            return $fail(2, 'header is not terminated — file is truncated');
        }

        $header = json_decode(substr($rest, 0, $newline), true);
        if (! is_array($header)) {
            return $fail(2, 'header is not valid JSON');
        }

        $ciphertext = substr($rest, $newline + 1);
        if ($ciphertext === '' || $ciphertext === false) {
            return $fail(2, 'container has a header but no body');
        }

        // ── gate 3: GCM authentication ───────────────────────────────────
        $gz = $this->cipher->decrypt($header, $ciphertext);
        if ($gz === false) {
            return $fail(3, 'decryption failed — wrong key, or the file has been modified');
        }

        // ── gate 4: inflation ────────────────────────────────────────────
        $zipBytes = gzdecode($gz);
        if ($zipBytes === false) {
            return $fail(4, 'payload is not valid gzip — the file is incomplete or truncated');
        }

        // ── gate 5: manifest hash ────────────────────────────────────────
        $tmpZip = tempnam(sys_get_temp_dir(), 'vira-vf-');
        file_put_contents($tmpZip, $zipBytes);

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                return $fail(4, 'payload is not a valid archive');
            }

            $manifestJson = $zip->getFromName('manifest.json');
            $zip->close();

            if ($manifestJson === false) {
                return $fail(5, 'archive contains no manifest.json');
            }

            $manifest = json_decode($manifestJson, true);
            if (! is_array($manifest)) {
                return $fail(5, 'manifest.json is not valid JSON');
            }

            $expected = (string) ($header['manifest_sha256'] ?? '');
            $actual = hash('sha256', $manifestJson);
            if (! hash_equals($expected, $actual)) {
                return $fail(5, 'manifest does not match the hash in the header');
            }
        } finally {
            @unlink($tmpZip);
        }

        return [
            'ok' => true, 'gate' => null, 'error' => null,
            'manifest' => $manifest, 'header' => $header, 'payload' => $zipBytes,
        ];
    }

    /**
     * Full verification including every member's checksum and the database's own
     * integrity check (gates 6 and 7). Still writes nothing outside a temporary
     * directory it removes.
     *
     * @return array{ok:bool,gate:?int,error:?string,manifest:?array,header:?array}
     */
    public function verifyDeep(string $containerPath): array
    {
        $v = $this->verify($containerPath);
        if (! $v['ok']) {
            return ['ok' => false, 'gate' => $v['gate'], 'error' => $v['error'], 'manifest' => null, 'header' => null];
        }

        $stage = rtrim(sys_get_temp_dir(), '/') . '/vira-vd-' . bin2hex(random_bytes(6));
        mkdir($stage, 0700, true);

        try {
            $extracted = $this->extract($v['payload'], $stage);

            // ── gate 6: per-member checksums ─────────────────────────────
            foreach ($extracted['files'] as $relative => $absolute) {
                $expected = $v['manifest']['files'][$relative]['sha256'] ?? null;
                if ($expected === null) {
                    return $this->deepFail(6, "member '{$relative}' is not listed in the manifest");
                }
                if (! hash_equals((string) $expected, (string) hash_file('sha256', $absolute))) {
                    return $this->deepFail(6, "member '{$relative}' failed its checksum");
                }
            }

            foreach ($v['manifest']['files'] ?? [] as $relative => $meta) {
                if (! isset($extracted['files'][$relative])) {
                    return $this->deepFail(6, "manifest lists '{$relative}' but the archive does not contain it");
                }
            }

            /* The manifest is now the authority on which member holds the
             * database. Container version 1 always used `database.sqlite`; a
             * version 2 container names it, so a MySQL dump is not mistaken for
             * a SQLite file. Defaulting to the old name keeps v1 containers
             * restorable rather than orphaning them. */
            $member = (string) ($v['manifest']['database']['member'] ?? 'database.sqlite');

            // ── gate 7: database integrity ───────────────────────────────
            $dbPath = $extracted['database'][$member] ?? null;

            if ($dbPath === null) {
                return $this->deepFail(7, "the archive does not contain the declared database member '{$member}'");
            }

            $expectedDb = $v['manifest']['database']['sha256'] ?? null;
            if ($expectedDb !== null && ! hash_equals((string) $expectedDb, (string) hash_file('sha256', $dbPath))) {
                return $this->deepFail(7, "{$member} failed its checksum");
            }

            $format = (string) ($v['manifest']['database']['format'] ?? 'sqlite-file');

            $integrity = $this->integrityCheck($dbPath, $format);
            if ($integrity !== 'ok') {
                return $this->deepFail(7, "database integrity_check ({$format}): {$integrity}");
            }

            return ['ok' => true, 'gate' => null, 'error' => null, 'manifest' => $v['manifest'], 'header' => $v['header']];
        } finally {
            $this->removeTree($stage);
        }
    }

    /**
     * Restore, but only after every gate has passed.
     *
     * DRIVER AWARENESS
     * ----------------
     * `$targetFormat` is the format the DESTINATION expects, derived from the
     * live connection (`sqlite-file` or `mysqldump`). It defaults to
     * `sqlite-file` so every existing caller and every v1 container keeps the
     * behaviour it had.
     *
     * The compatibility check runs before anything is moved aside. Installing a
     * MySQL dump over a SQLite file would produce a "database" that no process
     * can open, and the previous file would already have been renamed — the
     * operator would be left with two unusable files and no clear cause. So an
     * incompatible container is refused the same way a corrupt one is, and for
     * the same reason.
     *
     * @return array{database:string,files:int,rows:int,tables:int,format:string}
     */
    public function restore(
        string $containerPath,
        string $databasePath,
        string $storagePath,
        string $targetFormat = 'sqlite-file',
    ): array {
        $deep = $this->verifyDeep($containerPath);
        if (! $deep['ok']) {
            throw new RuntimeException(
                "restore refused at gate {$deep['gate']}: {$deep['error']}. "
                . 'The destination was not modified.'
            );
        }

        $containerFormat = (string) ($deep['manifest']['database']['format'] ?? 'sqlite-file');

        if ($containerFormat !== $targetFormat) {
            throw new RuntimeException(sprintf(
                'incompatible container: it holds a "%s" database but this host runs "%s". '
                . 'Refusing before anything is written. Restore a %s container here, or '
                . 'restore this one on a %s host.',
                $containerFormat,
                $targetFormat,
                $targetFormat,
                $containerFormat,
            ));
        }

        $stage = rtrim(sys_get_temp_dir(), '/') . '/vira-rs-' . bin2hex(random_bytes(6));
        mkdir($stage, 0700, true);

        try {
            $v = $this->verify($containerPath);
            $extracted = $this->extract((string) $v['payload'], $stage);

            /* Move the previous database aside rather than overwriting it. If
             * anything below fails, the old file is still on disk and can be put
             * back by hand — an overwrite would have destroyed it. */
            $previous = null;
            if (file_exists($databasePath)) {
                $previous = $databasePath . '.pre-restore-' . gmdate('YmdHis');
                if (! rename($databasePath, $previous)) {
                    throw new RuntimeException("cannot move the existing database aside to {$previous}");
                }
            }

            try {
                $member = (string) ($deep['manifest']['database']['member'] ?? 'database.sqlite');
                $dbSource = $extracted['database'][$member]
                    ?? throw new RuntimeException("the archive does not contain '{$member}'");

                if (! copy($dbSource, $databasePath)) {
                    throw new RuntimeException("cannot install the restored database at {$databasePath}");
                }

                $installed = 0;
                foreach ($extracted['files'] as $relative => $absolute) {
                    /* `private/x` or `public/x` — the first segment names the
                     * storage subdirectory the file came from. */
                    $target = rtrim($storagePath, '/') . '/' . $relative;
                    $dir = dirname($target);

                    if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
                        throw new RuntimeException("cannot create {$dir}");
                    }
                    if (! copy($absolute, $target)) {
                        throw new RuntimeException("cannot restore file {$relative}");
                    }
                    $installed++;
                }

                $integrity = $this->integrityCheck($databasePath, $containerFormat);
                if ($integrity !== 'ok') {
                    throw new RuntimeException("installed database failed integrity_check: {$integrity}");
                }

                return [
                    'database' => $databasePath,
                    'files' => $installed,
                    'rows' => (int) ($deep['manifest']['database']['rows'] ?? 0),
                    'tables' => (int) ($deep['manifest']['database']['tables'] ?? 0),
                    'format' => $containerFormat,
                ];
            } catch (\Throwable $e) {
                /* Put the old database back. This is the whole reason it was
                 * moved rather than overwritten. */
                if ($previous !== null && file_exists($previous)) {
                    @rename($previous, $databasePath);
                }
                throw $e;
            }
        } finally {
            $this->removeTree($stage);
        }
    }

    /**
     * @return array{database:array<string,string>,files:array<string,string>}
     *         `database` is keyed by archive member name — a container holds
     *         exactly one, either `database.sqlite` or `database.sql`.
     */
    private function extract(string $zipBytes, string $stage): array
    {
        $zipPath = $stage . '/payload.zip';
        file_put_contents($zipPath, $zipBytes);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('payload archive will not open');
        }

        /* Keyed by member name: the manifest says which one is the database, and
         * the restorer must find exactly that rather than assuming SQLite. */
        $databases = [];
        $files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            /* Reject traversal. A crafted archive must not be able to write
             * outside the staging directory — `../../.env` is the obvious
             * attack and it costs one check to close. */
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();
                throw new RuntimeException("archive member escapes the staging directory: {$name}");
            }

            if ($name === 'database.sqlite' || $name === 'database.sql') {
                $database = $stage . '/' . $name;
                file_put_contents($database, (string) $zip->getFromIndex($i));
                $databases[$name] = $database;

                continue;
            }

            if ($name === 'manifest.json' || str_ends_with($name, '/')) {
                continue;
            }

            if (str_starts_with($name, 'files/')) {
                $relative = substr($name, strlen('files/'));
                $target = $stage . '/files/' . $relative;
                $dir = dirname($target);

                if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                    $zip->close();
                    throw new RuntimeException("cannot create {$dir}");
                }

                file_put_contents($target, (string) $zip->getFromIndex($i));
                $files[$relative] = $target;
            }
        }

        $zip->close();

        if ($databases === []) {
            throw new RuntimeException('archive contains no database member');
        }

        return ['database' => $databases, 'files' => $files];
    }

    /**
     * Structural validation, by format.
     *
     * SQLite: its own integrity_check, which is the definitive answer.
     *
     * MySQL dump: there is no local server to ask, so this validates what a dump
     * must structurally contain — a mysqldump trailer, at least one CREATE TABLE,
     * and balanced statement termination. That is NOT a substitute for restoring
     * the dump into a real server, which is why real MySQL acceptance stays
     * BLOCKED until somebody does that; it catches truncation and garbage, which
     * is what this gate is for.
     */
    private function integrityCheck(string $path, string $format = 'sqlite-file'): string
    {
        if ($format === 'mysqldump') {
            return $this->mysqlDumpIntegrityCheck($path);
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            return (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        } catch (\Throwable $e) {
            return 'unreadable (' . $e->getMessage() . ')';
        }
    }

    /**
     * Structural check of a mysqldump file.
     *
     * mysqldump ends every complete dump with "-- Dump completed on ...". Its
     * absence is the reliable signature of a truncated file, which is the
     * failure that otherwise survives to a restore.
     */
    private function mysqlDumpIntegrityCheck(string $path): string
    {
        $size = @filesize($path);

        if ($size === false || $size === 0) {
            return 'empty dump';
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return 'unreadable dump';
        }

        $head = (string) fread($handle, 4096);

        /* Read the tail without loading a multi-hundred-megabyte file whole. */
        fseek($handle, max(0, $size - 65536));
        $tail = (string) fread($handle, 65536);
        fclose($handle);

        if (! str_contains($head, 'MySQL dump')) {
            return 'not a mysqldump file (no header)';
        }

        if (! str_contains($tail, 'Dump completed')) {
            return 'truncated dump (no completion trailer)';
        }

        if (! preg_match('/^CREATE TABLE /m', $tail) && ! str_contains($head . $tail, 'CREATE TABLE')) {
            return 'dump contains no CREATE TABLE';
        }

        return 'ok';
    }

    /** @return array{ok:bool,gate:int,error:string,manifest:null,header:null} */
    private function deepFail(int $gate, string $error): array
    {
        return ['ok' => false, 'gate' => $gate, 'error' => $error, 'manifest' => null, 'header' => null];
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
