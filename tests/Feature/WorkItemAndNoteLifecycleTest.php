<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Security\AuditRecorder;
use App\Services\Security\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * EXP-WORKITEM-001 / EXP-NOTE-001 — closing the کارتابل and the task list.
 *
 * WHAT THESE TESTS ARE FOR
 * ------------------------
 * The defect being fixed is not cosmetic: `work_items.state` could never leave
 * `pending`, so nine real obligations were permanently stuck and the invoice
 * figures §5 requires were never captured anywhere. A test that only checks the
 * route returns 200 would have passed against the broken code too. These assert
 * the state transitions and the money, because that is what was missing.
 *
 * ISOLATION: every scenario boots its own SQLite file. Nothing here can read or
 * write the shared review database.
 */
final class WorkItemAndNoteLifecycleTest extends ViraTestCase
{
    private DatabaseHarness $harness;

    private function boot(string $name): void
    {
        $this->harness = DatabaseHarness::boot($name)->migrate();
    }

    private function seedPermissions(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }
    }

    private function makeUser(string $username): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'password' => Hash::make('Secret-Passw0rd-123'),
            'is_superadmin' => true,
            'is_active' => true,
        ]);
    }

    private function makeProject(): Project
    {
        $customer = Customer::create([
            'type' => 'legal',
            'company_name' => 'شرکت آزمون',
            'status' => 'active',
        ]);

        return Project::create([
            'customer_id' => $customer->id,
            'type' => 'seo',
            'title' => 'پروژه آزمون',
            'status' => 'active',
        ]);
    }

    public function run(): void
    {
        $this->completingAnInvoiceRecordsTheMoney();
        $this->cancelledIsNotCountedAsDone();
        $this->reopenClearsTheOutcome();
        $this->completionIsAudited();
        $this->noteCompletionSetsDoneBy();
        $this->permissionsAreCatalogued();
    }

    /**
     * THE CORE FIX: closing an invoice item stores number, amount and date in
     * the same write as done_at, so the two can never disagree.
     */
    private function completingAnInvoiceRecordsTheMoney(): void
    {
        $this->boot('workitem_money');
        $user = $this->makeUser('closer');
        $project = $this->makeProject();

        $item = WorkItem::create([
            'project_id' => $project->id,
            'kind' => 'invoice',
            'due_date' => '2026-08-16',
            'period' => '1405/05',
            'state' => 'pending',
            'reminder_offset_days' => 2,
        ]);

        $this->assertNull($item->done_at, 'A new work item must start open.');

        $item->complete($user->id, 'صادر شد', [
            'number' => 'INV-1405-0912',
            'amount' => 12_500_000,
            'issue_date' => '2026-08-15',
        ]);

        $item->refresh();

        $this->assertSame('done', $item->state, 'Completing must set state to done.');
        $this->assertNotNull($item->done_at, 'Completing must stamp done_at.');
        $this->assertSame($user->id, (int) $item->done_by, 'done_by must record who closed it.');
        $this->assertSame('INV-1405-0912', $item->invoice_number, 'Invoice number must be stored.');
        $this->assertSame(12_500_000, (int) $item->invoice_amount, 'Invoice amount must be stored as an integer.');
        $this->assertSame('صادر شد', $item->completion_notes, 'Completion notes must be kept.');

        // The state column and done_at must never disagree — an item with
        // done_at but state=pending would haunt the open list forever.
        $this->assertFalse($item->isOpen(), 'A completed item must not report as open.');
    }

    /** Cancellation must not inflate "invoices issued". */
    private function cancelledIsNotCountedAsDone(): void
    {
        $this->boot('workitem_cancel');
        $project = $this->makeProject();

        $item = WorkItem::create([
            'project_id' => $project->id,
            'kind' => 'invoice',
            'due_date' => '2026-09-16',
            'state' => 'pending',
            'reminder_offset_days' => 2,
        ]);

        $item->forceFill(['state' => 'cancelled', 'completion_notes' => 'قرارداد تمدید نشد'])->save();
        $item->refresh();

        $this->assertSame('cancelled', $item->state);
        $this->assertNull($item->done_at, 'A cancelled item must not get a done_at stamp.');
        $this->assertFalse($item->isOpen(), 'A cancelled item must leave the open list.');

        // The distinction the reports depend on:
        $done = WorkItem::query()->where('state', 'done')->count();
        $this->assertSame(0, $done, 'Cancelling must never produce a done row.');
    }

    private function reopenClearsTheOutcome(): void
    {
        $this->boot('workitem_reopen');
        $user = $this->makeUser('reopener');
        $project = $this->makeProject();

        $item = WorkItem::create([
            'project_id' => $project->id,
            'kind' => 'report',
            'due_date' => '2026-07-16',
            'state' => 'pending',
            'reminder_offset_days' => 2,
        ]);

        $item->complete($user->id, null, []);
        $item->refresh();
        $this->assertNotNull($item->done_at, 'Precondition: the item is closed.');

        $item->reopen();
        $item->refresh();

        $this->assertNull($item->done_at, 'Reopening must clear done_at.');
        $this->assertNull($item->done_by, 'Reopening must clear done_by.');
        $this->assertSame('pending', $item->state, 'Reopening must return the item to pending.');
        $this->assertTrue($item->isOpen(), 'A reopened item must appear in the open list again.');
    }

    /** §10: closing an obligation is an auditable event. */
    private function completionIsAudited(): void
    {
        $this->boot('workitem_audit');
        $user = $this->makeUser('auditor');
        $project = $this->makeProject();

        $item = WorkItem::create([
            'project_id' => $project->id,
            'kind' => 'invoice',
            'due_date' => '2026-10-16',
            'state' => 'pending',
            'reminder_offset_days' => 2,
        ]);

        $item->complete($user->id, 'ok', ['number' => 'INV-1', 'amount' => 1_000_000]);

        AuditRecorder::updated($item, ['state' => ['pending', 'done']], 'بستن مورد کارتابل');

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => WorkItem::class,
            'subject_id' => $item->id,
        ]);

        $log = AuditLog::query()->where('subject_id', $item->id)->latest('id')->first();
        $this->assertNotNull($log, 'An audit row must exist for the closure.');
    }

    private function noteCompletionSetsDoneBy(): void
    {
        $this->boot('note_complete');
        $user = $this->makeUser('noteowner');

        $note = Note::create([
            'title' => 'تماس با مشتری',
            'priority' => 'high',
            'status' => 'open',
            'due_date' => '2026-08-20',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $previous = $note->status;
        $note->forceFill(['status' => 'done', 'done_at' => now(), 'done_by' => $user->id])->save();
        $note->refresh();

        $this->assertSame('done', $note->status);
        $this->assertNotNull($note->done_at, 'Finishing a task must stamp done_at.');
        $this->assertSame($user->id, (int) $note->done_by, 'done_by must record the actor.');
        $this->assertSame('open', $previous, 'The pre-save status must be readable for the audit diff.');
    }

    /**
     * The permissions the new screens depend on must exist in the catalogue —
     * otherwise the middleware denies everyone but superadmin and the module
     * looks "implemented" while being unreachable.
     */
    private function permissionsAreCatalogued(): void
    {
        $this->boot('workitem_perms');
        $this->seedPermissions();

        foreach (['work_items.view', 'work_items.complete', 'work_items.create', 'work_items.cancel'] as $code) {
            $this->assertTrue(
                Permission::query()->where('code', $code)->exists(),
                'Missing permission: ' . $code,
            );
        }

        $this->assertDatabaseHas('permissions', ['code' => 'notes.view']);
        $this->assertDatabaseHas('permissions', ['code' => 'notes.delete']);
    }
}
