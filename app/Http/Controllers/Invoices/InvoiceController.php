<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;
use App\Services\Accounting\InvoiceAccountingService;
use App\Services\Accounting\LedgerService;
use App\Services\Security\AuditRecorder;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * OD-41 §19.6 / §19.7 — the invoice and payment surfaces.
 *
 * WHY ISSUE IS A SEPARATE ACTION FROM CREATE
 * ------------------------------------------
 * A draft invoice is an editable working document with NO number. Issuing it
 * assigns the legal number, freezes the totals and posts revenue. Those are
 * different acts with different permissions (`invoices.create` vs
 * `invoices.issue`), because the second one is the moment money becomes a claim
 * on a customer.
 *
 * Editing is only possible while the invoice is a draft. Once issued, the only
 * paths are: record a payment, or void (which reverses the ledger entry).
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceAccountingService $accounting,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'payment' => (string) $request->query('payment', ''),
            'overdue' => (string) $request->query('overdue', ''),
        ];

        $query = Invoice::query()
            ->with(['customer:id,company_name,first_name,last_name', 'project:id,title'])
            ->withCount('items');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['payment'] !== '') {
            $query->where('payment_state', $filters['payment']);
        }

        if ($filters['overdue'] === '1') {
            $query->overdue();
        }

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('title', 'like', $term));
        }

        // Totals are computed over the whole filtered set, not the page: a
        // summary that changes when you turn the page is not a summary.
        $base = clone $query;

        return view('invoices.index', [
            'invoices' => $query->orderByDesc('issue_date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => Invoice::STATUSES,
            'paymentStates' => Invoice::PAYMENT_STATES,
            'summary' => [
                'issued_total' => (int) (clone $base)->where('status', 'issued')->sum('total'),
                'outstanding' => (int) (clone $base)->where('status', 'issued')
                    ->selectRaw('COALESCE(SUM(total - paid_amount),0) AS s')->value('s'),
                'overdue_count' => (clone $base)->overdue()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('invoices.create', [
            'customers' => Customer::query()->orderBy('company_name')->get(['id', 'company_name', 'first_name', 'last_name']),
            'contracts' => Contract::query()->orderBy('title')->get(['id', 'title', 'customer_id']),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title', 'customer_id']),
            'services' => ServiceType::options(),
            'defaultTaxBp' => $this->defaultTaxRateBp(),
            'preset' => [
                'customer_id' => $request->query('customer_id'),
                'project_id' => $request->query('project_id'),
                'contract_id' => $request->query('contract_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInvoice($request);

        $issueDate = JalaliDate::parse($data['issue_date']);
        $dueDate = JalaliDate::parse($data['due_date']);

        if ($issueDate === null || $dueDate === null) {
            return back()->withInput()->withErrors(['issue_date' => 'تاریخ‌ها معتبر نیستند.']);
        }

        if ($dueDate->toCarbon()->lessThan($issueDate->toCarbon())) {
            return back()->withInput()->withErrors(['due_date' => 'سررسید نمی‌تواند قبل از تاریخ صدور باشد.']);
        }

        $items = $this->parseItems($data['items'] ?? []);

        if ($items === []) {
            return back()->withInput()->withErrors(['items' => 'فاکتور باید حداقل یک ردیف داشته باشد.']);
        }

        $invoice = DB::transaction(function () use ($data, $request, $issueDate, $dueDate, $items) {
            $invoice = Invoice::create([
                'customer_id' => (int) $data['customer_id'],
                // `validate()` omits keys the request did not send at all, so a
                // nullable optional field is ABSENT, not null. Reading it with
                // `$data['x']` throws "Undefined array key" on any invoice
                // created without a contract — which is most of them.
                'contract_id' => ! empty($data['contract_id']) ? (int) $data['contract_id'] : null,
                'project_id' => ! empty($data['project_id']) ? (int) $data['project_id'] : null,
                'service_type' => $data['service_type'],
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'discount' => (int) (Money::parse($data['discount'] ?? '0')?->amount ?? 0),
                'tax_rate_bp' => (int) $data['tax_rate_bp'],
                'status' => 'draft',
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($items as $i => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_no' => $i + 1,
                    'description' => $item['description'],
                    'quantity_milli' => $item['quantity_milli'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => InvoiceItem::totalFor($item['unit_price'], $item['quantity_milli']),
                ]);
            }

            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });

        AuditRecorder::created($invoice, 'ایجاد پیش‌نویس فاکتور #' . $invoice->id);

        // "Save and issue" is one click in the UI but two steps in the domain —
        // the draft is persisted first, so a failure while posting to the ledger
        // leaves a recoverable draft rather than a half-issued invoice.
        if ($request->boolean('issue_now')) {
            try {
                $this->accounting->issue($invoice, $request->user()->id);
                AuditRecorder::record(
                    'invoice.issued', $invoice,
                    'صدور فاکتور ' . $invoice->displayName(),
                    ['status' => ['draft', 'issued']],
                );

                return redirect()->route('invoices.show', $invoice)
                    ->with('success', 'فاکتور صادر شد و سند درآمد ثبت گردید.');
            } catch (\RuntimeException $e) {
                return redirect()->route('invoices.show', $invoice)
                    ->with('error', 'فاکتور به‌صورت پیش‌نویس ذخیره شد اما صدور انجام نشد: ' . $e->getMessage());
            }
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'پیش‌نویس فاکتور ذخیره شد.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load([
            'items', 'customer', 'project', 'contract', 'journalEntry',
            'allocations.payment',
        ]);

        return view('invoices.show', [
            'invoice' => $invoice,
            'paymentStates' => Invoice::PAYMENT_STATES,
            'statuses' => Invoice::STATUSES,
            'payments' => Payment::query()
                ->where('customer_id', $invoice->customer_id)
                ->where('direction', 'in')
                ->orderByDesc('paid_on')
                ->limit(20)
                ->get(),
        ]);
    }

    /** Issue a draft. */
    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->accounting->issue($invoice, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditRecorder::record(
            'invoice.issued', $invoice,
            'صدور فاکتور ' . $invoice->displayName(),
            ['status' => ['draft', 'issued']],
        );

        return back()->with('success', 'فاکتور صادر شد.');
    }

    /** Record a receipt and allocate it to this invoice. */
    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:32'],
            'paid_on' => ['required', 'string', 'max:10'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:120'],
        ]);

        $amount = (int) (Money::parse($data['amount'])?->amount ?? 0);

        if ($amount <= 0) {
            return back()->withInput()->withErrors(['amount' => 'مبلغ معتبر نیست.']);
        }

        $paidOn = JalaliDate::parse($data['paid_on']);

        if ($paidOn === null) {
            return back()->withInput()->withErrors(['paid_on' => 'تاریخ پرداخت معتبر نیست.']);
        }

        if ($amount > $invoice->remaining()) {
            return back()->withInput()->withErrors([
                'amount' => 'مبلغ از مانده فاکتور (' . number_format($invoice->remaining()) . ' ریال) بیشتر است.',
            ]);
        }

        try {
            $payment = $this->accounting->recordPayment(
                $invoice->customer_id,
                $amount,
                $paidOn->toDateString(),
                [$invoice->id => $amount],
                $data['method'],
                $data['reference'] ?? null,
                $data['bank_account'] ?? null,
                'دریافت فاکتور ' . $invoice->displayName(),
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditRecorder::record(
            'payment.recorded', $payment,
            'ثبت دریافتی برای فاکتور ' . $invoice->displayName(),
            ['amount' => $amount],
        );

        return back()->with('success', 'دریافتی ثبت شد.');
    }

    /**
     * VAT rate default, read from settings — never hard-coded (§19.1).
     * Basis points, so 10% is 1000.
     */
    private function defaultTaxRateBp(): int
    {
        $raw = Setting::query()->where('key', 'accounting.vat_rate_bp')->value('value');

        return $raw !== null && is_numeric($raw) ? (int) $raw : 0;
    }

    /** @return array<string,mixed> */
    private function validateInvoice(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'service_type' => ['required', Rule::in(array_keys(ServiceType::MAP))],
            'issue_date' => ['required', 'string', 'max:10'],
            'due_date' => ['required', 'string', 'max:10'],
            'discount' => ['nullable', 'string', 'max:32'],
            'tax_rate_bp' => ['required', 'integer', 'min:0', 'max:10000'],
            'title' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'string', 'max:16'],
            'items.*.unit_price' => ['required', 'string', 'max:32'],
        ]);
    }

    /**
     * @param  array<int,array{description:string,quantity:string,unit_price:string}>  $rows
     * @return list<array{description:string,quantity_milli:int,unit_price:int}>
     */
    private function parseItems(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $unit = (int) (Money::parse((string) ($row['unit_price'] ?? '0'))?->amount ?? 0);

            // Quantity arrives as a Persian-digit decimal string; convert to
            // thousandths without going through float.
            $qtyRaw = Money::toAsciiDigits(trim((string) ($row['quantity'] ?? '1')))
                ?: '1';
            $qtyRaw = str_replace(['٬', ',', '٫', '/'], ['', '', '.', '.'], $qtyRaw);

            $quantityMilli = (int) round(((float) $qtyRaw) * 1000);

            if ($quantityMilli <= 0 || $unit <= 0) {
                continue;
            }

            $out[] = [
                'description' => (string) $row['description'],
                'quantity_milli' => $quantityMilli,
                'unit_price' => $unit,
            ];
        }

        return $out;
    }
}
