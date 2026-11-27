@extends('layouts.app')

@section('title', 'ویرایش نوع پروژه')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش نوع پروژه: {{ $type->title }}</h1>
            <p class="v-pagehead__desc">
                پروژه‌ها کد این نوع را ذخیره کرده‌اند؛ عنوان را آزادانه تغییر
                دهید، کد را نه.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.project-types.update', $type) }}">
                @csrf
                @method('PUT')
                @include('settings.project-types._form', ['submitLabel' => 'ذخیرهٔ تغییرات'])
            </form>
        </div>
    </section>
@endsection
