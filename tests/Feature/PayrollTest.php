<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipVersion;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\PayrollAccountingService;
use App\Services\Payroll\PayrollCalculator;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * OD-16 — the approved payroll formula.
 *
 * WHAT THESE TESTS GUARD AGAINST
 * ------------------------------
 * The client's decision was "fixed salary + manually entered line items, and
 * never invent a commission formula". A test suite is the only thing that keeps
 * that true eighteen months from now, so the assertions are about the FORMULA'S
 * SHAPE, not just its arithmetic:
 *
 *   - a negative manual item REDUCES net (and is not cancelled by a positive one)
 *   - projects_total NEVER enters the arithmetic, however large it is
 *   - a negative net is refused, not stored
 *   - a published period cannot be recomputed
 *   - amending after publication writes a restorable snapshot first
 */
final class PayrollTest extends ViraTestCase
{
    private DatabaseHarness $harness;

    private function boot(string $name): void
    {
        $this->harness = DatabaseHarness::boot($name)->migrate();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'حسابدار',
            'username' => 'payroll_acc',
            'password' => Hash::make('Secret-Passw0rd-123'),
            'is_superadmin' => true,
            'is_active' => true,
        ]);
    }

    private function makePeriod(string $state = 'draft'): PayrollPeriod
    {
        return PayrollPeriod::create([
            'jalali_year' => 1405,
            'jalali_month' => 8,
            'state' => $state,
        ]);
    }

    private function makeEmployee(string $code, string $first = 'کارمند'): Employee
    {
        return Employee::create([
            'code' => $code,
            'first_name' => $first,
            'last_name' => 'آزمون',
            'status' => 'active',
        ]);
    }

    private function makePayslip(PayrollPeriod $period, Employee $employee, array $attrs = []): Payslip
    {
        return Payslip::create(array_merge([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'base_salary' => 0,
            'bonus' => 0,
            'overtime' => 0,
            'advance' => 0,
            'deductions' => 0,
            'projects_total' => 0,
            'total_earnings' => 0,
            'total_deductions' => 0,
            'net_payable' => 0,
        ], $attrs));
    }

    public function run(): void
    {
        $this->formulaIsFixedSalaryPlusManualLines();
        $this->negativeItemReducesNetAndDoesNotCancel();
        $this->projectsTotalNeverEntersTheFormula();
        $this->negativeNetIsRefused();
        $this->publishedPeriodCannotBeRecomputed();
        $this->amendWritesRestorableSnapshot();
        $this->buildPeriodSkipsExistingPayslips();
        $this->negativeStoredAmountIsRefused();
        $this->postingSplitsGrossIntoNetAndWithheld();
        $this->postingRefusesADraftMonth();
        $this->postingRefusesAnInconsistentPayslip();
    }

    /** A published month plus the chart and an open fiscal period. */
    private function bootAccounted(string $name): void
    {
        $this->boot($name);
        (new ChartOfAccountsSeeder())->run();
        FiscalPeriod::create([
            'code' => '1405',
            'title' => 'سال ۱۴۰۵',
            'starts_on' => '2026-03-21',
            'ends_on' => '2027-03-20',
            'state' => 'open',
        ]);
    }

    /**
     * THE POSTING BUG THIS TEST EXISTS FOR
     * ------------------------------------
     * A 21,900,000 payroll with 400,000 of deductions must produce:
     *
     *     Dr 5-2-01  21,900,000
     *     Cr 2-1-03  21,500,000
     *     Cr 2-1-05     400,000
     *
     * An earlier implementation debited the deductions to the payable account,
     * giving debits of 22,300,000 against credits of 21,500,000. The ledger
     * service rejected it, the publish failed, and the month stayed in `computed`
     * — a payroll nobody could publish, discovered only by driving the real
     * operator journey. This assertion pins the correct arrangement.
     */
    private function postingSplitsGrossIntoNetAndWithheld(): void
    {
        $this->bootAccounted('pay_post_split');
        $user = $this->makeUser();
        $period = $this->makePeriod('draft');
        $employee = $this->makeEmployee('EMP-030');

        $payslip = $this->makePayslip($period, $employee, [
            'base_salary' => 20_000_000,
            'bonus' => 1_000_000,
            'deductions' => 400_000,
        ]);

        PayrollItem::create([
            'payslip_id' => $payslip->id, 'label' => 'حق مسکن',
            'direction' => 'earning', 'amount' => 900_000,
        ]);

        $calc = new PayrollCalculator();
        $calc->compute($payslip, $user);

        $period->forceFill(['state' => 'published', 'published_at' => now(), 'published_by' => $user->id])->save();

        $entry = app(PayrollAccountingService::class)->post($period->refresh(), $user);
        $entry->load('lines.account');

        $byAccount = [];
        foreach ($entry->lines as $line) {
            $code = $line->account->code;
            $byAccount[$code]['debit'] = ($byAccount[$code]['debit'] ?? 0) + (int) $line->debit;
            $byAccount[$code]['credit'] = ($byAccount[$code]['credit'] ?? 0) + (int) $line->credit;
        }

        $this->assertSame(21_900_000, $byAccount['5-2-01']['debit'] ?? 0, 'Gross wage is the expense.');
        $this->assertSame(21_500_000, $byAccount['2-1-03']['credit'] ?? 0, 'Net payable is what the employee receives.');
        $this->assertSame(400_000, $byAccount['2-1-05']['credit'] ?? 0, 'Withheld amounts are still a liability, so a CREDIT.');

        $debit = (int) $entry->lines->sum(fn ($l) => (int) $l->debit);
        $credit = (int) $entry->lines->sum(fn ($l) => (int) $l->credit);
        $this->assertSame($debit, $credit, 'The payroll entry must balance exactly.');
        $this->assertSame(21_900_000, $debit);
    }

    private function postingRefusesADraftMonth(): void
    {
        $this->bootAccounted('pay_post_draft');
        $user = $this->makeUser();
        $period = $this->makePeriod('draft');
        $employee = $this->makeEmployee('EMP-031');
        $this->makePayslip($period, $employee, ['base_salary' => 1_000_000]);

        $this->assertThrows(\RuntimeException::class, function () use ($period, $user) {
            app(PayrollAccountingService::class)->post($period, $user);
        }, 'A draft month must never reach the ledger.');
    }

    private function postingRefusesAnInconsistentPayslip(): void
    {
        $this->bootAccounted('pay_post_bad');
        $user = $this->makeUser();
        $period = $this->makePeriod('draft');
        $employee = $this->makeEmployee('EMP-032');

        // A row whose stored totals do not satisfy gross − deductions = net.
        // That is what an interrupted compute leaves behind, and posting it would
        // write an unbalanced entry.
        $this->makePayslip($period, $employee, [
            'base_salary' => 5_000_000,
            'total_earnings' => 5_000_000,
            'total_deductions' => 1_000_000,
            'net_payable' => 9_000_000,
        ]);

        $period->forceFill(['state' => 'published', 'published_at' => now(), 'published_by' => $user->id])->save();

        $this->assertThrows(\RuntimeException::class, function () use ($period, $user) {
            app(PayrollAccountingService::class)->post($period->refresh(), $user);
        }, 'Inconsistent payslip totals must be refused before they reach the ledger.');
    }

    /** THE FORMULA: base + bonus + overtime + positives − deductions − advance − negatives. */
    private function formulaIsFixedSalaryPlusManualLines(): void
    {
        $this->boot('pay_formula');
        $user = $this->makeUser();
        $period = $this->makePeriod();
        $employee = $this->makeEmployee('EMP-001');

        $payslip = $this->makePayslip($period, $employee, [
            'base_salary' => 20_000_000,
            'bonus' => 2_000_000,
            'overtime' => 1_500_000,
            'advance' => 3_000_000,
            'deductions' => 500_000,
        ]);

        PayrollItem::create(['payslip_id' => $payslip->id, 'label' => 'حق مسکن', 'amount' => 900_000]);
        PayrollItem::create(['payslip_id' => $payslip->id, 'label' => 'حق اولاد', 'amount' => 600_000]);
        PayrollItem::create(['payslip_id' => $payslip->id, 'label' => 'قسط وام', 'direction' => 'deduction', 'amount' => 1_000_000]);

        $calc = (new PayrollCalculator())->compute($payslip, $user);

        // earnings = 20M + 2M + 1.5M + 0.9M + 0.6M = 25M
        $this->assertSame(25_000_000, $calc->total_earnings,
            'Earnings must be base + bonus + overtime + positive manual items.');

        // deductions = 0.5M + 3M + 1M = 4.5M
        $this->assertSame(4_500_000, $calc->total_deductions,
            'Deductions must be deductions + advance + manual items marked as deduction.');

        $this->assertSame(20_500_000, $calc->net_payable,
            'Net = earnings − deductions.');
        $this->assertNotNull($calc->computed_at);
    }

    /**
     * A 1,000,000 allowance and a 1,000,000 loan instalment must have 2,000,000
     * of combined effect on the two totals, not cancel to zero. Summing one
     * signed column before splitting by direction is the classic bug that makes
     * a deduction vanish against an earning.
     */
    private function negativeItemReducesNetAndDoesNotCancel(): void
    {
        $this->boot('pay_sign');
        $user = $this->makeUser();
        $period = $this->makePeriod();
        $employee = $this->makeEmployee('EMP-002');

        $payslip = $this->makePayslip($period, $employee, ['base_salary' => 10_000_000]);

        PayrollItem::create(['payslip_id' => $payslip->id, 'label' => 'پاداش', 'direction' => 'earning', 'amount' => 1_000_000]);
        PayrollItem::create(['payslip_id' => $payslip->id, 'label' => 'کسورات', 'direction' => 'deduction', 'amount' => 1_000_000]);

        $calc = (new PayrollCalculator())->compute($payslip, $user);

        $this->assertSame(11_000_000, $calc->total_earnings);
        $this->assertSame(1_000_000, $calc->total_deductions,
            'A deduction line adds 1,000,000 to deductions, it does not subtract from earnings.');
        $this->assertSame(10_000_000, $calc->net_payable,
            'Net must be base 10M, with the bonus and deduction offsetting.');
    }

    /**
     * The anti-commission guard. `projects_total` exists for context; if it ever
     * leaks into the arithmetic, payroll would start paying a percentage the
     * client never approved.
     */
    private function projectsTotalNeverEntersTheFormula(): void
    {
        $this->boot('pay_no_commission');
        $user = $this->makeUser();
        $period = $this->makePeriod();
        $employee = $this->makeEmployee('EMP-003');

        $payslip = $this->makePayslip($period, $employee, [
            'base_salary' => 5_000_000,
            // A huge project total. If any commission formula creeps in, net
            // becomes something other than the base salary.
            'projects_total' => 900_000_000,
        ]);

        $calc = (new PayrollCalculator())->compute($payslip, $user);

        $this->assertSame(5_000_000, $calc->total_earnings,
            'projects_total must NEVER contribute to earnings (no invented commission).');
        $this->assertSame(5_000_000, $calc->net_payable);
        $this->assertSame(900_000_000, (int) $calc->projects_total,
            'The figure is still recorded, for context only.');
    }

    private function negativeNetIsRefused(): void
    {
        $this->boot('pay_negative');
        $user = $this->makeUser();
        $period = $this->makePeriod();
        $employee = $this->makeEmployee('EMP-004');

        $payslip = $this->makePayslip($period, $employee, [
            'base_salary' => 2_000_000,
            'advance' => 5_000_000,
        ]);

        $this->assertThrows(\RuntimeException::class, function () use ($payslip, $user) {
            (new PayrollCalculator())->compute($payslip, $user);
        }, 'A net below zero is a data-entry error and must be refused, not stored.');
    }

    private function publishedPeriodCannotBeRecomputed(): void
    {
        $this->boot('pay_published');
        $user = $this->makeUser();
        $period = $this->makePeriod('published');
        $employee = $this->makeEmployee('EMP-005');
        $payslip = $this->makePayslip($period, $employee, ['base_salary' => 3_000_000]);

        $this->assertThrows(\RuntimeException::class, function () use ($payslip, $user) {
            (new PayrollCalculator())->compute($payslip, $user);
        }, 'A published month must not be silently recomputed.');
    }

    private function amendWritesRestorableSnapshot(): void
    {
        $this->boot('pay_amend');
        $user = $this->makeUser();
        $period = $this->makePeriod('published');
        $employee = $this->makeEmployee('EMP-006');

        $payslip = $this->makePayslip($period, $employee, [
            'base_salary' => 8_000_000,
            'total_earnings' => 8_000_000,
            'net_payable' => 8_000_000,
        ]);

        $calc = new PayrollCalculator();
        $amended = $calc->amend($payslip, ['base_salary' => 9_000_000], 'اصلاح اشتباه ورودی', $user);

        $this->assertSame(9_000_000, (int) $amended->base_salary);

        $versions = PayslipVersion::query()->where('payslip_id', $payslip->id)->get();
        $this->assertCount(1, $versions, 'Amending must write exactly one snapshot.');

        $snapshot = $versions->first()->snapshot;
        $this->assertSame(8_000_000, (int) $snapshot['base_salary'],
            'The snapshot must hold the PRE-change value, so the original is reconstructible.');
        $this->assertSame('اصلاح اشتباه ورودی', $versions->first()->reason);
    }

    /** Building a period must never overwrite lines an accountant already typed. */
    private function buildPeriodSkipsExistingPayslips(): void
    {
        $this->boot('pay_build');
        $user = $this->makeUser();
        $period = $this->makePeriod();

        $this->makeEmployee('EMP-010', 'الف');
        $this->makeEmployee('EMP-011', 'ب');

        $calc = new PayrollCalculator();
        $first = $calc->buildPeriod($period, $user);
        $this->assertSame(2, $first, 'Both active employees get a payslip.');

        // Give one of them a manual line.
        $existing = Payslip::query()->where('employee_id', Employee::query()->where('code', 'EMP-010')->value('id'))->first();
        PayrollItem::create(['payslip_id' => $existing->id, 'label' => 'حق مسکن', 'amount' => 500_000]);

        $second = $calc->buildPeriod($period->refresh(), $user);
        $this->assertSame(0, $second, 'A rebuild must create nothing new.');

        $this->assertSame(1, PayrollItem::query()->where('payslip_id', $existing->id)->count(),
            'The manual line must survive a rebuild — losing typed input is worse than a missing row.');
    }

    /**
     * The column is unsigned in production, so a negative amount must never
     * reach it. The calculator refuses it with a message that tells the operator
     * what to do instead of letting a database error surface as a 500.
     */
    private function negativeStoredAmountIsRefused(): void
    {
        $this->boot('pay_neg_amount');
        $user = $this->makeUser();
        $period = $this->makePeriod();
        $employee = $this->makeEmployee('EMP-020');
        $payslip = $this->makePayslip($period, $employee, ['base_salary' => 4_000_000]);

        PayrollItem::create(['payslip_id' => $payslip->id, 'label' => 'قسط', 'amount' => 400_000]);
        // Force the bad value past the model, as a raw import or a legacy row would.
        \Illuminate\Support\Facades\DB::table('payroll_items')->update(['amount' => -400_000]);

        $this->assertThrows(\RuntimeException::class, function () use ($payslip, $user) {
            (new PayrollCalculator())->compute($payslip, $user);
        }, 'A negative stored amount must be refused, because the production column is unsigned.');
    }
}
