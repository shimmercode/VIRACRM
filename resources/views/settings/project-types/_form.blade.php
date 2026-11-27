@php
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn (string $f) => old($f, $type->{$f} ?? '');
    // A type with projects on it stores its code on those rows, so the code
    // cannot move; the server refuses the change and says why.
    $codeLocked = $type->exists && $type->projects_count !== null && $type->projects_count > 0;
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات نوع پروژه</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="title">عنوان</label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" maxlength="191" required
                       placeholder="پشتیبانی">
                <p class="v-hint">همین عنوان در فرم ثبت پروژه نمایش داده می‌شود.</p>
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="code">کد</label>
                <input id="code" name="code" type="text" class="v-input{{ $err('code') }}"
                       value="{{ $val('code') }}" maxlength="64" required dir="ltr"
                       placeholder="support">
                <p class="v-hint">حروف کوچک لاتین، رقم و - _ . — پروژه‌ها این کد را ذخیره می‌کنند.</p>
                @error('code')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        @if ($codeLocked)
            <p class="v-hint">این نوع پروژه در پروژه‌های ثبت‌شده استفاده شده است؛ کد آن قابل تغییر نیست.</p>
        @endif

        <label class="v-check">
            {{-- An unchecked checkbox posts nothing at all, so the hidden field
                 carries the "no". Without it a type could never be retired. --}}
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $type->is_active ?? true))>
            <span>فعال</span>
        </label>
        <p class="v-hint">نوع غیرفعال در فرم ثبت پروژه پیشنهاد نمی‌شود.</p>
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">{{ $submitLabel }}</button>
        <a href="{{ route('settings.project-types.index') }}" class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
