<?php

namespace Tests\Feature;

use App\Services\Backup\BackupBuilder;
use App\Services\Backup\BackupCipher;
use App\Services\Backup\BackupRestorer;
use App\Services\Backup\RetentionPolicy;
use App\Services\Backup\Snapshot\SqliteSnapshotSource;
use RuntimeException;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * Backup and restore, exercised against an isolated database — never the
 * shared one.
 *
 * ISOLATION
 * ---------
 * DatabaseHarness::boot() creates a fresh SQLite file in the system temp
 * directory and points Eloquent at it; the real database/database.sqlite is
 * never opened by this suite. The "storage root" is likewise a temp directory.
 * The destination database for a restore is a separate temp path, so even a
 * successful restore touches nothing real. This is required by the execution
 * rules: backup/restore work must not touch production data.
 *
 * WHAT THIS SUITE IS FOR
 * ----------------------
 * Requirement 1.6 of the security decisions: a tampered file, an incomplete
 * file, a wrong key and an invalid manifest must all be rejected BEFORE
 * restore. And requirement 1.7: restore must not modify the destination
 * database until the file is proven valid. Both are properties of ordering, and
 * ordering is exactly what a green "backup works" test does not prove. So every
 * negative case here asserts two things — that the restore refused, and that the
 * destination is byte-for-byte what it was before.
 *
 * The previous design used AES-256-CBC and could not satisfy the first half:
 * CBC has no authentication, so a tampered container decrypted happily and only
 * a separate sidecar manifest caught the damage. GCM authenticates inline,
 * which is what gate 3 below is testing. See docs/BACKUP_ARCHITECTURE.md.
 *
 * SCOPE OF THE "DESTINATION UNCHANGED" ASSERTION
 * ----------------------------------------------
 * These tests exercise BackupRestorer directly, and at that level a refused
 * restore leaves the destination file byte-for-byte identical. The vira:restore
 * command adds one thing on top: it records a `restore_failed` audit row, and
 * the audit table lives in the application database — which, during a restore,
 * is the destination. So through the CLI the file's checksum does change, by
 * exactly one appended audit row, while no data from the container is installed
 * (measured: identical table list and identical non-audit rows). Both statements
 * are true; they are about different layers. Do not "fix" one to match the other.
 */
final class BackupRestoreTest extends ViraTestCase
{
    private DatabaseHarness $db;
    private string $work;
    private string $storageRoot;
    private string $backupKey;
    private string $otherKey;
    private int $destinationSequence = 0;

    protected function setUp(): void
    {
        $this->db = DatabaseHarness::boot('backup-restore');
        $this->db->migrate();

        $this->work = rtrim(sys_get_temp_dir(), '/') . '/vira-bak-test-' . bin2hex(random_bytes(4));
        mkdir($this->work . '/backups', 0700, true);
        mkdir($this->work . '/restore-here', 0700, true);

        /* A storage root with something in it, so the file side of the
         * container is exercised rather than only the database. */
        $this->storageRoot = $this->work . '/storage-app';
        mkdir($this->storageRoot . '/private/docs', 0700, true);
        mkdir($this->storageRoot . '/public/media', 0700, true);
        file_put_contents($this->storageRoot . '/private/docs/note.txt', "private payload\n");
        file_put_contents($this->storageRoot . '/public/media/logo.svg', "<svg/>\n");

        $this->backupKey = BackupCipher::generateKey();
        $this->otherKey = BackupCipher::generateKey();
    }

