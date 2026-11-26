<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 / REQ-15.01 — an SEO service package.
 *
 * `seo_packages` was read by the project form and by nothing else: there was no
 * way to create one, change its keyword ceiling, or retire it. The keyword
 * limit that ProjectKeyword enforces was therefore a number nobody could move.
 *
 * `keyword_limit` is nullable and 0 means "no ceiling", which is what
 * KeywordRequest already assumes. The field accepts Persian digits because
 * every other numeric field in this product does.
 */
final class SeoPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('seoPackage')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9._-]*$/i',
                Rule::unique('seo_packages', 'code')->ignore($currentId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان پکیج',
            'code' => 'کد پکیج',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'پکیجی با این کد پیش‌تر ثبت شده است.',
            'code.regex' => 'کد پکیج تنها می‌تواند از حروف، رقم و - _ . تشکیل شود.',
        ];
    }

    /** Attributes ready for the model. */
    public function validatedForModel(): array
    {
        $limit = Money::toAsciiDigits(trim((string) $this->input('keyword_limit', '')));

        return [
            'title' => (string) $this->input('title'),
            'code' => (string) $this->input('code'),
            'description' => ($d = $this->input('description')) === '' || $d === null ? null : (string) $d,
            // Empty means "unlimited", stored as NULL rather than 0 so the two
            // meanings stay distinguishable in the data.
            'keyword_limit' => $limit === '' || ! ctype_digit($limit) ? null : (int) $limit,
            'is_active' => (bool) $this->input('is_active', true),
            'sort_order' => (int) ($this->input('sort_order') ?? 0),
        ];
    }
}
