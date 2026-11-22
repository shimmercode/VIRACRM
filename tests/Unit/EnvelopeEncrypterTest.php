<?php

namespace Tests\Unit;

use App\Services\Security\EnvelopeEncrypter;
use RuntimeException;
use Tests\ViraTestCase;

/**
 * REQ-06.03 — passwords encrypted at rest, view restricted, audited.
 * T-KEY-01..03.
 *
 * A CRM whose entire selling point includes "secrecy of the site passwords"
 * (client document §1) cannot afford a silent encryption regression. These
 * tests are deliberately hostile: wrong key, tampered ciphertext, truncated
 * payload, empty plaintext, Persian text, and very long secrets.
 */
final class EnvelopeEncrypterTest extends ViraTestCase
{
    private string $keyA;
    private string $keyB;

    protected function setUp(): void
    {
        // Fixed keys make failures reproducible; a real key comes from the
        // environment, never from source control.
        $this->keyA = base64_encode(str_repeat("\x01", 32));
        $this->keyB = base64_encode(str_repeat("\x02", 32));
    }

    public function run(): void
    {
        $this->roundTrip();
        $this->ciphertextIsNotPlaintext();
        $this->uniqueNonces();
        $this->wrongKeyFails();
        $this->tamperDetection();
        $this->rejectsMalformedPayloads();
        $this->fingerprint();
        $this->masking();
        $this->unicodeAndLongValues();
    }

    private function roundTrip(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);
        $secret = 'Wp-Admin!2026-strong';

        $this->assertSame($secret, $enc->decrypt($enc->encrypt($secret)));
        $this->assertSame('', $enc->decrypt($enc->encrypt('')), 'empty secret must round-trip');
        $this->assertSame('a', $enc->decrypt($enc->encrypt('a')));
    }

    private function ciphertextIsNotPlaintext(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);
        $secret = 'SuperSecret123';
        $payload = $enc->encrypt($secret);

        $this->assertNotContains($secret, $payload, 'plaintext must never appear in the stored value');
        $this->assertTrue(str_starts_with($payload, 'v1:'), 'payload is version-prefixed for future key rotation');
    }

    private function uniqueNonces(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);

        // Same plaintext twice must never produce the same ciphertext, otherwise
        // an attacker reading the database can tell two sites share a password.
        $this->assertFalse($enc->encrypt('same-value') === $enc->encrypt('same-value'));

        $seen = [];
        for ($i = 0; $i < 50; $i++) {
            $seen[$enc->encrypt('x')] = true;
        }
        $this->assertSame(50, count($seen), '50 encryptions of the same value must be 50 distinct payloads');
    }

    private function wrongKeyFails(): void
    {
        $a = new EnvelopeEncrypter($this->keyA);
        $b = new EnvelopeEncrypter($this->keyB);

        $this->assertThrows(
            RuntimeException::class,
            fn () => $b->decrypt($a->encrypt('secret')),
            'a different key must never silently return garbage'
        );
    }

    private function tamperDetection(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);
        $payload = $enc->encrypt('do-not-touch');

        // Flip one byte of the encoded body.
        $prefix = 'v1:';
        $body = substr($payload, strlen($prefix));
        $raw = base64_decode($body);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0xFF);
        $tampered = $prefix . base64_encode($raw);

        $this->assertThrows(
            RuntimeException::class,
            fn () => $enc->decrypt($tampered),
            'modified ciphertext must fail authentication'
        );
    }

    private function rejectsMalformedPayloads(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);

        $this->assertThrows(RuntimeException::class, fn () => $enc->decrypt('not-base64-at-all'));
        $this->assertThrows(RuntimeException::class, fn () => $enc->decrypt('v1:%%%'));
        $this->assertThrows(RuntimeException::class, fn () => $enc->decrypt('v1:' . base64_encode('short')),
            'payload shorter than a nonce must be rejected');
        $this->assertThrows(RuntimeException::class, fn () => $enc->decrypt(''));
    }

    private function fingerprint(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);

        $this->assertSame($enc->fingerprint('abc'), $enc->fingerprint('abc'));
        $this->assertFalse($enc->fingerprint('abc') === $enc->fingerprint('abd'));
        $this->assertSame(16, strlen($enc->fingerprint('abc')));
        $this->assertNotContains('abc', $enc->fingerprint('abc'));
    }

    private function masking(): void
    {
        // Compare by character count, not byte count: '•' is multibyte in UTF-8.
        $this->assertSame('', EnvelopeEncrypter::mask(''));
        $this->assertSame(8, mb_strlen(EnvelopeEncrypter::mask('short')), 'short secret → 8 bullets');
        $this->assertSame(12, mb_strlen(EnvelopeEncrypter::mask('a-much-longer-password')), 'long secret → 12 bullets');
        $this->assertSame(8, mb_strlen(EnvelopeEncrypter::mask('12345678')));

        // Masking must not leak exact length: 9 and 12 chars look identical.
        $this->assertSame(
            EnvelopeEncrypter::mask('123456789'),
            EnvelopeEncrypter::mask('123456789012'),
            'lengths in the same bucket must be indistinguishable'
        );

        // The mask must never contain any character of the secret.
        $this->assertNotContains('s', EnvelopeEncrypter::mask('secret'));
    }

    private function unicodeAndLongValues(): void
    {
        $enc = new EnvelopeEncrypter($this->keyA);

        $persian = 'رمز عبور هاست — نمونهٔ فارسی با نیم‌فاصله';
        $this->assertSame($persian, $enc->decrypt($enc->encrypt($persian)));

        $long = str_repeat('p@ssw0rd-', 500);
        $this->assertSame($long, $enc->decrypt($enc->encrypt($long)));

        $json = json_encode(['user' => 'admin', 'pass' => 'x', 'note' => 'یادداشت'], JSON_UNESCAPED_UNICODE);
        $this->assertSame($json, $enc->decrypt($enc->encrypt($json)));
    }
}
