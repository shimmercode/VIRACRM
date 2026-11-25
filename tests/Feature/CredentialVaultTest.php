<?php

namespace Tests\Feature;

use App\Models\CredentialType;
use App\Models\ProjectCredential;
use App\Services\Security\CredentialVault;
use App\Services\Security\EnvelopeEncrypter;
use RuntimeException;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * Proves credentials are encrypted in the REAL write path, not merely that a
 * cipher class exists.
 *
 * This distinction is the whole point. EnvelopeEncrypter has been unit-tested
 * for a while; until this suite existed, nothing in the application ever called
 * it, so "credentials are encrypted at rest" was an untested claim about a
 * capability that did not exist.
 */
final class CredentialVaultTest extends ViraTestCase
{
    private DatabaseHarness $db;
    private CredentialVault $vault;

    protected function setUp(): void
    {
        $this->db = DatabaseHarness::boot('credential-vault');
        $this->db->migrate();

        $this->vault = new CredentialVault(
            new EnvelopeEncrypter(base64_encode(str_repeat("\x5a", 32)))
        );
    }

    public function run(): void
    {
        $this->testPlaintextNeverReachesTheColumn();
        $this->testRoundTripRecoversTheSecret();
        $this->testWrongKeyIsRefused();
        $this->testTamperedCiphertextIsRefused();
        $this->testRotationReplacesRatherThanAccumulates();
        $this->testFingerprintMatchesWithoutDecrypting();
        $this->testMissingKeyFailsClosed();
        $this->testMaskedOutputHidesLength();

        $this->db->destroy();
    }

    /** The stored bytes must not contain the secret. */
    private function testPlaintextNeverReachesTheColumn(): void
    {
        $secret = 'S3cret-WP-Pass!';

        $credential = $this->makeCredential();
        $this->vault->store($credential, 'password', $secret);

        $row = $credential->values()->where('field', 'password')->first();

        $this->assertNotNull($row, 'a credential_values row should exist');
        $this->assertNotContains($secret, $row->ciphertext, 'ciphertext must not contain the plaintext');
        $this->assertTrue(
            str_starts_with($row->ciphertext, 'v1:'),
            'ciphertext carries a version prefix so a future algorithm can migrate'
        );
    }

    private function testRoundTripRecoversTheSecret(): void
    {
        $secret = 'p@ssw0rd-با-حروف-فارسی';

        $credential = $this->makeCredential();
        $this->vault->store($credential, 'password', $secret);

        $this->assertSame($secret, $this->vault->reveal($credential, 'password'),
            'decrypting must return exactly what was stored, including non-ASCII');
    }

    private function testWrongKeyIsRefused(): void
    {
        $credential = $this->makeCredential();
        $this->vault->store($credential, 'password', 'correct-horse');

        $wrong = new CredentialVault(
            new EnvelopeEncrypter(base64_encode(str_repeat("\x11", 32)))
        );

        $this->assertThrows(RuntimeException::class,
            fn () => $wrong->reveal($credential, 'password'),
            'a different key must fail loudly rather than return garbage');
    }

    private function testTamperedCiphertextIsRefused(): void
    {
        $credential = $this->makeCredential();
        $this->vault->store($credential, 'password', 'original');

        $row = $credential->values()->where('field', 'password')->first();

        // Flip one byte of the payload. GCM authenticates, so this must throw.
        $payload = substr($row->ciphertext, 3);
        $raw = base64_decode($payload, true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0xFF);

        $row->ciphertext = 'v1:' . base64_encode($raw);
        $row->save();

        $this->assertThrows(RuntimeException::class,
            fn () => $this->vault->reveal($credential, 'password'),
            'a modified row must fail authentication');
    }

    private function testRotationReplacesRatherThanAccumulates(): void
    {
        $credential = $this->makeCredential();

        $this->vault->store($credential, 'password', 'first-value');
        $this->vault->store($credential, 'password', 'second-value');

        $count = $credential->values()->where('field', 'password')->count();

        $this->assertSame(1, $count, 'rotation updates the field instead of keeping stale ciphertext');
        $this->assertSame('second-value', $this->vault->reveal($credential, 'password'));
    }

    private function testFingerprintMatchesWithoutDecrypting(): void
    {
        $credential = $this->makeCredential();
        $this->vault->store($credential, 'password', 'unchanged-pass');

        $this->assertTrue($this->vault->sameAsPrevious($credential, 'password', 'unchanged-pass'));
        $this->assertFalse($this->vault->sameAsPrevious($credential, 'password', 'different-pass'));
    }

    private function testMissingKeyFailsClosed(): void
    {
        /*
         * A blank key must disable storage, not fall back to plaintext. The
         * previous version of this system could have written readable secrets
         * if a misconfigured host omitted the variable; this asserts it cannot.
         */
        $this->assertThrows(RuntimeException::class,
            fn () => new EnvelopeEncrypter(''),
            'an empty key is rejected at construction');
    }

    private function testMaskedOutputHidesLength(): void
    {
        /*
         * Masking is bucketed: exact password length is a small hint, and the
         * UI has no reason to expose it.
         */
        $this->assertSame('••••••••', EnvelopeEncrypter::mask('short'));
        $this->assertSame('••••••••••••', EnvelopeEncrypter::mask('a-much-longer-password'));
        $this->assertSame('', EnvelopeEncrypter::mask(''));
    }

    /**
     * A minimal real project + credential type + credential record.
     *
     * The project is created through the real tables rather than with a
     * hard-coded id, so the foreign key proves the credential belongs to
     * something that exists.
     */
    private function makeCredential(): ProjectCredential
    {
        $customerId = $this->db->connection->table('customers')->insertGetId([
            'code' => 'CUST-' . uniqid(),
            'type' => 'company',
            'company_name' => 'شرکت آزمایشی',
            'status' => 'active',
        ]);

        $projectId = $this->db->connection->table('projects')->insertGetId([
            'code' => 'PRJ-' . uniqid(),
            'customer_id' => $customerId,
            'type' => 'seo',
            'title' => 'پروژه آزمایشی',
            'status' => 'active',
            'amount' => 0,
        ]);

        $type = CredentialType::firstOrCreate(
            ['code' => 'wordpress'],
            [
                'title' => 'وردپرس',
                'fields_schema' => [
                    ['key' => 'password', 'label' => 'رمز عبور', 'required' => true],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        return ProjectCredential::create([
            'project_id' => $projectId,
            'credential_type_id' => $type->getKey(),
            'label' => 'ورود مدیر',
            'public_url' => 'https://example.invalid/wp-admin',
            'username' => 'admin',
            'is_complete' => false,
        ]);
    }
}
