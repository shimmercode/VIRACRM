@extends('layouts.app')

@section('title', 'پروژه جدید')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.index') }}">پروژه‌ها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">پروژه جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ثبت پروژه جدید</h1>
            <p class="v-pagehead__desc">
                پروژه به یک مشتری متصل می‌شود و می‌تواند به قرارداد همان مشتری پیوند بخورد.
                نوع پروژه مشخص می‌کند کدام فیلدها لازم است.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('projects.store') }}" novalidate data-guard>
        @csrf
        @include('projects._form', ['project' => new \App\Models\Project()])
    </form>
@endsection
