@php
    use App\Enums\CustomerStatus;
    use App\Support\Money;

    /**
     * REQ-03.03 — shared create/edit form.
     *
     * One partial for both modes is deliberate: §12 forbids divergent
     * structures for sibling pages, and a form that changes shape between
     * create and edit is the most common way that rule gets broken.
     *
     * `$customer` is either an empty model (create) or the persisted record
     * (edit). Every value falls back to `old()` first, so a validation error
     * never costs the user their typing.
     */
    $isEdit = $customer->exists;
    $val = fn (string $field) => old($field, $customer->{$field} ?? '');
    $err = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';

    // A field marked invalid visually must also say so to assistive tech, and
    // its error text must be referenced rather than merely placed after it.
    // `aria-describedby` lists the hint always, then the error when present.
    $errState = function (string $field, string $hint = '') use ($errors): string {
        if (! $errors->has($field)) {
            return $hint ? ' aria-describedby="' . $hint . '"' : '';
        }
        $ids = implode(' ', array_filter([$hint, $field . '-error']));

        return ' aria-invalid="true" aria-describedby="' . $ids . '"';
    };

    $currentType = old('type', $customer->type?->value ?? 'legal');
@endphp

<div
    class="v-form"
    x-data="{ type: '{{ $currentType }}' }"
