<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\ServiceType;
use Database\Seeders\ChartOfAccountsSeeder as ChartOfAccounts;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * OD-41 §19.4 / §19.6 / §19.7 — the bridge between documents and the ledger.
 *
 * THE POSTINGS
 * ------------
 * Issuing an invoice (goods/services delivered, money not yet received):
 *
 *     Dr  1-1-02  حساب‌های دریافتنی مشتریان      total
 *     Cr  4-1-0x  درآمد ...                      subtotal − discount
 *     Cr  2-1-02  مالیات بر ارزش افزوده فروش      tax_amount
 *
 * Receiving a payment (money arrived):
 *
 *     Dr  1-1-01  بانک و صندوق                    amount
 *     Cr  1-1-02  حساب‌های دریافتنی مشتریان       amount
 *
 * These two are deliberately different entries at different times. A manager
 * asking "how much revenue did we earn" and a manager asking "how much cash came
 * in" must get different answers whenever a customer has not paid yet, and this
 * is the only structure where that is true by construction.
 *
 * DISCOUNT is posted as a reduction of revenue, not as an expense. Treating it as
 * an expense would inflate both gross revenue and costs and understate the margin.
 */
final class InvoiceAccountingService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /**
     * Issue an invoice: assign the controlled number, post the revenue entry.
     *
     * Idempotent by virtue of the ledger's (source_type, source_id) unique key: a
     * second call throws instead of double-booking revenue.
     */
    public function issue(Invoice $invoice, int $userId): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException('فقط فاکتور پیش‌نویس قابل صدور است.');
        }

        return DB::transaction(function () use ($invoice, $userId) {
            $invoice->loadMissing('items');

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('فاکتور بدون ردیف قابل صدور نیست.');
            }

            $invoice->recalculate();

            if ($invoice->total <= 0) {
                throw new RuntimeException('مبلغ فاکتور باید بزرگ‌تر از صفر باشد.');
            }

            $revenueAccount = ServiceType::revenueAccount($invoice->service_type);
            $netRevenue = $invoice->subtotal - $invoice->discount;

            $lines = [
                [
                    'account' => ChartOfAccounts::RECEIVABLE,
                    'debit' => $invoice->total,
                    'description' => 'فاکتور ' . ($invoice->number ?? '#' . $invoice->id),
                    'customer_id' => $invoice->customer_id,
                    'contract_id' => $invoice->contract_id,
                    'project_id' => $invoice->project_id,
                    'service_type' => $invoice->service_type,
                ],
                [
                    'account' => $revenueAccount,
                    'credit' => $netRevenue,
                    'description' => 'درآمد ' . ServiceType::label($invoice->service_type),
                    'customer_id' => $invoice->customer_id,
                    'contract_id' => $invoice->contract_id,
                    'project_id' => $invoice->project_id,
                    'service_type' => $invoice->service_type,
                ],
            ];

            // A zero-tax invoice must not produce a zero-amount line: INV-5 rejects
            // it, and rightly so — a zero line carries no information.
            if ($invoice->tax_amount > 0) {
                $lines[] = [
                    'account' => ChartOfAccounts::VAT_SALES,
                    'credit' => $invoice->tax_amount,
                    'description' => 'مالیات بر ارزش افزوده فاکتور',
                    'customer_id' => $invoice->customer_id,
                ];
            }

            $entry = $this->ledger->post([
                'entry_date' => $invoice->issue_date->toDateString(),
                'memo' => 'صدور فاکتور ' . ($invoice->title ?? ServiceType::label($invoice->service_type)),
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'lines' => $lines,
            ], $userId);

            $invoice->forceFill([
                'number' => $this->nextNumber($invoice),
                'status' => 'issued',
                'issued_by' => $userId,
                'journal_entry_id' => $entry->id,
            ])->save();

            return $invoice->refresh();
        });
    }

    /**
     * Record a receipt against one or more invoices.
     *
     * @param  array<int,int>  $allocations  invoice_id => amount
     */
    public function recordPayment(
        int $customerId,
        int $amount,
        string $paidOn,
        array $allocations,
        string $method,
        ?string $reference,
        ?string $bankAccount,
        ?string $notes,
        int $userId,
    ): Payment {
        if ($amount <= 0) {
            throw new RuntimeException('مبلغ پرداخت باید بزرگ‌تر از صفر باشد.');
        }

        $allocatedTotal = array_sum($allocations);

        if ($allocatedTotal > $amount) {
            throw new RuntimeException('جمع تخصیص از مبلغ پرداخت بیشتر است.');
        }

        return DB::transaction(function () use (
            $customerId, $amount, $paidOn, $allocations, $method,
            $reference, $bankAccount, $notes, $userId
        ) {
            $payment = Payment::create([
                'number' => $this->nextPaymentNumber(),
                'direction' => 'in',
                'customer_id' => $customerId,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'bank_account' => $bankAccount,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $entry = $this->ledger->post([
                'entry_date' => $paidOn,
                'memo' => 'دریافت وجه — ' . Payment::METHODS[$method] ?? $method,
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'lines' => [
                    [
                        'account' => ChartOfAccounts::CASH,
                        'debit' => $amount,
                        'description' => 'دریافت از مشتری',
                        'customer_id' => $customerId,
                    ],
                    [
                        'account' => ChartOfAccounts::RECEIVABLE,
                        'credit' => $amount,
                        'description' => 'تسویه مطالبات',
                        'customer_id' => $customerId,
                    ],
                ],
            ], $userId);

            $payment->forceFill(['journal_entry_id' => $entry->id])->save();

            foreach ($allocations as $invoiceId => $allocated) {
                if ($allocated <= 0) {
                    continue;
                }

                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

                if ($invoice->customer_id !== $customerId) {
                    throw new RuntimeException('فاکتور ' . $invoice->displayName() . ' متعلق به این مشتری نیست.');
                }

                if (! $invoice->isIssued()) {
                    throw new RuntimeException('فاکتور ' . $invoice->displayName() . ' صادر نشده است.');
                }

                if ($allocated > $invoice->remaining()) {
                    throw new RuntimeException(
                        'مبلغ تخصیص از مانده فاکتور ' . $invoice->displayName() . ' بیشتر است.'
                    );
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocated,
                ]);

                $invoice->syncPaymentState();
            }

            return $payment->refresh();
        });
    }

    /** Void an issued invoice by reversing its revenue entry. */
    public function void(Invoice $invoice, string $reason, int $userId): Invoice
    {
        if ($invoice->status !== 'issued') {
            throw new RuntimeException('فقط فاکتور صادرشده قابل ابطال است.');
        }

        if ($invoice->paid_amount > 0) {
            throw new RuntimeException('فاکتور دارای دریافتی قابل ابطال نیست؛ ابتدا برگشت وجه ثبت کنید.');
        }

        return DB::transaction(function () use ($invoice, $reason, $userId) {
            if ($invoice->journal_entry_id !== null) {
                $entry = $invoice->journalEntry;
                $this->ledger->reverse($entry, 'ابطال فاکتور ' . $invoice->number . ' — ' . $reason, $userId);
            }

            $invoice->forceFill([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_at' => now(),
            ])->save();

            return $invoice->refresh();
        });
    }

    /**
     * INV-1405-0001 style numbering.
     *
     * Derived from the count of already-numbered invoices in the current Jalali
     * year. Drafts carry no number at all, so the legal sequence has no gaps from
     * abandoned drafts.
     */
    private function nextNumber(Invoice $invoice): string
    {
        $year = \App\Support\JalaliDate::fromCarbon(
            \Carbon\CarbonImmutable::instance($invoice->issue_date)
        )->year;

        $count = Invoice::query()
            ->whereNotNull('number')
            ->where('number', 'like', 'INV-' . $year . '-%')
            ->count();

        // A unique collision here means two issuances raced. The unique index on
        // `number` will reject it, so the loop is a courtesy, not the guard.
        do {
            $candidate = sprintf('INV-%s-%04d', $year, ++$count);
        } while (Invoice::query()->where('number', $candidate)->exists());

        return $candidate;
    }

    private function nextPaymentNumber(): string
    {
        $year = \App\Support\JalaliDate::fromCarbon(\Carbon\CarbonImmutable::instance(now()))->year;

        $count = Payment::query()
            ->where('number', 'like', 'PAY-' . $year . '-%')
            ->count();

        do {
            $candidate = sprintf('PAY-%s-%04d', $year, ++$count);
        } while (Payment::query()->where('number', $candidate)->exists());

        return $candidate;
    }
}
