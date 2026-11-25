<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OD-16 — payroll line direction.
 *
 * WHY THIS MIGRATION EXISTS
 * -------------------------
 * `payroll_items.amount` is an UNSIGNED column. On SQLite a negative value is
 * stored happily, so the "a deduction is a negative line" design would appear to
 * work in development and then fail in production on MariaDB with
 * "Out of range value for column 'amount'". A migration that only fixes the
 * column type would still leave the domain meaning of the number ambiguous
 * (is 500,000 an earning or a deduction?).
 *
 * So direction becomes an explicit, constrained column:
 *   earning   → adds to total_earnings
 *   deduction → adds to total_deductions
 *
 * `amount` stays unsigned and always positive. The calculator is the only place
 * that interprets the pair, and it can no longer be fooled by sign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('direction', 16)->default('earning')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
