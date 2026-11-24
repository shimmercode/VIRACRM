@php
    use App\Support\JalaliDate;
    use App\Support\Money;

    /**
     * REQ-05.01–05.05 — shared create/edit project form.
     *
     * THE TYPE SWITCH
     * ---------------
     * §5 gives SEO projects a package and a keyword count, and design projects
     * a domain and a site type. Both sets of fields are in this form but shown
     * one at a time: changing the type select toggles which fieldset is
     * visible. The hidden one is also `disabled` via JS so the browser does not
     * submit it — a server that receives a stray `seo_package_id` on a design
     * project would ignore it (the request strips it by type), but not sending
     * it at all is cleaner and avoids a confusing "why is this filled in" when
     * the user switches back.
     *
     * No-JS behaviour: both fieldsets render and the server still ignores the
     * irrelevant one. Nothing is lost except the visual filtering.
     */
    $isEdit = $project->exists;

    $jalali = function (string $field) use ($project): string {
        $old = old($field);
        if ($old !== null) {
            return (string) $old;
        }

        $date = $project->{$field} ?? null;
        if ($date === null) {
            return '';
        }

        return Money::toPersianDigits(
            JalaliDate::fromCarbon(\Carbon\CarbonImmutable::parse($date))->formatAscii()
        );
    };

    $val = fn (string $f) => old($f, $project->{$f} ?? '');
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';

    $currentType = (string) old('type', $project->type ?? 'seo');
    $seo = $project->seoDetails;
    $design = $project->designDetails;
@endphp