>
    {{-- ── Section: identity ─────────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">اطلاعات اصلی</legend>
        <p class="v-fsection__hint">
            نوع مشتری تعیین می‌کند کدام فیلدها ثبت شوند؛ حقیقی و حقوقی هر دو پروندهٔ یکسانی در سامانه دارند.
        </p>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="type">نوع مشتری <span class="v-req" aria-hidden="true">*</span></label>
                <select id="type" name="type" class="v-select{{ $err('type') }}" x-model="type" required>
                    <option value="legal">حقوقی (شرکت، سازمان)</option>
                    <option value="natural">حقیقی (شخص)</option>
                </select>
            </div>

            <div class="v-field">
                <label class="v-label" for="status">وضعیت <span class="v-req" aria-hidden="true">*</span></label>
                <select id="status" name="status" class="v-select{{ $err('status') }}" required>
                    @foreach (CustomerStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($val('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Legal entity fields --}}
        <div x-show="type === 'legal'" x-cloak class="v-grid v-grid--2">
            <div class="v-field v-field--span2">
                <label class="v-label" for="company_name">
                    نام شرکت <span class="v-req" aria-hidden="true">*</span>
                </label>
                <input id="company_name" name="company_name" type="text"
                       class="v-input{{ $err('company_name') }}" value="{{ $val('company_name') }}"
                       :required="type === 'legal'"
                       {!! $errState('company_name', 'company_name-hint') !!}>
                <p class="v-hint" id="company_name-hint">همان‌طور که در اسناد رسمی آمده است.</p>
                @error('company_name')<p class="v-error" id="company_name-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="registration_number">شماره ثبت</label>
                <input id="registration_number" name="registration_number" type="text"
                       class="v-input{{ $err('registration_number') }}" value="{{ $val('registration_number') }}"
                       inputmode="numeric" dir="ltr">
                @error('registration_number')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="economic_code">کد اقتصادی</label>
                <input id="economic_code" name="economic_code" type="text"
                       class="v-input{{ $err('economic_code') }}" value="{{ $val('economic_code') }}"
                       inputmode="numeric" dir="ltr">
                @error('economic_code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="legal_representative">نمایندهٔ قانونی</label>
                <input id="legal_representative" name="legal_representative" type="text"
                       class="v-input{{ $err('legal_representative') }}" value="{{ $val('legal_representative') }}">
                @error('legal_representative')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Natural person fields --}}
        <div x-show="type === 'natural'" x-cloak class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="first_name">
                    نام <span class="v-req" aria-hidden="true">*</span>
                </label>
                <input id="first_name" name="first_name" type="text"
                       class="v-input{{ $err('first_name') }}" value="{{ $val('first_name') }}"
                       :required="type === 'natural'">
                @error('first_name')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="last_name">
                    نام خانوادگی <span class="v-req" aria-hidden="true">*</span>
                </label>
                <input id="last_name" name="last_name" type="text"
                       class="v-input{{ $err('last_name') }}" value="{{ $val('last_name') }}"
                       :required="type === 'natural'">
                @error('last_name')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="national_id">کد ملی</label>
                <input id="national_id" name="national_id" type="text"
                       class="v-input{{ $err('national_id') }}" value="{{ $val('national_id') }}"
                       inputmode="numeric" maxlength="10" dir="ltr"
                       aria-describedby="national_id-hint">
                <p class="v-hint" id="national_id-hint">۱۰ رقم، با رقم کنترلی معتبر.</p>
                @error('national_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="father_name">نام پدر</label>
                <input id="father_name" name="father_name" type="text"
                       class="v-input{{ $err('father_name') }}" value="{{ $val('father_name') }}">
                @error('father_name')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- ── Section: contact ──────────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">راه‌های تماس</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="mobile">موبایل</label>
                <input id="mobile" name="mobile" type="tel"
                       class="v-input{{ $err('mobile') }}" value="{{ $val('mobile') }}"
                       inputmode="tel" dir="ltr" placeholder="09xxxxxxxxx">
                @error('mobile')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="phone">تلفن ثابت</label>
                <input id="phone" name="phone" type="tel"
                       class="v-input{{ $err('phone') }}" value="{{ $val('phone') }}"
                       inputmode="tel" dir="ltr">
                @error('phone')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="email">ایمیل</label>
                <input id="email" name="email" type="email"
                       class="v-input{{ $err('email') }}" value="{{ $val('email') }}"
                       dir="ltr" autocomplete="off">
                @error('email')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="postal_code">کد پستی</label>
                <input id="postal_code" name="postal_code" type="text"
                       class="v-input{{ $err('postal_code') }}" value="{{ $val('postal_code') }}"
                       inputmode="numeric" dir="ltr" maxlength="10">
                @error('postal_code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="city">شهر</label>
                <input id="city" name="city" type="text"
                       class="v-input{{ $err('city') }}" value="{{ $val('city') }}">
                @error('city')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="province">استان</label>
                <input id="province" name="province" type="text"
                       class="v-input{{ $err('province') }}" value="{{ $val('province') }}">
                @error('province')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="address">نشانی</label>
                <textarea id="address" name="address" rows="2"
                          class="v-textarea{{ $err('address') }}">{{ $val('address') }}</textarea>
                @error('address')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- ── Section: websites (§3: several sites per customer) ────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">وب‌سایت‌ها</legend>
        <p class="v-fsection__hint">
            دامنهٔ هر سایت برای جست‌وجو و شناسایی مشتری ذخیره می‌شود. اولین وب‌سایت، سایت اصلی در نظر گرفته می‌شود.
        </p>

        <div class="v-repeat" x-data="{ rows: {{ max(1, count(old('websites', $customer->websites->map(fn ($w) => ['url' => $w->url, 'label' => $w->label])->all() ?: [['url' => '', 'label' => '']]))) }} }">
            <template x-for="(row, i) in rows" :key="i">
                <div class="v-repeat__row">
                    <div class="v-field">
                        <label class="v-label" x-bind:for="'website-' + i">نشانی سایت</label>
                        {{-- The repeated rows are a real array submit: PHP receives
                             websites[0][url], websites[0][label], … --}}
                        <input type="text" x-bind:name="'websites[' + i + '][url]'"
                               x-bind:id="'website-' + i"
                               class="v-input" dir="ltr" placeholder="example.com">
                    </div>
                    <div class="v-field">
                        <label class="v-label" x-bind:for="'website-label-' + i">برچسب</label>
                        <input type="text" x-bind:name="'websites[' + i + '][label]'"
                               x-bind:id="'website-label-' + i"
                               class="v-input" placeholder="سایت اصلی">
                    </div>
                    <button type="button" class="v-iconbtn v-repeat__remove" x-show="rows > 1"
                            @click="rows--" aria-label="حذف این ردیف">
                        <x-icon name="x" :size="16" />
                    </button>
                </div>
            </template>

            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="rows++">
                <x-icon name="plus" :size="14" /> افزودن وب‌سایت
            </button>
        </div>

        @error('websites.*.url')<p class="v-error">{{ $message }}</p>@enderror
    </fieldset>

    {{-- ── Section: relationship ─────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">شناخت مشتری</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="acquaintance_source">نحوهٔ آشنایی</label>
                <input id="acquaintance_source" name="acquaintance_source" type="text"
                       class="v-input{{ $err('acquaintance_source') }}"
                       value="{{ $val('acquaintance_source') }}"
                       placeholder="معرفی، شبکه‌های اجتماعی، نمایشگاه…">
                @error('acquaintance_source')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="v-field">
            <label class="v-label" for="notes">یادداشت</label>
            <textarea id="notes" name="notes" rows="3"
                      class="v-textarea{{ $err('notes') }}">{{ $val('notes') }}</textarea>
            @error('notes')<p class="v-error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    {{-- Duplicate confirmation. The server sets an error on this field when it
         finds a likely match; the checkbox is how the user says "yes, it really
         is a new customer". --}}
    @error('confirm_duplicate')
        <div class="v-confirmbox">
            <div class="v-confirmbox__icon"><x-icon name="warning" :size="20" /></div>
            <div class="v-confirmbox__body">
                <p class="v-confirmbox__title">مشتری مشابه پیدا شد</p>
                <p class="v-confirmbox__text">{{ $message }}</p>
                <label class="v-check">
                    <input type="checkbox" name="confirm_duplicate" value="1">
                    <span>تأیید می‌کنم این مشتری جدید است و باید جداگانه ثبت شود.</span>
                </label>
            </div>
        </div>
    @enderror

    {{-- ── Actions ───────────────────────────────────────────────────── --}}
    <div class="v-formactions">
        <a href="{{ $isEdit ? route('customers.show', $customer) : route('customers.index') }}"
           class="v-btn v-btn--ghost">انصراف</a>

        <button type="submit" class="v-btn v-btn--primary" data-guard>
            {{ $isEdit ? 'ذخیره تغییرات' : 'ثبت مشتری' }}
        </button>
    </div>
</div>
