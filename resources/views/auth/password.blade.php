@extends('layouts.app')

@section('title', 'تغییر رمز عبور')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">تغییر رمز عبور</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">تغییر رمز عبور</h1>
            <p class="v-pagehead__desc">
                رمز عبور باید حداقل ۱۲ نویسه باشد و شامل حروف بزرگ و کوچک و رقم باشد.
            </p>
        </div>
    </header>

    @if (session('warning'))
        <x-alert type="warning">{{ session('warning') }}</x-alert>
    @endif

    <div class="v-card v-card--narrow">
        <div class="v-card__body">
            <form method="POST" action="{{ route('password.update') }}" novalidate data-guard>
                @csrf
                @method('PUT')

                <div class="v-field">
                    <label class="v-label" for="current_password">رمز عبور فعلی</label>
                    <div class="v-inputgroup">
                        <input id="current_password" name="current_password" type="password"
                               class="v-input @error('current_password') is-invalid @enderror"
                               autocomplete="current-password" required>
                        <button type="button" class="v-inputgroup__btn" data-password-toggle="current_password"
                                aria-label="نمایش رمز عبور" aria-pressed="false">
                            <x-icon name="eye" :size="18" />
                        </button>
                    </div>
                    @error('current_password')<p class="v-error">{{ $message }}</p>@enderror
                </div>

                <div class="v-field">
                    <label class="v-label" for="password">رمز عبور جدید</label>
                    <div class="v-inputgroup">
                        <input id="password" name="password" type="password"
                               class="v-input @error('password') is-invalid @enderror"
                               autocomplete="new-password" required aria-describedby="pw-hint">
                        <button type="button" class="v-inputgroup__btn" data-password-toggle="password"
                                aria-label="نمایش رمز عبور" aria-pressed="false">
                            <x-icon name="eye" :size="18" />
                        </button>
                    </div>
                    <p class="v-hint" id="pw-hint">حداقل ۱۲ نویسه، شامل حرف بزرگ، حرف کوچک و رقم.</p>
                    @error('password')<p class="v-error">{{ $message }}</p>@enderror
                </div>

                <div class="v-field">
                    <label class="v-label" for="password_confirmation">تکرار رمز عبور جدید</label>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           class="v-input" autocomplete="new-password" required>
                </div>

                <div class="v-formactions">
                    <a href="{{ route('dashboard') }}" class="v-btn v-btn--ghost">انصراف</a>
                    <button type="submit" class="v-btn v-btn--primary">ذخیره رمز عبور</button>
                </div>
            </form>
        </div>
    </div>
@endsection
