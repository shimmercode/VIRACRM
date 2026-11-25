@extends('layouts.app')

@section('title', 'فاکتورها')

@section('content')
    @php
        $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian();
        $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v);
    @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">فاکتورها</h1>
            <p class="v-pagehead__desc">صدور فاکتور، دریافت وجه نیست؛ مبلغ فاکتور و وجه وصول‌شده دو عدد جدا هستند.</p>
        </div>

        @if (auth()->user()->hasPermission('invoices.create'))
            <div class="v-pagehead__actions">
                <a href="{{ route('invoices.create') }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> فاکتور جدید
                </a>
            </div>
        @endif
    </header>

    <div class="v-report-strip">
        <div class="v-report-strip__cell">
            <span class="v-report-strip__label">جمع فاکتورهای صادرشده</span>
            <span class="v-report-strip__value">{{ $money($summary['issued_total']) }}</span>
        </div>
        <div class="v-report-strip__cell">
            <span class="v-report-strip__label">ماندهٔ وصول‌نشده</span>
            <span class="v-report-strip__value">{{ $money($summary['outstanding']) }}</span>
        </div>
        <div class="v-report-strip__cell {{ $summary['overdue_count'] > 0 ? 'v-report-strip__cell--danger' : '' }}">
            <span class="v-report-strip__label">فاکتورهای معوق</span>
            <span class="v-report-strip__value">{{ $pd($summary['overdue_count']) }}</span>
        </div>
    </div>

    <form class="v-filterbar" method="GET" action="{{ route('invoices.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="شماره یا عنوان فاکتور…" aria-label="جست‌وجوی فاکتور">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-status">وضعیت</label>
            <select id="f-status" name="status" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-pay">پرداخت</label>
            <select id="f-pay" name="payment" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($paymentStates as $key => $label)
                    <option value="{{ $key }}" @selected($filters['payment'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-overdue">معوق</label>
            <select id="f-overdue" name="overdue" class="v-select v-select--sm">
                <option value="">همه</option>
                <option value="1" @selected($filters['overdue'] === '1')>فقط معوق</option>
            </select>
        </div>

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>
    </form>

    <div class="v-card">
        @if ($invoices->isEmpty())
            <x-empty-state title="فاکتوری یافت نشد"
                description="اولین فاکتور را از دکمهٔ «فاکتور جدید» بسازید."
                icon="document" />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">شماره</th>
                            <th scope="col">مشتری</th>
                            <th scope="col">صدور</th>
                            <th scope="col">سررسید</th>
                            <th scope="col" class="v-table__num">مبلغ</th>
                            <th scope="col" class="v-table__num">مانده</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col">پرداخت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a class="v-table__primary" href="{{ route('invoices.show', $invoice) }}">
                                        {{ $invoice->displayName() }}
                                    </a>
                                    @if ($invoice->title)
                                        <div class="v-table__sub">{{ $invoice->title }}</div>
                                    @endif
                                </td>
                                <td>{{ $invoice->customer?->display_name ?? '—' }}</td>
                                <td><x-fa-date :value="$invoice->issue_date" /></td>
                                <td>
                                    <x-fa-date :value="$invoice->due_date" />
                                    @if ($invoice->isOverdue())
                                        <div class="v-table__sub" style="color:var(--vira-danger)">معوق</div>
                                    @endif
                                </td>
                                <td class="v-table__num"><span class="v-money">{{ $money($invoice->total) }}</span></td>
                                <td class="v-table__num"><span class="v-money">{{ $money($invoice->remaining()) }}</span></td>
                                <td>
                                    @if ($invoice->status === 'issued')
                                        <span class="v-badge v-badge--success">صادرشده</span>
                                    @elseif ($invoice->status === 'void')
                                        <span class="v-badge v-badge--danger">ابطال‌شده</span>
                                    @else
                                        <span class="v-badge v-badge--neutral">پیش‌نویس</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($invoice->payment_state === 'paid')
                                        <span class="v-badge v-badge--success">تسویه</span>
                                    @elseif ($invoice->payment_state === 'partial')
                                        <span class="v-badge v-badge--warn">جزئی</span>
                                    @else
                                        <span class="v-badge v-badge--neutral">پرداخت‌نشده</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$invoices" />
        @endif
    </div>
@endsection
