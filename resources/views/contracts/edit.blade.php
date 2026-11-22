@extends('layouts.app')

@section('title', 'ویرایش ' . $contract->title)

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('contracts.index') }}">قراردادها</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('contracts.show', $contract) }}">{{ $contract->title }}</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">ویرایش</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش قرارداد</h1>
            <p class="v-pagehead__desc">{{ $contract->number }} · {{ $contract->title }}</p>
        </div>
    </header>

    <form method="POST" action="{{ route('contracts.update', $contract) }}" novalidate data-guard>
        @csrf
        @method('PUT')
        @include('contracts._form', ['contract' => $contract])
    </form>
@endsection
