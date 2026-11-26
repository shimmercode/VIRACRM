<?php

namespace Tests\Unit;

use App\Http\Controllers\Audit\AuditLogController;
use App\Models\AuditLog;
use Tests\ViraTestCase;

/**
 * The redaction that stands between the audit table and a browser.
 *
 * TWO LAYERS, AND WHY THE SECOND IS NOT REDUNDANT
 * ----------------------------------------------
 * `AuditRecorder` redacts on the way in. This suite tests the pass on the way
 * out, because the first layer cannot cover a row that never went through it: a
 * seeder, a data import, a future controller that calls `AuditLog::create()`
 * directly, or a row written before the redactor existed. audit-log-page.mjs
 * proves the same property over HTTP by inserting a poisoned row with raw SQL;
 * this pins down the function's edges, which a page render cannot.
 *
 * THE FALSE POSITIVE THAT ALMOST SHIPPED
 * --------------------------------------
 * The first version masked any word following a secret-ish keyword. That turns
 * «password changed for user 4» into «password [redacted] for user 4» —
 * destroying the exact sentence an investigator needs, while making the control
 * look broader than it is. testProseIsNotRedacted exists so that mistake cannot
 * come back: an over-eager redactor is a defect, not extra safety.
 */
final class AuditRedactionTest extends ViraTestCase
{
    public function run(): void
    {
        $this->testChangeSetValuesAreReplaced();
        $this->testRedactionIsRecursive();
        $this->testRedactionIsIdempotent();
        $this->testAssignedSecretsInProseAreMasked();
        $this->testEnvelopeCiphertextIsMasked();
        $this->testProseIsNotRedacted();
        $this->testPresentRunsBothLayers();
        $this->testPresentKeepsTheEventReviewable();
    }

    /** A secret VALUE is replaced; the field name survives. */
    private function testChangeSetValuesAreReplaced(): void
    {
        $secret = 'Hunter2-Super-Secret';

        $clean = AuditLog::redact([
            'password' => $secret,
            'api_key' => $secret,
            'remember_token' => $secret,
            'company_name' => 'Nika Gostar',
        ]);

        $this->assertSame('[redacted]', (string) $clean['password']);
        $this->assertSame('[redacted]', (string) $clean['api_key']);
        $this->assertSame('[redacted]', (string) $clean['remember_token']);
        $this->assertSame('Nika Gostar', (string) $clean['company_name'], 'a harmless field was redacted');

        $this->assertFalse(
            str_contains((string) json_encode($clean), $secret),
            'the secret survived somewhere in the redacted change set',
        );
    }

    /** Nested arrays must be walked, not just the top level. */
    private function testRedactionIsRecursive(): void
    {
        $secret = 'Nested-Secret-9';

        $clean = AuditLog::redact([
            'before' => ['password' => $secret, 'title' => 'old'],
            'after' => ['password' => $secret, 'title' => 'new'],
        ]);

        $this->assertSame('[redacted]', (string) $clean['before']['password']);
        $this->assertSame('[redacted]', (string) $clean['after']['password']);
        $this->assertSame('old', (string) $clean['before']['title']);
        $this->assertFalse(str_contains((string) json_encode($clean), $secret));
    }

    /**
     * Running it twice must change nothing.
     *
     * The audit page redacts on the way out rows that were already redacted on
     * the way in. If the second pass were not idempotent it would mangle
     * legitimate text on every render.
     */
    private function testRedactionIsIdempotent(): void
    {
        $once = AuditLog::redact(['password' => 'x', 'note' => 'kept']);
        $twice = AuditLog::redact($once);

        $this->assertSame($once, $twice);
    }

    /** `password: value` and `token = value` are the shapes that leak. */
    private function testAssignedSecretsInProseAreMasked(): void
    {
        $secret = 'Pl4in-Text-Pass';

        foreach ([
            "password: {$secret}",
            "password = {$secret}",
            "token:{$secret}",
            "api_key = {$secret}",
            "the password: {$secret} was used",
        ] as $text) {
            $out = (string) AuditLog::scrub($text);
            $this->assertFalse(
                str_contains($out, $secret),
                "the secret survived scrubbing: {$text} -> {$out}",
            );
            $this->assertContains('[redacted]', $out);
        }
    }

    /** The application's own envelope prefix, with no keyword in front. */
    private function testEnvelopeCiphertextIsMasked(): void
    {
        $ciphertext = 'v1:QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVowMTIzNA==';

        $out = (string) AuditLog::scrub('stored ' . $ciphertext . ' for project 4');

        $this->assertFalse(str_contains($out, $ciphertext), 'envelope ciphertext survived');
        $this->assertContains('[redacted]', $out);
        $this->assertContains('for project 4', $out, 'the surrounding context was destroyed');
    }

    /**
     * Ordinary prose must survive intact.
     *
     * This is the assertion the first implementation failed. «password changed
     * for user 4» carries no secret, and redacting it removes the information
     * the audit page exists to provide.
     */
    private function testProseIsNotRedacted(): void
    {
        $kept = [
            'password changed for user 4',
            'the api key was rotated by an administrator',
            'credential list opened',
            'token refreshed',
            'no secret here at all',
        ];

        foreach ($kept as $text) {
            $this->assertSame(
                $text,
                (string) AuditLog::scrub($text),
                "prose was redacted when it should have been left alone: {$text}",
            );
        }

        $this->assertNull(AuditLog::scrub(null));
        $this->assertSame('', (string) AuditLog::scrub(''));
    }

    /** present() is the single path the view uses, so both layers run there. */
    private function testPresentRunsBothLayers(): void
    {
        $secret = 'Present-Layer-Secret';

        $log = new AuditLog();
        $log->event = 'updated';
        $log->description = "password: {$secret}";
        $log->changes = ['password' => $secret, 'status' => 'active'];

        $row = AuditLogController::present($log);

        $this->assertFalse(
            str_contains((string) json_encode($row), $secret),
            'present() let a secret through to the view',
        );
        $this->assertSame('active', (string) $row['changes']['status'], 'a harmless change was dropped');
    }

    /**
     * Redaction must not make the event disappear.
     *
     * The requirement is that a sensitive event stays reviewable while the
     * confidential data does not: which fields changed, and that one of them was
     * a secret. A page that shows nothing is not a safer page, it is a useless
     * one.
     */
    private function testPresentKeepsTheEventReviewable(): void
    {
        $log = new AuditLog();
        $log->event = 'revealed';
        $log->description = 'password: S3cret! shown to user 2';
        $log->changes = ['password' => ['from' => 'S3cret!', 'to' => 'S3cret!']];

        $row = AuditLogController::present($log);

        $this->assertSame('revealed', (string) $row['event'], 'the event name was redacted');
        $this->assertArrayHasKey('password', (array) $row['changes'], 'the field name was dropped');
        $this->assertContains('[redacted]', (string) json_encode($row['changes']));
        $this->assertFalse(str_contains((string) json_encode($row), 'S3cret!'));
    }
}
