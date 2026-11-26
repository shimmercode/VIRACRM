@extends('layouts.app')

@section('title', 'پکیج سئوی جدید')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">پکیج سئوی جدید</h1>
            <p class="v-pagehead__desc">
                پکیج، بستهٔ خدماتی است که پروژهٔ سئو روی آن فروخته می‌شود و سقف
                کلمات کلیدی آن را تعیین می‌کند.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.seo-packages.store') }}">
                @csrf
                @include('settings.seo-packages._form', ['submitLabel' => 'ثبت پکیج'])
            </form>
        </div>
    </section>
@endsection
