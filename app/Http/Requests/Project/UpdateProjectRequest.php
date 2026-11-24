<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * REQ-05.01–05.05 — project update validation.
 *
 * Identical to the create rules. A project's type is editable, which means the
 * controller must clean up the *other* type's detail row when it changes; see
 * ProjectController::update.
 *
 * THREE THINGS THAT ARE EASY TO GET WRONG, AND ARE GUARDED HERE
 * -------------------------------------------------------------
 * 1. A contract belongs to exactly one customer. Picking customer A and
 *    contract of customer B would create a project whose contract and customer
 *    disagree — and the money would then be attributed to the wrong client.
 *    `validateContractCustomer` refuses it.
 *
 * 2. The contract is optional, but if given it must exist. A dangling id would
 *    silently produce a project with no link.
 *
 * 3. Dates arrive Jalali (§11) and are stored Gregorian. An impossible Jalali
 *    date (1404/12/30) is a Persian error, not a silent rollover.
 */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('projects.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'type' => ['required', Rule::in(array_keys(Project::TYPES))],
            'title' => ['required', 'string', 'max:190'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'amount' => ['nullable', 'string', 'max:32'],
            'start_date' => ['nullable', 'string', 'max:10'],
            'end_date' => ['nullable', 'string', 'max:10'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // SEO-only, ignored for design projects (see validatedForModel).
            'seo_package_id' => ['nullable', 'integer', 'exists:seo_packages,id'],
            'keyword_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'recurrence' => ['nullable', Rule::in(array_keys(\App\Models\SeoProjectDetail::RECURRENCES))],
            'reminder_offset_days' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Design-only, ignored for SEO projects.
            'domain' => ['nullable', 'string', 'max:190'],
            'site_type' => ['nullable', 'string', 'max:48'],
            'services' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'مشتری پروژه را انتخاب کنید.',
            'customer_id.exists' => 'مشتری انتخاب‌شده معتبر نیست.',
            'type.required' => 'نوع پروژه را انتخاب کنید.',
            'type.in' => 'نوع پروژه معتبر نیست.',
            'title.required' => 'عنوان پروژه را وارد کنید.',
            'status.required' => 'وضعیت پروژه را انتخاب کنید.',
            'contract_id.exists' => 'قرارداد انتخاب‌شده معتبر نیست.',
            'owner_user_id.exists' => 'مسئول انتخاب‌شده معتبر نیست.',
            'seo_package_id.exists' => 'پکیج سئو معتبر نیست.',
            'recurrence.in' => 'دوره تکرار معتبر نیست.',
            'domain.max' => 'دامنه بیش از حد طولانی است.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $this->validateContractCustomer($validator);
                $this->validateAmount($validator);
                $this->validateDates($validator);
                $this->validateDomain($validator);
            },
        ];
    }

    /** The chosen contract must belong to the chosen customer. */
    protected function validateContractCustomer($validator): void
    {
        $contractId = $this->input('contract_id');
        $customerId = $this->input('customer_id');

        if ($contractId === null || $contractId === '' || $customerId === null || $customerId === '') {
            return;
        }

        $contract = \App\Models\Contract::query()
            ->select('id', 'customer_id', 'title')
            ->find($contractId);

        if ($contract === null) {
            return; // the exists rule already reported it
        }

        if ((int) $contract->customer_id !== (int) $customerId) {
            $validator->errors()->add(
                'contract_id',
                'قرارداد انتخاب‌شده متعلق به این مشتری نیست. قرارداد «' . $contract->title . '» برای مشتری دیگری ثبت شده است.'
            );
        }
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
            $validator->errors()->add('amount', 'مبلغ پروژه نمی‌تواند منفی باشد.');
        }
    }

    protected function validateDates($validator): void
    {
        $start = null;

        if ($this->filled('start_date')) {
            $start = JalaliDate::parse((string) $this->input('start_date'));

            if ($start === null) {
                $validator->errors()->add('start_date', 'تاریخ شروع معتبر نیست. قالب صحیح: ۱۴۰۴/۰۱/۱۵');
            }
        }

        if (! $this->filled('end_date')) {
            return;
        }

        $end = JalaliDate::parse((string) $this->input('end_date'));

        if ($end === null) {
            $validator->errors()->add('end_date', 'تاریخ پایان معتبر نیست. قالب صحیح: ۱۴۰۴/۱۲/۲۹');

            return;
        }

        if ($start !== null && $end->toCarbon()->lessThan($start->toCarbon())) {
            $validator->errors()->add('end_date', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.');
        }
    }

    /**
     * A domain is shown to staff and used as the project's public identity, so
     * it must not be a free-text field where "example.com " and "http://..." are
     * accepted as-is. Accept a bare host, optionally with scheme, and validate
     * the host part.
     */
    protected function validateDomain($validator): void
    {
        if ($this->input('type') !== 'design' || ! $this->filled('domain')) {
            return;
        }

        $raw = trim((string) $this->input('domain'));
        $host = preg_replace('#^https?://#i', '', $raw) ?? $raw;
        $host = rtrim(mb_strtolower($host), '/');

        if ($host === '' || ! preg_match('/^[a-z0-9\x{0600}-\x{06FF}]([a-z0-9\x{0600}-\x{06FF}.\-]*)$/u', $host)) {
            $validator->errors()->add('domain', 'دامنه معتبر نیست. نمونه: example.com');

            return;
        }

        $this->merge(['domain' => $host]);
    }

    /** Attributes ready for the model. */
    public function validatedForModel(): array
    {
        $data = $this->safe()->only([
            'title', 'status', 'owner_user_id', 'notes',
        ]);

        $data['customer_id'] = (int) $this->input('customer_id');
        $data['contract_id'] = $this->filled('contract_id') ? (int) $this->input('contract_id') : null;
        $data['type'] = (string) $this->input('type');
        $data['owner_user_id'] = $this->filled('owner_user_id') ? (int) $this->input('owner_user_id') : null;

        $data['amount'] = (int) (Money::parse((string) $this->input('amount', ''))?->amount ?? 0);

        $start = $this->filled('start_date')
            ? JalaliDate::parse((string) $this->input('start_date'))
            : null;
        $data['start_date'] = $start?->toDateString();

        $end = $this->filled('end_date')
            ? JalaliDate::parse((string) $this->input('end_date'))
            : null;
        $data['end_date'] = $end?->toDateString();

        return $data;
    }

    /** SEO detail row, or null when this is a design project. */
    public function seoDetailAttributes(): ?array
    {
        if ($this->input('type') !== 'seo') {
            return null;
        }

        return [
            'seo_package_id' => $this->filled('seo_package_id') ? (int) $this->input('seo_package_id') : null,
            'keyword_count' => (int) ($this->input('keyword_count') ?? 0),
            'recurrence' => $this->filled('recurrence') ? (string) $this->input('recurrence') : null,
            'reminder_offset_days' => (int) ($this->input('reminder_offset_days') ?? 2),
        ];
    }

    /** Design detail row, or null when this is an SEO project. */
    public function designDetailAttributes(): ?array
    {
        if ($this->input('type') !== 'design') {
            return null;
        }

        return [
            'domain' => $this->filled('domain') ? (string) $this->input('domain') : null,
            'site_type' => $this->filled('site_type') ? (string) $this->input('site_type') : null,
            'services' => $this->filled('services') ? (string) $this->input('services') : null,
            'notes' => null,
        ];
    }
}
