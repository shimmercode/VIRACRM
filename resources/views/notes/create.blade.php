@extends('layouts.app')

@section('title', 'یادداشت جدید')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('notes.index') }}">یادداشت و وظیفه</a>
        <x-icon name="chevron" :size="12" class="rtl-mirror" />
        <span aria-current="page">یادداشت جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">یادداشت جدید</h1>
            <p class="v-pagehead__desc">یادداشت یا وظیفه‌ای ثبت کنید و در صورت نیاز به یک پرونده متصل کنید.</p>
        </div>
    </header>

    <div class="v-card">
        <form method="POST" action="{{ route('notes.store') }}" class="v-form">
            @csrf
            @include('notes._form')

            <div class="v-formactions">
                <button type="submit" class="v-btn v-btn--primary">ثبت یادداشت</button>
                <a href="{{ route('notes.index') }}" class="v-btn v-btn--ghost">انصراف</a>
            </div>
        </form>
    </div>
@endsection
