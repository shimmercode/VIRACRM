<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-10.04 — the audit trail.
 *
 * §10 requires recording create/edit/delete, viewing a password, and exporting
 * confidential data. The hard rule is that the log itself must never become the
 * leak: no passwords, no encryption keys, no session tokens, no full credential
 * ciphertext.
 *
 * REDACTION IS DEFENCE IN DEPTH, NOT THE PRIMARY CONTROL: callers must not pass
 * secrets in the first place. The redactor exists because one careless
 * `->toArray()` in a future controller should not put a WordPress admin
 * password into a table that the client's support staff can read.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'event', 'subject_type', 'subject_id',
        'description', 'changes', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Field names that must never be written to the log, anywhere.
     * Matched case-insensitively as a substring so `password_confirmation`,
     * `new_password` and `api_key` are all covered.
     */
    private const FORBIDDEN_FRAGMENTS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'ciphertext', 'private_key', 'encryption_key', 'remember_token',
        'credential', 'authorization', 'cookie',
    ];

    /**
     * Strip anything that could leak a credential from a change set.
     *
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    public static function redact(?array $changes): ?array
    {
        if ($changes === null) {
            return null;
        }

        $clean = [];

        foreach ($changes as $key => $value) {
            $lower = mb_strtolower((string) $key);

            $forbidden = false;
            foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
                if (str_contains($lower, $fragment)) {
                    $forbidden = true;
                    break;
                }
            }

            if ($forbidden) {
                $clean[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::redact($value);

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Compare two versions of a model and return what actually changed.
     * Only fields present in both arrays are compared, so a partial update
     * does not produce phantom "removed" entries.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from:mixed, to:mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue != $newValue) {
                $changes[$key] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        return self::redact($changes) ?? [];
    }
}