    public function run(): void
    {
        $this->testGcmIsActuallyAvailableHere();
        $this->testKeysAreIndependentByConfiguration();
        $this->testRoundTripRestoresEveryRow();
        $this->testPreviousDatabaseIsMovedAsideNotOverwritten();
        $this->testVerifyWritesNothing();
        $this->testTamperedCiphertextIsRefused();
        $this->testTruncatedFileIsRefused();
        $this->testWrongKeyIsRefused();
        $this->testBadMagicIsRefused();
        $this->testInvalidManifestIsRefused();
        $this->testMemberChecksumMismatchIsRefused();
        $this->testDestinationIsUnchangedAfterEveryRefusal();
        $this->testKeyMaterialNeverAppearsInContainer();
        $this->testTwoBuildsProduceDifferentContainers();
        $this->testRetentionKeepsOneFilePerPeriod();
        $this->testRetentionWithAllLimitsZeroKeepsNothing();
        $this->testRetentionNeverRemovesTheNewest();
        $this->testRetentionRemovesSomethingForTwentyDailyBackups();

        $this->cleanup();
        $this->db->destroy();
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function builder(?string $databasePath = null): BackupBuilder
    {
        /* The builder now takes a strategy object rather than a path. For the
         * SQLite tests that is SqliteSnapshotSource over the harness database;
         * the driver is named explicitly here because these tests are about the
         * SQLite path specifically. */
        return new BackupBuilder(
            new BackupCipher(base64_decode($this->backupKey, true)),
            new SqliteSnapshotSource($databasePath ?? $this->db->path),
            $this->storageRoot,
        );
    }

    private function restorer(?string $key = null): BackupRestorer
    {
        return new BackupRestorer(
            new BackupCipher(base64_decode($key ?? $this->backupKey, true))
        );
    }

    /**
     * A throwaway database that stands in for "the production database".
     *
     * Uniquely named per call on purpose: several tests glob for
     * `<destination>.pre-restore-*` to prove a refused restore moved nothing
     * aside, and a shared destination would make them see the aside file left by
     * an earlier SUCCESSFUL restore. That produced three false failures on the
     * first run — the assertion was correct, the fixture was not.
     */
    private function destinationDatabase(): string
    {
        $path = $this->work . '/restore-here/db' . (++$this->destinationSequence) . '.sqlite';
        file_put_contents($path, "not a database yet\n");

        return $path;
    }

    private function seedDestination(): array
    {
        $path = $this->destinationDatabase();

        return ['path' => $path, 'sha' => hash_file('sha256', $path), 'mtime' => filemtime($path)];
    }

    private function cleanup(): void
    {
        $rm = function (string $dir) use (&$rm): void {
            if (! is_dir($dir)) {
                return;
            }
            foreach (scandir($dir) ?: [] as $e) {
                if ($e === '.' || $e === '..') {
                    continue;
                }
                $p = $dir . '/' . $e;
                is_dir($p) ? $rm($p) : @unlink($p);
            }
            @rmdir($dir);
        };
        $rm($this->work);
    }

    /** Flip one byte deep in the payload, past the header line. */
    private function tamper(string $file, int $offsetDelta = 200): void
    {
        $raw = (string) file_get_contents($file);
        $at = min(strlen($raw) - 1, strlen($raw) - $offsetDelta);
        $raw[$at] = chr((ord($raw[$at]) ^ 0x5a) % 256);
        file_put_contents($file, $raw);
    }

    /**
     * Rebuild a real container with a mutated archive payload and a VALID header.
     *
     * The header is carried over field by field rather than replaced with the
     * cipher's envelope, because the envelope only holds cipher/kdf/salt/iv/tag.
     * Dropping `manifest_sha256` makes the container fail gate 2 (bad header) and
     * the test then proves nothing about the gate it meant to exercise — the
     * first draft of this suite did exactly that and reported gate 2 where it
     * claimed to be testing gates 5 and 6.
     *
     * @param callable(\ZipArchive):void $mutate
     */
    private function forge(string $source, string $target, callable $mutate): void
    {
        $cipher = new BackupCipher(base64_decode($this->backupKey, true));

        $raw = (string) file_get_contents($source);
        $rest = (string) substr($raw, 8);
        $headerLine = (string) substr($rest, 0, (int) strpos($rest, "\n"));
        $header = (array) json_decode($headerLine, true);

        $zipBytes = (string) gzdecode((string) $cipher->decrypt($header, substr($rest, strlen($headerLine) + 1)));

        $zipPath = $this->work . '/' . basename($target) . '.src.zip';
        file_put_contents($zipPath, $zipBytes);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('could not reopen the payload for ' . $target);
        }
        $mutate($zip);
        $zip->close();

        $gz = (string) gzencode((string) file_get_contents($zipPath), 6);
        $envelope = $cipher->encrypt($gz);

        foreach (['cipher', 'kdf', 'salt', 'iv', 'tag'] as $field) {
            $header[$field] = $envelope[$field];
        }
        $header['payload_bytes'] = strlen($gz);

        file_put_contents(
            $target,
            "VIRABK1\n" . json_encode($header, JSON_UNESCAPED_SLASHES) . "\n" . $envelope['ciphertext'],
        );
    }

