@extends('layouts.app')

@section('title', 'حقوق ' . $period->title())

@section('content')
    @php use App\Support\Money; use App\Models\PayrollPeriod; @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('payroll.index') }}">حقوق و دستمزد</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $period->title() }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">حقوق {{ $period->title() }}</h1>
            <p class="v-pagehead__desc">
                @if ($period->isPublished())
                    این دوره منتشر شده و ارقام آن قطعی است.
                    @if ($entry)
                        سند حسابداری آن در دفتر کل ثبت شده است.
                    @endif
                @else
                    پیش از انتشار، ارقام قابل تغییر است. پس از انتشار، اصلاح فقط با ثبت دلیل و
                    نگه‌داشتن نسخهٔ قبلی انجام می‌شود.
                @endif
            </p>
        </div>

        <div class="v-pagehead__actions">
            @php
                $tone = match ($period->state) {
                    'published' => 'success',
                    'computed' => 'info',
                    default => 'neutral',
                };
            @endphp
            <x-badge :tone="$tone" :dot="true">
                {{ PayrollPeriod::STATES[$period->state] ?? $period->state }}
            </x-badge>
        </div>
    </header>

    {{-- ── Totals ────────────────────────────────────────────────────── --}}
    <section class="v-stats" aria-label="خلاصهٔ دورهٔ حقوق">
        <div class="v-stat">
            <span class="v-stat__label">تعداد فیش</span>
            <span class="v-stat__value">{{ Money::toPersianDigits((string) $payslips->count()) }}</span>
        </div>
        <div class="v-stat">
            <span class="v-stat__label">جمع ناخالص</span>
            <span class="v-stat__value">{{ Money::fromRial($gross)->formatPersian() }}</span>
        </div>
        <div class="v-stat">
            <span class="v-stat__label">جمع کسورات</span>
            <span class="v-stat__value">{{ Money::fromRial($deductions)->formatPersian() }}</span>
        </div>
        <div class="v-stat v-stat--accent">
            <span class="v-stat__label">جمع خالص پرداختی</span>
            <span class="v-stat__value">{{ Money::fromRial($net)->formatPersian() }}</span>
        </div>
    </section>

    {{-- ── Actions legal in this state ───────────────────────────────── --}}
    @if ($canManage && ! $period->isPublished())
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">عملیات دوره</h2>
            </header>
            <div class="v-card__body v-actionsrow">
                <form method="POST" action="{{ route('payroll.build', $period) }}">
                    @csrf
                    <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
                        <x-icon name="users" :size="16" /> ساخت فیش کارمندان فعال
                    </button>
                </form>

                <form method="POST" action="{{ route('payroll.compute', $period) }}">
                    @csrf
                    <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
                        <x-icon name="check" :size="16" /> محاسبهٔ مجدد همه
                    </button>
                </form>

                @if ($canApprove)
                    <form method="POST" action="{{ route('payroll.publish', $period) }}"
                          data-confirm="انتشار دورهٔ {{ $period->title() }} قطعی است و سند آن در دفتر کل ثبت می‌شود. ادامه می‌دهید؟">
                        @csrf
                        <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="lock" :size="16" /> انتشار و ثبت سند
                        </button>
                    </form>
                @else
                    <p class="v-hint" style="margin:0">
                        انتشار دوره نیازمند مجوز «تأیید حقوق» است.
                    </p>
                @endif
            </div>
        </section>
    @endif

    {{-- ── The ledger entry, once posted ─────────────────────────────── --}}
    @if ($entry)
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">سند حسابداری</h2>
            </header>
            <div class="v-card__body">
                <p>
                    سند شمارهٔ
                    <a href="{{ route('accounting.journal.show', $entry) }}"><strong>{{ $entry->number }}</strong></a>
                    با مبلغ {{ Money::fromRial((int) $entry->lines()->sum('debit'))->formatPersian() }} ثبت شده است.
                    @if ($entry->status === 'reversed')
                        <x-badge tone="danger" :dot="true">برگشت‌خورده</x-badge>
                    @endif
                </p>
                <p class="v-muted">
                    بدهی «حقوق و دستمزد» و بستانکاری «حقوق پرداختنی» — کسورات و مساعده‌ها بدهکار
                    می‌شوند چون بخشی از تعهد را تسویه کرده‌اند.
                </p>
            </div>
        </section>
    @endif

    {{-- ── Payslip table ─────────────────────────────────────────────── --}}
    <div class="v-card">
        @if ($payslips->isEmpty())
            <x-empty-state title="این دوره فیش حقوقی ندارد"
                description="با دکمهٔ «ساخت فیش کارمندان فعال» برای هر کارمند شاغل یک فیش ساخته می‌شود."
                icon="banknote">
                @if ($canManage)
                    <form method="POST" action="{{ route('payroll.build', $period) }}">
                        @csrf
                        <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="users" :size="16" /> ساخت فیش‌ها
                        </button>
                    </form>
                @endif
            </x-empty-state>
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">کارمند</th>
                            <th scope="col" class="v-table__num">حقوق پایه</th>
                            <th scope="col" class="v-table__num">اقلام دستی</th>
                            <th scope="col" class="v-table__num">جمع دریافتی</th>
                            <th scope="col" class="v-table__num">کسورات</th>
                            <th scope="col" class="v-table__num">خالص پرداختی</th>
                            <th scope="col">محاسبه</th>
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payslips as $payslip)
                            <tr>
                                <td data-label="کارمند">
                                    <a class="v-table__primary" href="{{ route('payroll.payslip', $payslip) }}">
                                        {{ $payslip->employee?->displayName() ?? '—' }}
                                    </a>
                                    <div class="v-table__sub">
                                        {{ $payslip->employee?->code ? Money::toPersianDigits($payslip->employee->code) : '' }}
                                    </div>
                                </td>
                                <td data-label="حقوق پایه" class="v-table__num">{{ Money::fromRial((int) $payslip->base_salary)->formatPersian() }}</td>
                                <td data-label="اقلام دستی" class="v-table__num">
                                    {{ Money::toPersianDigits((string) $payslip->items->count()) }} قلم
                                </td>
                                <td data-label="جمع دریافتی" class="v-table__num">{{ Money::fromRial((int) $payslip->total_earnings)->formatPersian() }}</td>
                                <td data-label="کسورات" class="v-table__num">{{ Money::fromRial((int) $payslip->total_deductions)->formatPersian() }}</td>
                                <td data-label="خالص پرداختی" class="v-table__num">
                                    <strong>{{ Money::fromRial((int) $payslip->net_payable)->formatPersian() }}</strong>
                                </td>
                                <td data-label="محاسبه">
                                    @if ($payslip->computed_at)
                                        <x-fa-date :value="$payslip->computed_at" :with-time="true" />
                                    @else
                                        <x-badge tone="warning" :dot="true">محاسبه نشده</x-badge>
                                    @endif
                                </td>
                                <td class="v-table__actions">
                                    <a class="v-iconbtn" href="{{ route('payroll.payslip', $payslip) }}"
                                       aria-label="ویرایش فیش {{ $payslip->employee?->displayName() }}">
                                        @if ($period->isPublished())
                                            <x-icon name="eye" :size="16" />
                                        @else
                                            <x-icon name="edit" :size="16" />
                                        @endif
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
