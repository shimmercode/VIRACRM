@extends('layouts.app')

@section('title', 'دسترسی الزامی جدید')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">دسترسی الزامی جدید</h1>
            <p class="v-pagehead__desc">
                به محض ثبت، این دسترسی در بررسی نهایی‌سازی همان نوع پروژه اعمال
                می‌شود.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.access-requirements.store') }}">
                @csrf
                @include('settings.access-requirements._form', ['submitLabel' => 'ثبت دسترسی'])
            </form>
        </div>
    </section>
@endsection