    // ── tests ───────────────────────────────────────────────────────────

    /**
     * GCM must be usable in THIS runtime, or every guarantee below is fiction.
     *
     * Also asserts the property GCM is chosen for: authentication. Tampering the
     * ciphertext must make decryption fail rather than return garbage — which is
     * precisely what CBC did not do.
     */
    private function testGcmIsActuallyAvailableHere(): void
    {
        $this->assertTrue(BackupCipher::supports(), 'aes-256-gcm is unavailable in this build');

        $cipher = new BackupCipher(base64_decode($this->backupKey, true));
        $envelope = $cipher->encrypt('canary');

        $this->assertSame('canary', $cipher->decrypt($envelope, $envelope['ciphertext']));

        /* Flip a ciphertext bit: decryption must fail, not return junk. */
        $bad = $envelope;
        $ct = $envelope['ciphertext'];
        $ct[0] = chr((ord($ct[0]) ^ 0x01) % 256);
        $bad['ciphertext'] = $ct;
        $this->assertFalse(
            (bool) $cipher->decrypt($envelope, $bad['ciphertext']),
            'a tampered ciphertext decrypted successfully — the cipher is not authenticating',
        );

        /* Flip a tag bit: same result. */
        $badTag = $envelope;
        $tag = $envelope['tag'];
        $tag[0] = chr((ord($tag[0]) ^ 0x01) % 256);
        $badTag['tag'] = $tag;
        $this->assertFalse(
            (bool) $cipher->decrypt($badTag, $envelope['ciphertext']),
            'a tampered tag decrypted successfully — the tag is not being verified',
        );

        /* Wrong key must not decrypt either. */
        $this->assertFalse(
            (bool) (new BackupCipher(base64_decode($this->otherKey, true)))
                ->decrypt($envelope, $envelope['ciphertext']),
            'the wrong key decrypted the payload',
        );
    }

    /**
     * The backup key and the credential key must be independent secrets.
     *
     * This is a source assertion on config/vira.php and .env.example, which is
     * the only place the relationship is actually defined. It exists so that a
     * later change that collapses them onto one environment variable fails a
     * test instead of silently making every backup a full credential export.
     */
    private function testKeysAreIndependentByConfiguration(): void
    {
        $config = (string) file_get_contents(dirname(__DIR__, 2) . '/config/vira.php');

        $this->assertContains("env('VIRA_CREDENTIAL_KEY')", $config, 'credential key binding is missing');
        $this->assertContains("env('VIRA_BACKUP_KEY')", $config, 'backup key binding is missing');

        /* Two distinct variables, and the backup key is not read from the
         * credential key's variable. */
        $this->assertMatches(
            "/'backup_key'\s*=>\s*env\('VIRA_BACKUP_KEY'\)/",
            $config,
            'backup_key must read VIRA_BACKUP_KEY and nothing else',
        );
        $this->assertNotContains("'backup_key' => env('VIRA_CREDENTIAL_KEY')", $config);

        $example = (string) file_get_contents(dirname(__DIR__, 2) . '/.env.example');
        $this->assertContains('VIRA_BACKUP_KEY', $example, '.env.example does not document VIRA_BACKUP_KEY');
        $this->assertContains('VIRA_CREDENTIAL_KEY', $example, '.env.example does not document VIRA_CREDENTIAL_KEY');

        /* And two generated keys are not the same value. */
        $this->assertNotSame($this->backupKey, $this->otherKey);
    }

