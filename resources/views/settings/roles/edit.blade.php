@extends('layouts.app')

@section('title', 'ویرایش نقش')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش نقش: {{ $role->title }}</h1>
            <p class="v-pagehead__desc">
                تغییر مجوزها بلافاصله روی همهٔ کاربران این نقش اثر می‌گذارد.
                تغییرات در گزارش فعالیت ثبت می‌شود.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.roles.update', $role) }}">
                @csrf
                @method('PUT')
                @include('settings.roles._form', ['submitLabel' => 'ذخیرهٔ تغییرات'])
            </form>
        </div>
    </section>
@endsection
