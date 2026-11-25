@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'درآمد و هزینه — تفکیک ماهانه',
    'why' => 'فاز ۳ حسابداری. هیچ جدول درآمد/هزینه و هیچ مسیری وجود ندارد.',
    'clickable' => 'هیچ ردیفی افزوده یا ویرایش نمی‌شود. تغییر سال، نمودار را به‌روز نمی‌کند.',
])

@php
    $months = [
        ['فروردین', 420, 268], ['اردیبهشت', 390, 241], ['خرداد', 510, 302],
        ['تیر',   455, 289], ['مرداد',   640, 318], ['شهریور', 520, 295],
        ['مهر',   680, 341], ['آبان',   605, 322],
    ];
    $max = max(array_map(fn ($m) => max($m[1], $m[2]), $months));
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۴٬۲۲۰</span><span class="v-review-stat__label">جمع درآمد (میلیون ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۲٬۳۷۶</span><span class="v-review-stat__label">جمع هزینه (میلیون ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱٬۸۴۴</span><span class="v-review-stat__label">سود ناخالص</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۴۴٪</span><span class="v-review-stat__label">حاشیهٔ سود</span></div>
</div>

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;">درآمد در برابر هزینه</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <span class="v-badge v-badge--success">درآمد</span>
            <span class="v-badge v-badge--danger">هزینه</span>
        </div>
    </div>
    <div class="v-card__body">
        <div class="v-review-bars v-review-bars--pairs" role="img" aria-label="نمودار درآمد و هزینه — نمایشی">
            @foreach ($months as [$m, $income, $expense])
                <div class="v-review-bars__col">
                    <div class="v-review-bars__pair">
                        <div class="v-review-bars__track">
                            <div class="v-review-bars__fill" style="height:{{ round($income / $max * 100) }}%;"></div>
                        </div>
                        <div class="v-review-bars__track">
                            <div class="v-review-bars__fill v-review-bars__fill--expense" style="height:{{ round($expense / $max * 100) }}%;"></div>
                        </div>
                    </div>
                    <span class="v-review-bars__label">{{ $m }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h3 class="v-card__title" style="margin:0;">ردیف‌های درآمد</h3>
        <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">ثبت درآمد</span>
    </div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>تاریخ</th><th>منبع</th><th>مرتبط با</th><th class="v-table__num">مبلغ</th><th>وضعیت</th></tr></thead>
            <tbody>
                <tr><td class="v-small v-table__nowrap">۱۴۰۵/۰۸/۲۴</td><td class="v-table__primary">فاکتور خدمات پشتیبانی</td><td class="v-small">سلامت گستر</td><td class="v-table__num">۲۵۰٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--info">ثبت‌نشده</span></td></tr>
                <tr><td class="v-small v-table__nowrap">۱۴۰۵/۰۸/۲۲</td><td class="v-table__primary">دریافت نقدی</td><td class="v-small">پارس نوین</td><td class="v-table__num">۱۲۰٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--success">ثبت‌شده</span></td></tr>
                <tr><td class="v-small v-table__nowrap">۱۴۰۵/۰۸/۱۵</td><td class="v-table__primary">پیش‌پرداخت پروژه</td><td class="v-small">هلدینگ نیلگون</td><td class="v-table__num">۲۱۶٬۰۰۰٬۰۰۰</td><td><span class="v-badge v-badge--success">ثبت‌شده</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="v-alert v-alert--warning" style="margin-top:var(--vira-space-4);">
    <div class="v-alert__body">
        <strong>سؤال باز: «درآمد» چه زمانی شناسایی می‌شود؟</strong>
        <p class="v-small" style="margin:6px 0 0;">
            هنگام امضای قرارداد، هنگام صدور فاکتور، یا هنگام وصول وجه؟ سه عدد کاملاً متفاوت
            از همین داده تولید می‌شود. تا این تصمیم گرفته نشود، هر نمودار درآمدی
            ممکن است با واقعیت سازمان شما نخواند.
        </p>
    </div>
</div>

@endsection