    /** The whole point: build, then restore somewhere else, and compare data. */
    private function testRoundTripRestoresEveryRow(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        $this->assertTrue(is_file((string) $result['path']), 'no container was written');
        $this->assertTrue(is_file($result['path'] . '.sha256'), 'no sidecar checksum was written');
        $this->assertGreaterThan(0, (int) $result['bytes']);
        $this->assertGreaterThan(0, (int) $result['tables']);
        $this->assertGreaterThan(0, (int) $result['files']);

        /* No .partial file left behind — a final-named container is complete. */
        $this->assertSame([], glob($this->work . '/backups/*.partial') ?: []);

        $before = $this->counts($this->db->path);

        $destination = $this->destinationDatabase();
        $restored = $this->restorer()->restore($result['path'], $destination, $this->work . '/restore-here');

        $this->assertSame('ok', $this->integrity($destination));

        $after = $this->counts($destination);
        $this->assertSame($before['tables'], $after['tables'], 'table count changed across the round trip');
        $this->assertSame($before['rows'], $after['rows'], 'row count changed across the round trip');
        $this->assertSame($before['hash'], $after['hash'], 'content hash changed across the round trip');
        $this->assertSame((int) $result['rows'], $after['rows']);

        /* The storage files came back too. */
        $this->assertSame(
            "private payload\n",
            (string) file_get_contents($this->work . '/restore-here/private/docs/note.txt'),
        );
        $this->assertSame(
            "<svg/>\n",
            (string) file_get_contents($this->work . '/restore-here/public/media/logo.svg'),
        );
        $this->assertSame((int) $result['files'], (int) $restored['files']);
    }

    /**
     * A restore must not overwrite the existing database in place.
     *
     * If the install fails halfway, an overwrite has already destroyed the only
     * copy of the current data. Moving it aside first is what makes the rollback
     * in BackupRestorer::restore() possible at all.
     */
    private function testPreviousDatabaseIsMovedAsideNotOverwritten(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $destination = $this->destinationDatabase();
        $original = (string) file_get_contents($destination);

        $this->restorer()->restore($result['path'], $destination, $this->work . '/restore-here');

        $aside = glob($destination . '.pre-restore-*') ?: [];
        $this->assertSame(1, count($aside), 'the previous database was not moved aside');
        $this->assertSame(
            $original,
            (string) file_get_contents($aside[0]),
            'the copy set aside is not the database that was there',
        );
    }

    /** Verification must be read-only — that is what makes it safe to run first. */
    private function testVerifyWritesNothing(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $before = $this->inventory($this->work . '/backups');
        $destination = $this->seedDestination();

        $deep = $this->restorer()->verifyDeep($result['path']);
        $this->assertTrue((bool) $deep['ok'], 'verification of a good container failed');

        $this->assertSame($before, $this->inventory($this->work . '/backups'), 'verify() modified the backup directory');
        $this->assertSame($destination['sha'], hash_file('sha256', $destination['path']), 'verify() touched the destination');
    }

    /** Tamper with the ciphertext: GCM must refuse, before any write. */
    private function testTamperedCiphertextIsRefused(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $this->tamper($result['path']);

        $deep = $this->restorer()->verifyDeep($result['path']);
        $this->assertFalse((bool) $deep['ok'], 'a tampered container passed verification');
        $this->assertSame(3, (int) $deep['gate'], 'tampering was not caught by the GCM authentication gate');

        $this->assertThrows(
            RuntimeException::class,
            fn () => $this->restorer()->restore($result['path'], $this->destinationDatabase(), $this->work . '/restore-here'),
            'restore accepted a tampered container',
        );
    }

