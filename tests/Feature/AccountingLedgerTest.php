<?php

namespace Tests\Feature;

use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Customer;
use App\Models\User;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\InvoiceAccountingService;
use App\Services\Accounting\LedgerService;
use App\Services\Security\PermissionCatalog;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * OD-41 — the accounting invariants.
 *
 * WHY THESE ASSERTIONS AND NOT OTHERS
 * -----------------------------------
 * A ledger that "saves an entry" proves nothing. What must hold is that it
 * REFUSES the entries that would corrupt every report above it:
 *
 *   INV-1  fewer than two lines            → rejected
 *   INV-2  debit ≠ credit                  → rejected
 *   INV-3  a line both debit and credit    → rejected
 *   INV-4  posting to a group account      → rejected
 *   INV-5  a zero-amount line              → rejected
 *   INV-6  a closed fiscal period          → rejected
 *   INV-7  the same source posted twice    → rejected
 *
 * Plus the two behaviour claims a manager actually cares about:
 *   - issuing an invoice creates a receivable, NOT cash
 *   - receiving a payment moves cash, and does not re-recognise revenue
 *
 * If any of these pass by accident, the accounting is decoration.
 *
 * ISOLATION: one fresh SQLite file per scenario.
 */
final class AccountingLedgerTest extends ViraTestCase
{
    private DatabaseHarness $harness;

    private function boot(string $name): void
    {
        $this->harness = DatabaseHarness::boot($name)->migrate();
        (new ChartOfAccountsSeeder())->run();
    }

