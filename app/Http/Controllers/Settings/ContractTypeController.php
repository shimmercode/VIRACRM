<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ContractTypeRequest;
use App\Models\ContractType;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * §15 / REQ-15.01 — the contract type list.
 *
 * ContractController already offered `ContractType::where('is_active')` in the
 * contract form and ContractTemplateController offered all of them when picking
 * a template. The list itself was fixed at the four seeded rows.
 *
 * A type in use is retired, never deleted: contracts keep their
 * `contract_type_id`, and the FK is `ON DELETE SET NULL`, so a delete would
 * silently erase the type off every contract written on it.
 */
final class ContractTypeController extends Controller
{
    public function index(): View
    {
        return view('settings.contract-types.index', [
            'types' => ContractType::query()
                ->withCount('contracts')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.contract-types.create', ['type' => new ContractType()]);
    }

    public function store(ContractTypeRequest $request): RedirectResponse
    {
        $type = ContractType::query()->create($request->validatedForModel());

        AuditRecorder::created($type, 'ثبت نوع قرارداد: ' . $type->title);

        return redirect()
            ->route('settings.contract-types.index')
            ->with('success', 'نوع قرارداد ثبت شد.');
    }

    public function edit(ContractType $contractType): View
    {
        return view('settings.contract-types.edit', ['type' => $contractType]);
    }

    public function update(ContractTypeRequest $request, ContractType $contractType): RedirectResponse
    {
        $contractType->fill($request->validatedForModel())->save();

        AuditRecorder::updated($contractType, $contractType->getChanges(), 'ویرایش نوع قرارداد');

        return redirect()
            ->route('settings.contract-types.index')
            ->with('success', 'نوع قرارداد به‌روزرسانی شد.');
    }

    public function destroy(ContractType $contractType): RedirectResponse
    {
        $inUse = $contractType->contracts()->count();

        if ($inUse > 0) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $contractType,
                sprintf('حذف نوع قرارداد «%s» رد شد: در %d قرارداد استفاده شده است', $contractType->title, $inUse),
                ['refused' => 'in_use', 'contracts' => $inUse],
            );

            return redirect()
                ->route('settings.contract-types.index')
                ->with('error', sprintf(
                    'این نوع در %d قرارداد استفاده شده است؛ به‌جای حذف، آن را غیرفعال کنید.',
                    $inUse,
                ));
        }

        $title = $contractType->title;
        $contractType->delete();

        AuditRecorder::deleted($contractType, 'حذف نوع قرارداد: ' . $title);

        return redirect()
            ->route('settings.contract-types.index')
            ->with('success', 'نوع قرارداد حذف شد.');
    }
}
