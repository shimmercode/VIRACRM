@extends('layouts.app')

@section('title', 'کارمند جدید')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('employees.index') }}">پرسنل</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">کارمند جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ثبت پروندهٔ پرسنلی</h1>
            <p class="v-pagehead__desc">
                مبلغ ثابت حقوق در این فرم ثبت نمی‌شود. دستمزد هر ماه با فیش همان ماه
                تعیین می‌شود تا یک رقم قدیمی جای مبلغ واقعی را نگیرد.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('employees.store') }}" novalidate data-guard>
        @csrf
        @include('employees._form', ['employee' => new \App\Models\Employee(['status' => 'active'])])
    </form>
@endsection
