<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * REQ-03.01–03.05 — customer create validation.
 *
 * THREE THINGS THIS DOES THAT A PLAIN FORM REQUEST DOES NOT:
 *
 * 1. TYPE-SPECIFIC RULES. §3 requires different fields for حقیقی and حقوقی.
 *    Validating both sets unconditionally would force a legal entity to invent
 *    a first name.
 *
 * 2. NATIONAL ID CHECKSUM. An Iranian national ID has a check digit. Validating
 *    it catches transposed digits at data-entry time, which is the cheapest
 *    possible moment.
 *
 * 3. DUPLICATE WARNING. §3 forbids duplicate customers. Rather than silently
 *    blocking (which pushes staff to type "شرکت آریا ۲"), this surfaces the
 *    candidates and asks for explicit confirmation, unless the request carries
 *    `confirm_duplicate`.
 */
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers.create') ?? false;
    }

    public function rules(): array
    {
        $type = (string) $this->input('type');

        $rules = [
            'type' => ['required', 'in:natural,legal'],
            // Derived from the enum rather than written out again: a hardcoded
            // `in:...` list is a second definition of the same domain, and the
            // two drift the first time a status is added to CustomerStatus.
            'status' => ['required', Rule::in(array_column(CustomerStatus::cases(), 'value'))],
            'mobile' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:filter', 'max:190'],
            'address' => ['nullable', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'acquaintance_source' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Websites arrive as a list; each must look like a host or URL.
            'websites' => ['nullable', 'array', 'max:10'],
            'websites.*.url' => ['nullable', 'string', 'max:300'],
            'websites.*.label' => ['nullable', 'string', 'max:60'],

            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];

        if ($type === CustomerType::Legal->value) {
            $rules += [
                'company_name' => ['required', 'string', 'max:190'],
                'registration_number' => ['nullable', 'string', 'max:20'],
                'economic_code' => ['nullable', 'string', 'max:20'],
                'legal_representative' => ['nullable', 'string', 'max:190'],
            ];
        } else {
            $rules += [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'father_name' => ['nullable', 'string', 'max:100'],
                'national_id' => ['nullable', 'string', 'size:10'],
                'birth_date' => ['nullable', 'string', 'max:10'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'نوع مشتری را انتخاب کنید.',
            'type.in' => 'نوع مشتری نامعتبر است.',
            'status.required' => 'وضعیت مشتری را انتخاب کنید.',
            'company_name.required' => 'نام شرکت را وارد کنید.',
            'first_name.required' => 'نام را وارد کنید.',
            'last_name.required' => 'نام خانوادگی را وارد کنید.',
            'national_id.size' => 'کد ملی باید ۱۰ رقم باشد.',
            'email.email' => 'نشانی ایمیل معتبر نیست.',
            'websites.max' => 'حداکثر ۱۰ وب‌سایت قابل ثبت است.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateNationalId($validator);
                $this->validateMobile($validator);
                $this->warnAboutDuplicates($validator);
            },
        ];
    }

    /** Iranian national ID check digit (mod-11). */
    protected function validateNationalId(Validator $validator): void
    {
        $raw = $this->input('national_id');

        if ($raw === null || $raw === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', Money::toAsciiDigits((string) $raw)) ?? '';

        if (strlen($digits) !== 10) {
            return; // the `size:10` rule already reported it
        }

        // Repeating digits are structurally invalid IDs.
        if (preg_match('/^(\d)\1{9}$/', $digits)) {
            $validator->errors()->add('national_id', 'کد ملی وارد شده معتبر نیست.');

            return;
        }

        $check = (int) $digits[9];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * (10 - $i);
        }
        $remainder = $sum % 11;

        $valid = ($remainder < 2 && $check === $remainder)
            || ($remainder >= 2 && $check === 11 - $remainder);

        if (! $valid) {
            $validator->errors()->add('national_id', 'کد ملی وارد شده معتبر نیست (رقم کنترلی مطابقت ندارد).');
        }
    }

    protected function validateMobile(Validator $validator): void
    {
        $raw = $this->input('mobile');

        if ($raw === null || $raw === '') {
            return;
        }

        $normalized = Customer::normalizeMobile((string) $raw);

        // Iranian mobiles are 11 digits starting 09 after normalisation.
        if ($normalized === null || ! preg_match('/^09\d{9}$/', $normalized)) {
            $validator->errors()->add('mobile', 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.');
        }
    }

    protected function warnAboutDuplicates(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->boolean('confirm_duplicate')) {
            return;
        }

        $candidate = new Customer($this->validatedForModel());
        $candidate->setRelation('websites', collect($this->websiteModels()));

        $duplicates = Customer::duplicateCandidates($candidate);

        if ($duplicates === []) {
            return;
        }

        $lines = [];
        foreach (array_slice($duplicates, 0, 3) as $hit) {
            $lines[] = '«' . $hit['customer']->display_name . '» (' . implode('، ', $hit['reasons']) . ')';
        }

        $validator->errors()->add(
            'confirm_duplicate',
            'مشتری مشابه یافت شد: ' . implode(' — ', $lines)
            . '. اگر مشتری جدید است، ثبت را تأیید کنید.'
        );
    }

    /** @return list<\App\Models\CustomerWebsite> */
    protected function websiteModels(): array
    {
        $out = [];
        foreach ((array) $this->input('websites', []) as $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $out[] = new \App\Models\CustomerWebsite([
                'url' => $url,
                'domain' => \App\Models\CustomerWebsite::extractDomain($url),
                'label' => $row['label'] ?? null,
            ]);
        }

        return $out;
    }

    /** Only the attributes that belong on the model. */
    public function validatedForModel(): array
    {
        $data = $this->safe()->except(['websites', 'confirm_duplicate', 'type']);

        // Type is an enum cast; pass the backing value.
        $data['type'] = $this->input('type');

        // Drop the fields that do not apply to this customer type so a stale
        // browser value cannot leak into a legal entity's record.
        foreach (CustomerType::from($data['type'])->forbiddenFields() as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /** Normalised website rows, ready to persist. */
    public function websiteRows(): array
    {
        $rows = [];
        foreach ((array) $this->input('websites', []) as $index => $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $rows[] = [
                'url' => $url,
                'domain' => \App\Models\CustomerWebsite::extractDomain($url),
                'label' => $row['label'] ?? null,
                'is_primary' => $index === 0,
            ];
        }

        return $rows;
    }
}
