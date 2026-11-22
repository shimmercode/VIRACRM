@extends('layouts.app')

@section('title', 'ویرایش ' . $customer->display_name)

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('customers.index') }}">مشتریان</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('customers.show', $customer) }}">{{ $customer->display_name }}</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">ویرایش</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش مشتری</h1>
            <p class="v-pagehead__desc">{{ $customer->display_name }}@if($customer->code) · {{ $customer->code }}@endif</p>
        </div>
    </header>

    <form method="POST" action="{{ route('customers.update', $customer) }}" novalidate data-guard>
        @csrf
        @method('PUT')
        @include('customers._form', ['customer' => $customer])
    </form>
@endsection
