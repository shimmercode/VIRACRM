@php
    use App\Support\JalaliDate;
    use App\Support\Money;

    /**
     * §15 — shared employee form.
     *
     * TWO DELIBERATE OMISSIONS
     * ------------------------
     * 1. There is no salary field (OD-16): pay comes from the monthly payslip.
     * 2. Department and manager are HERE, now that §17 exists. Both are optional:
     *    a new hire may be recorded before the organisational chart is finalised,
     *    and a required field would push people to pick a wrong unit just to
     *    save the form. The organisation screen reports anyone left unassigned,
     *    so the omission is visible rather than silent.
     */
    $isEdit = $employee->exists;

    $jalali = function (string $field) use ($employee): string {
        $old = old($field);
        if ($old !== null) {
            return (string) $old;
        }

        $date = $employee->{$field} ?? null;

        return $date === null
            ? ''
            : Money::toPersianDigits(JalaliDate::fromCarbon(\Carbon\CarbonImmutable::parse($date))->formatAscii());
    };

    $val = fn (string $f) => old($f, $employee->{$f} ?? '');
    // The design system exposes `v-input--invalid`, not Bootstrap's `is-invalid`.
    // The wrong name fails silently: the field is red in the developer's mind and
    // plain grey in the browser.
    $err = fn (string $f) => $errors->has($f) ? ' v-input--invalid' : '';

    // The class lives on .v-input; a select and a textarea need the same modifier.
    $errSelect = fn (string $f) => $errors->has($f) ? ' v-select--invalid' : '';
    $errArea = fn (string $f) => $errors->has($f) ? ' v-textarea--invalid' : '';
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات فردی</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="first_name">
                    نام <span class="v-req" aria-hidden="true">*</span>
                </label>
                <input id="first_name" name="first_name" type="text" class="v-input{{ $err('first_name') }}"
                       value="{{ $val('first_name') }}" required autocomplete="off">
                @error('first_name')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="last_name">
                    نام خانوادگی <span class="v-req" aria-hidden="true">*</span>
                </label>
                <input id="last_name" name="last_name" type="text" class="v-input{{ $err('last_name') }}"
                       value="{{ $val('last_name') }}" required autocomplete="off">
                @error('last_name')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="national_id">کد ملی</label>
                <input id="national_id" name="national_id" type="text" class="v-input{{ $err('national_id') }}"
                       value="{{ $val('national_id') }}" inputmode="numeric" maxlength="10"
                       dir="ltr" aria-describedby="national_id-hint" autocomplete="off">
                <p class="v-hint" id="national_id-hint">
                    ده رقم، با رقم کنترل معتبر. کد ملی تکراری پذیرفته نمی‌شود.
                </p>
                @error('national_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="mobile">شمارهٔ همراه</label>
                <input id="mobile" name="mobile" type="text" class="v-input{{ $err('mobile') }}"
                       value="{{ $val('mobile') }}" inputmode="numeric" maxlength="11"
                       dir="ltr" placeholder="09xxxxxxxxx" autocomplete="off">
                @error('mobile')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="email">ایمیل</label>
                <input id="email" name="email" type="email" class="v-input{{ $err('email') }}"
                       value="{{ $val('email') }}" dir="ltr" autocomplete="off">
                @error('email')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="position">سِمت</label>
                <input id="position" name="position" type="text" class="v-input{{ $err('position') }}"
                       value="{{ $val('position') }}" autocomplete="off">
                @error('position')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="v-field">
            <label class="v-label" for="address">نشانی</label>
            <textarea id="address" name="address" class="v-textarea{{ $errArea('address') }}" rows="2">{{ $val('address') }}</textarea>
            @error('address')<p class="v-error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">همکاری</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="code">کد پرسنلی</label>
                <input id="code" name="code" type="text" class="v-input{{ $err('code') }}"
                       value="{{ $val('code') }}" dir="ltr"
                       placeholder="{{ $isEdit ? '' : 'در صورت خالی‌بودن، خودکار ساخته می‌شود' }}" autocomplete="off">
                @error('code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="status">
                    وضعیت <span class="v-req" aria-hidden="true">*</span>
                </label>
                <select id="status" name="status" class="v-select{{ $errSelect('status') }}" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('status', $employee->status ?? 'active') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('status')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="start_date">تاریخ شروع همکاری</label>
                <input id="start_date" name="start_date" type="text" class="v-input{{ $err('start_date') }}"
                       value="{{ $jalali('start_date') }}" placeholder="۱۴۰۳/۰۱/۱۵" inputmode="numeric"
                       data-jalali autocomplete="off">
                @error('start_date')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="end_date">تاریخ پایان همکاری</label>
                <input id="end_date" name="end_date" type="text" class="v-input{{ $err('end_date') }}"
                       value="{{ $jalali('end_date') }}" placeholder="۱۴۰۳/۱۲/۲۹" inputmode="numeric"
                       data-jalali autocomplete="off">
                <p class="v-hint">برای کارمندان شاغل خالی بگذارید.</p>
                @error('end_date')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="department_id">واحد سازمانی</label>
                <select id="department_id" name="department_id" class="v-select{{ $errSelect('department_id') }}">
                    <option value="">— بدون واحد —</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}"
                            @selected((string) old('department_id', $employee->department_id ?? '') === (string) $department->id)>
                            {{ $department->title }}{{ $department->parent_id ? ' (زیرمجموعه)' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('department_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="manager_id">مدیر مستقیم</label>
                <select id="manager_id" name="manager_id" class="v-select{{ $errSelect('manager_id') }}">
                    <option value="">— بدون مدیر —</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}"
                            @selected((string) old('manager_id', $employee->manager_id ?? '') === (string) $manager->id)>
                            {{ $manager->displayName() }}
                        </option>
                    @endforeach
                </select>
                @error('manager_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="user_id">حساب کاربری مرتبط</label>
                <select id="user_id" name="user_id" class="v-select{{ $errSelect('user_id') }}">
                    <option value="">— بدون حساب کاربری —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}"
                            @selected((string) old('user_id', $employee->user_id ?? '') === (string) $user->id)>
                            {{ $user->name }} ({{ $user->username }})
                        </option>
                    @endforeach
                </select>
                <p class="v-hint">اگر کارمند به سامانه وارد می‌شود، حساب او را اینجا پیوند دهید.</p>
                @error('user_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="v-field">
            <label class="v-label" for="notes">یادداشت</label>
            <textarea id="notes" name="notes" class="v-textarea{{ $errArea('notes') }}" rows="3">{{ $val('notes') }}</textarea>
            @error('notes')<p class="v-error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">
            {{ $isEdit ? 'ذخیرهٔ تغییرات' : 'ثبت پروندهٔ پرسنلی' }}
        </button>
        <a href="{{ $isEdit ? route('employees.show', $employee) : route('employees.index') }}"
           class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
