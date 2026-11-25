<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * OD-16 + OD-41 §19.3 — publishing a payroll month to the ledger.
 *
 * THE ENTRY
 * ---------
 *   Dr 5-2-01  حقوق و دستمزد              gross payroll  (Σ total_earnings)
 *   Cr 2-1-03  حقوق و دستمزد پرداختنی      net payable    (Σ net_payable)
 *   Cr 2-1-05  سایر بدهی‌های جاری           withheld       (Σ total_deductions)
 *
 * WHY THE DEDUCTIONS ARE A **CREDIT**, NOT A DEBIT
 * ------------------------------------------------
 * The gross wage is the expense; the employee never receives part of it,
 * because it was already advanced or is withheld. That withheld part is still an
 * obligation — to whoever the money is owed to (a loan fund, an insurer) — so it
 * is a liability, not a reduction of the liability to the employee.
 *
 * An earlier draft debited 2-1-03 for the deductions, which produced
 * debit 22,300,000 against credit 21,500,000 for a 21.9M payroll. The ledger
 * service refused it — correctly — and the flow harness caught it before the
 * month could be published. Debit gross = credit net + credit withheld is the
 * only arrangement that balances for any split.
 *
 * WHY PUBLISHING IS THE TRIGGER, AND ONLY ONCE
 * --------------------------------------------
 * A draft payroll is a spreadsheet. A published one is a promise to pay people.
 * Only the second belongs in the books. `source_type = payroll` and
 * `source_id = payroll_period_id` give the ledger its idempotence check, so a
 * double-click on «انتشار» cannot double the wage expense.
 *
 * Reversal is the correction path: a published month is never edited in place.
 */
final class PayrollAccountingService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /**
     * Post a published payroll period to the ledger.
     *
     * @throws RuntimeException when the period is not published, is empty, or
     *                          already has a live entry.
     */
    public function post(PayrollPeriod $period, User $actor): JournalEntry
    {
        if (! $period->isPublished()) {
            throw new RuntimeException('فقط دورهٔ حقوق منتشرشده به دفتر کل منتقل می‌شود.');
        }

        $payslips = Payslip::query()->where('payroll_period_id', $period->id)->get();

        if ($payslips->isEmpty()) {
            throw new RuntimeException('این دوره هیچ فیش حقوقی ندارد و سندی برای ثبت وجود ندارد.');
        }

        $gross = (int) $payslips->sum('total_earnings');
        $net = (int) $payslips->sum('net_payable');
        $deducted = (int) $payslips->sum('total_deductions');

        if ($gross <= 0) {
            throw new RuntimeException('مجموع حقوق این دوره صفر است؛ سندی ثبت نمی‌شود.');
        }

        // The identity that makes the entry balance. If it does not hold, the
        // payslip rows are internally inconsistent and posting would write an
        // unbalanced entry — refuse instead of letting the ledger service throw
        // a generic message the accountant cannot act on.
        if ($gross - $deducted !== $net) {
            throw new RuntimeException(
                'جمع حقوق با خالص پرداختی هم‌خوان نیست (ناخالص ' . number_format($gross)
                . ' − کسورات ' . number_format($deducted) . ' ≠ خالص ' . number_format($net)
                . '). فیش‌ها را دوباره محاسبه کنید.'
            );
        }

        $lines = [
            [
                'account' => \Database\Seeders\ChartOfAccountsSeeder::EXPENSE_SALARY,
                'debit' => $gross,
                'description' => 'حقوق و دستمزد ' . $period->title(),
            ],
            [
                'account' => \Database\Seeders\ChartOfAccountsSeeder::PAYROLL_PAYABLE,
                'credit' => $net,
                'description' => 'خالص پرداختنی کارکنان ' . $period->title(),
            ],
        ];

        // Only emit the withheld line when there is something to withhold, so a
        // clean month produces a two-line entry an auditor reads at a glance.
        if ($deducted > 0) {
            $lines[] = [
                'account' => \Database\Seeders\ChartOfAccountsSeeder::OTHER_CURRENT_LIABILITY,
                'credit' => $deducted,
                'description' => 'کسورات و مساعدهٔ کسرشده از حقوق ' . $period->title(),
            ];
        }

        $entryDate = $this->entryDate($period);

        return $this->ledger->post([
            'entry_date' => $entryDate,
            'memo' => 'ثبت حقوق و دستمزد ' . $period->title() . ' — '
                . \App\Support\Money::toPersianDigits((string) $payslips->count()) . ' فیش',
            'source_type' => 'payroll',
            'source_id' => $period->id,
            'lines' => $lines,
        ], $actor->id);
    }

    /**
     * The last day of the payroll month.
     *
     * A payroll belongs to the month in which people worked. Using "today" would
     * post November's wages into December when publishing on the 1st, and — worse
     * — could fail with "no open fiscal period" for a month never opened.
     */
    private function entryDate(PayrollPeriod $period): string
    {
        $lastDay = \App\Support\JalaliDate::monthLength((int) $period->jalali_year, (int) $period->jalali_month);

        return \App\Support\JalaliDate::fromJalali(
            (int) $period->jalali_year,
            (int) $period->jalali_month,
            $lastDay,
        )->toDateString();
    }

    /** Reverse a payroll posting — the only way to take it out of the books. */
    public function reverse(PayrollPeriod $period, string $reason, User $actor): JournalEntry
    {
        $entry = JournalEntry::query()
            ->where('source_type', 'payroll')
            ->where('source_id', $period->id)
            ->where('status', 'posted')
            ->first();

        if ($entry === null) {
            throw new RuntimeException('سند حسابداری معتبری برای این دورهٔ حقوق یافت نشد.');
        }

        return $this->ledger->reverse($entry, $reason, $actor->id);
    }
}
