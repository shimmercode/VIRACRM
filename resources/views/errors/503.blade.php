@extends('layouts.plain')

@section('title', 'سامانه در دسترس نیست')

@section('content')
    {{-- §8: a dedicated error page in the product's own language and layout,
         never Laravel's default English page. --}}
    <div class="v-errorpage">
        <span class="v-errorpage__icon"><x-icon name="warning" :size="30" /></span>
        <p class="v-errorpage__code">503</p>
        <h1 class="v-errorpage__title">سامانه در دسترس نیست</h1>
        <p class="v-errorpage__desc">در حال به‌روزرسانی هستیم. لطفاً چند دقیقه دیگر مراجعه کنید.</p>
        <div class="v-errorpage__actions">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="v-btn v-btn--ghost">
                <x-icon name="arrow" :size="16" /> بازگشت
            </a>
            <a href="{{ route('dashboard') }}" class="v-btn v-btn--primary">داشبورد</a>
        </div>
    </div>
@endsection
