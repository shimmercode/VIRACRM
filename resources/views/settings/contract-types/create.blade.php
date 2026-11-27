@extends('layouts.app')

@section('title', 'نوع قرارداد جدید')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">نوع قرارداد جدید</h1>
            <p class="v-pagehead__desc">
                نوع قرارداد در فرم ثبت قرارداد و هنگام انتخاب قالب پیشنهاد می‌شود.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.contract-types.store') }}">
                @csrf
                @include('settings.contract-types._form', ['submitLabel' => 'ثبت نوع قرارداد'])
            </form>
        </div>
    </section>
@endsection
