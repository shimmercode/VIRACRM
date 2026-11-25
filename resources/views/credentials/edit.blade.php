@extends('layouts.app')

@section('title', 'ویرایش دسترسی')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('credentials.index', $project) }}">دسترسی‌ها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $credential->label }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش دسترسی</h1>
            <p class="v-pagehead__desc">{{ $credential->type?->title ?? 'دسترسی' }} — {{ $project->title }}</p>
        </div>
    </header>

    @if (! $vaultReady && ! $hasSecret)
        <x-alert type="danger">
            کلید رمزنگاری تنظیم نشده است؛ ذخیرهٔ رمز غیرفعال است.
        </x-alert>
    @endif

    <form method="POST" action="{{ route('credentials.update', [$project, $credential]) }}" novalidate>
        @csrf
        @method('PUT')
        <div class="v-card">
          <div class="v-card__body">
            <div class="v-form">
                <div class="v-grid v-grid--2">
                    <div class="v-field">
                        <label class="v-label" for="label">
                            عنوان <span class="v-req" aria-hidden="true">*</span>
                        </label>
                        <input id="label" name="label" type="text" class="v-input" @error('label') aria-invalid="true" @enderror
                               value="{{ old('label', $credential->label) }}" required maxlength="120">
                        @error('label')
                            <p class="v-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="v-field">
                        <label class="v-label" for="username">نام کاربری</label>
                        <input id="username" name="username" type="text" class="v-input" @error('username') aria-invalid="true" @enderror
                               value="{{ old('username', $credential->username) }}" dir="ltr" maxlength="255" autocomplete="off">
                        @error('username')
                            <p class="v-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="v-field v-field--span2">
                        <label class="v-label" for="public_url">نشانی ورود</label>
                        <input id="public_url" name="public_url" type="url" class="v-input" @error('public_url') aria-invalid="true" @enderror
                               value="{{ old('public_url', $credential->public_url) }}" dir="ltr" maxlength="255" autocomplete="off">
                        @error('public_url')
                            <p class="v-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="v-field v-field--span2">
                        <label class="v-label" for="secret">
                            {{ $hasSecret ? 'رمز جدید (اختیاری)' : 'رمز عبور' }}
                        </label>
                        <input id="secret" name="secret" type="password" class="v-input" @error('secret') aria-invalid="true" @enderror
                               dir="ltr" maxlength="1000" autocomplete="new-password" data-secret>

                        {{-- The "leave empty" rule is the important thing on this
                             page: a user editing the label must not erase a
                             working password by submitting an empty field. --}}
                        <p class="v-hint">
                            @if ($hasSecret)
                                رمزی ذخیره شده است. برای تغییر آن رمز تازه را وارد کنید؛
                                <strong>خالی گذاشتن این فیلد رمز موجود را تغییر نمی‌دهد.</strong>
                            @else
                                هنوز رمزی ذخیره نشده است.
                            @endif
                        </p>
                        @error('secret')
                            <p class="v-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="v-formactions">
                    <a href="{{ route('credentials.index', $project) }}" class="v-btn v-btn--ghost">انصراف</a>
                    <button type="submit" class="v-btn v-btn--primary">ذخیرهٔ تغییرات</button>
                </div>
            </div>
          </div>
        </div>
    </form>
@endsection
