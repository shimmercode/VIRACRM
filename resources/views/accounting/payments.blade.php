@extends('layouts.app')

@section('title', 'دریافت و پرداخت')

@section('content')
    @php
        $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian();
    @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">دریافت و پرداخت</h1>
            <p class="v-pagehead__desc">هر دریافت، یک حرکت نقدی است — جدا از صدور فاکتور.</p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'payments'])

    <div class="v-report-strip">
        <div class="v-report-strip__cell v-report-strip__cell--success">
            <span class="v-report-strip__label">جمع دریافت‌ها</span>
            <span class="v-report-strip__value">{{ $money($totalIn) }}</span>
        </div>
        <div class="v-report-strip__cell">
            <span class="v-report-strip__label">جمع پرداخت‌ها</span>
            <span class="v-report-strip__value">{{ $money($totalOut) }}</span>
        </div>
    </div>

    <form class="v-filterbar" method="GET" action="{{ route('accounting.payments') }}">
        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-dir">نوع</label>
            <select id="f-dir" name="direction" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($directions as $key => $label)
                    <option value="{{ $key }}" @selected($direction === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>
    </form>

    <div class="v-card">
        @if ($payments->isEmpty())
            <x-empty-state title="پرداختی ثبت نشده است"
                description="با ثبت دریافت روی فاکتور صادرشده، اولین پرداخت اینجا دیده می‌شود." icon="banknote" />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">شماره</th>
                            <th scope="col">تاریخ</th>
                            <th scope="col">نوع</th>
                            <th scope="col">مشتری</th>
                            <th scope="col">فاکتور</th>
                            <th scope="col">روش</th>
                            <th scope="col" class="v-table__num">مبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td><span class="v-account-code">{{ $payment->number }}</span></td>
                                <td><x-fa-date :value="$payment->paid_on" /></td>
                                <td>
                                    <span class="v-badge v-badge--{{ $payment->direction === 'in' ? 'success' : 'neutral' }}">
                                        {{ $directions[$payment->direction] ?? $payment->direction }}
                                    </span>
                                </td>
                                <td>{{ $payment->customer?->display_name ?? '—' }}</td>
                                <td>
                                    @if ($payment->invoice)
                                        {{ $payment->invoice->displayName() }}
                                    @else
                                        <span class="v-dl__value--empty">—</span>
                                    @endif
                                </td>
                                <td>{{ $methods[$payment->method] ?? $payment->method }}</td>
                                <td class="v-table__num"><span class="v-money">{{ $money($payment->amount) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$payments" />
        @endif
    </div>
@endsection
