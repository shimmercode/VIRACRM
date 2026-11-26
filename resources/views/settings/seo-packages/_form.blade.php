@php
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn (string $f) => old($f, $package->{$f} ?? '');
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات پکیج</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="title">عنوان پکیج</label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" maxlength="191" required
                       placeholder="سئو استاندارد">
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="code">کد پکیج</label>
                <input id="code" name="code" type="text" class="v-input{{ $err('code') }}"
                       value="{{ $val('code') }}" maxlength="64" required dir="ltr"
                       placeholder="seo-standard">
                <p class="v-hint">حروف لاتین، رقم و - _ . — این کد در گزارش‌ها نشان داده می‌شود.</p>
                @error('code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="keyword_limit">سقف کلمات کلیدی</label>
                <input id="keyword_limit" name="keyword_limit" type="text" class="v-input{{ $err('keyword_limit') }}"
                       value="{{ $val('keyword_limit') }}" inputmode="numeric" dir="ltr"
                       placeholder="۴۰">
                {{-- This number is enforced by ProjectKeyword when a keyword is
                     added, so it is the one field here with an immediate effect. --}}
                <p class="v-hint">خالی یعنی بدون سقف. این عدد هنگام ثبت کلمهٔ کلیدی اعمال می‌شود.</p>
                @error('keyword_limit')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="sort_order">ترتیب نمایش</label>
                <input id="sort_order" name="sort_order" type="number" class="v-input{{ $err('sort_order') }}"
                       value="{{ old('sort_order', $package->sort_order ?? 0) }}" dir="ltr">
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="description">توضیحات</label>
                <textarea id="description" name="description" rows="3" class="v-input{{ $err('description') }}"
                          maxlength="2000">{{ $val('description') }}</textarea>
            </div>
        </div>

        <label class="v-check">
            {{-- An unchecked checkbox posts nothing at all, so without this the
                 server saw no value and fell back to its default — the package
                 could never be deactivated. The hidden field carries the "no". --}}
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $package->is_active ?? true))>
            <span>فعال</span>
        </label>
        <p class="v-hint">پکیج غیرفعال در فرم پروژه پیشنهاد نمی‌شود، اما پروژه‌های ثبت‌شده روی آن باقی می‌مانند.</p>
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">{{ $submitLabel }}</button>
        <a href="{{ route('settings.seo-packages.index') }}" class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
