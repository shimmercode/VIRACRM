<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-05.01–05.05 and REQ-12.* — projects, their type-specific details, and the
 * invoice/report work items that drive the کارتابل.
 *
 * THE CENTRAL DESIGN CHOICE
 * -------------------------
 * The client document §5-2 describes a reminder that fires two calendar days
 * before an invoice date or a report date, appears in the کارتابل, and — once
 * the invoice is sent — records the invoice number, amount, issue date and file.
 *
 * We model that as first-class `work_items` rows (one per scheduled invoice or
 * report), NOT as a reminder queue. Reasons:
 *   - the reminder is a *view* of the item's state, computed from due_date;
 *   - "overdue until an outcome is recorded" falls out naturally: an item with
 *     done_at = null and due_date in the past is overdue, by definition;
 *   - recurring periods generate the next item rather than re-arming a timer;
 *   - the invoice metadata the document requires has an obvious home.
 *
 * A reminder-fire log (`scheduled_events`) sits alongside for idempotency, so
 * re-running the scheduler cannot double-notify.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Configurable dictionaries (§15) ───────────────────────────────
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // seo | design
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seo_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('keyword_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable()->unique(); // VIRA-P-0001
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->index();  // seo | design
            $table->string('title');
            $table->string('status', 16)->default('draft')->index();

            $table->unsignedBigInteger('amount')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'type']);
            $table->index(['type', 'status']);
        });

        // §5-1: SEO project specifics.
        Schema::create('seo_project_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_package_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('keyword_count')->default(0);

            // §5-1: planned invoice and report dates drive the reminders.
            $table->string('recurrence', 16)->nullable(); // monthly|quarterly|semiannual|yearly
            $table->unsignedSmallInteger('reminder_offset_days')->default(2);

            $table->timestamps();

            $table->unique('project_id');
        });

        // §5-1: keywords are structured (count matters commercially), so they
        // are rows rather than a blob; a blob cannot be counted or deduplicated.
        Schema::create('project_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 191);
            $table->string('normalized', 191)->index();
            $table->string('status', 16)->default('pending'); // pending|working|done|removed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });

        // §5-3: design-project specifics.
        Schema::create('design_project_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->nullable();
            $table->string('site_type', 48)->nullable();    // فروشگاهی، شرکتی، ...
            $table->text('services')->nullable();           // scope of work
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('project_id');
        });

        // §5-1: an SEO project is worked on by a person who is paid per project.
        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->nullable(); // کارشناس سئو، طراح
            $table->unsignedBigInteger('amount')->default(0);   // per-project fee
            $table->unsignedInteger('coefficient')->default(100); // percent basis points
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'employee_id']);
            $table->index(['employee_id', 'is_active']);
        });

        // ── Work items: one row per scheduled invoice or report ───────────
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->index();       // invoice | report
            $table->date('due_date')->index();
            $table->string('period', 24)->nullable();  // 1405-02 — the billing period
            $table->string('state', 16)->default('pending')->index();

            // §5-2: what must be recorded once the invoice is sent.
            $table->string('invoice_number', 64)->nullable();
            $table->unsignedBigInteger('invoice_amount')->nullable();
            $table->date('invoice_issue_date')->nullable();
            $table->foreignId('document_attachment_id')->nullable();

            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_notes')->nullable();

            // Recurrence: when this item is completed, the next one is generated.
            $table->string('recurrence', 16)->nullable();
            $table->foreignId('generated_from_id')->nullable()->constrained('work_items')->nullOnDelete();

            $table->unsignedSmallInteger('reminder_offset_days')->default(2);
            $table->timestamps();

            // One invoice and one report per project per period — this is what
            // prevents duplicate reminders after a date change (§14).
            $table->unique(['project_id', 'kind', 'period']);
            $table->index(['state', 'due_date']);
        });

        // ── Notification log (idempotent scheduler) ───────────────────────
        Schema::create('scheduled_events', function (Blueprint $table) {
            $table->id();
            $table->string('unique_key', 64)->unique();
            $table->string('event_type', 48);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('kind', 24);  // due_soon | due_today | overdue
            $table->date('fire_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('fire_date');
        });

        // Observability for §5-2's "زمان‌بندی واقعی، Idempotent و قابل مشاهده".
        Schema::create('job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job', 64);
            $table->string('status', 16); // running|success|failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('job');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_runs');
        Schema::dropIfExists('scheduled_events');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('project_assignments');
        Schema::dropIfExists('design_project_details');
        Schema::dropIfExists('project_keywords');
        Schema::dropIfExists('seo_project_details');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('seo_packages');
        Schema::dropIfExists('project_types');
    }
};
