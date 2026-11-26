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
     * Scrub a free-text field for anything that looks like a secret value.
     *
     * `redact()` works on structured change sets, where the KEY names the field
     * and the value can simply be replaced. A description is one flat string —
     * «password changed for user 4» and «password is Hunter2» look the same to
     * the database, and only the second is a leak. So this masks the token that
     * follows a secret-ish keyword rather than dropping the whole message: the
     * event still has to be reviewable, which is the point of the audit page.
     *
     * This is the last line, not the first. Callers must not put secrets in a
     * description; nothing here can reliably tell a secret from a sentence.
     */
    public static function scrub(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $keywords = 'password|passwd|secret|token|api[_-]?key|apikey|private[_-]?key|credential';

        /*
         * Only the `key: value` / `key = value` forms are masked.
         *
         * The first draft also masked any word after the keyword, which turned
         * «password changed for user 4» into «password [redacted] for user 4» —
         * destroying the very information the audit page exists to show, and
         * making the control look stronger than it was. A secret assigned with
         * `:` or `=` is the realistic shape; a bare word after the keyword is
         * far more likely to be prose.
         */
        $assigned = '/\b(' . $keywords . ')\s*([:=])\s*(\S+)/iu';
        $text = (string) preg_replace($assigned, '$1$2 [redacted]', $text);

        /*
         * The application's own envelope prefix. EnvelopeEncrypter writes `v1:`
         * followed by base64, so if a ciphertext ever reached a description this
         * catches it even with no keyword in front of it. 16 characters is long
         * enough that ordinary text is never mistaken for one.
         */
        $envelope = '/\bv1:[A-Za-z0-9+\/=]{16,}/';

        return (string) preg_replace($envelope, '[redacted]', $text);
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
