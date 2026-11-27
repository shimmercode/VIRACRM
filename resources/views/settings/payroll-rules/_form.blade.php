@php
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn (string $f) => old($f, $rule->{$f} ?? '');
    $notes = old('notes', $rule->params['notes'] ?? '');
    $locked = ($rule->periods_count ?? 0) > 0;
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات قاعده</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="title">عنوان</label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" maxlength="191" required
                       placeholder="حقوق ثابت + اقلام دستی">
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="code">کد</label>
                <input id="code" name="code" type="text" class="v-input{{ $err('code') }}"
                       value="{{ $val('code') }}" maxlength="64" required dir="ltr"
                       placeholder="base-manual">
                @error('code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="method">روش محاسبه</label>
                <select id="method" name="method" class="v-select{{ $err('method') }}" required @disabled($locked)>
                    @foreach ($methods as $code => $label)
                        <option value="{{ $code }}" @selected((string) $val('method') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                {{-- A disabled select posts nothing, so the current method has to
                     travel as a hidden field or the save would fail validation. --}}
                @if ($locked)
                    <input type="hidden" name="method" value="{{ $rule->method }}">
                @endif
                <p class="v-hint">
                    تنها روش تأییدشدهٔ کارفرما (تصمیم OD-16) حقوق ثابت به‌علاوهٔ
                    اقلام دستی است. فرمول درصدی یا ضریب خودکار وجود ندارد و در این
                    صفحه ساخته نمی‌شود؛ مبالغ مرتبط با پروژه‌ها به‌صورت دستی توسط
                    حسابدار روی فیش وارد می‌شوند.
                </p>
                @error('method')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="notes">یادداشت قاعده</label>
                <textarea id="notes" name="notes" rows="3" class="v-input{{ $err('notes') }}"
                          maxlength="2000">{{ $notes }}</textarea>
            </div>
        </div>

        <label class="v-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $rule->is_active ?? true))>
            <span>فعال</span>
        </label>

        <label class="v-check">
            <input type="hidden" name="is_approved" value="0">
            <input type="checkbox" name="is_approved" value="1"
                   @checked(old('is_approved', $rule->is_approved ?? false))
                   @disabled(! $canApprove)>
            <span>تأییدشده</span>
        </label>
        <p class="v-hint">
            قاعدهٔ تأییدنشده برای محاسبهٔ هیچ دوره‌ای استفاده نمی‌شود. تغییر این
            وضعیت نیازمند مجوز «تأیید حقوق» است.
        </p>
        @error('is_approved')<p class="v-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">{{ $submitLabel }}</button>
        <a href="{{ route('settings.payroll-rules.index') }}" class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
