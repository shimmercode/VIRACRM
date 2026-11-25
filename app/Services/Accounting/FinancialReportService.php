<?php

namespace App\Services\Accounting;

use App\Models\FiscalPeriod;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * OD-41 §19.5 — trial balance, profit & loss, balance sheet.
 *
 * THE ONE PRINCIPLE
 * -----------------
 * Every figure here is a SUM over `journal_lines`, filtered by account type.
 * Nothing is computed from invoices, payments or any other operational table.
 * That is the whole reason the ledger exists: an invoice table can tell you what
 * was billed; only a ledger can tell you what the business earned, because it
 * also knows about credits, reversals and period boundaries.
 *
 * "NOT AVAILABLE" IS A REAL ANSWER
 * --------------------------------
 * The directive forbids showing a fake zero. A report over a period where no
 * entry exists returns `null` for the value and `false` for `has_data`, so the UI
 * can say «داده‌ای ثبت نشده» instead of «۰ ریال» — those mean completely
 * different things to a manager.
 */
final class FinancialReportService
{
    /**
     * Trial balance (تراز آزمایشی) for a period.
     *
     * Shows every POSTED account with its debit/credit totals. A non-zero
     * `difference` row is not a cosmetic warning: it means the ledger is broken,
     * and the UI must surface it rather than round it away.
     *
     * @return array{
     *     rows: list<array{code:string,title:string,type:string,debit:int,credit:int,balance:int}>,
     *     totals: array{debit:int,credit:int},
     *     balanced: bool,
     *     has_data: bool
     * }
     */
    public function trialBalance(?int $periodId = null): array
    {
        $query = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('e.status', 'posted');

        if ($periodId !== null) {
            $query->where('e.fiscal_period_id', $periodId);
        }

        $rows = $query
            ->groupBy('a.id', 'a.code', 'a.title', 'a.type', 'a.normal_balance')
            ->select([
                'a.code', 'a.title', 'a.type', 'a.normal_balance',
                DB::raw('COALESCE(SUM(l.debit),0) AS debit'),
                DB::raw('COALESCE(SUM(l.credit),0) AS credit'),
            ])
            ->orderBy('a.code')
            ->get();

        $out = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($rows as $row) {
            $debit = (int) $row->debit;
            $credit = (int) $row->credit;

            $totalDebit += $debit;
            $totalCredit += $credit;

            $out[] = [
                'code' => $row->code,
                'title' => $row->title,
                'type' => $row->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $row->normal_balance === 'debit' ? $debit - $credit : $credit - $debit,
            ];
        }

        return [
            'rows' => $out,
            'totals' => ['debit' => $totalDebit, 'credit' => $totalCredit],
            'balanced' => $totalDebit === $totalCredit,
            'has_data' => $out !== [],
        ];
    }

