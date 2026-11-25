@extends('layouts.app')

@section('title', 'دوره‌های مالی')

@section('content')
    @php $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v); @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">دوره‌های مالی</h1>
            <p class="v-pagehead__desc">
                بستن دوره، ثبت سند جدید در آن را متوقف می‌کند. بازکردن دورهٔ بسته‌شده مجوز جداگانه و رویداد جدا دارد.
            </p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'periods'])

    @if ($current)
        <div class="v-notice v-notice--warn">
            دورهٔ جاری: <strong>{{ $current->title }}</strong>
        </div>
    @endif

    <div class="v-card">
        @if ($periods->isEmpty())
            <x-empty-state title="دوره‌ای تعریف نشده است"
                description="برای ثبت سند، ابتدا یک دورهٔ مالی تعریف کنید." icon="calendar" />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">کد</th>
                            <th scope="col">عنوان</th>
                            <th scope="col">از</th>
                            <th scope="col">تا</th>
                            <th scope="col">اسناد</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($periods as $period)
                            <tr>
                                <td><span class="v-account-code">{{ $period->code }}</span></td>
                                <td>{{ $period->title }}</td>
                                <td><x-fa-date :value="$period->starts_on" /></td>
                                <td><x-fa-date :value="$period->ends_on" /></td>
                                <td>{{ $pd($period->entries_count) }}</td>
                                <td>
                                    @if ($period->isOpen())
                                        <span class="v-badge v-badge--warn">باز</span>
                                    @else
                                        <span class="v-badge v-badge--neutral">بسته</span>
                                    @endif
                                </td>
                                <td class="v-table__actions">
                                    @if ($period->isOpen())
                                        @if (auth()->user()->hasPermission('accounting.close'))
                                            <form method="POST" action="{{ route('accounting.periods.close', $period) }}"
                                                  onsubmit="return confirm('دوره بسته شود؟ ثبت سند جدید در آن ممکن نخواهد بود.');">
                                                @csrf
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">بستن دوره</button>
                                            </form>
                                        @endif
                                    @else
                                        @if (auth()->user()->hasPermission('accounting.reopen'))
                                            <form method="POST" action="{{ route('accounting.periods.reopen', $period) }}"
                                                  onsubmit="return confirm('دورهٔ بسته‌شده باز شود؟ این تغییر در گزارش رویدادها ثبت می‌شود.');">
                                                @csrf
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">بازکردن</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
