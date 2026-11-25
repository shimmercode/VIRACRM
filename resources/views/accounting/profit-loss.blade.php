@extends('layouts.app')

@section('title', 'سود و زیان')

@section('content')
    @php
        $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian();
    @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">سود و زیان</h1>
            <p class="v-pagehead__desc">
                درآمد و هزینهٔ دوره، بر پایهٔ اسناد قطعی. مبلغ فروش بدون دریافت وجه، درآمد محسوب می‌شود؛
                «وجه نقد دریافتی» گزارش جداگانه است.
            </p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'pl'])

    <form class="v-filterbar" method="GET" action="{{ route('accounting.profit-loss') }}">
        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-period">دوره مالی</label>
            <select id="f-period" name="period" class="v-select v-select--sm">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}" @selected($periodId === $period->id)>{{ $period->title }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>
    </form>

    @if (! $report['has_data'])
        {{-- §19.5 + the "no fabricated zero" rule: nothing posted is NOT zero
             profit, and saying «۰ ریال» here would be a lie a manager acts on. --}}
        <div class="v-notice">
            <div class="v-notice__title">داده‌ای برای این دوره ثبت نشده است</div>
            سود و زیان تنها زمانی محاسبه می‌شود که سند قطعی در دوره وجود داشته باشد.
            این گزارش عمداً عدد صفر نشان نمی‌دهد؛ «صفر» و «بدون داده» دو چیز متفاوت‌اند.
        </div>
    @else
        <div class="v-report-strip">
            <div class="v-report-strip__cell">
                <span class="v-report-strip__label">جمع درآمد</span>
                <span class="v-report-strip__value">{{ $money($report['revenue_total']) }}</span>
            </div>
            <div class="v-report-strip__cell">
                <span class="v-report-strip__label">جمع هزینه</span>
                <span class="v-report-strip__value">{{ $money($report['expense_total']) }}</span>
            </div>
            <div class="v-report-strip__cell {{ $report['net'] >= 0 ? 'v-report-strip__cell--success' : 'v-report-strip__cell--danger' }}">
                <span class="v-report-strip__label">سود / زیان خالص</span>
                <span class="v-report-strip__value">{{ $money($report['net']) }}</span>
            </div>
        </div>

        <div class="v-grid v-grid--2">
            <section class="v-card">
                <h2 class="v-card__title">درآمد</h2>
                @if ($report['revenue'] === [])
                    <p class="v-hint">درآمدی ثبت نشده است.</p>
                @else
                    <dl class="v-dl">
                        @foreach ($report['revenue'] as $row)
                            <div>
                                <dt>{{ $row['title'] }} <span class="v-account-code">{{ $row['code'] }}</span></dt>
                                <dd><span class="v-money">{{ $money($row['amount']) }}</span></dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>

            <section class="v-card">
                <h2 class="v-card__title">هزینه</h2>
                @if ($report['expense'] === [])
                    <p class="v-hint">هزینه‌ای ثبت نشده است.</p>
                @else
                    <dl class="v-dl">
                        @foreach ($report['expense'] as $row)
                            <div>
                                <dt>{{ $row['title'] }} <span class="v-account-code">{{ $row['code'] }}</span></dt>
                                <dd><span class="v-money">{{ $money($row['amount']) }}</span></dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        </div>

        @if ($byService !== [])
            <section class="v-card">
                <h2 class="v-card__title">درآمد به تفکیک خدمت</h2>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead><tr><th scope="col">خدمت</th><th scope="col" class="v-table__num">درآمد</th></tr></thead>
                        <tbody>
                            @foreach ($byService as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="v-table__num"><span class="v-money">{{ $money($row['amount']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($byCustomer !== [])
            <section class="v-card">
                <h2 class="v-card__title">درآمد به تفکیک مشتری</h2>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead><tr><th scope="col">مشتری</th><th scope="col" class="v-table__num">درآمد</th></tr></thead>
                        <tbody>
                            @foreach ($byCustomer as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="v-table__num"><span class="v-money">{{ $money($row['amount']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
@endsection
