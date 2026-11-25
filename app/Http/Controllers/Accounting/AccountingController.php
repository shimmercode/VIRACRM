<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\LedgerService;
use App\Services\Security\AuditRecorder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * OD-41 §19.5 — the accounting module's read surfaces plus period control.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * -----------------------------
 * There is no "create manual journal entry" screen in v1. §19.2 says an entry
 * without a valid source must not be recorded, and a free-form entry form is
 * exactly that. Entries are created by the services that own the business event
 * (invoice issue, payment, payroll, expense). Manual adjustments go through a
 * dedicated, permission-gated flow in a later slice — not through a generic form
 * that anyone with `.view` could reach.
 *
 * Period closing and reopening are separate permissions because §19.2 treats them
 * as separate acts: closing locks the books, reopening rewrites history.
 */
class AccountingController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reports,
    ) {
    }

    /** Chart of accounts, as a tree. */
    public function chart(): View
    {
        $accounts = LedgerAccount::query()->orderBy('code')->get();

        // Group by root so the view can render five collapsible sections without
        // recursing over arbitrary depth in Blade.
        $tree = $accounts->groupBy(fn (LedgerAccount $a) => explode('-', $a->code)[0]);

        return view('accounting.chart', [
            'tree' => $tree,
            'types' => LedgerAccount::TYPES,
            'postableCount' => $accounts->where('is_postable', true)->count(),
        ]);
    }

    /** Trial balance (§19.5). */
    public function trialBalance(Request $request): View
    {
        $periods = FiscalPeriod::query()->orderByDesc('code')->get();

        // Default to the period that CONTAINS TODAY. Picking "the first open
        // period by descending code" lands on next year's period, which is open
        // and empty — so the report renders its honest "no data" state while
        // this year's postings sit in a period nobody selected. That reads as
        // "the accounting is broken" when the default was simply wrong.
        $periodId = $request->query('period') !== null
            ? (int) $request->query('period')
            : $this->reports->currentPeriod()?->id;

        $report = $this->reports->trialBalance($periodId);

        return view('accounting.trial-balance', [
            'report' => $report,
            'periods' => $periods,
            'periodId' => $periodId,
            'selectedPeriod' => $periods->firstWhere('id', $periodId),
        ]);
    }

    /** Profit & loss (§19.5). */
    public function profitLoss(Request $request): View
    {
        $periods = FiscalPeriod::query()->orderByDesc('code')->get();
        $periodId = $request->query('period') !== null
            ? (int) $request->query('period')
            : $this->reports->currentPeriod()?->id;

        $report = $periodId !== null
            ? $this->reports->profitAndLoss($periodId)
            : ['revenue' => [], 'expense' => [], 'revenue_total' => 0,
               'expense_total' => 0, 'net' => null, 'has_data' => false];

        return view('accounting.profit-loss', [
            'report' => $report,
            'periods' => $periods,
            'periodId' => $periodId,
            'byService' => $periodId !== null ? $this->reports->revenueBy('service', $periodId) : [],
            'byCustomer' => $periodId !== null ? $this->reports->revenueBy('customer', $periodId) : [],
        ]);
    }

    /** Balance sheet (§19.5). */
    public function balanceSheet(Request $request): View
    {
        $asOf = $request->query('as_of');
        $sheet = $this->reports->balanceSheet($asOf !== null && $asOf !== '' ? (string) $asOf : null);

        return view('accounting.balance-sheet', [
            'sheet' => $sheet,
            'asOf' => $asOf,
        ]);
    }

    /** Journal entries list. */
    public function journal(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'source' => (string) $request->query('source', ''),
        ];

        $query = JournalEntry::query()
            ->with(['fiscalPeriod:id,code,title', 'postedBy:id,name'])
            ->withSum('lines as total_debit_sum', 'debit');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['source'] !== '') {
            $query->where('source_type', $filters['source']);
        }

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('memo', 'like', $term));
        }

        return view('accounting.journal', [
            'entries' => $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'sources' => JournalEntry::SOURCES,
            'statuses' => JournalEntry::STATUSES,
        ]);
    }

    /** One entry with its lines and the balance proof. */
    public function journalShow(JournalEntry $entry): View
    {
        $entry->load([
            'lines.account', 'lines.customer', 'lines.project',
            'fiscalPeriod', 'postedBy', 'reversedBy',
        ]);

        return view('accounting.journal-show', ['entry' => $entry]);
    }

    /** Fiscal period list + close/reopen controls. */
    public function periods(): View
    {
        $periods = FiscalPeriod::query()
            ->withCount('entries')
            ->orderByDesc('code')
            ->get();

        return view('accounting.periods', [
            'periods' => $periods,
            'current' => $this->reports->currentPeriod(),
        ]);
    }

    public function closePeriod(Request $request, FiscalPeriod $period): RedirectResponse
    {
        if (! $period->isOpen()) {
            return back()->with('error', 'این دوره از قبل بسته است.');
        }

        $period->forceFill([
            'state' => 'closed',
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
        ])->save();

        AuditRecorder::record(
            'period.closed',
            $period,
            'بستن دوره مالی ' . $period->title,
            ['state' => ['open', 'closed']],
        );

        return back()->with('success', 'دوره مالی بسته شد. ثبت سند جدید در آن مجاز نیست.');
    }

    /**
     * Reopen a closed period.
     *
     * A separate, sensitive permission from closing, with its own audit wording:
     * reopening lets figures that were already reported change, which is the one
     * act an accountant may need to explain a year later.
     */
    public function reopenPeriod(Request $request, FiscalPeriod $period): RedirectResponse
    {
        if ($period->isOpen()) {
            return back()->with('error', 'این دوره باز است.');
        }

        $period->forceFill(['state' => 'open', 'closed_at' => null, 'closed_by' => null])->save();

        AuditRecorder::record(
            'period.reopened',
            $period,
            'بازکردن دوره مالی بسته‌شده ' . $period->title,
            ['state' => ['closed', 'open']],
        );

        return back()->with('success', 'دوره مالی باز شد.');
    }

    /** Void an issued invoice, reversing its revenue entry. */
    public function voidInvoice(Request $request, \App\Models\Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            app(\App\Services\Accounting\InvoiceAccountingService::class)
                ->void($invoice, $data['void_reason'], $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditRecorder::record(
            'invoice.voided', $invoice,
            'ابطال فاکتور ' . $invoice->displayName(),
            ['status' => ['issued', 'void']],
        );

        return back()->with('success', 'فاکتور ابطال شد و سند برگشتی ثبت گردید.');
    }

    /** Payments list — receipts and disbursements. */
    public function payments(Request $request): View
    {
        $direction = (string) $request->query('direction', '');

        $query = Payment::query()->with(['customer:id,company_name,first_name,last_name', 'invoice:id,number,total']);

        if ($direction !== '') {
            $query->where('direction', $direction);
        }

        return view('accounting.payments', [
            'payments' => $query->orderByDesc('paid_on')->orderByDesc('id')->paginate(25)->withQueryString(),
            'direction' => $direction,
            'methods' => Payment::METHODS,
            'directions' => Payment::DIRECTIONS,
            // Totals cover the whole filtered set, not the visible page.
            'totalIn' => (int) Payment::query()->where('direction', 'in')->sum('amount'),
            'totalOut' => (int) Payment::query()->where('direction', 'out')->sum('amount'),
        ]);
    }
}
