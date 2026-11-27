@extends('layouts.app')

@section('title', 'نوع پروژه جدید')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">نوع پروژه جدید</h1>
            <p class="v-pagehead__desc">
                نوع جدید در فرم ثبت پروژه قابل انتخاب می‌شود. بخش سئو و طراحی فقط
                برای همان دو نوع داخلی وجود دارد؛ نوع تازه پروژه‌ای بدون آن بخش‌ها
                می‌سازد.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.project-types.store') }}">
                @csrf
                @include('settings.project-types._form', ['submitLabel' => 'ثبت نوع پروژه'])
            </form>
        </div>
    </section>
@endsection
