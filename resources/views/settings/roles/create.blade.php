@extends('layouts.app')

@section('title', 'نقش جدید')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">نقش جدید</h1>
            <p class="v-pagehead__desc">
                نقش، مجموعهٔ مجوزهایی است که به کاربر اختصاص می‌یابد. مدیر ارشد
                بدون نقش، همهٔ مجوزها را دارد.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.roles.store') }}">
                @csrf
                @include('settings.roles._form', ['submitLabel' => 'ثبت نقش'])
            </form>
        </div>
    </section>
@endsection
