<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * OD-16 — the approved payroll formula, in exactly one place.
 *
 *   total_earnings   = base + bonus + overtime + Σ(earning items)
 *   total_deductions = deductions + advance + Σ(deduction items)
 *   net_payable      = total_earnings − total_deductions
 *
 * WHAT IS DELIBERATELY NOT COMPUTED
 * ---------------------------------
 * There is no commission, no project-revenue share, no productivity bonus, and
 * no automatic tax-withholding rate. The client's decision was explicit, and
 * `projects_total` — which IS recorded for context — is never read by the
 * arithmetic below. If someone later wants a formula, it enters as an APPROVED
 * `payroll_rules` row (is_approved = true), not as a clever line in this class.
 *
 * NEGATIVE NET IS AN ERROR, NOT A PAYSLIP
 * ---------------------------------------
 * If deductions exceed earnings the net goes negative. That is not a pay packet
 * of minus 2 million; it is a data-entry mistake or an unapproved loan schedule.
 * It is refused with a message naming the employee, because a negative payslip
 * silently reduces the published payroll total and nobody notices until the
 * bank file is wrong.
 */
final class PayrollCalculator
{
    /**
     * Recompute one payslip from its inputs and manual lines.
     *
     * Called on a draft period. Publishing a period is what makes figures final.
     */
    public function compute(Payslip $payslip, User $actor): Payslip
    {
        $period = $payslip->period;

        if ($period === null) {
            throw new RuntimeException('دورهٔ حقوق برای این فیش یافت نشد.');
        }

        if ($period->isPublished()) {
            throw new RuntimeException(
                'دورهٔ حقوق منتشر شده است. محاسبهٔ مجدد ممکن نیست؛ برای اصلاح، نسخهٔ جدید ثبت کنید.'
            );
        }

        $items = PayrollItem::query()->where('payslip_id', $payslip->id)->get();

        // Sum the two directions SEPARATELY. Summing signed values in one
        // pass would let a 1,000,000 deduction cancel a 1,000,000 allowance and
        // produce a payroll that looks balanced but pays the wrong amount.
        $positiveItems = (int) $items->filter(fn ($i) => $i->isEarning())->sum('amount');
        $negativeItems = (int) $items->reject(fn ($i) => $i->isEarning())->sum('amount');

        $earnings = (int) $payslip->base_salary
            + (int) $payslip->bonus
            + (int) $payslip->overtime
            + $positiveItems;

        $deductions = (int) $payslip->deductions
            + (int) $payslip->advance
            + $negativeItems;

        $net = $earnings - $deductions;

        if ($net < 0) {
            throw new RuntimeException(
                'خالص پرداختی «' . $payslip->employee?->displayName() . '» منفی می‌شود ('
                . number_format($net) . ' ریال). کسورات از مجموع دریافتی بیشتر است؛ ورودی‌ها را اصلاح کنید.'
            );
        }

        if (PayrollItem::query()->where('payslip_id', $payslip->id)->where('amount', '<', 0)->exists()) {
            throw new RuntimeException('مبلغ اقلام حقوق نمی‌تواند منفی باشد؛ برای کسورات، جهت قلم را «کسورات» انتخاب کنید.');
        }

        $payslip->forceFill([
            'total_earnings' => $earnings,
            'total_deductions' => $deductions,
            'net_payable' => $net,
            'computed_at' => now(),
            'computed_by' => $actor->id,
        ])->save();

        return $payslip->refresh();
    }

    /**
     * Build payslips for every active employee in a period.
     *
     * Existing payslips are left untouched: recomputing the whole month would
     * erase manual lines an accountant already typed. New employees get a row
     * with zero base salary, which is visibly wrong rather than invisibly absent
     * — an employee silently missing from payroll is the worse failure.
     */
    public function buildPeriod(PayrollPeriod $period, User $actor): int
    {
        if ($period->isPublished()) {
            throw new RuntimeException('دورهٔ منتشرشده قابل بازسازی نیست.');
        }

        return DB::transaction(function () use ($period, $actor) {
            $created = 0;

            $employees = Employee::query()->active()->orderBy('code')->get();

            foreach ($employees as $employee) {
                $exists = Payslip::query()
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Payslip::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'base_salary' => 0,
                    'worked_days' => null,
                    'bonus' => 0,
                    'overtime' => 0,
                    'advance' => 0,
                    'deductions' => 0,
                    'projects_total' => 0,
                    'total_earnings' => 0,
                    'total_deductions' => 0,
                    'net_payable' => 0,
                    'payroll_rule_id' => $period->payroll_rule_id,
                ]);

                $created++;
            }

            if ($period->state === 'draft') {
                $period->forceFill(['state' => 'computed'])->save();
            }

            return $created;
        });
    }

    /**
     * Recompute every payslip in the period and return the totals.
     *
     * @return array{count:int,total_earnings:int,total_deductions:int,total_net:int}
     */
    public function computePeriod(PayrollPeriod $period, User $actor): array
    {
        $payslips = Payslip::query()
            ->where('payroll_period_id', $period->id)
            ->with(['employee', 'items'])
            ->get();

        foreach ($payslips as $payslip) {
            $this->compute($payslip, $actor);
        }

        return [
            'count' => $payslips->count(),
            'total_earnings' => (int) Payslip::query()->where('payroll_period_id', $period->id)->sum('total_earnings'),
            'total_deductions' => (int) Payslip::query()->where('payroll_period_id', $period->id)->sum('total_deductions'),
            'total_net' => (int) Payslip::query()->where('payroll_period_id', $period->id)->sum('net_payable'),
        ];
    }

    /**
     * Change a payslip AFTER it has been published.
     *
     * A snapshot of the old values is written first, so the employee's original
     * figure remains reconstructible. The client's directive requires an audit
     * trail on payroll changes; this is that trail, in the domain, not only in
     * the generic audit log.
     */
    public function amend(Payslip $payslip, array $changes, string $reason, User $actor): Payslip
    {
        return DB::transaction(function () use ($payslip, $changes, $reason, $actor) {
            $nextVersion = (int) PayslipVersion::query()->where('payslip_id', $payslip->id)->max('version') + 1;

            PayslipVersion::create([
                'payslip_id' => $payslip->id,
                'version' => $nextVersion,
                'snapshot' => $payslip->load(['items'])->toArray(),
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            $payslip->fill(array_intersect_key($changes, array_flip([
                'base_salary', 'worked_days', 'bonus', 'overtime', 'advance',
                'deductions', 'input_notes',
            ])));

            $payslip->computed_at = now();
            $payslip->computed_by = $actor->id;
            $payslip->save();

            return $payslip->refresh();
        });
    }
}
