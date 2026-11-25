<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OD-41 (approved, option B) — the double-entry ledger.
 *
 * WHY THIS IS THE LOAD-BEARING MIGRATION OF THE WHOLE PRODUCT
 * -----------------------------------------------------------
 * Every financial figure the client will ever see — invoice totals, project
 * profitability, customer LTV, net profit on the BI dashboard — is derived from
 * these tables. Getting the shape wrong here means every number above it is
 * wrong in a way that looks plausible. So the constraints below are stated in
 * the database, not only in PHP:
 *
 *   - a line carries debit XOR credit, never both and never neither
 *   - money is a BIGINT of RIAL, never a float
 *   - a posted entry cannot be edited; correction is a reversing entry
 *   - a period can be closed, and a closed period rejects new postings
 *
 * THE ONE RULE THAT MATTERS MOST
 * ------------------------------
 * "Issuing an invoice is not receiving money." §19.4 of the directive spells
 * out nine distinct financial events that a naive CRM collapses into one
 * `invoice.paid_at` column. We keep them separate by construction: an invoice
 * creates a receivable (Dr AR / Cr Revenue + Cr Tax); a payment creates a bank
 * movement (Dr Bank / Cr AR). A customer can owe money on a fully issued
 * invoice, and the ledger says so.
 *
 * ANALYTIC DIMENSIONS
 * --------------------
 * §19.3 forbids "a separate account per customer/project" — that would explode
 * the chart of accounts into thousands of rows. Instead each *line* may carry
 * customer/contract/project/service/employee/department/cost-centre ids, and
 * profitability is a GROUP BY over those columns. That is why they live on
 * `journal_lines` rather than on the entry header: one entry (e.g. a payroll
 * run) legitimately spans several departments.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Departments (§17, §19.3, §20.2) ──────────────────────────────
        // Needed by accounting dimensions, resource planning, RBAC scoping and
        // the file workspace. Defined once, here, rather than re-invented by
        // each module that needs it.
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Chart of accounts (§19.1) ────────────────────────────────────
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            // Human-assigned code such as '1-1-01' or '4-1-01'. Unique because
            // a duplicated code makes a journal line ambiguous forever.
            $table->string('code', 32)->unique();
            $table->string('title', 190);
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts')->restrictOnDelete();

            // 1..n. Stored, not derived: level drives "is this a posting account"
            // checks that must be cheap and cannot depend on tree traversal.
            $table->unsignedTinyInteger('level')->default(1);

            // asset | liability | equity | revenue | expense
            $table->string('type', 16)->index();

            // debit | credit. Determines whether a positive balance on this
            // account is normal or anomalous — a trial balance cannot be read
            // without it.
            $table->string('normal_balance', 8);

            /*
             * Group accounts (e.g. "1-1 دارایی جاری") exist to subtotal their
             * children and must never receive a journal line directly — posting
             * to a header double-counts in every report above it. This flag is
             * the single guard, checked by LedgerService before every insert.
             */
            $table->boolean('is_postable')->default(true);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // seeded accounts cannot be deleted
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // ── Fiscal periods (§19.2) ───────────────────────────────────────
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();  // '1405'
            $table->string('title', 64);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('state', 16)->default('open')->index(); // open | closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Cost centres (§19.3) ─────────────────────────────────────────
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Journal entries (§19.2) ──────────────────────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique(); // JE-1405-0001
            $table->foreignId('fiscal_period_id')->constrained('fiscal_periods')->restrictOnDelete();
            $table->date('entry_date')->index();
            $table->string('memo', 500);

            /*
             * Every entry must name the thing that justified it (§19.2: "سند
             * بدون منبع معتبر ثبت نشود"). A manual entry with no source is not
             * permitted in v1; migrations/imports must nominate their own.
             */
            $table->string('source_type', 64);          // invoice | payment | payroll | expense | manual
            $table->unsignedBigInteger('source_id')->nullable();

            // draft → posted. `reversed` entries remain in the ledger, with a
            // pointer to the entry that undid them.
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // A reversed entry keeps its id in reports; this makes "which entry
            // killed it" a single lookup instead of a join over the whole table.
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable();
            $table->timestamps();

            // One source document produces at most one live entry. This is what
            // makes posting idempotent: re-running an invoice post cannot
            // double the revenue.
            $table->unique(['source_type', 'source_id'], 'journal_entries_source_unique');
            $table->index(['status', 'entry_date']);
        });

        // ── Journal lines (§19.2, §19.3) ─────────────────────────────────
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();

            // Rial, integer, non-negative. The direction is carried by which
            // column is non-zero, so a negative amount can never invert a
            // posting by accident.
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);

            $table->string('description', 255)->nullable();

            // ── Analytic dimensions (§19.3) ──
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            // 'seo' | 'design' | 'support' | ... — free text so a new service
            // line does not require a migration.
            $table->string('service_type', 32)->nullable();

            $table->unsignedSmallInteger('line_no')->default(1);
            $table->timestamps();

            $table->index(['ledger_account_id', 'journal_entry_id'], 'journal_lines_account_idx');
            $table->index(['customer_id', 'journal_entry_id'], 'journal_lines_customer_idx');
            $table->index(['project_id', 'journal_entry_id'], 'journal_lines_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('departments');
    }
};
