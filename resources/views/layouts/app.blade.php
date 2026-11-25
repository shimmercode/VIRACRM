<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'VIRA CRM') · ويرا</title>

    <link rel="preload" href="{{ asset('build/fonts/Vazirmatn-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('build/css/app.css') }}?v={{ @filemtime(public_path('build/css/app.css')) }}">
    @stack('styles')
</head>
<body class="v-app {{ $bodyClass ?? '' }}">

<a class="v-skip" href="#main">پرش به محتوای اصلی</a>

<div class="v-shell" data-shell>
    {{-- Sidebar: right side in RTL, collapsible, permission-filtered (§11) --}}
    <x-nav />

    <div class="v-main">
        <x-topbar :title="$pageTitle ?? ''" />

        <main class="v-content" id="main" tabindex="-1">
            @if (session('success'))
                <x-alert type="success" :dismissible="true">{{ session('success') }}</x-alert>
            @endif

            @if (session('warning'))
                <x-alert type="warning" :dismissible="true">{{ session('warning') }}</x-alert>
            @endif

            {{-- A REFUSED ACTION IS NOT THE SAME AS A VALIDATION ERROR.
                 `back()->with('error', ...)` is how a service says "I will not do
                 that" — a published payroll cannot be recomputed, a period is
                 already closed, a void was refused. Without this block every one
                 of those messages was written to the session and then thrown
                 away, so the page simply reloaded unchanged and looked broken.
                 It is separate from the $errors summary below because it is not
                 a field error and must appear even when validation passed. --}}
            @if (session('error'))
                <x-alert type="danger" :dismissible="true" role="alert">{{ session('error') }}</x-alert>
            @endif

            {{-- A failed login or a refused action must be visible but must not
                 reveal internals, so only the message is shown. --}}
            @if (session('status'))
                <x-alert type="info" :dismissible="true">{{ session('status') }}</x-alert>
            @endif

            {{-- tabindex="-1" makes the summary programmatically focusable so a
                 keyboard or screen-reader user is taken to it after a failed
                 submit, instead of being left at the top of a long form with no
                 indication that anything happened. --}}
            @if ($errors->any() && ! isset($suppressErrorSummary))
                <x-alert type="danger" :dismissible="true" id="error-summary" tabindex="-1">
                    <strong>لطفاً خطاهای زیر را بررسی کنید:</strong>
                    <ul class="v-alert__list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('build/js/app.js') }}?v={{ @filemtime(public_path('build/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
