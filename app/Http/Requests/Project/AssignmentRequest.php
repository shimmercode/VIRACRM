<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * §5-3 / REQ-05.05 — attaching an expert to a project.
 *
 * The requirement is «امکان ثبت مسئول پروژه»: recording who works on a project.
 * That is an edit of the project, so the route is gated by `projects.update`
 * rather than by a new permission area — a person who may change a project may
 * say who is on it.
 *
 * TWO RULES HERE EXIST BECAUSE OF THE SCHEMA, NOT CONVENIENCE:
 *
 *   1. `project_assignments` has UNIQUE(project_id, employee_id). Without an
 *      explicit unique rule a second attach would reach the database and come
 *      back as a 500 constraint violation — an error page where the user needed
 *      a sentence. The rule makes it a field error instead.
 *
 *   2. `amount` and `coefficient` are NOT NULL with defaults. An empty field
 *      must therefore become 0 / 100, never NULL, or the insert fails.
 *
 * Dates arrive as Jalali (§11) and are stored as ISO, exactly as the contract
 * form does; an impossible Jalali date is a field error, not a stored NULL.
 */
final class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route middleware already checked `projects.update`; nothing else
        // about an assignment needs a separate authorisation decision.
        return true;
    }

    public function rules(): array
    {
        $projectId = (int) $this->route('project')?->id;
        $currentId = $this->route('assignment')?->id;

        return [
            /*
             * An employee who was soft-deleted must not be selectable: the
             * workload report counts assignments joined to employees, so a
             * deleted expert would either vanish from a report that still
             * counts the project, or resurrect. Refuse it here.
             */
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->whereNull('deleted_at'),
                Rule::unique('project_assignments', 'employee_id')
                    ->where('project_id', $projectId)
                    ->ignore($currentId),
            ],
            'role' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'کارشناس',
            'role' => 'نقش',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.unique' => 'این کارشناس پیش‌تر به همین پروژه تخصیص یافته است.',
            'employee_id.exists' => 'کارشناس انتخاب‌شده وجود ندارد یا حذف شده است.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateMoney($validator);
            $this->validateDates($validator);
        });
    }

    private function validateMoney(Validator $validator): void
    {
        $amount = Money::parse((string) $this->input('amount', '0'));

        if ($this->filled('amount') && $amount === null) {
            $validator->errors()->add('amount', 'مبلغ تخصیص معتبر نیست.');

            return;
        }

        if ($amount !== null && $amount->isNegative()) {
            $validator->errors()->add('amount', 'مبلغ تخصیص نمی‌تواند منفی باشد.');
        }

        $coefficient = $this->input('coefficient', '');
        if ($coefficient === '' || $coefficient === null) {
            return;
        }

        if (! ctype_digit(Money::toAsciiDigits((string) $coefficient))) {
            $validator->errors()->add('coefficient', 'ضریب باید یک عدد صحیح باشد.');

            return;
        }

        // 100 is "the full project fee". Above 100 is a deliberate multiplier,
        // so it is allowed but bounded: an unbounded percent field is a place
        // to put a typo, and this figure feeds the workload report.
        if ((int) Money::toAsciiDigits((string) $coefficient) > 1000) {
            $validator->errors()->add('coefficient', 'ضریب نمی‌تواند بیش از ۱۰۰۰ درصد باشد.');
        }
    }

    private function validateDates(Validator $validator): void
    {
        $started = $this->input('started_on');
        $ended = $this->input('ended_on');

        $from = null;
        if ($started !== null && $started !== '') {
            $from = JalaliDate::parse((string) $started);
            if ($from === null) {
                $validator->errors()->add('started_on', 'تاریخ شروع معتبر نیست. قالب صحیح: ۱۴۰۴/۰۱/۱۵');
            }
        }

        if ($ended === null || $ended === '') {
            return;
        }

        $to = JalaliDate::parse((string) $ended);

        if ($to === null) {
            $validator->errors()->add('ended_on', 'تاریخ پایان معتبر نیست. قالب صحیح: ۱۴۰۴/۱۲/۲۹');

            return;
        }

        if ($from !== null && $to->toCarbon()->lessThan($from->toCarbon())) {
            $validator->errors()->add('ended_on', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.');
        }
    }

    /** Attributes ready for the model: dates to ISO, money to rials. */
    public function validatedForModel(): array
    {
        $amount = Money::parse((string) $this->input('amount', '0'));

        $coefficientRaw = Money::toAsciiDigits((string) $this->input('coefficient', ''));
        $coefficient = ctype_digit($coefficientRaw) ? (int) $coefficientRaw : 100;

        return [
            'employee_id' => (int) $this->input('employee_id'),
            'role' => ($role = $this->input('role')) === '' || $role === null ? null : (string) $role,
            // NOT NULL columns: an empty amount means "no fee agreed yet", not NULL.
            'amount' => (int) ($amount?->amount ?? 0),
            'coefficient' => $coefficient < 0 || $coefficient > 1000 ? 100 : $coefficient,
            'started_on' => JalaliDate::parse((string) ($this->input('started_on') ?? ''))?->toDateString(),
            'ended_on' => JalaliDate::parse((string) ($this->input('ended_on') ?? ''))?->toDateString(),
            'is_active' => (bool) $this->input('is_active', true),
        ];
    }
}
