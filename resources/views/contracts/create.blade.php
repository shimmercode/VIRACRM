@extends('layouts.app')

@section('title', 'قرارداد جدید')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('contracts.index') }}">قراردادها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">قرارداد جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ثبت قرارداد جدید</h1>
            <p class="v-pagehead__desc">قرارداد باید به یک مشتری موجود متصل شود. تاریخ‌ها شمسی وارد می‌شوند.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('contracts.store') }}" novalidate data-guard>
        @csrf
        @include('contracts._form', ['contract' => new \App\Models\Contract()])
    </form>
@endsection
