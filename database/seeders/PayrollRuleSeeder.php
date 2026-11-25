<?php

namespace Database\Seeders;

use App\Models\PayrollRule;
use Illuminate\Database\Seeder;

/**
 * OD-16 — the one payroll method the client approved.
 *
 * WHY THIS IS SEEDED AND NOT LEFT EMPTY
 * -------------------------------------
 * `payroll_periods.payroll_rule_id` points at a rule, and the payroll screen
 * shows which method a month was computed under. A period created against no
 * rule would still compute — the formula in PayrollCalculator is the law — but
 * the audit trail would not say what method was in force, and a future
 * client-defined formula would have nothing to attach to.
 *
 * `is_approved = true` is deliberate and is NOT a shortcut: `manual_lines` is
 * the method OD-16 records the client choosing. A rule whose method nobody has
 * approved must stay `is_approved = false`, which makes `isUsable()` false and
 * keeps it out of computation.
 *
 * IDEMPOTENT via updateOrCreate on `code`.
 */
class PayrollRuleSeeder extends Seeder
{
    public function run(): void
    {
        PayrollRule::updateOrCreate(
            ['code' => 'od16-manual-lines'],
            [
                'title' => 'حقوق ثابت + اقلام دستی (تصمیم OD-16)',
                'method' => PayrollRule::METHOD_MANUAL,
                'params' => [
                    'notes' => 'هیچ فرمول پورسانتی وجود ندارد؛ اقلام مثبت و منفی دستی وارد می‌شوند.',
                ],
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ]
        );

        $this->command?->info('Payroll rules: 1 synced.');
    }
}
