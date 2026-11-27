@php
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn (string $f) => old($f, $type->{$f} ?? '');
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات نوع قرارداد</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="title">عنوان</label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" maxlength="191" required
                       placeholder="پشتیبانی ماهانه">
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="code">کد</label>
                <input id="code" name="code" type="text" class="v-input{{ $err('code') }}"
                       value="{{ $val('code') }}" maxlength="64" required dir="ltr"
                       placeholder="maintenance">
                <p class="v-hint">حروف لاتین، رقم و - _ . — این کد در داده تغییر نمی‌کند.</p>
                @error('code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="sort_order">ترتیب نمایش</label>
                <input id="sort_order" name="sort_order" type="number" class="v-input{{ $err('sort_order') }}"
                       value="{{ old('sort_order', $type->sort_order ?? 0) }}" dir="ltr">
                <p class="v-hint">عدد کوچک‌تر بالاتر می‌نشیند.</p>
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="description">توضیحات</label>
                <textarea id="description" name="description" rows="3" class="v-input{{ $err('description') }}"
                          maxlength="2000">{{ $val('description') }}</textarea>
            </div>
        </div>

        <label class="v-check">
            {{-- An unchecked checkbox posts nothing at all, so the hidden field
                 carries the "no". Without it the type could never be retired. --}}
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $type->is_active ?? true))>
            <span>فعال</span>
        </label>
        <p class="v-hint">نوع غیرفعال در فرم قرارداد پیشنهاد نمی‌شود، اما قراردادهای ثبت‌شده روی آن باقی می‌مانند.</p>
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">{{ $submitLabel }}</button>
        <a href="{{ route('settings.contract-types.index') }}" class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
