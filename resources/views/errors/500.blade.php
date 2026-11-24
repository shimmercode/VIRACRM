@extends('layouts.plain')

@section('title', 'خطای غیرمنتظره')

@section('content')
    {{-- §8: a dedicated error page in the product's own language and layout,
         never Laravel's default English page.

         The 500 page is the hardest one to get right: it is shown exactly when
         something has gone wrong, so it must not itself depend on much. It
         therefore says nothing about the cause — a stack trace or exception
         message shown to the user is an information leak, and the directive
         forbids exposing internals. The correlation id below is generated
         independently of the exception, so this page cannot fail because the
         error handler failed.

         `APP_DEBUG=false` in production is what actually surfaces this page
         instead of Laravel's debug screen; the deployment guide asserts it. --}}
    <div class="v-errorpage">
        <span class="v-errorpage__icon"><x-icon name="warning" :size="30" /></span>
        <p class="v-errorpage__code">500</p>
        <h1 class="v-errorpage__title">خطای غیرمنتظره</h1>
        <p class="v-errorpage__desc">
            مشکلی در پردازش درخواست پیش آمد و رویداد ثبت شد.
            اگر تکرار شد، این شناسه را به پشتیبانی اعلام کنید:
        </p>
        <p class="v-errorpage__desc" dir="ltr">
            <code>{{ substr(sha1((string) microtime(true) . (string) random_int(1000, 9999)), 0, 12) }}</code>
        </p>
        <div class="v-errorpage__actions">
            <a href="{{ route('dashboard') }}" class="v-btn v-btn--primary">داشبورد</a>
        </div>
    </div>
@endsection
