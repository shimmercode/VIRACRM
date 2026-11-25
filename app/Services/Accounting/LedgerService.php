<?php

namespace App\Services\Accounting;

use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * OD-41 — the only way a journal entry is ever written.
 *
 * WHY EVERY WRITE GOES THROUGH HERE
 * ---------------------------------
 * Double-entry is a set of invariants, not a UI feature. If any controller can
 * insert lines directly, then sooner or later one of them will insert three lines
 * that do not sum to zero, and every report above it becomes subtly wrong. This
 * class is the single gate that enforces:
 *
 *   INV-1  an entry has at least two lines
 *   INV-2  total debit == total credit, exactly, in integer rial
 *   INV-3  each line is debit XOR credit (never both, never neither)
 *   INV-4  every account posted to is postable and active
 *   INV-5  every line's amount is a positive integer
 *   INV-6  the entry date falls inside an OPEN fiscal period
 *   INV-7  one source document produces at most one live entry (idempotence)
 *
 * A posted entry is immutable. Correction is `reverse()`, which writes a mirror
 * entry and links both — never an update, never a delete. §19.2.
 */
final class LedgerService
{
    /**
     * Create and post an entry in one transaction.
     *
     * @param  array{
     *     entry_date: string,
     *     memo: string,
     *     source_type: string,
     *     source_id?: int|null,
     *     lines: list<array{
     *         account: string,
     *         debit?: int,
     *         credit?: int,
     *         description?: string,
     *         customer_id?: int|null,
     *         contract_id?: int|null,
     *         project_id?: int|null,
     *         employee_id?: int|null,
     *         department_id?: int|null,
     *         cost_center_id?: int|null,
     *         service_type?: string|null
     *     }>
     * }  $payload
     */
    public function post(array $payload, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($payload, $userId) {
            $this->assertSourceNotAlreadyPosted($payload['source_type'], $payload['source_id'] ?? null);

            $period = $this->resolveOpenPeriod($payload['entry_date']);

            $normalised = $this->validateLines($payload['lines']);

            $entry = JournalEntry::create([
                'number' => $this->nextNumber($period),
                'fiscal_period_id' => $period->id,
                'entry_date' => $payload['entry_date'],
                'memo' => $payload['memo'],
                'source_type' => $payload['source_type'],
                'source_id' => $payload['source_id'] ?? null,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $userId,
                'created_by' => $userId,
            ]);

            foreach ($normalised as $i => $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'] ?? null,
                    'customer_id' => $line['customer_id'] ?? null,
                    'contract_id' => $line['contract_id'] ?? null,
                    'project_id' => $line['project_id'] ?? null,
                    'employee_id' => $line['employee_id'] ?? null,
                    'department_id' => $line['department_id'] ?? null,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'service_type' => $line['service_type'] ?? null,
                    'line_no' => $i + 1,
                ]);
            }

