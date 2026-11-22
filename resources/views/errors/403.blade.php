{{-- §8: a 403 keeps the signed-in user inside the application shell (losing the
     sidebar makes a permission wall look like a broken product). Guests get a
     standalone page, because they have no shell to return to. --}}
@php($signedIn = auth()->check())

@if ($signedIn)
    @extends('layouts.app')
@else
    @extends('layouts.plain')
@endif

@section('title', 'دسترسی غیرمجاز')

@section('content')
    <div class="v-errorpage">
        <span class="v-errorpage__icon"><x-icon name="lock" :size="30" /></span>
        <p class="v-errorpage__code">۴۰۳</p>
        <h1 class="v-errorpage__title">دسترسی غیرمجاز</h1>
        <p class="v-errorpage__desc">
            شما به این بخش دسترسی ندارید. اگر فکر می‌کنید این خطاست، با مدیر سامانه تماس بگیرید.
        </p>
        <div class="v-errorpage__actions">
            @if ($signedIn)
                <a href="{{ route('dashboard') }}" class="v-btn v-btn--primary">بازگشت به داشبورد</a>
            @else
                <a href="{{ route('login') }}" class="v-btn v-btn--primary">ورود به سامانه</a>
            @endif
        </div>
    </div>
@endsection
