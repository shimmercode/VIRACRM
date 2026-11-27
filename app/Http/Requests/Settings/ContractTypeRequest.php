<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 / REQ-15.01 — «تعریف انواع قرارداد».
 *
 * `contract_types` was seeded and read by the contract form, but nothing could
 * add, rename or retire a type: the four shipped rows were the whole list for
 * good. This is the validation behind the screen that changes that.
 */
final class ContractTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('contractType')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9._-]*$/i',
                Rule::unique('contract_types', 'code')->ignore($currentId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان نوع قرارداد',
            'code' => 'کد نوع قرارداد',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'نوع قراردادی با این کد پیش‌تر ثبت شده است.',
            'code.regex' => 'کد تنها می‌تواند از حروف لاتین، رقم و - _ . تشکیل شود.',
        ];
    }

    public function validatedForModel(): array
    {
        return [
            'title' => trim((string) $this->input('title')),
            'code' => strtolower(trim((string) $this->input('code'))),
            'description' => ($d = $this->input('description')) === '' || $d === null ? null : (string) $d,
            'is_active' => (bool) $this->input('is_active', true),
            'sort_order' => (int) ($this->input('sort_order') ?? 0),
        ];
    }
}