    private function makeUser(string $username): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'password' => Hash::make('Secret-Passw0rd-123'),
            'is_superadmin' => true,
            'is_active' => true,
        ]);
    }

    private function makePeriod(string $code = '1405', string $state = 'open'): FiscalPeriod
    {
        return FiscalPeriod::create([
            'code' => $code,
            'title' => 'سال ' . $code,
            'starts_on' => '1405-01-01' > '' ? '2026-03-21' : '2026-01-01',
            'ends_on' => '2027-03-20',
            'state' => $state,
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'type' => 'legal',
            'company_name' => 'شرکت آزمون حسابداری',
            'status' => 'active',
        ]);
    }

    public function run(): void
    {
        $this->chartOfAccountsIsComplete();
        $this->unbalancedEntryIsRejected();
        $this->singleLineEntryIsRejected();
        $this->doubleSidedLineIsRejected();
        $this->groupAccountIsNotPostable();
        $this->zeroAmountLineIsRejected();
        $this->closedPeriodRejectsPosting();
        $this->duplicateSourceIsRejected();
        $this->invoiceCreatesReceivableNotCash();
        $this->paymentMovesCashAndSettlesInvoice();
        $this->postedEntryIsImmutable();
        $this->reversalRestoresNetZero();
        $this->trialBalanceAlwaysBalances();
        $this->profitAndLossReportsNullWithoutData();
        $this->revenueByCustomerDimension();
        $this->balanceSheetRollsProfitIntoEquity();
    }

    /** The approved OD-41 structure must be present with correct group flags. */
    private function chartOfAccountsIsComplete(): void
    {
        $this->boot('acc_coa');

        // 41 = every code in the directive's §19.1 list: 13 group headers
        // (5 roots + 8 sub-groups) and 28 posting accounts. Asserted exactly so
        // adding or losing an account is a deliberate, visible change rather
        // than a silent drift.
        $this->assertSame(41, LedgerAccount::query()->count(),
            'The approved chart has 41 accounts (groups + leaves).');

        $this->assertSame(28, LedgerAccount::query()->where('is_postable', true)->count(),
            '28 posting accounts; the other 13 are group headers.');

        // The five roots.
        foreach (['1', '2', '3', '4', '5'] as $code) {
            $this->assertTrue(
                LedgerAccount::query()->where('code', $code)->exists(),
                'Missing root account ' . $code,
            );
        }

        $cash = LedgerAccount::query()->where('code', '1-1-01')->first();
        $this->assertTrue($cash->is_postable, 'Cash is a posting account.');
        $this->assertSame('asset', $cash->type);
        $this->assertSame('debit', $cash->normal_balance);

        $group = LedgerAccount::query()->where('code', '1-1')->first();
        $this->assertFalse($group->is_postable, 'A level-2 group must not accept postings.');

        // Revenue accounts the invoice service depends on must exist and be postable.
        foreach (['4-1-01', '4-1-02', '4-1-03', '4-1-04'] as $code) {
            $acc = LedgerAccount::query()->where('code', $code)->first();
            $this->assertTrue($acc !== null && $acc->is_postable, 'Missing revenue account ' . $code);
        }
    }

    private function unbalancedEntryIsRejected(): void
    {
        $this->boot('acc_unbalanced');
        $user = $this->makeUser('acc1');
        $this->makePeriod();

        $service = new LedgerService();

        $this->assertThrows(\RuntimeException::class, function () use ($service, $user) {
            $service->post([
                'entry_date' => '2026-05-01',
                'memo' => 'سند نامتوازن',
                'source_type' => 'manual',
                'source_id' => 1,
                'lines' => [
                    ['account' => '1-1-01', 'debit' => 100_000],
                    ['account' => '4-1-01', 'credit' => 90_000],
                ],
            ], $user->id);
        }, 'An entry whose debit total differs from its credit total must be refused.');

        $this->assertSame(0, JournalEntry::query()->count(),
            'A rejected posting must leave no partial entry behind.');
    }

    private function singleLineEntryIsRejected(): void
    {
        $this->boot('acc_single');
        $this->makeUser('acc2');
        $this->makePeriod();

        $this->assertThrows(\RuntimeException::class, function () {
            (new LedgerService())->post([
                'entry_date' => '2026-05-01',
                'memo' => 'تک‌خطی',
                'source_type' => 'manual',
                'source_id' => 2,
                'lines' => [
                    ['account' => '1-1-01', 'debit' => 50_000],
                ],
            ]);
        }, 'INV-1: one line cannot be a double entry.');
    }

    private function doubleSidedLineIsRejected(): void
    {
        $this->boot('acc_double');
        $this->makeUser('acc3');
        $this->makePeriod();

        $this->assertThrows(\RuntimeException::class, function () {
            (new LedgerService())->post([
                'entry_date' => '2026-05-01',
                'memo' => 'خط دوطرفه',
                'source_type' => 'manual',
                'source_id' => 3,
                'lines' => [
                    ['account' => '1-1-01', 'debit' => 10_000, 'credit' => 10_000],
                    ['account' => '4-1-01', 'credit' => 10_000],
                ],
            ]);
        }, 'INV-3: a line must be debit XOR credit.');
    }

    private function groupAccountIsNotPostable(): void
    {
        $this->boot('acc_group');
        $this->makeUser('acc4');
        $this->makePeriod();

        $this->assertThrows(\RuntimeException::class, function () {
            (new LedgerService())->post([
                'entry_date' => '2026-05-01',
                'memo' => 'ثبت روی حساب گروهی',
                'source_type' => 'manual',
                'source_id' => 4,
                'lines' => [
                    ['account' => '1-1', 'debit' => 1_000],
                    ['account' => '4-1-01', 'credit' => 1_000],
                ],
            ]);
        }, 'INV-4: posting to a group account double-counts every report above it.');
    }

    private function zeroAmountLineIsRejected(): void
    {
        $this->boot('acc_zero');
        $this->makeUser('acc5');
        $this->makePeriod();

        $this->assertThrows(\RuntimeException::class, function () {
            (new LedgerService())->post([
                'entry_date' => '2026-05-01',
                'memo' => 'خط صفر',
                'source_type' => 'manual',
                'source_id' => 5,
                'lines' => [
                    ['account' => '1-1-01', 'debit' => 1_000],
                    ['account' => '4-1-01', 'credit' => 1_000],
                    ['account' => '2-1-02', 'credit' => 0],
                ],
            ]);
        }, 'INV-5: a zero-amount line carries no information and must be refused.');
    }

    private function closedPeriodRejectsPosting(): void
    {
        $this->boot('acc_closed');
        $this->makeUser('acc6');
        $this->makePeriod('1405', 'closed');

        $this->assertThrows(\RuntimeException::class, function () {
            (new LedgerService())->post([
                'entry_date' => '2026-05-01',
                'memo' => 'سند در دوره بسته',
                'source_type' => 'manual',
                'source_id' => 6,
                'lines' => [
                    ['account' => '1-1-01', 'debit' => 1_000],
                    ['account' => '4-1-01', 'credit' => 1_000],
                ],
            ]);
        }, 'INV-6: a closed fiscal period must reject new postings.');
    }

    /**
     * INV-7 is the idempotence guard: re-posting the same invoice must never
     * double the revenue, which is the classic silent accounting bug.
     */
    private function duplicateSourceIsRejected(): void
    {
        $this->boot('acc_dup');
        $user = $this->makeUser('acc7');
        $this->makePeriod();

        $service = new LedgerService();
        $payload = [
            'entry_date' => '2026-05-01',
            'memo' => 'ثبت اول',
            'source_type' => 'expense',
            'source_id' => 77,
            'lines' => [
                ['account' => '5-2-02', 'debit' => 5_000],
                ['account' => '1-1-01', 'credit' => 5_000],
            ],
        ];

        $service->post($payload, $user->id);

        $this->assertThrows(\RuntimeException::class, function () use ($service, $payload, $user) {
            $service->post($payload, $user->id);
        }, 'INV-7: the same source document must not produce a second entry.');

        $this->assertSame(1, JournalEntry::query()->count());
    }

    /**
     * THE CENTRAL CLAIM (§19.4): issuing an invoice creates a receivable and
     * revenue, and touches NO cash. If this ever passes with cash moving,
     * "billed" and "collected" have been conflated.
     */
    private function invoiceCreatesReceivableNotCash(): void
    {
        $this->boot('acc_invoice');
        $user = $this->makeUser('acc8');
        $this->makePeriod();
        $customer = $this->makeCustomer();

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'service_type' => 'seo',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'discount' => 0,
            'tax_rate_bp' => 1000, // 10%, from settings in real use
            'status' => 'draft',
            'title' => 'فاکتور سئو اردیبهشت',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'line_no' => 1,
            'description' => 'خدمات سئو',
            'quantity_milli' => 1000,
            'unit_price' => 10_000_000,
            'line_total' => 10_000_000,
        ]);

        $issued = (new InvoiceAccountingService(new LedgerService()))
            ->issue($invoice, $user->id);

        $this->assertSame('issued', $issued->status, 'The invoice must be issued.');
        $this->assertNotNull($issued->number, 'A controlled number must be assigned at issue.');
        $this->assertSame(10_000_000, $issued->subtotal);
        $this->assertSame(1_000_000, $issued->tax_amount, '10% of the net subtotal.');
        $this->assertSame(11_000_000, $issued->total);

        $entry = $issued->journalEntry;
        $this->assertNotNull($entry, 'Issuing must post a journal entry.');
        $this->assertTrue($entry->isBalanced(), 'The posted entry must balance.');

        $byAccount = $entry->lines->keyBy(fn ($l) => $l->account->code);
        $this->assertSame(11_000_000, $byAccount['1-1-02']->debit,
            'Receivable must be debited for the gross total.');
        $this->assertSame(10_000_000, $byAccount['4-1-01']->credit,
            'SEO revenue must be credited with the net amount.');
        $this->assertSame(1_000_000, $byAccount['2-1-02']->credit,
            'VAT must be a liability, separate from revenue.');

        // The claim that matters: no cash movement on issue.
        $cash = LedgerAccount::query()->where('code', '1-1-01')->first();
        $this->assertSame(0, $cash->balance(),
            'Issuing an invoice must NOT move cash — that is a payment, not a billing.');

        // And the invoice is unpaid.
        $this->assertSame('unpaid', $issued->payment_state);
        $this->assertSame(11_000_000, $issued->remaining());
    }

    private function paymentMovesCashAndSettlesInvoice(): void
    {
        $this->boot('acc_payment');
        $user = $this->makeUser('acc9');
        $this->makePeriod();
        $customer = $this->makeCustomer();

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'service_type' => 'design',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'tax_rate_bp' => 0,
            'status' => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'line_no' => 1,
            'description' => 'طراحی سایت',
            'quantity_milli' => 1000,
            'unit_price' => 50_000_000,
            'line_total' => 50_000_000,
        ]);

        $svc = new InvoiceAccountingService(new LedgerService());
        $invoice = $svc->issue($invoice, $user->id);

        $revenueBefore = LedgerAccount::query()->where('code', '4-1-02')->first()->balance();
        $this->assertSame(50_000_000, $revenueBefore, 'Revenue recognised at issue.');

        // Partial receipt.
        $svc->recordPayment(
            $customer->id,
            20_000_000,
            '2026-05-10',
            [$invoice->id => 20_000_000],
            'transfer',
            'REF-1',
            'ملت',
            null,
            $user->id,
        );

        $invoice->refresh();
        $this->assertSame('partial', $invoice->payment_state, 'A partial receipt sets payment_state=partial.');
        $this->assertSame(20_000_000, $invoice->paid_amount);
        $this->assertSame(30_000_000, $invoice->remaining());

        $cash = LedgerAccount::query()->where('code', '1-1-01')->first();
        $this->assertSame(20_000_000, $cash->balance(), 'Cash must increase by the received amount.');

        $revenueAfter = LedgerAccount::query()->where('code', '4-1-02')->first()->balance();
        $this->assertSame($revenueBefore, $revenueAfter,
            'Receiving cash must NOT create revenue a second time.');

        // Settle the rest.
        $svc->recordPayment(
            $customer->id, 30_000_000, '2026-05-20',
            [$invoice->id => 30_000_000], 'transfer', 'REF-2', 'ملت', null, $user->id,
        );

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_state);
        $this->assertSame(0, $invoice->remaining());
    }

    private function postedEntryIsImmutable(): void
    {
        $this->boot('acc_immutable');
        $user = $this->makeUser('acc10');
        $this->makePeriod();

        $entry = (new LedgerService())->post([
            'entry_date' => '2026-05-01',
            'memo' => 'سند قطعی',
            'source_type' => 'opening',
            'source_id' => 1,
            'lines' => [
                ['account' => '1-1-01', 'debit' => 1_000],
                ['account' => '3-1-01', 'credit' => 1_000],
            ],
        ], $user->id);

        $this->assertSame('posted', $entry->status);

        $this->assertThrows(\RuntimeException::class, function () use ($entry) {
            $entry->memo = 'تلاش برای ویرایش';
            $entry->save();
        }, '§19.2: a posted entry must not be editable.');

        $this->assertThrows(\RuntimeException::class, function () use ($entry) {
            $entry->delete();
        }, '§19.2: a posted entry must not be deletable.');
    }

    private function reversalRestoresNetZero(): void
    {
        $this->boot('acc_reversal');
        $user = $this->makeUser('acc11');
        $this->makePeriod();

        $service = new LedgerService();
        $entry = $service->post([
            'entry_date' => '2026-05-01',
            'memo' => 'هزینه اجاره',
            'source_type' => 'expense',
            'source_id' => 501,
            'lines' => [
                ['account' => '5-2-02', 'debit' => 8_000],
                ['account' => '1-1-01', 'credit' => 8_000],
            ],
        ], $user->id);

        $rent = LedgerAccount::query()->where('code', '5-2-02')->first();
        $this->assertSame(8_000, $rent->balance());

        $mirror = $service->reverse($entry, 'ثبت اشتباه', $user->id);

        $entry->refresh();
        $this->assertSame('reversed', $entry->status, 'The original must be marked reversed, not deleted.');
        $this->assertSame($mirror->id, (int) $entry->reversed_by_entry_id);
        $this->assertTrue($mirror->isBalanced(), 'The mirror entry must balance.');

        $this->assertSame(0, LedgerAccount::query()->where('code', '5-2-02')->first()->balance(),
            'Net effect of an entry and its reversal must be zero.');

        $this->assertSame(2, JournalEntry::query()->count(),
            'Both the original and the reversal stay visible in the ledger.');
    }

    private function trialBalanceAlwaysBalances(): void
    {
        $this->boot('acc_trial');
        $user = $this->makeUser('acc12');
        $period = $this->makePeriod();

        $service = new LedgerService();

        foreach ([1, 2, 3] as $i) {
            $service->post([
                'entry_date' => '2026-05-0' . $i,
                'memo' => 'سند ' . $i,
                'source_type' => 'manual',
                'source_id' => 100 + $i,
                'lines' => [
                    ['account' => '1-1-01', 'debit' => 1_000 * $i],
                    ['account' => '4-1-04', 'credit' => 1_000 * $i],
                ],
            ], $user->id);
        }

        $report = (new FinancialReportService())->trialBalance($period->id);

        $this->assertTrue($report['has_data']);
        $this->assertTrue($report['balanced'], 'A trial balance over valid postings must balance.');
        $this->assertSame(6_000, $report['totals']['debit']);
        $this->assertSame(6_000, $report['totals']['credit']);
    }

    /**
     * The directive forbids a fabricated zero: a period with no entries must
     * report null profit, not 0 — those mean different things to a manager.
     */
    private function profitAndLossReportsNullWithoutData(): void
    {
        $this->boot('acc_empty_pl');
        $this->makeUser('acc13');
        $period = $this->makePeriod();

        $pl = (new FinancialReportService())->profitAndLoss($period->id);

        $this->assertFalse($pl['has_data'], 'An empty period has no data.');
        $this->assertNull($pl['net'], 'Net profit must be null, not 0, when nothing was posted.');
        $this->assertSame(0, $pl['revenue_total']);
    }

    /** §19.3: revenue per customer without an account per customer. */
    private function revenueByCustomerDimension(): void
    {
        $this->boot('acc_dimension');
        $user = $this->makeUser('acc14');
        $period = $this->makePeriod();
        $customer = $this->makeCustomer();

        (new LedgerService())->post([
            'entry_date' => '2026-05-01',
            'memo' => 'درآمد مشتری',
            'source_type' => 'manual',
            'source_id' => 900,
            'lines' => [
                [
                    'account' => '1-1-02', 'debit' => 7_000,
                    'customer_id' => $customer->id, 'service_type' => 'seo',
                ],
                [
                    'account' => '4-1-01', 'credit' => 7_000,
                    'customer_id' => $customer->id, 'service_type' => 'seo',
                ],
            ],
        ], $user->id);

        $byCustomer = (new FinancialReportService())->revenueBy('customer', $period->id);

        $this->assertCount(1, $byCustomer);
        $this->assertSame($customer->id, (int) $byCustomer[0]['key']);
        $this->assertSame(7_000, $byCustomer[0]['amount']);
        $this->assertContains('شرکت آزمون', $byCustomer[0]['label']);

        $byService = (new FinancialReportService())->revenueBy('service', $period->id);
        $this->assertCount(1, $byService);
        $this->assertSame(7_000, $byService[0]['amount']);
    }

    /**
     * The balance sheet must balance on ANY date inside an open year.
     *
     * This is the assertion that would have caught the original bug: revenue and
     * expense are not balance-sheet accounts, so their net has to be carried into
     * equity as current-period earnings. Without that roll-forward, an invoice
     * raises assets while equity stays at zero until year-end closing, and a
     * perfectly correct ledger produces an "unbalanced" balance sheet.
     */
    private function balanceSheetRollsProfitIntoEquity(): void
    {
        $this->boot('acc_bs');
        $user = $this->makeUser('acc15');
        $this->makePeriod();
        $customer = $this->makeCustomer();

        $svc = new InvoiceAccountingService(new LedgerService());

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'service_type' => 'support',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'tax_rate_bp' => 0,
            'status' => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'line_no' => 1,
            'description' => 'پشتیبانی',
            'quantity_milli' => 1000,
            'unit_price' => 4_000_000,
            'line_total' => 4_000_000,
        ]);

        $invoice = $svc->issue($invoice, $user->id);

        // Before payment: assets 4M (receivable), equity 4M (profit), balance holds.
        $sheet = (new FinancialReportService())->balanceSheet();
        $this->assertTrue($sheet['has_data']);
        $this->assertSame(4_000_000, $sheet['assets'], 'A receivable is an asset.');
        $this->assertSame(0, $sheet['liabilities']);
        $this->assertSame(4_000_000, $sheet['net_profit'], 'Net profit rolls forward.');
        $this->assertTrue($sheet['balanced'],
            'Assets must equal liabilities + equity once current-period profit is carried in.');

        // Pay it: assets change composition (AR -> cash) but total is unchanged.
        $svc->recordPayment(
            $customer->id, 4_000_000, '2026-05-15',
            [$invoice->id => 4_000_000], 'transfer', null, null, null, $user->id,
        );

        $sheet2 = (new FinancialReportService())->balanceSheet();
        $this->assertSame(4_000_000, $sheet2['assets'],
            'Settling a receivable replaces it with cash; total assets are unchanged.');
        $this->assertTrue($sheet2['balanced'], 'Still balanced after receipt.');
    }
}
