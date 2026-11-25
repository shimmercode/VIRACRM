@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'گزارش‌های مدیریتی — داشبورد، فیلتر و نمودار',
    'why' => 'هیچ مسیر /reports وجود ندارد. مجوز reports.view و reports.export در جدول نقش‌ها هست، ولی هیچ صفحه‌ای از آن استفاده نمی‌کند.',
    'clickable' => 'هیچ گزارشی تولید یا خروجی گرفته نمی‌شود. تغییر بازهٔ تاریخ هیچ نموداری را به‌روز نمی‌کند.',
])

@php
    // Bars are drawn with inline width percentages — no chart library, because
    // the CSP forbids external scripts and the product ships none.
    $kpis = [
        ['درآمد قراردادهای فعال', '۲٬۹۱۵٬۰۰۰٬۰۰۰', 'ریال', '+۱۲٪', 'success'],
        ['قراردادهای در انتظار امضا', '۲', 'مورد', 'بدون تغییر', 'warning'],
        ['نرخ تبدیل سرنخ', '۳۴٪', '—', '+۵٪', 'success'],
        ['میانگین زمان وصول', '۳۸', 'روز', '−۴ روز', 'success'],
    ];

    $monthly = [
        ['فروردین', 62], ['اردیبهشت', 48], ['خرداد', 71],
        ['تیر', 55], ['مرداد', 82], ['شهریور', 66],
        ['مهر', 91], ['آبان', 74],
    ];

    $byStatus = [
        ['فعال', 5, 'success'],
        ['پیش‌نویس', 2, 'info'],
        ['پایان‌یافته', 1, 'neutral'],
    ];
    $statusTotal = array_sum(array_column($byStatus, 1));
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    @foreach ($kpis as [$label, $value, $unit, $delta, $tone])
        <div class="v-review-stat">
            <span class="v-review-stat__value">{{ $value }}</span>
            <span class="v-review-stat__label">{{ $label }}</span>
            <span class="v-small" style="color:var(--vira-{{ $tone === 'success' ? 'success' : 'warning' }});">▲ {{ $delta }} <span class="v-muted">{{ $unit }}</span></span>
        </div>
    @endforeach
</div>

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;">درآمد ماهانه</h2>
        <div style="display:flex;gap:8px;">
            <span class="v-chip"><span class="v-chip__key">از</span><span class="v-chip__value">۱۴۰۵/۰۱/۰۱</span></span>
            <span class="v-chip"><span class="v-chip__key">تا</span><span class="v-chip__value">۱۴۰۵/۰۸/۳۰</span></span>
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">اعمال</span>
        </div>
    </div>
    <div class="v-card__body">
        <div class="v-review-bars" role="img" aria-label="نمودار ستونی درآمد ماهانه — نمایشی">
            @foreach ($monthly as [$month, $pct])
                <div class="v-review-bars__col">
                    <span class="v-review-bars__value">{{ $pct }}٪</span>
                    <div class="v-review-bars__track">
                        <div class="v-review-bars__fill" style="height:{{ $pct }}%;"></div>
                    </div>
                    <span class="v-review-bars__label">{{ $month }}</span>
                </div>
            @endforeach
        </div>
        <p class="v-small v-muted" style="margin:12px 0 0;">
            نمودار با درصد نسبی رسم شده است — هیچ کتابخانهٔ نموداری در محصول نیست و
            CSP بارگذاری اسکریپت خارجی را منع می‌کند.
        </p>
    </div>
</div>

<div class="v-detail-grid">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">قراردادها بر اساس وضعیت</h3></div>
            <div class="v-card__body">
                @foreach ($byStatus as [$label, $n, $tone])
                    <div class="v-review-bar-row">
                        <span class="v-review-bar-row__label">{{ $label }}</span>
                        <div class="v-review-bar-row__track">
                            <div class="v-review-bar-row__fill v-review-bar-row__fill--{{ $tone }}"
                                 style="width:{{ round($n / $statusTotal * 100) }}%;"></div>
                        </div>
                        <span class="v-review-bar-row__value">{{ $n }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="v-card" style="margin-top:var(--vira-space-4);">
            <div class="v-card__head"><h3 class="v-card__title">جدول دادهٔ گزارش</h3></div>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead><tr><th>مشتری</th><th>قرارداد فعال</th><th class="v-table__num">مبلغ</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        <tr><td class="v-table__primary">شرکت آرمان داده پرداز</td><td>۱</td><td class="v-table__num">۴۸۰٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--success">فعال</span></td></tr>
                        <tr><td class="v-table__primary">گروه صنعتی پارس نوین</td><td>۱</td><td class="v-table__num">۳۲۰٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--success">فعال</span></td></tr>
                        <tr><td class="v-table__primary">هلدینگ فناوری نیلگون</td><td>۱</td><td class="v-table__num">۷۲۰٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--success">فعال</span></td></tr>
                        <tr><td class="v-table__primary">پخش دارویی سلامت گستر</td><td>۱</td><td class="v-table__num">۱٬۱۵۰٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--success">فعال</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">گزارش‌های آماده</h3></div>
            <div class="v-card__body" style="display:grid;gap:6px;">
                <span class="v-btn v-btn--ghost v-btn--block" aria-disabled="true">درآمد به تفکیک ماه</span>
                <span class="v-btn v-btn--ghost v-btn--block" aria-disabled="true">قراردادهای نزدیک به انقضا</span>
                <span class="v-btn v-btn--ghost v-btn--block" aria-disabled="true">عملکرد کارشناسان</span>
                <span class="v-btn v-btn--ghost v-btn--block" aria-disabled="true">مطالبات معوق</span>
                <span class="v-btn v-btn--ghost v-btn--block" aria-disabled="true">سودآوری پروژه‌ها</span>
            </div>
            <div class="v-card-row">
                <span class="v-small v-muted">خروجی: Excel / PDF — طرح</span>
                <div class="v-card-row__actions">
                    <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">خروجی</span>
                </div>
            </div>
        </div>

        <div class="v-alert v-alert--warning" style="margin-top:var(--vira-space-4);">
            <div class="v-alert__body">
                <strong>اعداد این صفحه از هیچ جدولی خوانده نشده‌اند</strong>
                <p class="v-small" style="margin:6px 0 0;">
                    برای نشان‌دادن چیدمان نوشته شده‌اند. گزارش واقعی نیازمند
                    تعریف «درآمد» است — مبلغ قرارداد، مبلغ فاکتور، یا مبلغ وصول‌شده؟
                    این تصمیم گرفته نشده و هر سه عدد متفاوت‌اند.
                </p>
            </div>
        </div>
    </aside>
</div>

@endsection