            return $entry->refresh();
        });
    }

    /**
     * Reverse a posted entry by writing its mirror image.
     *
     * The original stays in the ledger with `status = reversed`; the mirror is a
     * new, separate entry. Both are visible in the general ledger, so an auditor
     * can see what happened and when — which is the entire point of not deleting.
     */
    public function reverse(JournalEntry $entry, string $reason, ?int $userId = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw new RuntimeException('فقط سند قطعی قابل برگشت است.');
        }

        return DB::transaction(function () use ($entry, $reason, $userId) {
            $period = $this->resolveOpenPeriod(now()->toDateString());

            $mirror = JournalEntry::create([
                'number' => $this->nextNumber($period),
                'fiscal_period_id' => $period->id,
                'entry_date' => now()->toDateString(),
                'memo' => 'برگشت سند ' . $entry->number . ' — ' . $reason,
                // A reversal is its own source so INV-7 (one entry per source)
                // does not block it, while remaining traceable to the original.
                'source_type' => 'reversal',
                'source_id' => $entry->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $userId,
                'reverses_entry_id' => $entry->id,
                'created_by' => $userId,
            ]);

            foreach ($entry->lines as $i => $line) {
                JournalLine::create([
                    'journal_entry_id' => $mirror->id,
                    'ledger_account_id' => $line->ledger_account_id,
                    // Swap: what was debited is now credited.
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => 'برگشت: ' . ($line->description ?? ''),
                    'customer_id' => $line->customer_id,
                    'contract_id' => $line->contract_id,
                    'project_id' => $line->project_id,
                    'employee_id' => $line->employee_id,
                    'department_id' => $line->department_id,
                    'cost_center_id' => $line->cost_center_id,
                    'service_type' => $line->service_type,
                    'line_no' => $i + 1,
                ]);
            }

            // Written with forceFill on the query builder path, because the model
            // forbids update() once posted. This one field is the sole permitted
            // mutation on a posted row.
            JournalEntry::query()->whereKey($entry->id)->update([
                'status' => 'reversed',
                'reversed_by_entry_id' => $mirror->id,
            ]);

            return $mirror->refresh();
        });
    }

    /** INV-1..INV-5. Returns lines with the account resolved. */
    private function validateLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new RuntimeException('هر سند باید حداقل دو خط داشته باشد.');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        $out = [];

        foreach ($lines as $index => $line) {
            $code = (string) ($line['account'] ?? '');

            $account = LedgerAccount::query()->where('code', $code)->first();

            if ($account === null) {
                throw new RuntimeException('حساب با کد «' . $code . '» یافت نشد.');
            }

            if (! $account->is_postable || ! $account->is_active) {
                throw new RuntimeException(
                    'حساب «' . $account->code . ' — ' . $account->title . '» حساب گروهی است و قابل ثبت نیست.'
                );
            }

            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new RuntimeException('مبلغ بدهکار یا بستانکار نمی‌تواند منفی باشد.');
            }

            if (($debit > 0) === ($credit > 0)) {
                throw new RuntimeException(
                    'خط ' . ($index + 1) . ': هر خط باید دقیقاً یکی از بدهکار یا بستانکار باشد.'
                );
            }

            if ($debit < 1 && $credit < 1) {
                throw new RuntimeException('خط ' . ($index + 1) . ': مبلغ نمی‌تواند صفر باشد.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $out[] = array_merge($line, [
                'ledger_account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        if ($totalDebit !== $totalCredit) {
            throw new RuntimeException(
                'سند تراز نیست: جمع بدهکار ' . $totalDebit . ' و جمع بستانکار ' . $totalCredit . ' ریال.'
            );
        }

        return $out;
    }

    /** INV-6. */
    private function resolveOpenPeriod(string $date): FiscalPeriod
    {
        $period = FiscalPeriod::forDate($date);

        if ($period === null) {
            throw new RuntimeException('برای تاریخ ' . $date . ' دوره مالی تعریف نشده است.');
        }

        if (! $period->isOpen()) {
            throw new RuntimeException('دوره مالی ' . $period->title . ' بسته است و ثبت سند در آن مجاز نیست.');
        }

        return $period;
    }

    /** INV-7. */
    private function assertSourceNotAlreadyPosted(string $sourceType, ?int $sourceId): void
    {
        if ($sourceId === null) {
            return;
        }

        $exists = JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'برای این منبع قبلاً سند ثبت شده است (منبع: ' . $sourceType . ' #' . $sourceId . ').'
            );
        }
    }

    /**
     * JE-1405-0001 style numbering, sequential within a period.
     *
     * Deliberately not a global auto-increment: an auditor expects the first
     * entry of each fiscal year to be 0001, and gaps in a year are evidence of
     * deletion.
     */
    private function nextNumber(FiscalPeriod $period): string
    {
        $count = JournalEntry::query()->where('fiscal_period_id', $period->id)->count();

        return sprintf('JE-%s-%04d', $period->code, $count + 1);
    }
}
