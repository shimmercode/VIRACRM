@extends('layouts.app')

@section('title', 'ویرایش قاعدهٔ حقوق')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش قاعدهٔ حقوق: {{ $rule->title }}</h1>
            <p class="v-pagehead__desc">
                این قاعده سند محاسبهٔ دوره‌هایی است که با آن ساخته شده‌اند؛ روش آن
                تغییرپذیر نیست.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.payroll-rules.update', $rule) }}">
                @csrf
                @method('PUT')
                @include('settings.payroll-rules._form', ['submitLabel' => 'ذخیرهٔ تغییرات'])
            </form>
        </div>
    </section>
@endsection
