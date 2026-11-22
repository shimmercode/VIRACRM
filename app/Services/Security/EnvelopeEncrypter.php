<?php

namespace App\Services\Security;

use RuntimeException;

/**
 * REQ-06.03 — credentials (WordPress, host, domain, GSC/GA) encrypted at rest.
 *
 * ALGORITHM: AES-256-GCM (authenticated encryption), random 96-bit nonce per
 * record, key derived from a 32-byte master key held outside the database.
 *
 * WHY OpenSSL AND NOT libsodium: libsodium is the nicer API but it is an
 * *optional* extension. Client shared hosting is the constraint here, and
 * `openssl` is compiled into essentially every PHP 7.4+ build while `sodium`
 * often is not. Choosing the extension that is actually present on the target
 * host beats choosing the more elegant one that fails at runtime. This was
 * verified: the development PHP build itself has openssl and no sodium.
 *
 * GCM rather than CBC because GCM authenticates: a tampered row fails loudly
 * instead of decrypting into garbage that then gets displayed to a user.
 *
 * KEY MANAGEMENT: the key lives outside the document root, never in the
 * database and never in Git (docs/phase-1/17-key-management.md). A missing or
 * malformed key is a hard failure — the CRM refuses to operate rather than
 * quietly storing plaintext.
 */
final class EnvelopeEncrypter
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_BYTES = 32;
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;
    private const PREFIX = 'v1';

    private string $key;

    public function __construct(string $base64Key)
    {
        $decoded = base64_decode($base64Key, true);

        if ($decoded === false || strlen($decoded) !== self::KEY_BYTES) {
            throw new RuntimeException(
                'CREDENTIAL_ENCRYPTION_KEY must be a base64-encoded 32-byte key. '
                . 'Generate one with: php artisan vira:key'
            );
        }

        $this->key = $decoded;
    }

    /** Generate a fresh key, base64-encoded, for .env / env.php. */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    /**
     * Encrypt a plaintext secret.
     *
     * Output format: "v1:" . base64(nonce || tag || ciphertext)
     * Self-describing so a future algorithm change can migrate old rows.
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $cipher = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::PREFIX,
            self::TAG_BYTES
        );

        if ($cipher === false) {
            throw new RuntimeException('Encryption failed. Check that the openssl extension is available.');
        }

        return self::PREFIX . ':' . base64_encode($nonce . $tag . $cipher);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * Throws rather than returning partial data: a failed authentication tag
     * means the row was tampered with or the key is wrong, and either way the
     * safe outcome is to refuse.
     */
    public function decrypt(string $payload): string
    {
        $parts = explode(':', $payload, 2);

        if (count($parts) !== 2 || $parts[0] !== self::PREFIX) {
            throw new RuntimeException('Unrecognised ciphertext format (expected v1: prefix).');
        }

        $raw = base64_decode($parts[1], true);

        // NOTE: '<' not '<=' — encrypting the empty string yields exactly
        // nonce+tag bytes with an empty ciphertext body, which is valid.
        if ($raw === false || strlen($raw) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Ciphertext is malformed or truncated.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $tag = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $cipher = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $cipher,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::PREFIX
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Credential decryption failed: authentication tag mismatch. '
                . 'The key is wrong or the stored value was modified.'
            );
        }

        return $plaintext;
    }

    /**
     * A stable, non-reversible fingerprint used for duplicate detection and
     * audit logs, so support staff can say "this is the same password as
     * before" without ever seeing it.
     */
    public function fingerprint(string $plaintext): string
    {
        return substr(hash_hmac('sha256', $plaintext, $this->key), 0, 16);
    }

    /**
     * Masked form for the UI. Length is bucketed on purpose: exact password
     * length is itself a small hint an attacker can use.
     */
    public static function mask(string $plaintext): string
    {
        $length = mb_strlen($plaintext);

        return match (true) {
            $length === 0 => '',
            $length <= 8 => '••••••••',
            default => '••••••••••••',
        };
    }

    /**
     * Confirm the running PHP can actually do this.
     * Called during installation so a host deficiency surfaces before any
     * credential is stored, not on the first attempt to read one back.
     *
     * @return list<string> problems; empty when healthy
     */
    public static function environmentProblems(): array
    {
        $problems = [];

        if (! extension_loaded('openssl')) {
            return ['The openssl extension is not loaded. Credentials cannot be encrypted.'];
        }

        if (! in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            $problems[] = 'The ' . self::CIPHER . ' cipher is unavailable in this OpenSSL build.';
        }

        // Prove the full round trip once, rather than trusting the cipher list.
        try {
            $probe = new self(base64_encode(str_repeat("\x2a", self::KEY_BYTES)));
            $sample = 'probe-' . bin2hex(random_bytes(8));
            if ($probe->decrypt($probe->encrypt($sample)) !== $sample) {
                $problems[] = 'AES-256-GCM self-test did not round-trip correctly.';
            }
        } catch (\Throwable $e) {
            $problems[] = 'Encryption self-test failed: ' . $e->getMessage();
        }

        return $problems;
    }
}
