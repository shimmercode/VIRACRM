<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 — employee create/edit validation.
 *
 * THREE RULES THAT MATTER FOR REAL DATA
 * -------------------------------------
 * 1. `national_id` is a 10-digit Iranian ID with a check digit. Validating only
 *    "10 numbers" lets a typo through, and that typo then ends up on an insurance
 *    filing. The check digit is computed here.
 * 2. National id and mobile are unique-ish in practice: two employees with the
 *    same national id is a duplicate payroll record, which double-pays a person.
 *    Duplicates are refused (excluding the record being edited).
 * 3. Dates are Jalali (§11) and stored Gregorian. An end date before the start
 *    date is refused rather than silently stored.
 *
 * There is NO salary field, by OD-16.
 */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('employee') ? 'personnel.update' : 'personnel.create';

        return $this->user()?->hasPermission($ability) ?? false;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'code' => ['nullable', 'string', 'max:32', Rule::unique('employees', 'code')->ignore($employeeId)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            // Not `exists:employees,id` only — a person cannot be their own
            // manager, which would make the org chart a one-node cycle.
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => [
                'nullable', 'string', 'regex:/^[0-9]{10}$/',
                Rule::unique('employees', 'national_id')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:1000'],
            'position' => ['nullable', 'string', 'max:190'],
            'start_date' => ['nullable', 'string', 'max:10'],
            'end_date' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'نام کارمند را وارد کنید.',
            'last_name.required' => 'نام خانوادگی کارمند را وارد کنید.',
            'status.required' => 'وضعیت کارمند را انتخاب کنید.',
            'national_id.regex' => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
            'national_id.unique' => 'این کد ملی قبلاً برای کارمند دیگری ثبت شده است.',
            'mobile.regex' => 'شمارهٔ همراه باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
            'email.email' => 'ایمیل معتبر نیست.',
            'code.unique' => 'این کد پرسنلی قبلاً استفاده شده است.',
            'user_id.exists' => 'کاربر انتخاب‌شده معتبر نیست.',
            'department_id.exists' => 'واحد سازمانی انتخاب‌شده معتبر نیست.',
            'manager_id.exists' => 'مدیر انتخاب‌شده معتبر نیست.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $this->checkNationalId($validator);
                $this->checkDates($validator);
                $this->checkManager($validator);
            },
        ];
    }

    /**
     * Iranian national id check digit.
     *
     * Ten identical digits (0000000000, 1111111111 …) are rejected on purpose:
     * they pass the check digit but are the placeholder everyone types to skip a
     * required field, and they are what makes two different employees collide.
     */
    private function checkNationalId($validator): void
    {
        $id = (string) $this->input('national_id', '');

        if ($id === '') {
            return;
        }

        if (preg_match('/^(\d)\1{9}$/', $id) === 1) {
            $validator->errors()->add('national_id', 'کد ملی نمی‌تواند ده رقم یکسان باشد.');

            return;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $id[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $id[9];
        $valid = ($remainder < 2 && $check === $remainder) || ($remainder >= 2 && $check === 11 - $remainder);

        if (! $valid) {
            $validator->errors()->add('national_id', 'کد ملی معتبر نیست؛ رقم کنترل آن درست نیست.');
        }
    }

    /** A manager must not be the employee themselves. */
    private function checkManager($validator): void
    {
        $self = $this->route('employee')?->id;
        $managerId = $this->input('manager_id');

        if ($self !== null && $managerId !== null && $managerId !== '' && (int) $managerId === (int) $self) {
            $validator->errors()->add('manager_id', 'یک کارمند نمی‌تواند مدیر خودش باشد.');
        }
    }

    private function checkDates($validator): void
    {
        $start = null;

        if ($this->filled('start_date')) {
            $start = JalaliDate::parse((string) $this->input('start_date'));

            if ($start === null) {
                $validator->errors()->add('start_date', 'تاریخ شروع معتبر نیست. قالب: ۱۴۰۳/۰۱/۱۵');
            }
        }

        if (! $this->filled('end_date')) {
            return;
        }

        $end = JalaliDate::parse((string) $this->input('end_date'));

        if ($end === null) {
            $validator->errors()->add('end_date', 'تاریخ پایان معتبر نیست. قالب: ۱۴۰۳/۱۲/۲۹');

            return;
        }

        if ($start !== null && $end->toCarbon()->lessThan($start->toCarbon())) {
            $validator->errors()->add('end_date', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.');
        }
    }

    /** Attributes ready for the model. */
    public function validatedForModel(): array
    {
        $data = $this->safe()->only([
            'first_name', 'last_name', 'national_id', 'mobile', 'email',
            'address', 'position', 'status', 'notes',
        ]);

        $data['code'] = $this->filled('code') ? (string) $this->input('code') : null;
        $data['user_id'] = $this->filled('user_id') ? (int) $this->input('user_id') : null;
        $data['department_id'] = $this->filled('department_id') ? (int) $this->input('department_id') : null;
        $data['manager_id'] = $this->filled('manager_id') ? (int) $this->input('manager_id') : null;

        $data['start_date'] = $this->filled('start_date')
            ? JalaliDate::parse((string) $this->input('start_date'))?->toDateString()
            : null;

        $data['end_date'] = $this->filled('end_date')
            ? JalaliDate::parse((string) $this->input('end_date'))?->toDateString()
            : null;

        return $data;
    }
}
