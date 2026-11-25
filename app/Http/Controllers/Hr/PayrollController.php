<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRule;
use App\Models\Payslip;
use App\Models\PayslipVersion;
use App\Models\Project;
use App\Services\Accounting\PayrollAccountingService;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Security\AuditRecorder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * OD-16 — payroll, from an empty month to a posted ledger entry.
 *
 * THE FOUR ACTS
 * -------------
 *   1. definePeriod()   create the month (once; unique on year+month)
 *   2. build()          create a payslip row per active employee
 *   3. editPayslip()    an accountant types base + manual lines, then compute
 *   4. publish()        freeze the month and post it to the ledger
 *
 * ORDER MATTERS AND IS ENFORCED IN THE DOMAIN, NOT THE VIEW
 * ---------------------------------------------------------
 * You cannot publish an empty month; you cannot edit a published one; you cannot
 * post the same month twice. Each of those is a service-level refusal so a direct
 * POST cannot bypass a disabled button.
 *
 * WHY THERE IS NO "COMMISSION" SCREEN
 * -----------------------------------
 * The client's decision (OD-16) is fixed salary plus manually recorded items.
 * `projects_total` is shown for context and is never multiplied by anything.
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly PayrollAccountingService $accounting,
    ) {
    }

    /** List of payroll months, newest first, with their totals. */
    public function index(Request $request): View
    {
        $periods = PayrollPeriod::query()
            ->withCount('payslips')
            ->orderByDesc('jalali_year')
            ->orderByDesc('jalali_month')
            ->get();

        // Totals per period in one query rather than N+1 inside the view.
        $totals = Payslip::query()
            ->selectRaw('payroll_period_id, SUM(net_payable) as net, SUM(total_earnings) as gross')
            ->groupBy('payroll_period_id')
            ->get()
            ->keyBy('payroll_period_id');

        return view('payroll.index', [
            'periods' => $periods,
            'totals' => $totals,
            'canManage' => $request->user()->hasPermission('payroll.manage'),
            'rules' => PayrollRule::query()->orderBy('code')->get(),
        ]);
    }

    /** Create a month. Re-requesting the same month returns the existing one. */
    public function storePeriod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jalali_year' => ['required', 'integer', 'min:1390', 'max:1500'],
            'jalali_month' => ['required', 'integer', 'min:1', 'max:12'],
        ], [
            'jalali_year.required' => 'سال را وارد کنید.',
            'jalali_year.min' => 'سال وارد‌شده معتبر نیست.',
            'jalali_month.required' => 'ماه را انتخاب کنید.',
            'jalali_month.min' => 'ماه معتبر نیست.',
            'jalali_month.max' => 'ماه معتبر نیست.',
        ]);

        $existing = PayrollPeriod::query()
            ->where('jalali_year', $data['jalali_year'])
            ->where('jalali_month', $data['jalali_month'])
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('payroll.show', $existing)
                ->with('error', 'دورهٔ ' . $existing->title() . ' از قبل وجود دارد.');
        }

        $period = PayrollPeriod::create([
            'jalali_year' => $data['jalali_year'],
            'jalali_month' => $data['jalali_month'],
            'state' => 'draft',
            'payroll_rule_id' => PayrollRule::query()->where('is_active', true)->value('id'),
        ]);

        AuditRecorder::record('payroll.period_created', $period, 'ایجاد دورهٔ حقوق ' . $period->title());

        return redirect()
            ->route('payroll.show', $period)
            ->with('success', 'دورهٔ ' . $period->title() . ' ایجاد شد.');
    }

    /** One month: its payslips, totals, and the actions legal in its state. */
    public function show(Request $request, PayrollPeriod $period): View
    {
        $payslips = Payslip::query()
            ->with(['employee:id,code,first_name,last_name,status', 'items.project:id,title,code'])
            ->where('payroll_period_id', $period->id)
            // Ordered by employee name through a correlated subquery, which both
            // SQLite and MariaDB accept; `orderBy(Employee::select(...))` is not
            // valid Eloquent and silently ignores the subquery.
            ->orderByRaw('(select last_name from employees where employees.id = payslips.employee_id)')
            ->get();

        $entry = JournalEntry::query()
            ->where('source_type', 'payroll')
            ->where('source_id', $period->id)
            ->with('reversedBy')
            ->first();

        return view('payroll.show', [
            'period' => $period,
            'payslips' => $payslips,
            'entry' => $entry,
            'gross' => (int) $payslips->sum('total_earnings'),
            'deductions' => (int) $payslips->sum('total_deductions'),
            'net' => (int) $payslips->sum('net_payable'),
            'canManage' => $request->user()->hasPermission('payroll.manage'),
            'canApprove' => $request->user()->hasPermission('payroll.approve'),
            'projects' => Project::query()->select('id', 'title', 'code')->orderBy('title')->limit(300)->get(),
        ]);
    }

    /**
     * Create payslip rows for every active employee.
     *
     * Idempotent by design: existing rows are untouched, so an accountant's
     * typed lines survive a re-run. See PayrollCalculator::buildPeriod().
     */
    public function build(Request $request, PayrollPeriod $period): RedirectResponse
    {
        try {
            $created = $this->calculator->buildPeriod($period, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditRecorder::record(
            'payroll.built',
            $period,
            'ایجاد فیش‌های دورهٔ ' . $period->title() . ' — ' . $created . ' فیش جدید'
        );

        return back()->with(
            $created > 0 ? 'success' : 'error',
            $created > 0
                ? 'برای ' . Money::toPersianDigits((string) $created) . ' کارمند فیش حقوقی ایجاد شد.'
                : 'همهٔ کارمندان فعال از قبل فیش دارند؛ فیش تازه‌ای ایجاد نشد.'
        );
    }

    /** One payslip: edit form, manual lines, and the version trail. */
    public function payslip(Request $request, Payslip $payslip): View
    {
        $payslip->load([
            'employee', 'period', 'items.project:id,title,code',
            'versions' => fn ($q) => $q->with('creator:id,name')->orderByDesc('version'),
        ]);

        return view('payroll.payslip', [
            'payslip' => $payslip,
            'projects' => Project::query()->select('id', 'title', 'code')->orderBy('title')->limit(300)->get(),
            'canManage' => $request->user()->hasPermission('payroll.manage'),
            'isLocked' => $payslip->period?->isPublished() ?? false,
        ]);
    }

    /**
     * Save inputs + manual lines and recompute.
     *
     * A published payslip may only be changed through amend(), which snapshots
     * first and demands a reason — that is what `?amend=1` selects.
     */
    public function updatePayslip(Request $request, Payslip $payslip): RedirectResponse
    {
        $period = $payslip->period;

        if ($period === null) {
            return back()->with('error', 'دورهٔ حقوق این فیش یافت نشد.');
        }

        $isAmend = $request->boolean('amend');

        $data = $request->validate([
            'base_salary' => ['required', 'string', 'max:32'],
            'worked_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'bonus' => ['nullable', 'string', 'max:32'],
            'overtime' => ['nullable', 'string', 'max:32'],
            'advance' => ['nullable', 'string', 'max:32'],
            'deductions' => ['nullable', 'string', 'max:32'],
            'input_notes' => ['nullable', 'string', 'max:2000'],
            'amend_reason' => [$isAmend ? 'required' : 'nullable', 'string', 'max:500'],

            'items' => ['nullable', 'array', 'max:50'],
            'items.*.label' => ['nullable', 'string', 'max:190'],
            'items.*.direction' => ['nullable', 'in:earning,deduction'],
            'items.*.amount' => ['nullable', 'string', 'max:32'],
            'items.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'items.*.coefficient' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], [
            'base_salary.required' => 'حقوق پایه را وارد کنید.',
            'amend_reason.required' => 'برای اصلاح فیش منتشرشده، دلیل اصلاح الزامی است.',
            'items.max' => 'حداکثر ۵۰ قلم برای هر فیش مجاز است.',
            'items.*.direction.in' => 'جهت قلم باید دریافتی یا کسورات باشد.',
        ]);

        if ($period->isPublished() && ! $isAmend) {
            return back()->with('error', 'این دوره منتشر شده است. برای اصلاح، دلیل اصلاح را وارد کنید.');
        }

        $money = function (?string $raw, string $field) use ($request): int {
            if ($raw === null || trim($raw) === '') {
                return 0;
            }

            $parsed = Money::parse($raw);

            if ($parsed === null) {
                throw new RuntimeException('مبلغ فیلد «' . $field . '» معتبر نیست. فقط رقم به ریال وارد کنید.');
            }

            return (int) $parsed->amount;
        };

        try {
            $changes = [
                'base_salary' => $money($data['base_salary'], 'حقوق پایه'),
                'bonus' => $money($data['bonus'] ?? null, 'پاداش'),
                'overtime' => $money($data['overtime'] ?? null, 'اضافه‌کاری'),
                'advance' => $money($data['advance'] ?? null, 'مساعده'),
                'deductions' => $money($data['deductions'] ?? null, 'کسورات'),
                'worked_days' => $data['worked_days'] ?? null,
                'input_notes' => $data['input_notes'] ?? null,
            ];
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $cleanItems = [];
        foreach ((array) ($data['items'] ?? []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amountRaw = trim((string) ($row['amount'] ?? ''));

            // An entirely blank row is the natural result of leaving one of the
            // dynamic inputs empty. Skipping it is correct; treating it as a
            // zero-rial line would fill the payslip with meaningless rows.
            if ($label === '' && $amountRaw === '') {
                continue;
            }

            if ($label === '') {
                return back()->withInput()->with('error', 'هر قلم حقوق باید عنوان داشته باشد.');
            }

            $amount = Money::parse($amountRaw);

            if ($amount === null || $amount->isNegative() || (int) $amount->amount === 0) {
                return back()->withInput()->with('error', 'مبلغ قلم «' . $label . '» باید عددی بزرگ‌تر از صفر باشد.');
            }

            $cleanItems[] = [
                'label' => $label,
                'direction' => ($row['direction'] ?? 'earning') === 'deduction' ? 'deduction' : 'earning',
                'amount' => (int) $amount->amount,
                'project_id' => ! empty($row['project_id']) ? (int) $row['project_id'] : null,
                'coefficient' => (int) ($row['coefficient'] ?? 100),
            ];
        }

        try {
            DB::transaction(function () use ($payslip, $data, $changes, $cleanItems, $isAmend, $request): void {
                if ($isAmend) {
                    $this->calculator->amend($payslip, $changes, (string) $data['amend_reason'], $request->user());
                } else {
                    $payslip->fill($changes)->save();

                    // Replace the manual lines wholesale: the form is the full
                    // list, so removed rows must actually disappear. Merging
                    // would resurrect a deleted deduction and quietly pay less.
                    PayrollItem::query()->where('payslip_id', $payslip->id)->delete();

                    foreach ($cleanItems as $item) {
                        PayrollItem::create($item + ['payslip_id' => $payslip->id]);
                    }

                    $this->calculator->compute($payslip->refresh(), $request->user());
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditRecorder::record(
            $isAmend ? 'payroll.amended' : 'payroll.updated',
            $payslip,
            'ثبت/ویرایش فیش حقوقی ' . $payslip->employee?->displayName() . ' — ' . $period->title(),
            ['net_payable' => [null, (int) $payslip->refresh()->net_payable]]
        );

        return redirect()
            ->route('payroll.show', $period)
            ->with('success', $isAmend
                ? 'فیش اصلاح شد و نسخهٔ قبلی در تاریخچه نگه داشته شد.'
                : 'فیش حقوقی محاسبه و ذخیره شد.');
    }

    /** Add or remove one manual line without leaving the page. */
    public function storeItem(Request $request, Payslip $payslip): RedirectResponse
    {
        $period = $payslip->period;

        if ($period !== null && $period->isPublished()) {
            return back()->with('error', 'این دوره منتشر شده است؛ قلم جدید افزوده نمی‌شود.');
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:190'],
            'direction' => ['required', 'in:earning,deduction'],
            'amount' => ['required', 'string', 'max:32'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'coefficient' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], [
            'label.required' => 'عنوان قلم را وارد کنید.',
            'amount.required' => 'مبلغ قلم را وارد کنید.',
        ]);

        $amount = Money::parse($data['amount']);

        if ($amount === null || $amount->isNegative() || (int) $amount->amount === 0) {
            return back()->with('error', 'مبلغ قلم باید عددی بزرگ‌تر از صفر باشد.');
        }

        PayrollItem::create([
            'payslip_id' => $payslip->id,
            'label' => $data['label'],
            'direction' => $data['direction'],
            'amount' => (int) $amount->amount,
            'project_id' => ! empty($data['project_id']) ? (int) $data['project_id'] : null,
            'coefficient' => (int) ($data['coefficient'] ?? 100),
        ]);

        try {
            $this->calculator->compute($payslip->refresh(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditRecorder::record('payroll.item_added', $payslip, 'افزودن قلم حقوق «' . $data['label'] . '»');

        return back()->with('success', 'قلم حقوق افزوده و فیش دوباره محاسبه شد.');
    }

    public function destroyItem(Request $request, PayrollItem $item): RedirectResponse
    {
        $payslip = $item->payslip;

        if ($payslip?->period !== null && $payslip->period->isPublished()) {
            return back()->with('error', 'این دوره منتشر شده است؛ اقلام آن قابل حذف نیست.');
        }

        $label = (string) $item->label;
        $item->delete();

        if ($payslip !== null) {
            try {
                $this->calculator->compute($payslip->refresh(), $request->user());
            } catch (RuntimeException) {
                // Removing a deduction can push net below base, never below zero,
                // so this is defensive only — the line is already gone.
            }
        }

        AuditRecorder::record('payroll.item_removed', $payslip, 'حذف قلم حقوق «' . $label . '»');

        return back()->with('success', 'قلم حقوق حذف و فیش دوباره محاسبه شد.');
    }

    /** Recompute every payslip of a draft month. */
    public function compute(Request $request, PayrollPeriod $period): RedirectResponse
    {
        try {
            $totals = $this->calculator->computePeriod($period, $request->user());

            if ($period->state === 'draft') {
                $period->forceFill(['state' => 'computed'])->save();
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditRecorder::record('payroll.computed', $period, 'محاسبهٔ مجدد دورهٔ ' . $period->title());

        return back()->with('success',
            'محاسبه انجام شد: ' . Money::toPersianDigits((string) $totals['count']) . ' فیش، '
            . 'خالص مجموع ' . Money::fromRial((int) $totals['total_net'])->formatPersian() . '.'
        );
    }

    /**
     * Publish: freeze the month, then post it to the ledger.
     *
     * The ledger post is inside the same transaction as the state change. A
     * published payroll that never reached the books would understate wage
     * expense for as long as nobody checked.
     */
    public function publish(Request $request, PayrollPeriod $period): RedirectResponse
    {
        if (! $request->user()->hasPermission('payroll.approve')) {
            abort(403);
        }

        if ($period->isPublished()) {
            return back()->with('error', 'این دوره از قبل منتشر شده است.');
        }

        $payslips = Payslip::query()->where('payroll_period_id', $period->id)->get();

        if ($payslips->isEmpty()) {
            return back()->with('error', 'دورهٔ خالی منتشر نمی‌شود. ابتدا فیش‌ها را ایجاد کنید.');
        }

        $uncomputed = $payslips->filter(fn (Payslip $p) => $p->computed_at === null);

        if ($uncomputed->isNotEmpty()) {
            return back()->with('error',
                'برای ' . Money::toPersianDigits((string) $uncomputed->count())
                . ' فیش هنوز محاسبه انجام نشده است. ابتدا «محاسبهٔ مجدد» را اجرا کنید.'
            );
        }

        try {
            DB::transaction(function () use ($period, $request): void {
                // ORDER IS LOAD-BEARING.
                //
                // The accounting service refuses to post a month that is not
                // published — that guard is what stops a draft from reaching the
                // books. So the state must flip FIRST, inside this transaction,
                // and the ledger entry second. Doing it the other way round made
                // every publish fail with «فقط دورهٔ حقوق منتشرشده…» while the
                // period stayed `computed`, which is exactly how the flow harness
                // caught it.
                //
                // Both writes are in one transaction: if the ledger post throws,
                // the state change rolls back and the month stays editable rather
                // than becoming a published promise that never reached the books.
                $period->forceFill([
                    'state' => 'published',
                    'published_at' => now(),
                    'published_by' => $request->user()->id,
                ])->save();

                $this->accounting->post($period, $request->user());
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditRecorder::record('payroll.published', $period, 'انتشار حقوق ' . $period->title());

        return back()->with('success', 'دورهٔ حقوق منتشر و سند آن در دفتر کل ثبت شد.');
    }
}
