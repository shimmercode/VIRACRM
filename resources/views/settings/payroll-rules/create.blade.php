@extends('layouts.app')

@section('title', 'قاعدهٔ حقوق جدید')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">قاعدهٔ حقوق جدید</h1>
            <p class="v-pagehead__desc">
                قاعده، روش محاسبهٔ دورهٔ حقوق را ثبت می‌کند. تا تأیید نشود، برای
                هیچ دوره‌ای به کار نمی‌رود.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.payroll-rules.store') }}">
                @csrf
                @include('settings.payroll-rules._form', ['submitLabel' => 'ثبت قاعده'])
            </form>
        </div>
    </section>
@endsection
