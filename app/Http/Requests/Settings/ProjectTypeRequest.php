<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 / REQ-15.01 — «تعریف انواع پروژه».
 *
 * The `project_types` table existed and was seeded with exactly the two kinds
 * `Project::TYPES` names, and nothing read it: the form and the validator both
 * used the constant, so the table was a copy of a constant nobody could edit.
 * `ProjectType::options()` now feeds both, which is what makes a row here mean
 * something.
 */
final class ProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('projectType')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:64',
                // Lowercase only: `projects.type` stores this string and the
                // behavioural map keys are lowercase.
                'regex:/^[a-z][a-z0-9._-]*$/',
                Rule::unique('project_types', 'code')->ignore($currentId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'عنوان نوع پروژه', 'code' => 'کد نوع پروژه'];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'نوع پروژه‌ای با این کد پیش‌تر ثبت شده است.',
            'code.regex' => 'کد تنها می‌تواند از حروف کوچک لاتین، رقم و - _ . تشکیل شود.',
        ];
    }

    public function validatedForModel(): array
    {
        return [
            'title' => trim((string) $this->input('title')),
            'code' => strtolower(trim((string) $this->input('code'))),
            'is_active' => (bool) $this->input('is_active', true),
        ];
    }
}
