<?php

namespace App\Http\Requests\Contract;

use App\Enums\ContractStatus;
use App\Support\JalaliDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * REQ-04.01–04.06 — contract create validation.
 *
 * DATE HANDLING: the form shows Jalali dates (§11). The request accepts Jalali
 * input and converts to Gregorian for storage, because §11 also requires
 * standard storage. A user who types an impossible Jalali date (1404/12/30)
 * gets a Persian error instead of a silent rollover into the next year.
 *
 * MONEY HANDLING: the amount arrives as text with Persian digits and thousands
 * separators. Money::parse returns null for anything ambiguous, which becomes a
 * validation error rather than a zero amount.
 */
class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('contracts.create') ?? false;
    }

    public function rules(): array
    {
        $selectable = array_map(fn (ContractStatus $s) => $s->value, ContractStatus::selectable());

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'string', 'max:32'],
            'start_date' => ['required', 'string', 'max:10'],
            'end_date' => ['nullable', 'string', 'max:10'],
            'status' => ['required', 'in:' . implode(',', $selectable)],
            'party_one_name' => ['nullable', 'string', 'max:190'],
            'party_two_name' => ['nullable', 'string', 'max:190'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'مشتری قرارداد را انتخاب کنید.',
            'customer_id.exists' => 'مشتری انتخاب‌شده معتبر نیست.',
            'title.required' => 'عنوان قرارداد را وارد کنید.',
            'amount.required' => 'مبلغ قرارداد را وارد کنید.',
            'start_date.required' => 'تاریخ شروع را وارد کنید.',
            'status.required' => 'وضعیت قرارداد را انتخاب کنید.',
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $this->validateAmount($validator);
            $this->validateDates($validator);
        }];
    }

    protected function validateAmount($validator): void
    {
        $raw = $this->input('amount');

        if ($raw === null || $raw === '') {
            return;
        }

        $money = Money::parse((string) $raw);

        if ($money === null) {
            $validator->errors()->add('amount', 'مبلغ وارد‌شده معتبر نیست. فقط رقم به ریال وارد کنید.');

            return;
        }

        if ($money->isNegative()) {
            $validator->errors()->add('amount', 'مبلغ قرارداد نمی‌تواند منفی باشد.');
        }
    }

    protected function validateDates($validator): void
    {
        $start = JalaliDate::parse((string) $this->input('start_date'));

        if ($this->filled('start_date') && $start === null) {
            $validator->errors()->add('start_date', 'تاریخ شروع معتبر نیست. قالب صحیح: ۱۴۰۴/۰۱/۱۵');
        }

        $endRaw = (string) $this->input('end_date', '');
        if ($endRaw === '') {
            return;
        }

        $end = JalaliDate::parse($endRaw);

        if ($end === null) {
            $validator->errors()->add('end_date', 'تاریخ پایان معتبر نیست. قالب صحیح: ۱۴۰۴/۱۲/۲۹');

            return;
        }

        if ($start !== null && $end->toCarbon()->lessThan($start->toCarbon())) {
            $validator->errors()->add('end_date', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.');
        }
    }

    /** Attributes ready for the model, with dates and money converted. */
    public function validatedForModel(): array
    {
        $data = $this->safe()->except(['start_date', 'end_date', 'amount', 'customer_id']);
        $data['customer_id'] = (int) $this->input('customer_id');

        $start = JalaliDate::parse((string) $this->input('start_date'));
        $data['start_date'] = $start?->toDateString();

        $endRaw = (string) $this->input('end_date', '');
        $end = $endRaw !== '' ? JalaliDate::parse($endRaw) : null;
        $data['end_date'] = $end?->toDateString();

        $data['amount'] = (int) (Money::parse((string) $this->input('amount'))?->amount ?? 0);

        // A contract with no end date cannot be "active" in any scheduling
        // sense; keep it a draft rather than letting the expiry logic guess.
        if (($data['status'] ?? '') === ContractStatus::Active->value && $data['end_date'] === null) {
            $data['status'] = ContractStatus::Draft->value;
        }

        return $data;
    }

    /** Echo Jalali values back into the form after a validation failure. */
    public function jalaliInput(): array
    {
        $out = [];

        foreach (['start_date', 'end_date'] as $field) {
            $value = (string) $this->input($field, '');
            $out[$field] = $value;
        }

        return $out;
    }
}
