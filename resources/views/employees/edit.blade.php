@extends('layouts.app')

@section('title', 'ویرایش پروندهٔ ' . $employee->displayName())

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('employees.index') }}">پرسنل</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('employees.show', $employee) }}">{{ $employee->displayName() }}</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">ویرایش</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش پروندهٔ پرسنلی</h1>
            <p class="v-pagehead__desc">تغییرات در گزارش رویدادها ثبت می‌شود.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('employees.update', $employee) }}" novalidate data-guard>
        @csrf
        @method('PUT')
        @include('employees._form', ['employee' => $employee])
    </form>
@endsection
