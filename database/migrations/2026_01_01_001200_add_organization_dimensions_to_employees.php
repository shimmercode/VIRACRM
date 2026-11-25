<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §17 — employees belong to an organisational unit, and may report to a manager.
 *
 * WHY THIS MIGRATION EXISTS
 * -------------------------
 * `App\Models\Employee` declares `department_id`, `manager_id` and the
 * `department()` / `manager()` relations, and `Department::employees()` is a
 * hasMany — but the personnel migration never created either column. The
 * relations therefore resolved to a column that did not exist, which is silent
 * until something counts them: `withCount('employees')` on the organisation
 * screen produced "no such column: employees.department_id" and a 500.
 *
 * A model may describe a column that does not exist as long as nothing queries
 * it; the moment a report groups by it, the schema is the truth. This corrects
 * the schema rather than deleting the relation, because the relation is the
 * intended design (§17) and an employee really does belong to a unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('user_id')
                ->constrained('departments')->nullOnDelete();
            // A manager leaving the company must not delete their reports.
            $table->foreignId('manager_id')->nullable()->after('department_id')
                ->constrained('employees')->nullOnDelete();

            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['department_id', 'manager_id']);
        });
    }
};
