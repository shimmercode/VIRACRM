@extends('layouts.app')

@section('title', 'ویرایش پکیج سئو')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش پکیج: {{ $package->title }}</h1>
            <p class="v-pagehead__desc">
                تغییر سقف کلمات کلیدی، پروژه‌هایی را که اکنون روی این پکیج هستند
                نیز شامل می‌شود؛ سقف در لحظهٔ ثبت هر کلمه بررسی می‌شود.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.seo-packages.update', $package) }}">
                @csrf
                @method('PUT')
                @include('settings.seo-packages._form', ['submitLabel' => 'ذخیرهٔ تغییرات'])
            </form>
        </div>
    </section>
@endsection
