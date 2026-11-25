<?php

namespace App\Services\Security;

use App\Models\CredentialValue;
use App\Models\ProjectCredential;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only path through which a credential secret is written or read.
 *
 * WHY THIS EXISTS
 * ---------------
 * EnvelopeEncrypter has been correct and tested since F-phase M4, but nothing
 * called it. The tables were created, the cipher was proven, and no row ever
 * passed through the two — so "credential encryption" was a component, not a
 * capability. The directive makes exactly this distinction: the existence of
 * EnvelopeEncrypter is not proof that credentials are encrypted in the real
 * flow.
 *
 * This class closes that gap. Every write goes `plaintext -> encrypt() ->
 * credential_values.ciphertext`; every read goes the other way and is refused
 * if the tag fails.
 *
 * FAIL-CLOSED ON A MISSING KEY
 * ----------------------------
 * If VIRA_CREDENTIAL_KEY is absent, `store()` throws before writing anything.
 * The alternative — silently storing plaintext — would be far worse than a
 * refused request, because nobody would notice it until the backup surfaced.
 *
 * The accepted risk (OD-29) is that losing the key loses the credentials
 * permanently. That risk is only acceptable if a *missing* key is loud.
 */
class CredentialVault
{
    private EnvelopeEncrypter $cipher;

    public function __construct(?EnvelopeEncrypter $cipher = null)
    {
        $this->cipher = $cipher ?? self::fromConfig();
    }

    /**
     * Build from the configured key, refusing to continue without one.
     *
     * `config('vira.credential_key')` is resolved from VIRA_CREDENTIAL_KEY.
     * A blank value is a configuration error, not a reason to fall back.
     */
    public static function fromConfig(): EnvelopeEncrypter
    {
        $key = (string) (config('vira.credential_key') ?? env('VIRA_CREDENTIAL_KEY') ?? '');

        if (trim($key) === '') {
            throw new RuntimeException(
                'VIRA_CREDENTIAL_KEY is not set. Credential storage is disabled because '
                . 'storing secrets without a key would write plaintext.'
            );
        }

        return new EnvelopeEncrypter($key);
    }

    public static function isConfigured(): bool
    {
        $key = (string) (config('vira.credential_key') ?? env('VIRA_CREDENTIAL_KEY') ?? '');

        return trim($key) !== '';
    }

    /**
     * Store or replace one secret field.
     *
     * Replacement deletes the old row first: the old ciphertext should not
     * remain in the table once the secret has rotated. Audit lives in
     * audit_logs, which records that a rotation happened without the value.
     */
    public function store(ProjectCredential $credential, string $field, string $plaintext): CredentialValue
    {
        // Encrypt BEFORE touching the database, so a crypto failure cannot
        // leave a half-written record behind.
        $ciphertext = $this->cipher->encrypt($plaintext);
        $fingerprint = $this->cipher->fingerprint($plaintext);

        return DB::transaction(function () use ($credential, $field, $ciphertext, $fingerprint) {
            CredentialValue::query()
                ->where('project_credential_id', $credential->getKey())
                ->where('field', $field)
                ->delete();

            $value = CredentialValue::create([
                'project_credential_id' => $credential->getKey(),
                'field' => $field,
                'ciphertext' => $ciphertext,
                'fingerprint' => $fingerprint,
                'created_at' => now(),
            ]);

            $this->refreshCompleteness($credential);

            return $value;
        });
    }

    /**
     * Read one secret back.
     *
     * Throws when the tag fails: a wrong key or a modified row must surface as
     * an error, never as a plausible-looking wrong password shown to a user.
     */
    public function reveal(ProjectCredential $credential, string $field): ?string
    {
        $row = CredentialValue::query()
            ->where('project_credential_id', $credential->getKey())
            ->where('field', $field)
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->cipher->decrypt($row->ciphertext);
    }

    /** Every decrypted field, for a caller that has already been authorised. */
    public function revealAll(ProjectCredential $credential): array
    {
        $out = [];

        foreach ($credential->values()->get() as $row) {
            $out[$row->field] = $this->cipher->decrypt($row->ciphertext);
        }

        return $out;
    }

    /**
     * Masked summary for list views: which fields exist, never their values.
     *
     * @return array<string,string> field => bullets
     */
    public function masked(ProjectCredential $credential): array
    {
        $out = [];

        foreach ($credential->values()->pluck('field') as $field) {
            $out[$field] = '••••••••';
        }

        return $out;
    }

    /** Whether a newly supplied secret equals the stored one, without decrypting. */
    public function sameAsPrevious(ProjectCredential $credential, string $field, string $candidate): bool
    {
        $row = CredentialValue::query()
            ->where('project_credential_id', $credential->getKey())
            ->where('field', $field)
            ->first();

        if ($row === null || $row->fingerprint === null) {
            return false;
        }

        return hash_equals($row->fingerprint, $this->cipher->fingerprint($candidate));
    }

    public function forget(ProjectCredential $credential, string $field): void
    {
        CredentialValue::query()
            ->where('project_credential_id', $credential->getKey())
            ->where('field', $field)
            ->delete();

        $this->refreshCompleteness($credential);
    }

    /** Keep the denormalised flag in step with the values that actually exist. */
    private function refreshCompleteness(ProjectCredential $credential): void
    {
        $credential->unsetRelation('values');
        $complete = $credential->isComplete();

        if ($credential->is_complete !== $complete) {
            $credential->forceFill(['is_complete' => $complete])->save();
        }
    }
}
