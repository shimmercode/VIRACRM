@extends('layouts.app')

@section('title', 'ویرایش پروژه')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.index') }}">پروژه‌ها</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">ویرایش</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش پروژه</h1>
            <p class="v-pagehead__desc">
                شمارهٔ پروژه: <span dir="ltr">{{ $project->code }}</span>
                @if ($project->type === 'seo')
                    · تغییر نوع پروژه، مشخصات سئوی آن را حذف می‌کند.
                @else
                    · تغییر نوع پروژه، مشخصات طراحی آن را حذف می‌کند.
                @endif
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('projects.update', $project) }}" novalidate data-guard>
        @csrf
        @method('PUT')
        @include('projects._form', ['project' => $project])
    </form>
@endsection
