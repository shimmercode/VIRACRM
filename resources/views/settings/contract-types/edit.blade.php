@extends('layouts.app')

@section('title', 'ویرایش نوع قرارداد')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش نوع قرارداد: {{ $type->title }}</h1>
            <p class="v-pagehead__desc">
                تغییر عنوان، قراردادهای ثبت‌شده را نیز شامل می‌شود؛ کد را تغییر
                ندهید مگر آنکه بدانید چه می‌کنید.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.contract-types.update', $type) }}">
                @csrf
                @method('PUT')
                @include('settings.contract-types._form', ['submitLabel' => 'ذخیرهٔ تغییرات'])
            </form>
        </div>
    </section>
@endsection
