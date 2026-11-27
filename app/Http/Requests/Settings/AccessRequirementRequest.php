<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 / REQ-15.01 — «فهرست دسترسی‌های الزامی», and §6's gate.
 *
 * `credential_requirements` was read by FinalizationGate::blockingFor() but had
 * no screen, so the list of accesses that stop an SEO project being activated
 * was whatever the seeder wrote. This is the screen that changes it, and
 * changing it changes the gate immediately — no deployment.
 */
final class AccessRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('accessRequirement')?->id;

        return [
            'project_type' => ['required', 'string', Rule::in(array_keys(ProjectType::options()))],
            'credential_type_id' => [
                'required',
                'integer',
                Rule::exists('credential_types', 'id')->where('is_active', true),
                // The table has a unique index on this pair; catching it here
                // turns a 500 into a field error.
                Rule::unique('credential_requirements')
                    ->where('project_type', (string) $this->input('project_type'))
                    ->ignore($currentId),
            ],
            'is_required' => ['nullable', 'boolean'],
            'blocks_finalize' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'project_type' => 'نوع پروژه',
            'credential_type_id' => 'نوع دسترسی',
        ];
    }

    public function messages(): array
    {
        return [
            'credential_type_id.unique' => 'این دسترسی پیش‌تر برای این نوع پروژه ثبت شده است.',
            'credential_type_id.exists' => 'نوع دسترسی انتخاب‌شده وجود ندارد یا غیرفعال است.',
            'project_type.in' => 'نوع پروژه معتبر نیست.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /*
             * blockingFor() only counts a requirement that is BOTH required and
             * blocking, so "blocks finalisation" on a non-required access does
             * nothing at all. Refusing the combination here is better than
             * saving a row that looks like a rule and is not one.
             */
            if (! $this->input('is_required') && $this->input('blocks_finalize')) {
                $validator->errors()->add(
                    'blocks_finalize',
                    'دسترسی غیرالزامی نمی‌تواند جلوی نهایی‌سازی را بگیرد؛ ابتدا «الزامی» را علامت بزنید.',
                );
            }
        });
    }

    public function validatedForModel(): array
    {
        return [
            'project_type' => (string) $this->input('project_type'),
            'credential_type_id' => (int) $this->input('credential_type_id'),
            'is_required' => (bool) $this->input('is_required', false),
            'blocks_finalize' => (bool) $this->input('blocks_finalize', false),
        ];
    }
}
