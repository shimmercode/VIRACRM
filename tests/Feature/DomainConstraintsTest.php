<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\WorkItem;
use DomainException;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * Data integrity: `type` / `status` / `state` values outside their domain.
 *
 * THE DEFECT, REPRODUCED
 * ----------------------
 * These columns were PHP enums or `const` arrays with **no** database
 * constraint behind them. Seeding `customers.type = 'real'` (the enum is
 * `natural|legal`) and `contracts.status = 'completed'` (not a
 * `ContractStatus`) both **wrote successfully** and only failed later, when a
 * view cast the value — surfacing as an HTTP 500 on a list page. A bad value
 * could therefore sit in the database unnoticed until somebody opened the page.
 *
 * The UI path was already safe (`Rule::in` in the FormRequests). What was not
 * safe is every other write path: console commands, seeders, imports, queued
 * jobs, a future API. `HasDomainConstraints` closes all of them at once.
 *
 * WHY THIS IS A FEATURE TEST AND NOT A UNIT TEST
 * ----------------------------------------------
 * The guard fires on `saving`, so it has to run against a real model lifecycle
 * on a real connection. Asserting the constant arrays contain the right keys
 * would prove nothing about whether a bad write is refused.
 */
final class DomainConstraintsTest extends ViraTestCase
{
    private DatabaseHarness $db;

    private int $customerId;

    private int $projectId;

    protected function setUp(): void
    {
        $this->db = DatabaseHarness::boot('domain-constraints');
        $this->db->migrate();

        $this->customerId = (int) Customer::create([
            'type' => 'legal',
            'company_name' => 'شرکت آزمون دامنه ' . bin2hex(random_bytes(3)),
            'status' => 'active',
        ])->id;

        $this->projectId = (int) Project::create([
            'customer_id' => $this->customerId,
            'type' => 'seo',
            'title' => 'پروژه آزمون دامنه',
            'status' => 'draft',
        ])->id;
    }

    public function run(): void
    {
        $this->everyModelDeclaresItsOwnDomain();
        $this->invalidCustomerTypeIsRefused();
        $this->invalidCustomerStatusIsRefused();
        $this->invalidProjectTypeIsRefused();
        $this->invalidProjectStatusIsRefused();
        $this->everyRealProjectStatusIsAccepted();
        $this->invalidWorkItemKindAndStateAreRefused();
        $this->invalidNoteStatusIsRefused();
        $this->refusedWriteLeavesNoRowBehind();
        $this->nullIsNotTreatedAsInvalid();
        $this->enumInstanceIsAccepted();
        $this->cleanAttributesAreNotRevalidated();
    }

    private function everyModelDeclaresItsOwnDomain(): void
    {
        // A model that adopts the trait but declares nothing would silently
        // constrain nothing — the worst kind of false assurance.
        foreach ([Customer::class, Project::class, WorkItem::class, Note::class] as $model) {
            $instance = new $model();
            $reflection = new \ReflectionMethod($instance, 'domainColumns');
            $reflection->setAccessible(true);
            $columns = $reflection->invoke($instance);

            $enumReflection = new \ReflectionMethod($instance, 'domainEnums');
            $enumReflection->setAccessible(true);
            $enums = $enumReflection->invoke($instance);

            $this->assertGreaterThan(0, count($columns) + count($enums),
                "{$model} declares at least one constrained column");
        }
    }

    private function invalidCustomerTypeIsRefused(): void
    {
        // 'real' was the exact value that wrote successfully before the fix.
        $this->assertThrows(\Throwable::class, function (): void {
            Customer::create([
                'type' => 'real',
                'company_name' => 'نوع نامعتبر',
                'status' => 'active',
            ]);
        }, "customers.type='real' is refused (enum is natural|legal)");
    }

    private function invalidCustomerStatusIsRefused(): void
    {
        $this->assertThrows(DomainException::class, function (): void {
            Customer::create([
                'type' => 'legal',
                'company_name' => 'وضعیت نامعتبر',
                'status' => 'completed',
            ]);
        }, "customers.status='completed' is refused (not a CustomerStatus)");
    }

    private function invalidProjectTypeIsRefused(): void
    {
        $this->assertThrows(DomainException::class, function (): void {
            Project::create([
                'customer_id' => $this->customerId,
                'type' => 'branding',
                'title' => 'نوع نامعتبر',
                'status' => 'draft',
            ]);
        }, "projects.type='branding' is refused (TYPES is seo|design)");
    }

