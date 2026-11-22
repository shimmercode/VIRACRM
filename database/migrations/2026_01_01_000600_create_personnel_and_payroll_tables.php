<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-07.01–07.05 — personnel and payroll.
 *
 * ⚠️ BUSINESS-RULE BOUNDARY ⚠️
 * The client document §7 lists the *components* of a payslip (base, per-project
 * amounts, bonus, overtime, deductions, advance, net) but does NOT define the
 * formula that turns them into a net figure, nor the commission base
 * (contract value vs invoiced vs paid). This is OD-05 / OD-06 and is
 * deliberately BLOCKED pending owner approval.
 *
 * The schema therefore stores INPUTS and a FROZEN RESULT. `payroll_rules` holds
 * the chosen method as data; `payslips.total_*` holds the computed outcome,
 * which after approval becomes immutable. No formula is hard-coded anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_id', 10)->nullable()->unique();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->string('position')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 16)->default('active')->index(); // active|suspended|terminated
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // §7: the personnel contract scan.
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->string('title')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('agreed_amount')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // §15: "روش محاسبه حقوق" must be configurable, not invented by us.
        Schema::create('payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title');
            $table->string('method', 32); // fixed|project_based|hybrid — chosen by the owner
            $table->json('params')->nullable(); // coefficients, caps, rounding, commission base
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false); // ⚠️ must be true before use
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_version_of')->nullable()->constrained('payroll_rules')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('jalali_year', 4);
            $table->string('jalali_month', 2);
            $table->string('state', 16)->default('draft'); // draft|published|locked
            $table->foreignId('payroll_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['jalali_year', 'jalali_month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Inputs, as recorded by the payroll operator.
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedInteger('worked_days')->nullable();
            $table->unsignedBigInteger('bonus')->default(0);
            $table->unsignedBigInteger('overtime')->default(0);
            $table->unsignedBigInteger('advance')->default(0);
            $table->unsignedBigInteger('deductions')->default(0);
            $table->text('input_notes')->nullable();

            // Frozen outputs. computed_at records that a human approved this figure.
            $table->unsignedBigInteger('projects_total')->default(0);
            $table->unsignedBigInteger('total_earnings')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->unsignedBigInteger('net_payable')->default(0);

            $table->foreignId('payroll_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('computed_at')->nullable();
            $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();

            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });

        // §7: per-project amounts feeding the payslip.
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedInteger('coefficient')->default(100);
            $table->timestamps();

            $table->index('payslip_id');
        });

        // §7: corrections are versioned, never destructive.
        Schema::create('payslip_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot');
            $table->text('reason');           // change log, required by §7
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payslip_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_versions');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_rules');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employees');
    }
};
