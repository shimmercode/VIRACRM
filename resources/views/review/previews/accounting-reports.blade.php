@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'گزارش‌های مالی — سود و زیان، ترازنامه، جریان نقد',
    'why' => 'فاز ۳ حسابداری. هیچ جدول حسابداری و هیچ مسیری وجود ندارد؛ این صفحه طرح سه گزارش استاندارد است.',
    'clickable' => 'هیچ گزارشی محاسبه یا خروجی گرفته نمی‌شود. انتخاب بازه هیچ عددی را تغییر نمی‌دهد.',
])

@php
    $pnl = [
        ['درآمد خدمات',        4_220_000_000, 'income', true],
        ['هزینهٔ حقوق و دستمزد', 1_930_000_000, 'expense', false],
        ['هزینهٔ اجاره و جاری',    380_000_000, 'expense', false],
        ['هزینهٔ بازاریابی',       240_000_000, 'expense', false],
        ['استهلاک',                95_000_000, 'expense', false],
    ];
    $income = 4_220_000_000;
    $expense = 1_930_000_000 + 380_000_000 + 240_000_000 + 95_000_000;
    $profit = $income - $expense;
@endphp

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;">گزارش‌های مالی</h2>
        <div style="display:flex;gap:8px;align-items:center;">
            <span class="v-chip"><span class="v-chip__key">دوره</span><span class="v-chip__value">۱۴۰۵/۰۱/۰۱ – ۱۴۰۵/۰۸/۳۰</span></span>
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">PDF</span>
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">Excel</span>
        </div>
    </div>
    <div class="v-card__body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">سود و زیان</span>
        <span class="v-btn v-btn--ghost v-btn--sm" aria-disabled="true">ترازنامه</span>
        <span class="v-btn v-btn--ghost v-btn--sm" aria-disabled="true">جریان نقد</span>
        <span class="v-btn v-btn--ghost v-btn--sm" aria-disabled="true">دفتر کل</span>
    </div>
</div>

<div class="v-detail-grid">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">صورت سود و زیان</h3></div>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead><tr><th>شرح</th><th class="v-table__num">مبلغ (ریال)</th><th class="v-table__num">٪ درآمد</th></tr></thead>
                    <tbody>
                        @foreach ($pnl as [$label, $amount, $type, $isIncome])
                            <tr>
                                <td>
                                    <span style="{{ $isIncome ? 'font-weight:600;' : 'padding-inline-start:16px;' }}">{{ $label }}</span>
                                </td>
                                <td class="v-table__num v-dl__money">{{ number_format($amount) }}</td>
                                <td class="v-table__num v-small">{{ round($amount / $income * 100) }}٪</td>
                            </tr>
                        @endforeach
                        <tr style="background:var(--vira-surface-sunken);">
                            <td style="font-weight:700;">سود عملیاتی</td>
                            <td class="v-table__num v-dl__money" style="font-weight:700;">{{ number_format($profit) }}</td>
                            <td class="v-table__num v-small" style="font-weight:700;">{{ round($profit / $income * 100) }}٪</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">جریان نقد (خلاصه)</h3></div>
            <div class="v-card__body">
                <div class="v-dl">
                    <div class="v-dl__item"><span class="v-dl__term">ورودی عملیاتی</span><span class="v-dl__value v-dl__money" style="color:var(--vira-success);">+۳٬۱۴۰٬۰۰۰٬۰۰۰</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">خروجی عملیاتی</span><span class="v-dl__value v-dl__money" style="color:var(--vira-danger);">−۲٬۲۹۵٬۰۰۰٬۰۰۰</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">خالص عملیاتی</span><span class="v-dl__value v-dl__money"><strong>+۸۴۵٬۰۰۰٬۰۰۰</strong></span></div>
                    <div class="v-dl__item"><span class="v-dl__term">خالص سرمایه‌ای</span><span class="v-dl__value v-dl__money">−۹۸٬۰۰۰٬۰۰۰</span></div>
                </div>
            </div>
        </div>

        <div class="v-alert v-alert--danger" style="margin-top:var(--vira-space-4);">
            <div class="v-alert__body">
                <strong>گزارش مالی بدون دفتر کل معتبر نیست</strong>
                <p class="v-small" style="margin:6px 0 0;">
                    این اعداد از هیچ سندی مشتق نشده‌اند. یک گزارش مالی واقعی باید از
                    اسناد حسابداری قابل ردیابی باشد؛ اینجا فقط چیدمان جدول نشان داده
                    می‌شود. هیچ ادعایی دربارهٔ درستی مالی نمی‌شود.
                </p>
            </div>
        </div>
    </aside>
</div>

@endsection
