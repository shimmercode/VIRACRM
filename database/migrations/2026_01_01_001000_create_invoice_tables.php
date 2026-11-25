<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OD-41 §19.6 / §19.7 — invoices and payments.
 *
 * THE SEPARATION THIS SCHEMA ENFORCES
 * -----------------------------------
 * `invoices.status` describes the INVOICE (draft/issued/void) and
 * `invoices.payment_state` describes whether money arrived (unpaid/partial/paid).
 * They are two columns because they are two facts. §19.4 is explicit that issuing
 * an invoice is not receiving money, and a single `status` column with values like
 * 'paid' collapses that distinction — after which "revenue" and "cash collected"
 * can never be reported separately again.
 *
 * Money is INTEGER RIAL throughout. Item amounts are stored per line so a
 * partially-credited invoice can be explained; `total` is derived and stored so
 * a report does not have to re-sum on every row.
 *
 * TAX RATES ARE NOT HARDCODED (§19.1). `tax_rate_bp` is basis points (1000 = 10%)
 * captured on the invoice at issue time, so a later rate change cannot retroactively
 * rewrite an issued invoice's total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Numbering is controlled (§19.6): assigned at issue, not at draft,
            // so abandoned drafts do not leave gaps in the legal sequence.
            $table->string('number', 32)->nullable()->unique();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('service_type', 32)->default('other');
            $table->date('issue_date')->index();
            $table->date('due_date')->index();

            // Rial, integers.
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);

            $table->string('status', 16)->default('draft')->index();       // draft|issued|void
            $table->string('payment_state', 16)->default('unpaid')->index(); // unpaid|partial|paid

            $table->string('title', 190)->nullable();
            $table->text('notes')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamp('voided_at')->nullable();

            // The work item that prompted this invoice, if any — keeps the
            // کارتابل and the ledger talking about the same thing.
            $table->foreignId('work_item_id')->nullable()->constrained('work_items')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['payment_state', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->string('description', 500);
            // Quantity in thousandths so fractions are exact integers, not floats.
            $table->unsignedInteger('quantity_milli')->default(1000);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('direction', 8)->index(); // in | out
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on')->index();
            $table->string('method', 24)->default('transfer'); // cash|transfer|cheque|online
            $table->string('reference', 120)->nullable();
            $table->string('bank_account', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A payment can be allocated across several invoices (or none, as an
        // advance). The pivot keeps allocation explicit instead of guessing it
        // from the payment's single invoice_id.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
