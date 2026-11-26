<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Models\ProjectKeyword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * §5-1 / REQ-05.03 — one target keyword.
 *
 * TWO RULES ARE THE POINT OF HAVING ROWS AT ALL:
 *
 * 1. Uniqueness is on `normalized`, not on the raw text. ProjectKeyword::
 *    normalize() folds Arabic ي/ك into Persian ی/ک, turns ZWNJ into a space,
 *    collapses whitespace and lower-cases, so «طراحی سایت», «طراحی  سایت» and
 *    «طراحی‌سایت» are the same keyword. Comparing the raw strings would let all
 *    three in and the count the package is sold on would be fiction.
 *
 * 2. The package's `keyword_limit` is a ceiling on real rows. This is why the
 *    count is computed rather than typed: a limit checked against a
 *    manually-entered number enforces nothing.
 */
final class KeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = (int) $project?->id;
        $currentId = $this->route('keyword')?->id;

        $normalized = ProjectKeyword::normalize((string) $this->input('keyword', ''));

        return [
            'keyword' => [
                'required',
                'string',
                'max:191',
                /*
                 * A closure rather than Rule::unique, for two reasons that each
                 * broke a real check:
                 *
                 * Rule::unique validates the attribute's own value, so it would
                 * compare the RAW text against the normalized column — and
                 * «طراحی‌سایت» (ZWNJ) is not the string «طراحی سایت», so every
                 * spelling variant got through and the table filled with
                 * duplicates.
                 *
                 * And its ->ignore() did not exclude the row being edited, so
                 * correcting a keyword reported it as already taken.
                 *
                 * Comparing normalized to normalized and excluding the current
                 * row by key is the behaviour the check needs.
                 */
                function (string $attribute, mixed $value, callable $fail) use ($projectId, $currentId, $normalized): void {
                    $taken = ProjectKeyword::query()
                        ->where('project_id', $projectId)
                        ->where('normalized', $normalized)
                        ->when($currentId !== null, fn ($query) => $query->whereKey('!=', $currentId))
                        ->exists();

                    if ($taken) {
                        $fail('این کلمهٔ کلیدی پیش‌تر برای همین پروژه ثبت شده است.');
                    }
                },
            ],
            'status' => ['required', Rule::in(array_keys(ProjectKeyword::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['keyword' => 'کلمهٔ کلیدی', 'status' => 'وضعیت'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforcePackageLimit($validator);
        });
    }

    /**
     * Refuse a keyword once the package's limit is reached.
     *
     * Only enforced on create: editing an existing keyword must stay possible
     * at the limit, or a full package would lock its own keywords out of being
     * corrected. A keyword already marked `removed` is not occupying a slot.
     */
    private function enforcePackageLimit(Validator $validator): void
    {
        if ($this->route('keyword') !== null || $validator->errors()->has('keyword')) {
            return;
        }

        $project = $this->route('project');
        $limit = (int) ($project?->seoDetails?->package?->keyword_limit ?? 0);

        // 0 or null means the package sets no ceiling.
        if ($limit <= 0) {
            return;
        }

        $used = (int) $project->keywords()
            ->where('status', '!=', 'removed')
            ->count();

        if ($used >= $limit) {
            $validator->errors()->add(
                'keyword',
                sprintf('سقف کلمات کلیدی این پکیج (%d کلمه) پر شده است.', $limit),
            );
        }
    }
}
