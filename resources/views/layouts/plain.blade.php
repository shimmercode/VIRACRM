<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'ویرا')</title>
    <link rel="preload" href="{{ asset('build/fonts/Vazirmatn-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('build/css/app.css') }}?v={{ @filemtime(public_path('build/css/app.css')) }}">
</head>
<body class="v-app v-app--plain">
{{-- Standalone layout for error pages. It must never assume a signed-in user:
     a 404 for a guest is a normal event and must not itself error. --}}
<main class="v-plain" id="main">
    @yield('content')
</main>
</body>
</html>
