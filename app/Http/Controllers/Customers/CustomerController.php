<?php

namespace App\Http\Controllers\Customers;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * REQ-03.01–03.08 — the customer module.
 *
 * PERMISSIONS: every action is guarded by the route middleware. This class does
 * not repeat the check; doing it twice invites the two copies to drift.
 *
 * N+1: the list eager-loads `websites` and the owner, and uses `withCount` for
 * the contract/project tallies, so a page of 25 rows issues a constant number
 * of queries rather than growing with the page.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'created_at'),
            'dir' => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        // Whitelist: a sort parameter taken from the URL must never reach SQL.
        $sortable = [
            'created_at', 'company_name', 'last_name', 'first_name',
            'status', 'type', 'contracts_count',
        ];
        if (! in_array($filters['sort'], $sortable, true)) {
            $filters['sort'] = 'created_at';
        }

        $customers = Customer::query()
            ->with(['websites', 'owner'])
            ->withCount(['contracts', 'projects'])
            ->when($filters['q'] !== '', fn ($q) => $q->search($filters['q']))
            ->when($filters['type'] !== '', fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderBy($filters['sort'], $filters['dir'])
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'total' => Customer::query()->count(),
            'statuses' => [
                'lead' => 'سرنخ',
                'prospect' => 'در مذاکره',
                'active' => 'مشتری فعال',
                'inactive' => 'غیرفعال',
                'archived' => 'بایگانی',
            ],
        ]);
    }

    public function create(): View
    {
        return view('customers.create', [
            'types' => [CustomerType::Natural, CustomerType::Legal],
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        // Wrapped in a transaction: a customer without its websites is a
        // half-written record, and the user would have no way to know.
        $customer = DB::transaction(function () use ($request): Customer {
            $customer = Customer::create($request->validatedForModel());

            // §3: websites are part of the profile, saved with it.
            foreach ($request->websiteRows() as $row) {
                $customer->websites()->create($row);
            }

            return $customer;
        });

        AuditRecorder::created($customer, 'ایجاد مشتری: ' . $customer->display_name);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'مشتری با موفقیت ثبت شد.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'websites',
            'contacts',
            'owner',
            'contracts' => fn ($q) => $q->orderByDesc('start_date')->limit(10),
            'projects' => fn ($q) => $q->orderByDesc('created_at')->limit(10),
            'noteEntries' => fn ($q) => $q->orderByDesc('created_at')->limit(10),
        ]);

        // §3 requires a central profile: counts for every relationship, taken in
        // one query each rather than per row.
        $stats = [
            'contracts' => $customer->contracts()->count(),
            'active_contracts' => $customer->contracts()->live()->count(),
            'projects' => $customer->projects()->count(),
            'notes' => $customer->noteEntries()->where('status', 'open')->count(),
        ];

        $totalValue = $customer->contracts()->live()->sum('amount');

        return view('customers.show', [
            'customer' => $customer,
            'stats' => $stats,
            'totalValue' => \App\Support\Money::fromRial((int) $totalValue),
            'auditTrail' => $this->recentAudit($customer),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $customer->load('websites');

        return view('customers.edit', [
            'customer' => $customer,
            'types' => [CustomerType::Natural, CustomerType::Legal],
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $before = $customer->getAttributes();

        DB::transaction(function () use ($request, $customer): void {
            $customer->update($request->validatedForModel());

            // Replace the website set. Diffing per row would be more code for
            // the same visible result, and the table is tiny (max 10 rows).
            $customer->websites()->delete();

            foreach ($request->websiteRows() as $row) {
                $customer->websites()->create($row);
            }
        });

        $after = $customer->fresh()->getAttributes();

        $changes = \App\Models\AuditLog::diff($before, $after);
        if ($changes !== []) {
            AuditRecorder::updated($customer, $changes, 'ویرایش مشتری: ' . $customer->display_name);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'تغییرات مشتری ذخیره شد.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $name = $customer->display_name;
        $customer->delete();

        AuditRecorder::deleted($customer, 'حذف مشتری: ' . $name);

        return redirect()
            ->route('customers.index')
            ->with('success', 'مشتری حذف شد.');
    }

    /** §3 timeline: real audit rows for this record, never a fabricated feed. */
    private function recentAudit(Customer $customer)
    {
        return \App\Models\AuditLog::query()
            ->with('user:id,name,username')
            ->where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();
    }
}
