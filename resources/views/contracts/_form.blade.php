@php
    use App\Support\JalaliDate;
    use App\Support\Money;

    /**
     * REQ-04.01–04.06 — shared create/edit contract form.
     *
     * DATES ARE SHOWN IN JALALI (§11). The stored values are Gregorian dates;
     * this partial converts them for display, and StoreContractRequest converts
     * them back. A Gregorian date is never shown to a Persian-speaking user and
     * a Jalali string is never stored.
     */
    $isEdit = $contract->exists;

    // Prefer the submitted value after a validation error, then the model.
    $jalali = function (string $field) use ($contract): string {
        $old = old($field);
        if ($old !== null) {
            return (string) $old;
        }

        $date = $contract->{$field} ?? null;
        if ($date === null) {
            return '';
        }

        // Persian digits in the field, ASCII slashes: what users type.
        return Money::toPersianDigits(
            JalaliDate::fromCarbon(\Carbon\CarbonImmutable::parse($date))->formatAscii()
        );
    };

    $val = fn (string $f) => old($f, $contract->{$f} ?? '');
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
@endphp

<div class="v-form">
    {{-- ── Section: parties ──────────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">طرفین قرارداد</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="customer_id">
                    مشتری <span class="v-req" aria-hidden="true">*</span>
                </label>
                <select id="customer_id" name="customer_id" class="v-select{{ $err('customer_id') }}" required>
                    <option value="">— انتخاب مشتری —</option>
                    @foreach ($customers as $option)
                        <option value="{{ $option->id }}"
                            @selected((string) old('customer_id', $contract->customer_id ?? $preselectedCustomer ?? '') === (string) $option->id)>
                            {{ $option->display_name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="v-error">{{ $message }}</p>@enderror
                <p class="v-hint">
                    مشتری در فهرست نیست؟
                    @if (auth()->user()->hasPermission('customers.create'))
                        <a class="v-link" href="{{ route('customers.create') }}">ابتدا مشتری را ثبت کنید</a>.
                    @endif
                </p>
            </div>

            <div class="v-field">
                <label class="v-label" for="contract_type_id">نوع قرارداد</label>
                <select id="contract_type_id" name="contract_type_id" class="v-select{{ $err('contract_type_id') }}">
                    <option value="">— بدون دسته‌بندی —</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected((string) old('contract_type_id', $contract->contract_type_id ?? '') === (string) $type->id)>
                            {{ $type->title }}
                        </option>
                    @endforeach
                </select>
                @error('contract_type_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- ── Section: terms ────────────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">موضوع و شرایط</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field v-field--span2">
                <label class="v-label" for="title">عنوان قرارداد <span class="v-req" aria-hidden="true">*</span></label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" required maxlength="190"
                       placeholder="مثلاً: قرارداد سئو و بهینه‌سازی سایت">
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="amount">مبلغ (ریال) <span class="v-req" aria-hidden="true">*</span></label>
                <input id="amount" name="amount" type="text" class="v-input{{ $err('amount') }}"
                       value="{{ $val('amount') }}" required inputmode="numeric" dir="ltr"
                       aria-describedby="amount-hint">
                <p class="v-hint" id="amount-hint">فقط رقم؛ جداکنندهٔ هزارگان اختیاری است.</p>
                @error('amount')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="status">وضعیت <span class="v-req" aria-hidden="true">*</span></label>
                <select id="status" name="status" class="v-select{{ $err('status') }}" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $contract->status?->value ?? 'draft') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('status')<p class="v-error">{{ $message }}</p>@enderror
                <p class="v-hint">وضعیت «در آستانه انقضا» به‌صورت خودکار از تاریخ پایان محاسبه می‌شود.</p>
            </div>

            <div class="v-field">
                <label class="v-label" for="start_date">تاریخ شروع <span class="v-req" aria-hidden="true">*</span></label>
                <input id="start_date" name="start_date" type="text" class="v-input{{ $err('start_date') }}"
                       value="{{ $jalali('start_date') }}" required data-jalali inputmode="numeric"
                       placeholder="۱۴۰۴/۰۱/۱۵" aria-describedby="start_date-hint">
                <p class="v-hint" id="start_date-hint">شمسی، به شکل ۱۴۰۴/۰۱/۱۵</p>
                @error('start_date')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="end_date">تاریخ پایان</label>
                <input id="end_date" name="end_date" type="text" class="v-input{{ $err('end_date') }}"
                       value="{{ $jalali('end_date') }}" data-jalali inputmode="numeric"
                       placeholder="۱۴۰۵/۰۱/۱۴" aria-describedby="end_date-hint">
                <p class="v-hint" id="end_date-hint">خالی بگذارید اگر قرارداد پایان باز دارد.</p>
                @error('end_date')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="description">شرح موضوع</label>
                <textarea id="description" name="description" rows="4"
                          class="v-textarea{{ $err('description') }}">{{ $val('description') }}</textarea>
                @error('description')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- ── Section: internal ─────────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">یادداشت داخلی</legend>
        <p class="v-fsection__hint">این بخش فقط برای تیم داخلی است و در متن قرارداد نمی‌آید.</p>

        <div class="v-field">
            <label class="v-label" for="internal_notes">یادداشت</label>
            <textarea id="internal_notes" name="internal_notes" rows="3"
                      class="v-textarea{{ $err('internal_notes') }}">{{ $val('internal_notes') }}</textarea>
            @error('internal_notes')<p class="v-error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <div class="v-formactions">
        <a href="{{ $isEdit ? route('contracts.show', $contract) : route('contracts.index') }}"
           class="v-btn v-btn--ghost">انصراف</a>
        <button type="submit" class="v-btn v-btn--primary" data-guard>
            {{ $isEdit ? 'ذخیره تغییرات' : 'ثبت قرارداد' }}
        </button>
    </div>
</div>
