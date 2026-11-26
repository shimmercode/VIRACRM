@extends('layouts.app')

@section('title', 'پکیج‌های سئو')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">پکیج‌های سئو</h1>
            <p class="v-pagehead__desc">
                بسته‌های خدماتی که پروژهٔ سئو روی آن‌ها تعریف می‌شود. سقف کلمات
                کلیدی هر پکیج هنگام ثبت کلمه اعمال می‌شود.
            </p>
        </div>
        @if (auth()->user()->hasPermission('settings.manage'))
            <div class="v-pagehead__actions">
                <a href="{{ route('settings.seo-packages.create') }}" class="v-btn v-btn--primary">پکیج جدید</a>
            </div>
        @endif
    </header>

    <section class="v-card">
        <div class="v-card__body">
            @if ($packages->isEmpty())
                <x-empty-state
                    title="پکیجی ثبت نشده است"
                    description="بدون پکیج، پروژهٔ سئو بدون سقف کلمات کلیدی ثبت می‌شود."
                    icon="folder"
                />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">پکیج</th>
                                <th scope="col">کد</th>
                                <th scope="col">سقف کلمات</th>
                                <th scope="col">پروژه‌ها</th>
                                <th scope="col">وضعیت</th>
                                @if (auth()->user()->hasPermission('settings.manage'))<th scope="col"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($packages as $package)
                                <tr>
                                    <td class="v-table__primary">
                                        {{ $package->title }}
                                        @if ($package->description)
                                            <span class="v-table__sub">{{ $package->description }}</span>
                                        @endif
                                    </td>
                                    <td dir="ltr">{{ $package->code }}</td>
                                    <td class="v-table__num">
                                        {{ $package->keyword_limit
                                            ? \App\Support\Money::toPersianDigits((string) $package->keyword_limit)
                                            : 'بدون سقف' }}
                                    </td>
                                    <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $package->details_count) }}</td>
                                    <td>
                                        <span class="v-badge {{ $package->is_active ? 'v-badge--success' : 'v-badge--neutral' }}">
                                            {{ $package->is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    @if (auth()->user()->hasPermission('settings.manage'))
                                        <td class="v-table__num">
                                            <a href="{{ route('settings.seo-packages.edit', $package) }}"
                                               class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            {{-- A package with projects on it cannot be deleted, only
                                                 deactivated: the projects must keep showing which
                                                 package they were sold on. --}}
                                            @if ($package->details_count === 0)
                                                <form method="POST" action="{{ route('settings.seo-packages.destroy', $package) }}" class="v-inlineform">
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
