@extends('layouts.app')

@section('title', $template->exists ? 'ویرایش قالب قرارداد' : 'قالب قرارداد جدید')

@section('content')
    @php use App\Support\JalaliDate; @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('contracts.templates.index') }}">قالب‌ها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $template->exists ? 'ویرایش' : 'قالب جدید' }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $template->exists ? 'ویرایش قالب قرارداد' : 'ثبت قالب قرارداد' }}</h1>
            <p class="v-pagehead__desc">
                @if ($usedCount > 0)
                    این قالب در {{ \App\Support\Money::toPersianDigits((string) $usedCount) }} قرارداد
                    نهایی‌شده استفاده شده است. ذخیرهٔ تغییرات، نسخهٔ جدیدی می‌سازد و نسخهٔ فعلی را
                    برای سابقه غیرفعال می‌کند.
                @else
                    این قالب هنوز در هیچ قرارداد نهایی‌شده‌ای استفاده نشده است؛ تغییرات در همان نسخه ذخیره می‌شود.
                @endif
            </p>
        </div>
    </header>

    <form method="POST"
          action="{{ $template->exists ? route('contracts.templates.update', $template) : route('contracts.templates.store') }}"
          novalidate data-guard>
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif

        <div class="v-form">
            <fieldset class="v-fsection">
                <legend class="v-fsection__legend">مشخصات قالب</legend>

                <div class="v-grid v-grid--2">
                    <div class="v-field">
                        <label class="v-label" for="title">
                            عنوان <span class="v-req" aria-hidden="true">*</span>
                        </label>
                        <input id="title" name="title" type="text" class="v-input{{ $errors->has('title') ? ' v-input--invalid' : '' }}"
                               value="{{ old('title', $template->title) }}" required>
                        @error('title')<p class="v-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="v-field">
                        <label class="v-label" for="contract_type_id">نوع قرارداد</label>
                        <select id="contract_type_id" name="contract_type_id" class="v-select">
                            <option value="">— همهٔ انواع —</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}"
                                    @selected((string) old('contract_type_id', $template->contract_type_id ?? '') === (string) $type->id)>
                                    {{ $type->title }}
                                </option>
                            @endforeach
                        </select>
                        <p class="v-hint">اگر خالی باشد، این قالب برای همهٔ انواع قابل استفاده است.</p>
                    </div>
                </div>

                <div class="v-field">
                    <label class="v-label" for="body">
                        متن قالب <span class="v-req" aria-hidden="true">*</span>
                    </label>
                    <textarea id="body" name="body" class="v-textarea{{ $errors->has('body') ? ' v-textarea--invalid' : '' }}"
                              rows="16" required>{{ old('body', $template->body) }}</textarea>
                    @error('body')<p class="v-error">{{ $message }}</p>@enderror
                </div>

                <div class="v-dash-actions">
                    <label class="v-check">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $template->is_active ?? true))>
                        <span>فعال</span>
                    </label>
                    <label class="v-check">
                        <input type="checkbox" name="is_default" value="1"
                               @checked(old('is_default', $template->is_default ?? false))>
                        <span>قالب پیش‌فرض این نوع</span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="v-fsection">
                <legend class="v-fsection__legend">جای‌نگهدارهای مجاز</legend>
                <p class="v-fsection__hint">
                    این متن را در بدنهٔ قالب بگذارید؛ هنگام نهایی‌سازی با مقدار واقعی قرارداد جایگزین می‌شود.
                </p>
                @php
                    // Literal braces for display. `{{ '}}' }}` does not parse: the
                    // closing braces end the echo. Building them from single
                    // characters keeps the source unambiguous.
                    $lb = '{' . '{';
                    $rb = '}' . '}';
                @endphp
                <div class="v-tokenlist">
                    @foreach ($tokens as $token => $label)
                        <span class="v-tokenlist__item" title="{{ $label }}">
                            <code dir="ltr">{{ $lb }}{{ $token }}{{ $rb }}</code>
                        </span>
                    @endforeach
                </div>
            </fieldset>

            <div class="v-formactions">
                <button type="submit" class="v-btn v-btn--primary">
                    {{ $template->exists ? 'ذخیرهٔ قالب' : 'ثبت قالب' }}
                </button>
                <a href="{{ route('contracts.templates.index') }}" class="v-btn v-btn--ghost">انصراف</a>
            </div>
        </div>
    </form>
@endsection
