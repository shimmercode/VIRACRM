<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ورود · ويرا</title>
    <link rel="preload" href="{{ asset('build/fonts/Vazirmatn-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('build/css/app.css') }}?v={{ @filemtime(public_path('build/css/app.css')) }}">
</head>
<body class="v-auth">

<main class="v-auth__layout">
    {{-- The brand panel is decorative and hidden below 900px, where the form
         needs the whole width and a second column would only add scroll. --}}
    <section class="v-auth__aside" aria-hidden="true">
        <div class="v-auth__brand">
            <span class="v-brand__mark v-brand__mark--lg">و</span>
            <span class="v-brand__name v-brand__name--lg">ویرا</span>
        </div>
        <p class="v-auth__tagline">
            مدیریت مشتریان، قراردادها و پروژه‌ها<br>در یک فضای کاری یکپارچه.
        </p>
        <div class="v-auth__rule"></div>
        <p class="v-auth__foot">ویرا آژانس · پنل داخلی</p>
    </section>

    <section class="v-auth__panel">
        <div class="v-auth__box">
            <div class="v-auth__mobile-brand">
                <span class="v-brand__mark">و</span>
                <span class="v-brand__name">ویرا</span>
            </div>

            <h1 class="v-auth__title">ورود به حساب</h1>
            <p class="v-auth__lead">برای ادامه، نام کاربری و رمز عبور خود را وارد کنید.</p>

            @if (session('status'))
                <x-alert type="info">{{ session('status') }}</x-alert>
            @endif

            @if ($errors->any())
                {{-- One generic message. The field-level error below repeats the
                     same sentence, so no information about *why* it failed leaks
                     (REQ-10.05). --}}
                <x-alert type="danger">{{ $errors->first('username') }}</x-alert>
            @endif

            {{-- `data-guard` gives the submit a busy state: the button is disabled and an
                 in-flight ring replaces its label while the request is outstanding. Login
                 was the only form without it, so the most-used submit in the product gave
                 no feedback at all and a double click looked reasonable. --}}
            <form method="POST" action="{{ route('login.store') }}" class="v-auth__form" novalidate data-guard>
                @csrf

                <div class="v-field">
                    <label class="v-label" for="username">نام کاربری</label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        class="v-input @error('username') is-invalid @enderror"
                        value="{{ old('username') }}"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        autofocus
                        @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                    >
                    @error('username')
                        <p class="v-error" id="username-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="v-field">
                    <label class="v-label" for="password">رمز عبور</label>
                    <div class="v-inputgroup">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="v-input @error('username') is-invalid @enderror"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="v-inputgroup__btn" data-password-toggle="password"
                                aria-label="نمایش رمز عبور" aria-pressed="false">
                            <x-icon name="eye" :size="18" />
                        </button>
                    </div>
                    @error('password')
                        <p class="v-error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="v-check">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>مرا به خاطر بسپار</span>
                </label>

                <button type="submit" class="v-btn v-btn--primary v-btn--block">ورود</button>
            </form>

            <p class="v-auth__help">
                رمز عبور خود را فراموش کرده‌اید؟ با مدیر سامانه تماس بگیرید.
            </p>
        </div>

        <p class="v-auth__copyright">© ویرا آژانس — همهٔ حقوق محفوظ است.</p>
    </section>
</main>

<script src="{{ asset('build/js/app.js') }}?v={{ @filemtime(public_path('build/js/app.js')) }}" defer></script>
</body>
</html>
