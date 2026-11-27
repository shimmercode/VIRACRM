@extends('layouts.app')

@section('title', 'ویرایش دسترسی الزامی')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">
                ویرایش دسترسی الزامی:
                {{ $requirement->credentialType?->title ?? $requirement->credential_type_id }}
            </h1>
            <p class="v-pagehead__desc">
                برداشتن علامت «مانع نهایی‌سازی»، پروژه‌های ناقص را آزاد می‌کند.
            </p>
        </div>
    </header>

    <section class="v-card">
        <div class="v-card__body">
            <form method="POST" action="{{ route('settings.access-requirements.update', $requirement) }}">
                @csrf
                @method('PUT')
                @include('settings.access-requirements._form', ['submitLabel' => 'ذخیرهٔ تغییرات'])
            </form>
        </div>
    </section>
@endsection
