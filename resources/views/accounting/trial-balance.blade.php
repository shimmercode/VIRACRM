@extends('layouts.app')

@section('title', 'تراز آزمایشی')

@section('content')
    @php
        $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v);
        $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian();
    @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">تراز آزمایشی</h1>
            <p class="v-pagehead__desc">
                جمع بدهکار و بستانکار همهٔ اسناد قطعی. اگر این دو برابر نباشند، دفتر مشکل دارد.
            </p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'trial'])

    <form class="v-filterbar" method="GET" action="{{ route('accounting.trial-balance') }}">
        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-period">دوره مالی</label>
            <select id="f-period" name="period" class="v-select v-select--sm">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}" @selected($periodId === $period->id)>
                        {{ $period->title }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>
    </form>

    {{-- An imbalance is not a cosmetic warning. Surfaced first, in danger tone,
         because every report above this one is suspect until it is explained. --}}
    @if (! $report['balanced'])
        <div class="v-notice v-notice--danger" role="alert">
            <div class="v-notice__title">دفتر تراز نیست</div>
            جمع بدهکار {{ $money($report['totals']['debit']) }} ریال و جمع بستانکار
            {{ $money($report['totals']['credit']) }} ریال است.
            اختلاف: {{ $money(abs($report['totals']['debit'] - $report['totals']['credit'])) }} ریال.
        </div>
    @endif

    @if (! $report['has_data'])
        <x-empty-state
            title="در این دوره سندی ثبت نشده است"
            description="پس از صدور فاکتور یا ثبت دریافت، اسناد به‌صورت خودکار در این گزارش دیده می‌شوند."
            icon="chart"
        />
    @else
        <div class="v-report-strip">
            <div class="v-report-strip__cell">
                <span class="v-report-strip__label">جمع بدهکار</span>
                <span class="v-report-strip__value">{{ $money($report['totals']['debit']) }}</span>
            </div>
            <div class="v-report-strip__cell">
                <span class="v-report-strip__label">جمع بستانکار</span>
                <span class="v-report-strip__value">{{ $money($report['totals']['credit']) }}</span>
            </div>
            <div class="v-report-strip__cell {{ $report['balanced'] ? 'v-report-strip__cell--success' : 'v-report-strip__cell--danger' }}">
                <span class="v-report-strip__label">وضعیت تراز</span>
                <span class="v-report-strip__value">
                    {{ $report['balanced'] ? 'تراز است' : 'نامتوازن' }}
                </span>
            </div>
        </div>

        <div class="v-card">
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">کد</th>
                            <th scope="col">حساب</th>
                            <th scope="col" class="v-table__num">بدهکار</th>
                            <th scope="col" class="v-table__num">بستانکار</th>
                            <th scope="col" class="v-table__num">مانده</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr>
                                <td><span class="v-account-code">{{ $row['code'] }}</span></td>
                                <td>{{ $row['title'] }}</td>
                                <td class="v-table__num">
                                    <span class="v-money">{{ $money($row['debit']) }}</span>
                                </td>
                                <td class="v-table__num">
                                    <span class="v-money">{{ $money($row['credit']) }}</span>
                                </td>
                                <td class="v-table__num">
                                    <span class="v-money {{ $row['balance'] < 0 ? 'v-money--negative' : '' }}">
                                        {{ $money($row['balance']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2">جمع کل</th>
                            <th class="v-table__num">
                                <span class="v-money v-money--total">{{ $money($report['totals']['debit']) }}</span>
                            </th>
                            <th class="v-table__num">
                                <span class="v-money v-money--total">{{ $money($report['totals']['credit']) }}</span>
                            </th>
                            <th class="v-table__num">—</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <p class="v-hint">همهٔ مبالغ به ریال است.</p>
    @endif
@endsection
