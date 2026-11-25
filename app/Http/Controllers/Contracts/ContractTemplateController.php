<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Services\Contracts\ContractRenderer;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * REQ-04.04 / 04.05 — contract templates.
 *
 * WHY A TEMPLATE IS VERSIONED RATHER THAN EDITED
 * ----------------------------------------------
 * A template is the source a finalised contract was rendered from. If an
 * administrator edits it in place, every already-finalised contract still holds
 * its own rendered body — so nothing breaks — but the audit question "what did
 * the template say when this was signed" becomes unanswerable.
 *
 * So editing a template that has been used creates a NEW row with `version + 1`
 * and deactivates the old one. `contracts.template_id` keeps pointing at the
 * exact version used, and the history stays readable.
 *
 * PLACEHOLDER VALIDATION IS DONE ON SAVE, NOT ON RENDER
 * ----------------------------------------------------
 * A template with `{{contact.number}}` (a typo for `contract.number`) would fail
 * at finalisation time, in front of a client. It is rejected here, where the
 * person who made the typo can still fix it.
 */
class ContractTemplateController extends Controller
{
    public function index(): View
    {
        $templates = ContractTemplate::query()
            ->with('contractType:id,title')
            ->orderBy('title')
            ->orderByDesc('version')
            ->get();

        return view('contracts.templates.index', [
            'templates' => $templates,
            // Grouped so the list reads as "one template, its versions" rather
            // than a flat wall of near-identical rows.
            'byTitle' => $templates->groupBy(fn (ContractTemplate $t) => $t->title),
            'tokens' => ContractRenderer::tokens(),
        ]);
    }

    public function create(): View
    {
        return view('contracts.templates.form', [
            'template' => new ContractTemplate(['is_active' => true, 'version' => 1]),
            'types' => ContractType::query()->orderBy('sort_order')->get(),
            'tokens' => ContractRenderer::tokens(),
            'usedCount' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        if ($error = $this->tokenError($data['body'])) {
            return back()->withInput()->with('error', $error);
        }

        // Only one default per contract type: two defaults make "which body did
        // it use?" depend on row order.
        if (! empty($data['is_default'])) {
            ContractTemplate::query()
                ->where('contract_type_id', $data['contract_type_id'] ?? null)
                ->update(['is_default' => false]);
        }

        $template = ContractTemplate::create([
            'contract_type_id' => $data['contract_type_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'version' => 1,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default'),
            'created_by' => $request->user()->id,
        ]);

        AuditRecorder::created($template, 'ایجاد قالب قرارداد: ' . $template->title);

        return redirect()
            ->route('contracts.templates.index')
            ->with('success', 'قالب قرارداد ثبت شد.');
    }

    public function edit(ContractTemplate $template): View
    {
        return view('contracts.templates.form', [
            'template' => $template,
            'types' => ContractType::query()->orderBy('sort_order')->get(),
            'tokens' => ContractRenderer::tokens(),
            'usedCount' => \App\Models\Contract::query()->where('template_id', $template->id)->count(),
        ]);
    }

    /**
     * Editing a used template creates the next version.
     *
     * An UNUSED template is edited in place: there is no history to protect, and
     * spawning versions of a template nobody has seen is noise.
     */
    public function update(Request $request, ContractTemplate $template): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        if ($error = $this->tokenError($data['body'])) {
            return back()->withInput()->with('error', $error);
        }

        $used = \App\Models\Contract::query()->where('template_id', $template->id)->exists();

        if (! empty($data['is_default'])) {
            ContractTemplate::query()
                ->where('contract_type_id', $data['contract_type_id'] ?? null)
                ->update(['is_default' => false]);
        }

        if ($used) {
            $template->forceFill(['is_active' => false])->save();

            $new = ContractTemplate::create([
                'contract_type_id' => $data['contract_type_id'] ?? null,
                'title' => $data['title'],
                'body' => $data['body'],
                'version' => (int) $template->version + 1,
                'is_active' => $request->boolean('is_active', true),
                'is_default' => $request->boolean('is_default'),
                'created_by' => $request->user()->id,
            ]);

            AuditRecorder::record(
                'contract_template.versioned',
                $new,
                'نسخهٔ ' . $new->version . ' قالب قرارداد «' . $new->title . '» (قالب قبلی غیرفعال شد)',
                ['version' => [(int) $template->version, (int) $new->version]],
            );

            return redirect()
                ->route('contracts.templates.index')
                ->with('success',
                    'این قالب در قراردادهای نهایی‌شده استفاده شده است؛ بنابراین نسخهٔ '
                    . \App\Support\Money::toPersianDigits((string) $new->version)
                    . ' ساخته شد و نسخهٔ قبلی برای سابقه غیرفعال ماند.'
                );
        }

        $template->update([
            'contract_type_id' => $data['contract_type_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default'),
        ]);

        AuditRecorder::updated($template, ['body' => ['…', '…']], 'ویرایش قالب قرارداد: ' . $template->title);

        return redirect()
            ->route('contracts.templates.index')
            ->with('success', 'قالب قرارداد ذخیره شد.');
    }

    public function destroy(ContractTemplate $template): RedirectResponse
    {
        $used = \App\Models\Contract::query()->where('template_id', $template->id)->exists();

        if ($used) {
            $template->update(['is_active' => false, 'is_default' => false]);

            return back()->with('warning',
                'این قالب در قراردادهای نهایی‌شده استفاده شده است، پس حذف نشد و غیرفعال شد.'
            );
        }

        $title = $template->title;
        $template->delete();

        AuditRecorder::deleted($template, 'حذف قالب قرارداد: ' . $title);

        return back()->with('success', 'قالب قرارداد حذف شد.');
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'contract_type_id' => ['nullable', 'integer', Rule::exists('contract_types', 'id')],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'عنوان قالب را وارد کنید.',
            'body.required' => 'متن قالب الزامی است.',
            'contract_type_id.exists' => 'نوع قرارداد انتخاب‌شده معتبر نیست.',
        ]);
    }

    private function tokenError(string $body): ?string
    {
        $unknown = (new ContractRenderer())->unknownTokens($body);

        if ($unknown === []) {
            return null;
        }

        return 'جای‌نگهدار ناشناخته در متن قالب: '
            . implode('، ', array_map(fn (string $t) => '{{' . $t . '}}', $unknown))
            . '. فقط جای‌نگهدارهای فهرست راهنما مجاز هستند.';
    }
}