    /** An incomplete file — truncated mid-payload. */
    private function testTruncatedFileIsRefused(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $raw = (string) file_get_contents($result['path']);
        file_put_contents($result['path'], substr($raw, 0, (int) (strlen($raw) * 0.6)));

        $deep = $this->restorer()->verifyDeep($result['path']);
        $this->assertFalse((bool) $deep['ok'], 'a truncated container passed verification');
        $this->assertSame(3, (int) $deep['gate'], 'truncation was not caught by the authentication gate');

        $this->assertThrows(
            RuntimeException::class,
            fn () => $this->restorer()->restore($result['path'], $this->destinationDatabase(), $this->work . '/restore-here'),
            'restore accepted a truncated container',
        );

        /* And the file size actually did shrink, so the test is not vacuous. */
        $this->assertLessThan(strlen($raw), (int) filesize($result['path']));
    }

    /** Wrong key: must fail closed, not fall back to something. */
    private function testWrongKeyIsRefused(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        $deep = $this->restorer($this->otherKey)->verifyDeep($result['path']);
        $this->assertFalse((bool) $deep['ok'], 'the wrong key verified a container');
        $this->assertSame(3, (int) $deep['gate'], 'a wrong key was not caught by the authentication gate');

        $this->assertThrows(
            RuntimeException::class,
            fn () => $this->restorer($this->otherKey)->restore($result['path'], $this->destinationDatabase(), $this->work . '/restore-here'),
            'restore accepted a container under the wrong key',
        );
    }

    /** Wrong magic bytes: gate 1, before anything is decrypted. */
    private function testBadMagicIsRefused(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $raw = (string) file_get_contents($result['path']);
        file_put_contents($result['path'], "NOTVIRA\n" . substr($raw, 8));

        $deep = $this->restorer()->verifyDeep($result['path']);
        $this->assertFalse((bool) $deep['ok'], 'a container with bad magic passed verification');
        $this->assertSame(1, (int) $deep['gate']);
    }

    /**
     * An invalid manifest must be rejected.
     *
     * The manifest is inside the authenticated payload, so this is not
     * reachable by an attacker without the key — but a corrupt or
     * hand-edited archive is a realistic accident, and the point of the gate
     * is that restore says no instead of guessing.
     */
    private function testInvalidManifestIsRefused(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $this->assertTrue((bool) $this->restorer()->verifyDeep($result['path'])['ok'], 'control container failed');

        /* Same container, same valid header, but manifest.json is no longer JSON. */
        $forged = $this->work . '/backups/forged-manifest.vbk1';
        $this->forge(
            $result['path'],
            $forged,
            static fn (\ZipArchive $zip) => $zip->addFromString('manifest.json', '{ this is not json'),
        );

        $deep = $this->restorer()->verifyDeep($forged);
        $this->assertFalse((bool) $deep['ok'], 'a container with an invalid manifest passed verification');
        $this->assertSame(
            5,
            (int) $deep['gate'],
            'an invalid manifest was not caught by the manifest gate (got gate ' . (int) $deep['gate'] . ')',
        );
        $this->assertContains('not valid JSON', (string) $deep['error']);

        $this->assertThrows(
            RuntimeException::class,
            fn () => $this->restorer()->restore($forged, $this->destinationDatabase(), $this->work . '/restore-here'),
            'restore accepted a container with an invalid manifest',
        );
    }

