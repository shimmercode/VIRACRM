<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * AES-256-GCM envelope for backup containers.
 *
 * WHY GCM AND NOT CBC
 * -------------------
 * The first backup design (docs/BACKUP_RESTORE_GUIDE.md) used AES-256-CBC. The
 * security audit disproved it directly: a byte flipped inside the ciphertext
 * decrypted with exit status 0. CBC provides confidentiality only — it has no
 * notion of "this file was altered" — so a tampered or truncated backup would
 * have been handed to a restore as though it were fine.
 *
 * GCM is authenticated. Verified in this runtime rather than assumed
 * (docs/BACKUP_ARCHITECTURE.md §1): decrypting with a tampered ciphertext, a
 * tampered tag, or the wrong key each return false. That single property is what
 * makes the restore gates in BackupRestorer trustworthy.
 *
 * IF A HOST LACKS GCM
 * -------------------
 * GCM needs OpenSSL >= 1.0.1 and PHP >= 7.1. `supports()` reports the truth at
 * runtime; a caller must check it before encrypting rather than discovering it
 * from a false return. The documented fallback is Encrypt-then-MAC over
 * `header || ciphertext` with hash_hmac + hash_equals — the same container, only
 * the `cipher` field differs. It is not implemented here because implementing an
 * unused second crypto path means maintaining two ways to be wrong.
 */
final class BackupCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_BYTES = 32;
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;
    private const SALT_BYTES = 16;
    private const HKDF_INFO = 'vira-backup-v1';

    /** @param string $key raw 32-byte key */
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== self::KEY_BYTES) {
            throw new RuntimeException(
                'VIRA_BACKUP_KEY must decode to exactly 32 bytes (got ' . strlen($key) . ').'
            );
        }
    }

    public static function supports(): bool
    {
        return extension_loaded('openssl')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    /**
     * Generate a key the way EnvelopeEncrypter does for credentials, so both
     * secrets are produced and stored in the same shape.
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    /**
     * @return array{cipher:string,kdf:string,salt:string,iv:string,tag:string,ciphertext:string}
     */
    public function encrypt(string $plaintext): array
    {
        if (! self::supports()) {
            throw new RuntimeException(
                'aes-256-gcm is unavailable in this PHP/OpenSSL build; refusing to '
                . 'write an unauthenticated backup. See docs/BACKUP_ARCHITECTURE.md §1.'
            );
        }

        /* A fresh salt AND a fresh IV per backup. The IV alone would be enough
         * for confidentiality, but deriving a per-file key means two backups made
         * with the same passphrase never share a keystream even if an IV were
         * somehow repeated. */
        $salt = random_bytes(self::SALT_BYTES);
        $iv = random_bytes(self::IV_BYTES);
        $derived = $this->deriveKey($salt);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $derived,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('encryption failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        return [
            'cipher' => self::CIPHER,
            'kdf' => 'hkdf-sha256',
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => $ciphertext,
        ];
    }

    /**
     * @param  array{cipher:string,kdf:string,salt:string,iv:string,tag:string}  $header
     * @return string|false  false when authentication fails — never a partial plaintext
     */
    public function decrypt(array $header, string $ciphertext): string|false
    {
        if (($header['cipher'] ?? '') !== self::CIPHER) {
            return false;
        }

        $salt = base64_decode($header['salt'] ?? '', true);
        $iv = base64_decode($header['iv'] ?? '', true);
        $tag = base64_decode($header['tag'] ?? '', true);

        if ($salt === false || $iv === false || $tag === false) {
            return false;
        }
        if (strlen($iv) !== self::IV_BYTES || strlen($tag) !== self::TAG_BYTES) {
            return false;
        }

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->deriveKey($salt),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        return $plain;
    }

    private function deriveKey(string $salt): string
    {
        return hash_hkdf('sha256', $this->key, self::KEY_BYTES, self::HKDF_INFO, $salt);
    }
}
