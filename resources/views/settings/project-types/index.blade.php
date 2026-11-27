@extends('layouts.app')

@section('title', 'انواع پروژه')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">انواع پروژه</h1>
            <p class="v-pagehead__desc">
                فهرستی که فرم ثبت پروژه از آن می‌خواند. بخش اختصاصی سئو و طراحی
                فقط برای همان دو نوع وجود دارد.
            </p>
        </div>
        @if (auth()->user()->hasPermission('settings.manage'))
            <div class="v-pagehead__actions">
                <a href="{{ route('settings.project-types.create') }}" class="v-btn v-btn--primary">نوع جدید</a>
            </div>
        @endif
    </header>

    <section class="v-card">
        <div class="v-card__body">
            @if ($types->isEmpty())
                <x-empty-state
                    title="نوع پروژه‌ای ثبت نشده است"
                    description="در این حالت فرم پروژه دو نوع داخلی سئو و طراحی را پیشنهاد می‌دهد."
                    icon="folder"
                />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">نوع پروژه</th>
                                <th scope="col">کد</th>
                                <th scope="col">پروژه‌ها</th>
                                <th scope="col">بخش اختصاصی</th>
                                <th scope="col">وضعیت</th>
                                @if (auth()->user()->hasPermission('settings.manage'))<th scope="col"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($types as $type)
                                <tr>
                                    <td class="v-table__primary">{{ $type->title }}</td>
                                    <td dir="ltr">{{ $type->code }}</td>
                                    <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $type->projects_count) }}</td>
                                    <td>
                                        {{-- Honest about what a type gets: only the two built-in
                                             kinds have a detail fieldset and a finalisation gate. --}}
                                        @if (array_key_exists($type->code, \App\Models\Project::TYPES))
                                            <span class="v-badge v-badge--info">دارد</span>
                                        @else
                                            <span class="v-badge v-badge--neutral">ندارد</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="v-badge {{ $type->is_active ? 'v-badge--success' : 'v-badge--neutral' }}">
                                            {{ $type->is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    @if (auth()->user()->hasPermission('settings.manage'))
                                        <td class="v-table__num">
                                            <a href="{{ route('settings.project-types.edit', $type) }}"
                                               class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            @if ($type->projects_count === 0)
                                                <form method="POST" action="{{ route('settings.project-types.destroy', $type) }}" class="v-inlineform">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-btn v-btn--ghost v-btn--sm v-btn--danger">حذف</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