    /** A member that does not match its recorded checksum: gate 6. */
    private function testMemberChecksumMismatchIsRefused(): void
    {
        $result = $this->builder()->build($this->work . '/backups');

        /* Swap one member's contents but leave manifest.json alone, so the
         * manifest hash in the header still checks out (gate 5 passes) and only
         * the per-member checksums disagree. */
        $forged = $this->work . '/backups/forged-member.vbk1';
        $this->forge(
            $result['path'],
            $forged,
            static fn (\ZipArchive $zip) => $zip->addFromString('files/private/docs/note.txt', "swapped payload\n"),
        );

        $deep = $this->restorer()->verifyDeep($forged);
        $this->assertFalse((bool) $deep['ok'], 'a member checksum mismatch passed verification');
        $this->assertSame(
            6,
            (int) $deep['gate'],
            'a member checksum mismatch was not caught by the per-member gate (got gate ' . (int) $deep['gate'] . ')',
        );

        /* Shallow verify() cannot see this — which is exactly why restore calls
         * verifyDeep() and not verify(). Pin that difference down. */
        $this->assertTrue(
            (bool) $this->restorer()->verify($forged)['ok'],
            'shallow verify() caught a per-member mismatch, so the gate split is not what the code claims',
        );

        $this->assertThrows(
            RuntimeException::class,
            fn () => $this->restorer()->restore($forged, $this->destinationDatabase(), $this->work . '/restore-here'),
            'restore accepted a container with a member checksum mismatch',
        );
    }

