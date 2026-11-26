<?php

namespace Tests\Feature;

use App\Models\CredentialRequirement;
use App\Models\CredentialType;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\Projects\FinalizationGate;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * §6 and the §14 acceptance criterion «پروژه سئو بدون تکمیل دسترسی‌های اجباری
 * قابل نهایی‌سازی نباشد».
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * The `credential_requirements` table shipped with a `blocks_finalize` column and
 * a comment naming §6 — and zero rows, zero readers. The rule existed only as a
 * schema, so nothing could ever enforce it, and the seeded data proved it: two SEO
 * projects sat at status=active with no accesses at all. A column that models a
 * rule is not the rule; these tests pin the rule itself.
 *
 * Each case here is a question the production path really asks:
 *  - can I create an SEO project already active?            → no
 *  - can I activate one with the accesses in place?         → yes
 *  - can I still save a draft / cancel a project?           → yes, always
 *  - does a type with no requirements configured get gated? → no
 */
final class FinalizationGateTest extends ViraTestCase
{
    private DatabaseHarness $db;

    /** @var array<string,int> credential code => id */
    private array $types = [];

    protected function setUp(): void
    {
        $this->db = DatabaseHarness::boot('finalization-gate');
        $this->db->migrate();

        foreach (['wordpress', 'hosting', 'domain', 'ga4'] as $i => $code) {
            $type = CredentialType::create([
                'code' => $code,
                'title' => 'نوع ' . $code,
                'fields_schema' => [['key' => 'password', 'label' => 'رمز', 'required' => true]],
                'is_active' => true,
                'sort_order' => $i,
            ]);

            $this->types[$code] = (int) $type->id;
        }

        // wordpress, hosting and domain block; ga4 is merely nice to have.
        foreach (['wordpress', 'hosting', 'domain'] as $code) {
            CredentialRequirement::create([
                'project_type' => 'seo',
                'credential_type_id' => $this->types[$code],
                'is_required' => true,
                'blocks_finalize' => true,
            ]);
        }

        CredentialRequirement::create([
            'project_type' => 'seo',
            'credential_type_id' => $this->types['ga4'],
            'is_required' => false,
            'blocks_finalize' => false,
        ]);
    }

    public function run(): void
    {
        $this->blockingListComesFromTheTableNotTheCode();
        $this->nonBlockingRequirementsDoNotBlock();
        $this->aNewProjectIsMissingEveryBlockingType();
        $this->eachAccessRemovedOneBlocker();
        $this->draftPausedAndCancelledAreNeverGated();
        $this->activeAndCompletedAreGated();
        $this->aTypeWithNoRequirementsIsNotGated();
        $this->messageNamesTheMissingKindsInPersian();
    }

    private function blockingListComesFromTheTableNotTheCode(): void
    {
        $codes = CredentialRequirement::blockingFor('seo')->pluck('code')->all();

        $this->assertSame(['wordpress', 'hosting', 'domain'], $codes,
            'blockingFor() reads credential_requirements and honours sort_order');
        $this->assertSame([], CredentialRequirement::blockingFor('design')->all(),
            'design has no requirements configured, so no gate');
    }

    private function nonBlockingRequirementsDoNotBlock(): void
    {
        $project = $this->projectWithAccesses(['wordpress', 'hosting', 'domain']);

        // ga4 is recorded as a requirement but with blocks_finalize=false.
        $this->assertSame([], FinalizationGate::missing((int) $project->id, 'seo'),
            'a requirement flagged non-blocking is not reported missing');
    }

    private function aNewProjectIsMissingEveryBlockingType(): void
    {
        $missing = FinalizationGate::missing(null, 'seo');

        $this->assertCount(3, $missing, 'an unsaved project cannot have any access');
        $this->assertTrue(FinalizationGate::blocks(null, 'seo', 'active'),
            '§14: creating an SEO project straight into active is refused');
    }

    private function eachAccessRemovedOneBlocker(): void
    {
        $one = $this->projectWithAccesses(['wordpress']);
        $this->assertCount(2, FinalizationGate::missing((int) $one->id, 'seo'),
            'one of three accesses leaves two blockers');

        $two = $this->projectWithAccesses(['wordpress', 'hosting']);
        $this->assertCount(1, FinalizationGate::missing((int) $two->id, 'seo'),
            'two of three leaves exactly the domain');
        $this->assertSame(['domain'],
            array_map(static fn ($t) => $t->code, FinalizationGate::missing((int) $two->id, 'seo')),
            'the remaining blocker is the one not yet recorded');

        $all = $this->projectWithAccesses(['wordpress', 'hosting', 'domain']);
        $this->assertSame([], FinalizationGate::missing((int) $all->id, 'seo'),
            'all three recorded leaves nothing missing');
        $this->assertFalse(FinalizationGate::blocks((int) $all->id, 'seo', 'active'),
            'the gate releases once the accesses exist');
        $this->assertFalse(FinalizationGate::blocks((int) $all->id, 'seo', 'completed'),
            'and it stays released for completed');
    }

    private function draftPausedAndCancelledAreNeverGated(): void
    {
        /*
         * §6 explicitly allows an incomplete draft: «در صورت نیاز، امکان ذخیره
         * پیش‌نویس ناقص وجود داشته باشد». Gating draft would make the documented
         * workflow impossible; gating paused/cancelled would trap bad data in the
         * system, because you could no longer park or abandon an incomplete
         * project.
         */
        foreach (['draft', 'paused', 'cancelled'] as $status) {
            $this->assertFalse(FinalizationGate::blocks(null, 'seo', $status),
                "status {$status} is not gated even with nothing recorded");
        }
    }

    private function activeAndCompletedAreGated(): void
    {
        $this->assertSame(['active', 'completed'], FinalizationGate::GATED_STATUSES,
            'exactly the statuses that mean "this project is real" are gated');
    }

    private function aTypeWithNoRequirementsIsNotGated(): void
    {
        $this->assertFalse(FinalizationGate::blocks(null, 'design', 'active'),
            'a design project is never gated by SEO access rules');
        $this->assertSame([], FinalizationGate::missing(null, 'design'),
            'an empty requirement list means no gate, not an invented default');
    }

    private function messageNamesTheMissingKindsInPersian(): void
    {
        $message = FinalizationGate::message(FinalizationGate::missing(null, 'seo'));

        $this->assertContains('دسترسی‌های اجباری', $message,
            'the message uses the requirement document\'s own words');
        $this->assertContains('«نوع wordpress»', $message,
            'and names each missing kind so the user is not left guessing');
        $this->assertContains('پیش‌نویس', $message,
            'and says what to do instead: save a draft');
    }

    /** @param list<string> $codes */
    private function projectWithAccesses(array $codes): Project
    {
        $customer = Customer::create([
            'type' => 'legal',
            'company_name' => 'مشتری آزمون دروازه ' . bin2hex(random_bytes(3)),
            'status' => 'active',
        ]);

        $project = Project::create([
            'customer_id' => (int) $customer->id,
            'type' => 'seo',
            'title' => 'پروژه آزمون دروازه ' . bin2hex(random_bytes(3)),
            'status' => 'draft',
            'amount' => 0,
        ]);

        foreach ($codes as $code) {
            ProjectCredential::create([
                'project_id' => (int) $project->id,
                'credential_type_id' => $this->types[$code],
                'label' => 'دسترسی ' . $code,
            ]);
        }

        return $project;
    }
}
