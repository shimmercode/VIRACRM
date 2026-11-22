@extends('layouts.plain')

@section('title', 'صفحه پیدا نشد')

@section('content')
    {{-- §8: a dedicated error page in the product's own language and layout,
         never Laravel's default English page. --}}
    <div class="v-errorpage">
        <span class="v-errorpage__icon"><x-icon name="search" :size="30" /></span>
        <p class="v-errorpage__code">404</p>
        <h1 class="v-errorpage__title">صفحه پیدا نشد</h1>
        <p class="v-errorpage__desc">نشانی وارد‌شده وجود ندارد یا حذف شده است.</p>
        <div class="v-errorpage__actions">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="v-btn v-btn--ghost">
                <x-icon name="arrow" :size="16" /> بازگشت
            </a>
            <a href="{{ route('dashboard') }}" class="v-btn v-btn--primary">داشبورد</a>
        </div>
    </div>
@endsection