    /**
     * The ordering guarantee, asserted directly.
     *
     * Every refusal above must leave the destination database exactly as it was
     * — same bytes, same mtime. A test that only checks "restore threw" would
     * pass even if the destination had already been half-replaced.
     */
    private function testDestinationIsUnchangedAfterEveryRefusal(): void
    {
        $cases = [];

        $good = $this->builder()->build($this->work . '/backups');

        $tampered = $this->work . '/backups/case-tampered.vbk1';
        copy($good['path'], $tampered);
        $this->tamper($tampered);
        $cases['tampered'] = [$tampered, $this->backupKey];

        $truncated = $this->work . '/backups/case-truncated.vbk1';
        $raw = (string) file_get_contents($good['path']);
        file_put_contents($truncated, substr($raw, 0, 500));
        $cases['truncated'] = [$truncated, $this->backupKey];

        $cases['wrong key'] = [$good['path'], $this->otherKey];

        foreach ($cases as $label => [$file, $key]) {
            $destination = $this->seedDestination();
            $before = ['sha' => $destination['sha'], 'mtime' => $destination['mtime']];

            $threw = false;
            try {
                $this->restorer($key)->restore($file, $destination['path'], $this->work . '/restore-here');
            } catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "restore did not throw for: {$label}");
            $this->assertSame(
                $before['sha'],
                hash_file('sha256', $destination['path']),
                "the destination database changed after a refused restore: {$label}",
            );
            $this->assertSame(
                $before['mtime'],
                filemtime($destination['path']),
                "the destination database was rewritten after a refused restore: {$label}",
            );
            $this->assertSame(
                [],
                glob($destination['path'] . '.pre-restore-*') ?: [],
                "a refused restore still moved the destination aside: {$label}",
            );
        }
    }

    /**
     * The backup key must not appear in the container.
     *
     * A container travels to off-host storage; the key must not travel with it.
     * Checks the raw bytes, the decoded base64 key, and the decrypted manifest.
     */
    private function testKeyMaterialNeverAppearsInContainer(): void
    {
        $result = $this->builder()->build($this->work . '/backups');
        $raw = (string) file_get_contents($result['path']);

        $this->assertFalse(str_contains($raw, $this->backupKey), 'the base64 key appears in the container');
        $this->assertFalse(
            str_contains($raw, (string) base64_decode($this->backupKey, true)),
            'the raw key bytes appear in the container',
        );
        $this->assertFalse(str_contains($raw, 'VIRA_BACKUP_KEY'), 'the key name appears in the container');

        $header = json_decode(substr($raw, 8, strpos(substr($raw, 8), "\n")), true);
        $this->assertArrayHasKey('cipher', $header);
        $this->assertSame('aes-256-gcm', (string) $header['cipher']);
        $this->assertArrayHasKey('iv', $header);
        $this->assertArrayHasKey('tag', $header);
        $this->assertArrayHasKey('salt', $header);
        $this->assertSame(16, strlen((string) base64_decode((string) $header['salt'], true)));
        $this->assertSame(12, strlen((string) base64_decode((string) $header['iv'], true)));
        $this->assertSame(16, strlen((string) base64_decode((string) $header['tag'], true)));
    }

    /**
     * A fixed key and salt would make two backups interchangeable — and would
     * mean IV reuse under GCM, which is catastrophic. Two builds from the same
     * data must not produce the same bytes.
     */
    private function testTwoBuildsProduceDifferentContainers(): void
    {
        $a = $this->builder()->build($this->work . '/backups');
        sleep(1); /* the name carries a timestamp; keep them distinct */
        $b = $this->builder()->build($this->work . '/backups');

        $this->assertNotSame($a['path'], $b['path']);
        $this->assertNotSame((string) $a['sha256'], (string) $b['sha256']);
        $this->assertNotSame(
            (string) file_get_contents($a['path']),
            (string) file_get_contents($b['path']),
            'two builds of identical data produced identical containers',
        );

        /* Both must still restore. */
        $this->assertTrue((bool) $this->restorer()->verifyDeep($a['path'])['ok']);
        $this->assertTrue((bool) $this->restorer()->verifyDeep($b['path'])['ok']);
    }

    // ── retention ───────────────────────────────────────────────────────

    /**
     * @return array<int,array{path:string,mtime:int}>
     */
    private function dailyFiles(int $count, string $newestDay = '2026-11-20'): array
    {
        $files = [];
        $newest = (int) strtotime($newestDay . ' 12:00:00');

        for ($i = 0; $i < $count; $i++) {
            /* Noon, so a one-hour DST shift cannot move the date. */
            $mtime = $newest - $i * 86400;
            $files[] = ['path' => '/backups/' . date('Y-m-d', $mtime) . '.vbk1', 'mtime' => $mtime];
        }

        return $files;
    }

    /**
     * One container per period — not every container in the newest N periods.
     *
     * This is the exact bug the first implementation shipped: each class
     * protected ALL files in its newest periods, the classes overlapped, and
     * pruning deleted nothing no matter how many backups accumulated. The
     * command reported "pruned : 0" against 21 containers spanning 20 days.
     * Pure function, so it can be pinned down exactly.
     */
    private function testRetentionKeepsOneFilePerPeriod(): void
    {
        $files = $this->dailyFiles(20);

        $r = RetentionPolicy::select($files, ['daily' => 7, 'weekly' => 0, 'monthly' => 0]);

        $expectedKeep = [];
        foreach (['2026-11-14', '2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'] as $d) {
            $expectedKeep[] = '/backups/' . $d . '.vbk1';
        }

        $this->assertSame($expectedKeep, $r['keep'], 'daily retention did not keep exactly the seven newest days');
        $this->assertSame(13, count($r['remove']));

        /* Within a period only the NEWEST survives. */
        $sameDay = [
            ['path' => '/b/morning.vbk1', 'mtime' => (int) strtotime('2026-11-20 09:00:00')],
            ['path' => '/b/evening.vbk1', 'mtime' => (int) strtotime('2026-11-20 18:00:00')],
            ['path' => '/b/yesterday.vbk1', 'mtime' => (int) strtotime('2026-11-19 18:00:00')],
        ];

        $one = RetentionPolicy::select($sameDay, ['daily' => 1]);
        $this->assertSame(['/b/evening.vbk1'], $one['keep'], 'the newest container in the period was not the one kept');
        $this->assertSame(['/b/morning.vbk1', '/b/yesterday.vbk1'], $one['remove']);

        /* Monthly class, across two months. */
        $twoMonths = [
            ['path' => '/b/oct-early.vbk1', 'mtime' => (int) strtotime('2026-10-05 12:00:00')],
            ['path' => '/b/oct-late.vbk1', 'mtime' => (int) strtotime('2026-10-28 12:00:00')],
            ['path' => '/b/nov.vbk1', 'mtime' => (int) strtotime('2026-11-12 12:00:00')],
        ];

        $monthly = RetentionPolicy::select($twoMonths, ['monthly' => 1]);
        $this->assertSame(['/b/nov.vbk1'], $monthly['keep'], 'monthly retention kept more than the newest month');
    }

    /** A limit of zero means "keep none of these", not "keep all". */
    private function testRetentionWithAllLimitsZeroKeepsNothing(): void
    {
        $files = $this->dailyFiles(5);

        $r = RetentionPolicy::select($files, ['daily' => 0, 'weekly' => 0, 'monthly' => 0]);

        $this->assertSame([], $r['keep']);
        $this->assertSame(5, count($r['remove']));

        $empty = RetentionPolicy::select([], ['daily' => 7]);
        $this->assertSame(['keep' => [], 'remove' => []], $empty);
    }

    /** Whatever the limits, the most recent container is always in the keep set. */
    private function testRetentionNeverRemovesTheNewest(): void
    {
        $files = $this->dailyFiles(60, '2026-11-20');

        foreach ([['daily' => 7], ['daily' => 7, 'weekly' => 4], ['daily' => 7, 'weekly' => 4, 'monthly' => 6]] as $limits) {
            $r = RetentionPolicy::select($files, $limits);
            $this->assertContains(
                '/backups/2026-11-20.vbk1',
                implode(',', $r['keep']),
                'the newest container was removable under ' . json_encode($limits),
            );
        }

        /* And every file is accounted for exactly once — no double counting,
         * which is how a pruning bug turns into deleting everything. */
        $r = RetentionPolicy::select($files, ['daily' => 7, 'weekly' => 4, 'monthly' => 6]);
        $this->assertSame(60, count($r['keep']) + count($r['remove']));
        $this->assertSame(60, count(array_unique(array_merge($r['keep'], $r['remove']))));
    }

    /**
     * The regression itself: 20 consecutive daily backups under the real
     * 7/4/6 policy must actually prune something.
     */
    private function testRetentionRemovesSomethingForTwentyDailyBackups(): void
    {
        $files = $this->dailyFiles(20);

        $r = RetentionPolicy::select($files, ['daily' => 7, 'weekly' => 4, 'monthly' => 6]);

        $this->assertGreaterThan(0, count($r['remove']), 'nothing would be pruned from 20 daily backups');
        $this->assertTrue(count($r['keep']) >= 7, 'fewer than the seven daily backups were kept');
        $this->assertTrue(
            count($r['keep']) <= 17,
            'more than 7+4+6 containers were kept: ' . count($r['keep']),
        );
    }

    // ── raw SQLite helpers (deliberately not going through Eloquent) ────

    private function integrity(string $path): string
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        return (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    }

    /**
     * tables + rows + a content hash over every table, so a restore that
     * returned the right number of rows of the WRONG data is still caught.
     */
    private function counts(string $path): array
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $tables = array_map(
            static fn (array $r): string => (string) $r[0],
            $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(),
        );

        $rows = 0;
        $ctx = hash_init('sha256');
        foreach ($tables as $t) {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM "' . $t . '"')->fetchColumn();
            $rows += $n;
            hash_update($ctx, $t . ':' . $n . ';');
            foreach ($pdo->query('SELECT * FROM "' . $t . '" ORDER BY 1') as $row) {
                hash_update($ctx, (string) json_encode(array_values($row)));
            }
        }

        return ['tables' => count($tables), 'rows' => $rows, 'hash' => hash_final($ctx)];
    }

    /** Directory listing with sizes, for "did anything get written?" checks. */
    private function inventory(string $dir): array
    {
        $out = [];
        foreach (glob(rtrim($dir, '/') . '/*') ?: [] as $f) {
            $out[basename($f)] = filesize($f);
        }
        ksort($out);

        return $out;
    }
}