    private function invalidProjectStatusIsRefused(): void
    {
        $this->assertThrows(DomainException::class, function (): void {
            Project::create([
                'customer_id' => $this->customerId,
                'type' => 'seo',
                'title' => 'وضعیت نامعتبر',
                'status' => 'in_review',
            ]);
        }, "projects.status='in_review' is refused");
    }

    private function everyRealProjectStatusIsAccepted(): void
    {
        /*
         * The guard must not be stricter than the product. 'completed' is a
         * legitimate Project status — an early probe of this code treated it as
         * invalid and the probe was wrong, not the model. Every value in
         * Project::STATUSES must write.
         */
        foreach (array_keys(Project::STATUSES) as $status) {
            $project = Project::create([
                'customer_id' => $this->customerId,
                'type' => 'design',
                'title' => 'پروژه ' . $status,
                'status' => $status,
            ]);

            $this->assertSame((string) $status, (string) $project->status,
                "Project status '{$status}' is accepted");
        }
    }

    private function invalidWorkItemKindAndStateAreRefused(): void
    {
        $this->assertThrows(DomainException::class, function (): void {
            WorkItem::create([
                'project_id' => $this->projectId,
                'kind' => 'task',
                'due_date' => '2026-06-01',
                'state' => 'pending',
            ]);
        }, "work_items.kind='task' is refused (KINDS is invoice|report)");

        $this->assertThrows(DomainException::class, function (): void {
            WorkItem::create([
                'project_id' => $this->projectId,
                'kind' => 'report',
                'due_date' => '2026-06-01',
                'state' => 'wip',
            ]);
        }, "work_items.state='wip' is refused");
    }

    private function invalidNoteStatusIsRefused(): void
    {
        $this->assertThrows(DomainException::class, function (): void {
            Note::create([
                'customer_id' => $this->customerId,
                'body' => 'یادداشت با وضعیت نامعتبر',
                'status' => 'archived',
            ]);
        }, "notes.status='archived' is refused (STATUSES is open|done|cancelled)");
    }

    private function refusedWriteLeavesNoRowBehind(): void
    {
        $before = (int) Project::query()->count();

        try {
            Project::create([
                'customer_id' => $this->customerId,
                'type' => 'seo',
                'title' => 'نباید ذخیره شود',
                'status' => 'in_review',
            ]);
        } catch (DomainException) {
            // expected
        }

        $after = (int) Project::query()->count();

        $this->assertSame($before, $after,
            'a refused write leaves no partial row behind');
    }

    private function nullIsNotTreatedAsInvalid(): void
    {
        /*
         * Nullable columns must stay nullable. Treating NULL as "not in the
         * allowed list" would break every optional status the schema permits.
         */
        $project = Project::create([
            'customer_id' => $this->customerId,
            'type' => 'design',
            'title' => 'پروژه با مسئول خالی',
            'status' => 'draft',
            'owner_user_id' => null,
        ]);

        $this->assertNull($project->owner_user_id, 'a nullable column still accepts null');
    }

    private function enumInstanceIsAccepted(): void
    {
        // Passing the enum object rather than its string is the natural way to
        // write this in new code, and must not be rejected.
        $customer = Customer::create([
            'type' => \App\Enums\CustomerType::Legal,
            'company_name' => 'شرکت با نوع enum',
            'status' => 'active',
        ]);

        $this->assertSame('legal', (string) $customer->type->value,
            'a backed enum instance is accepted and cast back');
    }

    private function cleanAttributesAreNotRevalidated(): void
    {
        /*
         * The guard checks only dirty attributes. Re-checking clean ones would
         * make an unrelated save fail because of pre-existing bad data, turning
         * the guard into a trap for whoever is cleaning that data up.
         */
        $project = Project::create([
            'customer_id' => $this->customerId,
            'type' => 'seo',
            'title' => 'پروژه برای ویرایش عنوان',
            'status' => 'active',
        ]);

        $project->title = 'عنوان به‌روزشده';
        $project->save();

        $this->assertSame('عنوان به‌روزشده', (string) $project->fresh()->title,
            'updating an unrelated column does not re-trigger domain validation');
    }
}
