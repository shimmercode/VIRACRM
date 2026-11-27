<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\PayrollRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 / REQ-15.01 — «نحوه محاسبه حقوق».
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 * ----------------------------------
 * There is no field here for a formula, a percentage or a per-project rate, and
 * `method` is locked to PayrollRule::METHODS, which contains exactly one entry:
 * fixed salary plus manually entered line items. That is client decision OD-16,
 * and the directive behind this screen forbids inventing a payroll formula. The
 * amounts that relate to project counts already exist as `payroll_items` rows
 * carrying a `project_id`, entered by an accountant and reviewed on the payslip;
 * nothing in this screen generates them.
 *
 * What the screen does give the owner is the auditable record OD-16 asks for:
 * which rule a period was computed under, and whether it was approved.
 */
final class PayrollRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('payrollRule')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9._-]*$/i',
                Rule::unique('payroll_rules', 'code')->ignore($currentId),
            ],
            'method' => ['required', Rule::in(array_keys(PayrollRule::METHODS))],
            'is_active' => ['nullable', 'boolean'],
            'is_approved' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان قاعده',
            'code' => 'کد قاعده',
            'method' => 'روش محاسبه',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'قاعده‌ای با این کد پیش‌تر ثبت شده است.',
            'code.regex' => 'کد تنها می‌تواند از حروف لاتین، رقم و - _ . تشکیل شود.',
            'method.in' => 'روش محاسبهٔ انتخاب‌شده تأیید نشده است.',
        ];
    }

    public function validatedForModel(): array
    {
        return [
            'title' => trim((string) $this->input('title')),
            'code' => strtolower(trim((string) $this->input('code'))),
            'method' => (string) $this->input('method'),
            'is_active' => (bool) $this->input('is_active', true),
            'is_approved' => (bool) $this->input('is_approved', false),
            // params stays a one-key array on purpose. Letting arbitrary JSON in
            // would let a value a calculator might one day read be typed without
            // review, which is exactly what the approval gate is for.
            'params' => ['notes' => ($n = $this->input('notes')) === '' || $n === null ? null : (string) $n],
        ];
    }

    public function notes(): ?string
    {
        $n = $this->input('notes');

        return $n === '' || $n === null ? null : (string) $n;
    }
}
