@extends('layouts.app')

@section('title', 'مشتری جدید')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('customers.index') }}">مشتریان</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">مشتری جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ثبت مشتری جدید</h1>
            <p class="v-pagehead__desc">
                اطلاعات مشتری را در بخش‌های زیر وارد کنید. فیلدهای ستاره‌دار الزامی هستند.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('customers.store') }}" novalidate data-guard>
        @csrf
        @include('customers._form', ['customer' => new \App\Models\Customer()])
    </form>
@endsection
