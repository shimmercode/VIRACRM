<?php

namespace App\Http\Controllers\Contracts;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\Security\AuditRecorder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * REQ-04.01–04.10 — the contract module.
 *
 * FINANCIAL AMOUNTS: `contracts.view_financials` is a separate permission
 * because §10 requires financial access to be restricted independently. A user
 * without it sees the contract but not the amount — the amount is never sent
 * to the page at all, rather than hidden with CSS.
 */
class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'customer_id' => (string) $request->query('customer_id', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => (string) $request->query('sort', 'start_date'),
            'dir' => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $sortable = ['start_date', 'end_date', 'title', 'amount', 'status', 'created_at'];
        if (! in_array($filters['sort'], $sortable, true)) {
            $filters['sort'] = 'start_date';
        }

        $contracts = Contract::query()
            ->with(['customer:id,type,company_name,first_name,last_name', 'contractType:id,title,code'])
            ->filter($filters)
            ->orderBy($filters['sort'], $filters['dir'])
            ->paginate(20)
            ->withQueryString();

        return view('contracts.index', [
            'contracts' => $contracts,
            'filters' => $filters,
            'types' => ContractType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'statuses' => $this->statusOptions(),
            'canSeeAmounts' => $request->user()->hasPermission('contracts.view_financials'),
            'total' => Contract::query()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('contracts.create', [
            'types' => ContractType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'statuses' => ContractStatus::selectable(),
            'customers' => $this->customerOptions(),
            'preselectedCustomer' => $request->query('customer_id'),
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $data = $request->validatedForModel();

        $contract = new Contract($data);
        $contract->number = $this->nextNumber();
        $contract->owner_user_id = $request->user()->id;
        $contract->save();

        AuditRecorder::created($contract, 'ایجاد قرارداد: ' . $contract->title);

        return redirect()
            ->route('contracts.show', $contract)
            ->with('success', 'قرارداد با موفقیت ثبت شد.');
    }

    public function show(Request $request, Contract $contract): View
    {
        $contract->load([
            'customer',
            'contractType',
            'owner:id,name,username',
            'versions' => fn ($q) => $q->limit(5),
            'files.attachment',
        ]);

        $canSeeAmounts = $request->user()->hasPermission('contracts.view_financials');
        $warningDays = (int) (Setting::get('contracts.expiry_warning_days') ?? 30);

        return view('contracts.show', [
            'contract' => $contract,
            'canSeeAmounts' => $canSeeAmounts,
            'warningDays' => $warningDays,
            'isExpiring' => $contract->isExpiringSoon($warningDays),
            'auditTrail' => AuditLog::query()
                ->with('user:id,name,username')
                ->where('subject_type', Contract::class)
                ->where('subject_id', $contract->id)
                ->orderByDesc('created_at')
                ->limit(12)
                ->get(),
        ]);
    }

    public function edit(Contract $contract): View
    {
        return view('contracts.edit', [
            'contract' => $contract,
            'types' => ContractType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'statuses' => ContractStatus::selectable(),
            'customers' => $this->customerOptions($contract->customer_id),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $before = $contract->getAttributes();
        $contract->update($request->validatedForModel());
        $after = $contract->fresh()->getAttributes();

        $changes = AuditLog::diff($before, $after);
        if ($changes !== []) {
            AuditRecorder::updated($contract, $changes, 'ویرایش قرارداد: ' . $contract->title);
        }

        return redirect()
            ->route('contracts.show', $contract)
            ->with('success', 'تغییرات قرارداد ذخیره شد.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $title = $contract->title;
        $contract->delete();

        AuditRecorder::deleted($contract, 'حذف قرارداد: ' . $title);

        return redirect()
            ->route('contracts.index')
            ->with('success', 'قرارداد حذف شد.');
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function customerOptions(?int $includeId = null)
    {
        return Customer::query()
            ->select('id', 'type', 'company_name', 'first_name', 'last_name')
            ->orderBy('company_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get();
    }

    private function statusOptions(): array
    {
        $out = [];
        foreach (ContractStatus::selectable() as $status) {
            $out[$status->value] = $status->label();
        }

        return $out;
    }

    /**
     * Human-readable contract number: VIRA-C-0001.
     * Derived from the max id rather than a count, so a deletion cannot cause
     * two contracts to share a number.
     */
    private function nextNumber(): string
    {
        $max = (int) Contract::withTrashed()->max('id');

        return 'VIRA-C-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