    /**
     * Profit & loss (سود و زیان) for a period.
     *
     * Revenue and expense only. `net` is revenue minus expense and is null when
     * there is nothing to subtract — a business with no revenue and no expense
     * has no profit figure, it has no data.
     *
     * @return array{
     *     revenue: list<array{code:string,title:string,amount:int}>,
     *     expense: list<array{code:string,title:string,amount:int}>,
     *     revenue_total: int,
     *     expense_total: int,
     *     net: int|null,
     *     has_data: bool
     * }
     */
    public function profitAndLoss(int $periodId): array
    {
        $rows = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('e.status', 'posted')
            ->where('e.fiscal_period_id', $periodId)
            ->whereIn('a.type', ['revenue', 'expense'])
            ->groupBy('a.id', 'a.code', 'a.title', 'a.type')
            ->select([
                'a.code', 'a.title', 'a.type',
                // Revenue is credit-normal, expense debit-normal: this expression
                // yields a positive "amount earned/spent" in both cases.
                DB::raw("CASE WHEN a.type = 'revenue' THEN COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) ELSE COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0) END AS amount"),
            ])
            ->orderBy('a.code')
            ->get();

        $revenue = [];
        $expense = [];

        foreach ($rows as $row) {
            $line = ['code' => $row->code, 'title' => $row->title, 'amount' => (int) $row->amount];

            if ($row->type === 'revenue') {
                $revenue[] = $line;
            } else {
                $expense[] = $line;
            }
        }

        $revenueTotal = array_sum(array_column($revenue, 'amount'));
        $expenseTotal = array_sum(array_column($expense, 'amount'));
        $hasData = $rows->isNotEmpty();

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'net' => $hasData ? $revenueTotal - $expenseTotal : null,
            'has_data' => $hasData,
        ];
    }

    /**
     * Balance sheet (ترازنامه) at a point in time.
     *
     * Assets = liabilities + equity must hold if the ledger is balanced. The
     * check is returned rather than asserted, so an imbalance shows up on screen
     * as a warning instead of an exception that hides the rest of the report.
     *
     * @return array{assets:int,liabilities:int,equity:int,balanced:bool,has_data:bool}
     */
    public function balanceSheet(?string $asOf = null): array
    {
        $query = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('e.status', 'posted')
            ->whereIn('a.type', ['asset', 'liability', 'equity', 'revenue', 'expense']);

        if ($asOf !== null) {
            $query->whereDate('e.entry_date', '<=', $asOf);
        }

        $row = $query->select([
            DB::raw("COALESCE(SUM(CASE WHEN a.type = 'asset' THEN l.debit - l.credit END),0) AS assets"),
            DB::raw("COALESCE(SUM(CASE WHEN a.type = 'liability' THEN l.credit - l.debit END),0) AS liabilities"),
            DB::raw("COALESCE(SUM(CASE WHEN a.type = 'equity' THEN l.credit - l.debit END),0) AS equity"),
            // Revenue/expense are NOT balance-sheet accounts, but their net must
            // be carried into equity as current-period earnings. Without this the
            // sheet cannot balance on any date inside an open year: assets rise
            // when an invoice is issued while equity stays at zero until the year
            // is closed. That is not a rounding problem — it is a sign error that
            // makes a correct ledger look broken.
            DB::raw("COALESCE(SUM(CASE WHEN a.type = 'revenue' THEN l.credit - l.debit END),0) AS revenue"),
            DB::raw("COALESCE(SUM(CASE WHEN a.type = 'expense' THEN l.debit - l.credit END),0) AS expense"),
            DB::raw('COUNT(*) AS n'),
        ])->first();

        $assets = (int) $row->assets;
        $liabilities = (int) $row->liabilities;
        $bookEquity = (int) $row->equity;
        // Net profit (or loss) for the period up to `asOf`, rolled into equity.
        $netProfit = (int) $row->revenue - (int) $row->expense;
        $equity = $bookEquity + $netProfit;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'book_equity' => $bookEquity,
            'net_profit' => $netProfit,
            'balanced' => $assets === $liabilities + $equity,
            'has_data' => (int) $row->n > 0,
        ];
    }

    /**
     * Revenue grouped by an analytic dimension (§19.3).
     *
     * This is what makes "revenue per customer" possible without a separate
     * account per customer. Dimensions are read off the journal line, so a single
     * service can answer per-customer, per-project and per-service questions
     * without touching the chart of accounts.
     *
     * @param  'customer'|'project'|'service'|'department'  $dimension
     * @return list<array{key:int|string,label:string,amount:int}>
     */
    public function revenueBy(string $dimension, ?int $periodId = null): array
    {
        $column = match ($dimension) {
            'customer' => 'l.customer_id',
            'project' => 'l.project_id',
            'department' => 'l.department_id',
            'service' => 'l.service_type',
            default => throw new \InvalidArgumentException('بُعد ناشناخته: ' . $dimension),
        };

        $query = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('e.status', 'posted')
            ->where('a.type', 'revenue')
            ->whereNotNull($column);

        if ($periodId !== null) {
            $query->where('e.fiscal_period_id', $periodId);
        }

        $rows = $query
            ->groupBy($column)
            ->select([
                $column . ' AS dim',
                DB::raw('COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) AS amount'),
            ])
            ->orderByDesc('amount')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Resolve labels in one query per dimension rather than N+1.
        $ids = $rows->pluck('dim')->filter()->all();
        $labels = $this->labelsFor($dimension, $ids);

        return $rows->map(fn ($r) => [
            'key' => $r->dim,
            'label' => $labels[$r->dim] ?? ('#' . $r->dim),
            'amount' => (int) $r->amount,
        ])->all();
    }

    /** @return array<int|string,string> */
    private function labelsFor(string $dimension, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return match ($dimension) {
            'customer' => DB::table('customers')
                ->whereIn('id', $ids)
                ->selectRaw('id, COALESCE(company_name, TRIM(COALESCE(first_name,\'\') || \' \' || COALESCE(last_name,\'\'))) AS label')
                ->pluck('label', 'id')->all(),
            'project' => DB::table('projects')->whereIn('id', $ids)->pluck('title', 'id')->all(),
            'department' => DB::table('departments')->whereIn('id', $ids)->pluck('title', 'id')->all(),
            'service' => array_combine(
                $ids,
                array_map(fn ($s) => \App\Support\ServiceType::label((string) $s), $ids),
            ),
            default => [],
        };
    }

    /** Convenience: the period currently containing today. */
    public function currentPeriod(): ?FiscalPeriod
    {
        return FiscalPeriod::forDate(now()->toDateString());
    }
}
