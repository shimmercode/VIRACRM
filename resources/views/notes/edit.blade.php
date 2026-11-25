@extends('layouts.app')

@section('title', 'ویرایش یادداشت')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('notes.index') }}">یادداشت و وظیفه</a>
        <x-icon name="chevron" :size="12" class="rtl-mirror" />
        <span aria-current="page">{{ $note->title }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ویرایش یادداشت</h1>
            <p class="v-pagehead__desc">تغییرات در گزارش رویدادها ثبت می‌شود.</p>
        </div>

        @if (auth()->user()->hasPermission('notes.delete'))
            <div class="v-pagehead__actions">
                <form method="POST" action="{{ route('notes.destroy', $note) }}"
                      onsubmit="return confirm('این یادداشت حذف شود؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="v-btn v-btn--danger">
                        <x-icon name="trash" :size="16" /> حذف
                    </button>
                </form>
            </div>
        @endif
    </header>

    <div class="v-card">
        <form method="POST" action="{{ route('notes.update', $note) }}" class="v-form">
            @csrf
            @method('PUT')
            @include('notes._form')

            <div class="v-formactions">
                <button type="submit" class="v-btn v-btn--primary">ذخیره تغییرات</button>
                <a href="{{ route('notes.index') }}" class="v-btn v-btn--ghost">انصراف</a>
            </div>
        </form>
    </div>
@endsection
