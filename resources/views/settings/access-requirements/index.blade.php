@extends('layouts.app')

@section('title', 'دسترسی‌های الزامی')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">دسترسی‌های الزامی</h1>
            <p class="v-pagehead__desc">
                دسترسی‌هایی که تا ثبت نشدنشان، پروژهٔ آن نوع فعال یا نهایی
                نمی‌شود. این فهرست همان چیزی است که دروازهٔ نهایی‌سازی می‌خواند.
            </p>
        </div>
        @if (auth()->user()->hasPermission('settings.manage'))
            <div class="v-pagehead__actions">
                <a href="{{ route('settings.access-requirements.create') }}" class="v-btn v-btn--primary">دسترسی جدید</a>
            </div>
        @endif
    </header>

    <section class="v-card">
        <div class="v-card__body">
            @if ($requirements->isEmpty())
                <x-empty-state
                    title="هیچ دسترسی الزامی تعریف نشده است"
                    description="با فهرست خالی، هیچ پروژه‌ای به دلیل کمبود دسترسی متوقف نمی‌شود."
                    icon="folder"
                />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">نوع پروژه</th>
                                <th scope="col">دسترسی</th>
                                <th scope="col">الزامی</th>
                                <th scope="col">مانع نهایی‌سازی</th>
                                @if (auth()->user()->hasPermission('settings.manage'))<th scope="col"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requirements as $requirement)
                                <tr>
                                    <td class="v-table__primary">{{ $projectTypes[$requirement->project_type] ?? $requirement->project_type }}</td>
                                    <td>{{ $requirement->credentialType?->title ?? '—' }}</td>
                                    <td>
                                        <span class="v-badge {{ $requirement->is_required ? 'v-badge--success' : 'v-badge--neutral' }}">
                                            {{ $requirement->is_required ? 'بله' : 'خیر' }}
                                        </span>
                                    </td>
                                    <td>
                                        {{-- Only the pair required+blocking actually gates, which is
                                             what FinalizationGate::blockingFor() filters on. --}}
                                        @if ($requirement->is_required && $requirement->blocks_finalize)
                                            <span class="v-badge v-badge--danger">بله</span>
                                        @else
                                            <span class="v-badge v-badge--neutral">خیر</span>
                                        @endif
                                    </td>
                                    @if (auth()->user()->hasPermission('settings.manage'))
                                        <td class="v-table__num">
                                            <a href="{{ route('settings.access-requirements.edit', $requirement) }}"
                                               class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            <form method="POST" action="{{ route('settings.access-requirements.destroy', $requirement) }}" class="v-inlineform">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm v-btn--danger">حذف</button>
                                            </form>
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