<div class="v-form">
    {{-- ── Section: identity ─────────────────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات پروژه</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="customer_id">
                    مشتری <span class="v-req" aria-hidden="true">*</span>
                </label>
                <select id="customer_id" name="customer_id" class="v-select{{ $err('customer_id') }}" required>
                    <option value="">— انتخاب مشتری —</option>
                    @foreach ($customers as $option)
                        <option value="{{ $option->id }}"
                            @selected((string) old('customer_id', $project->customer_id ?? $preselectedCustomer ?? '') === (string) $option->id)>
                            {{ $option->display_name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="contract_id">قرارداد مرتبط</label>
                <select id="contract_id" name="contract_id" class="v-select{{ $err('contract_id') }}"
                        aria-describedby="contract_id-hint">
                    <option value="">— بدون قرارداد —</option>
                </select>
                <p class="v-hint" id="contract_id-hint">
                    فهرست پس از انتخاب مشتری پر می‌شود. قرارداد باید متعلق به همان مشتری باشد.
                </p>
                @error('contract_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="type">
                    نوع پروژه <span class="v-req" aria-hidden="true">*</span>
                </label>
                <select id="type" name="type" class="v-select{{ $err('type') }}" required data-project-type>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($currentType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="status">
                    وضعیت <span class="v-req" aria-hidden="true">*</span>
                </label>
                <select id="status" name="status" class="v-select{{ $err('status') }}" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project->status ?? 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="title">
                    عنوان پروژه <span class="v-req" aria-hidden="true">*</span>
                </label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" required maxlength="190"
                       placeholder="مثلاً: سئو و بهینه‌سازی فروشگاه اینترنتی">
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- ── Section: SEO-only ────────────────────────────────────────── --}}
    <fieldset class="v-fsection" data-type-section="seo"
              @if ($currentType !== 'seo') hidden @endif>
        <legend class="v-fsection__legend">مشخصات پروژه سئو</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="seo_package_id">پکیج سئو</label>
                <select id="seo_package_id" name="seo_package_id" class="v-select{{ $err('seo_package_id') }}">
                    <option value="">— بدون پکیج —</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}"
                            @selected((string) old('seo_package_id', $seo->seo_package_id ?? '') === (string) $package->id)>
                            {{ $package->title }}@if ($package->keyword_limit) ({{ Money::toPersianDigits((string) $package->keyword_limit) }} کلمه)@endif
                        </option>
                    @endforeach
                </select>
                @error('seo_package_id')<p class="v-error">{{ $message }}</p>@enderror
                @if ($packages->isEmpty())
                    <p class="v-hint">هنوز پکیج سئویی تعریف نشده است؛ می‌توانید بدون پکیج ادامه دهید.</p>
                @endif
            </div>

            <div class="v-field">
                <label class="v-label" for="keyword_count">تعداد کلمات کلیدی</label>
                <input id="keyword_count" name="keyword_count" type="number" min="0" step="1"
                       class="v-input{{ $err('keyword_count') }}" dir="ltr"
                       value="{{ $val('keyword_count') ?: ($seo->keyword_count ?? '') }}">
                @error('keyword_count')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="recurrence">دورهٔ تکرار فاکتور/گزارش</label>
                <select id="recurrence" name="recurrence" class="v-select{{ $err('recurrence') }}">
                    <option value="">— بدون تکرار —</option>
                    @foreach (\App\Models\SeoProjectDetail::RECURRENCES as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('recurrence', $seo->recurrence ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('recurrence')<p class="v-error">{{ $message }}</p>@enderror
                <p class="v-hint">تعیین دوره، تولید خودکار کارهای فاکتور و گزارش را ممکن می‌کند.</p>
            </div>

            <div class="v-field">
                <label class="v-label" for="reminder_offset_days">یادآوری چند روز قبل</label>
                <input id="reminder_offset_days" name="reminder_offset_days" type="number" min="0" max="60"
                       step="1" class="v-input{{ $err('reminder_offset_days') }}" dir="ltr"
                       value="{{ $val('reminder_offset_days') ?: ($seo->reminder_offset_days ?? 2) }}">
                @error('reminder_offset_days')<p class="v-error">{{ $message }}</p>@enderror
                <p class="v-hint">پیش‌فرض ۲ روز تقویمی، مطابق §5-2.</p>
            </div>
        </div>
    </fieldset>

    {{-- ── Section: design-only ─────────────────────────────────────── --}}
    <fieldset class="v-fsection" data-type-section="design"
              @if ($currentType !== 'design') hidden @endif>
        <legend class="v-fsection__legend">مشخصات پروژه طراحی</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="domain">دامنه سایت</label>
                <input id="domain" name="domain" type="text" class="v-input{{ $err('domain') }}" dir="ltr"
                       value="{{ $val('domain') ?: ($design->domain ?? '') }}"
                       placeholder="example.com" aria-describedby="domain-hint">
                <p class="v-hint" id="domain-hint">بدون http؛ نمونه: example.com</p>
                @error('domain')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="site_type">نوع سایت</label>
                <input id="site_type" name="site_type" type="text" class="v-input{{ $err('site_type') }}"
                       list="site-type-options" maxlength="48"
                       value="{{ $val('site_type') ?: ($design->site_type ?? '') }}"
                       placeholder="فروشگاهی، شرکتی، …">
                <datalist id="site-type-options">
                    <option value="فروشگاهی"></option>
                    <option value="شرکتی"></option>
                    <option value="خبری"></option>
                    <option value="پرتفولیو"></option>
                    <option value="زمینه‌ای"></option>
                </datalist>
                @error('site_type')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="services">دامنهٔ خدمات</label>
                <textarea id="services" name="services" rows="3"
                          class="v-textarea{{ $err('services') }}">{{ $val('services') ?: ($design->services ?? '') }}</textarea>
                @error('services')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- ── Section: money, dates, owner ─────────────────────────────── --}}
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مبلغ، زمان‌بندی و مسئول</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="amount">مبلغ پروژه (ریال)</label>
                <input id="amount" name="amount" type="text" class="v-input{{ $err('amount') }}"
                       value="{{ $val('amount') }}" inputmode="numeric" dir="ltr"
                       aria-describedby="amount-hint">
                <p class="v-hint" id="amount-hint">فقط رقم؛ جداکنندهٔ هزارگان اختیاری است.</p>
                @error('amount')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="owner_user_id">مسئول پروژه</label>
                <select id="owner_user_id" name="owner_user_id" class="v-select{{ $err('owner_user_id') }}">
                    <option value="">— خودم —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}"
                            @selected((string) old('owner_user_id', $project->owner_user_id ?? '') === (string) $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('owner_user_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="start_date">تاریخ شروع</label>
                <input id="start_date" name="start_date" type="text" class="v-input{{ $err('start_date') }}"
                       value="{{ $jalali('start_date') }}" data-jalali inputmode="numeric"
                       placeholder="۱۴۰۴/۰۱/۱۵" aria-describedby="start_date-hint">
                <p class="v-hint" id="start_date-hint">شمسی، به شکل ۱۴۰۴/۰۱/۱۵</p>
                @error('start_date')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="end_date">تاریخ پایان</label>
                <input id="end_date" name="end_date" type="text" class="v-input{{ $err('end_date') }}"
                       value="{{ $jalali('end_date') }}" data-jalali inputmode="numeric"
                       placeholder="۱۴۰۵/۰۱/۱۴" aria-describedby="end_date-hint">
                <p class="v-hint" id="end_date-hint">خالی بگذارید اگر پایان باز دارد.</p>
                @error('end_date')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="notes">یادداشت داخلی</label>
                <textarea id="notes" name="notes" rows="3"
                          class="v-textarea{{ $err('notes') }}">{{ $val('notes') }}</textarea>
                @error('notes')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    <div class="v-formactions">
        <a href="{{ $isEdit ? route('projects.show', $project) : route('projects.index') }}"
           class="v-btn v-btn--ghost">انصراف</a>
        <button type="submit" class="v-btn v-btn--primary" data-guard>
            {{ $isEdit ? 'ذخیره تغییرات' : 'ثبت پروژه' }}
        </button>
    </div>
</div>
