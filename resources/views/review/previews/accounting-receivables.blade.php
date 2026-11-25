@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'مطالبات و بدهی‌ها — سن مطالبات',
    'why' => 'فاز ۳ حسابداری. هیچ جدول مطالبات و هیچ مسیری وجود ندارد.',
    'clickable' => 'هیچ مطالبه‌ای وصول یا تعدیل نمی‌شود. «ارسال یادآوری» ایمیلی ارسال نمی‌کند.',
])

@php
    $buckets = [
        ['جاری', 0, 30, 1_420_000_000, 'success'],
        ['۱ تا ۳۰ روز', 31, 60, 520_000_000, 'info'],
        ['۳۱ تا ۶۰ روز', 61, 90, 180_000_000, 'warning'],
        ['بیش از ۹۰ روز', 91, null, 60_000_000, 'danger'],
    ];
    $total = array_sum(array_column($buckets, 3));

    $receivables = [
        ['پخش دارویی سلامت گستر', 'RVW-C-0007', '۲۵۰٬۰۰۰٬۰۰۰', '۱۴۰۵/۰۸/۰۱', 24, 'open'],
        ['هلدینگ فناوری نیلگون', 'RVW-C-0005', '۲۱۶٬۰۰۰٬۰۰۰', '۱۴۰۵/۰۷/۱۵', 41, 'partial'],
        ['گروه صنعتی پارس نوین', 'RVW-C-0002', '۶۴٬۰۰۰٬۰۰۰', '۱۴۰۵/۰۶/۱۰', 76, 'overdue'],
        ['کلینیک تخصصی مهرگان', 'RVW-C-0006', '۶۰٬۰۰۰٬۰۰۰', '۱۴۰۵/۰۴/۲۰', 127, 'overdue'],
        ['فروشگاه اینترنتی دیبا', 'RVW-C-0003', '۹۶٬۰۰۰٬۰۰۰', '۱۴۰۵/۰۸/۲۰', 5, 'open'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">{{ number_format($total / 1_000_000) }}</span><span class="v-review-stat__label">کل مطالبات (میلیون ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱۸۰</span><span class="v-review-stat__label">سررسیدشده (میلیون ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۶۰</span><span class="v-review-stat__label">بیش از ۹۰ روز</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۴۵</span><span class="v-review-stat__label">میانگین تأخیر (روز)</span></div>
</div>

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__head"><h2 style="margin:0;">سن مطالبات</h2></div>
    <div class="v-card__body">
        @foreach ($buckets as [$label, $from, $to, $amount, $tone])
            <div class="v-review-bar-row">
                <span class="v-review-bar-row__label">{{ $label }}</span>
                <div class="v-review-bar-row__track">
                    <div class="v-review-bar-row__fill v-review-bar-row__fill--{{ $tone }}"
                         style="width:{{ round($amount / $total * 100) }}%;"></div>
                </div>
                <span class="v-review-bar-row__value">{{ number_format($amount / 1_000_000) }} م</span>
            </div>
        @endforeach
        <p class="v-small v-muted" style="margin:12px 0 0;">
            مرزهای سن مطالبات یک انتخاب مدیریتی است. اینجا ۳۰/۶۰/۹۰ فرض شده —
            اگر سازمان شما مرز دیگری دارد، همین عددها باید عوض شوند.
        </p>
    </div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h3 class="v-card__title" style="margin:0;">مطالبات باز</h3>
        <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">ارسال یادآوری گروهی</span>
    </div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>مشتری</th><th>قرارداد</th><th class="v-table__num">مانده</th><th>سررسید</th><th>تأخیر</th><th>وضعیت</th></tr></thead>
            <tbody>
                @foreach ($receivables as [$name, $contract, $amount, $due, $days, $state])
                    <tr>
                        <td class="v-table__primary">{{ $name }}</td>
                        <td class="v-code v-small">{{ $contract }}</td>
                        <td class="v-table__num">{{ $amount }}</td>
                        <td class="v-small v-table__nowrap">{{ $due }}</td>
                        <td class="v-table__num">
                            @if ($days > 90)
                                <span style="color:var(--vira-danger);font-weight:700;">{{ $days }} روز</span>
                            @elseif ($days > 60)
                                <span style="color:var(--vira-warning);font-weight:600;">{{ $days }} روز</span>
                            @else
                                {{ $days }} روز
                            @endif
                        </td>
                        <td>
                            @if ($state === 'open')
                                <span class="v-badge v-badge--info">باز</span>
                            @elseif ($state === 'partial')
                                <span class="v-badge v-badge--warning">پرداخت جزئی</span>
                            @else
                                <span class="v-badge v-badge--danger">معوق</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="v-alert v-alert--warning" style="margin-top:var(--vira-space-4);">
    <div class="v-alert__body">
        <strong>«ارسال یادآوری» به ایمیل واقعی وصل نیست</strong>
        <p class="v-small" style="margin:6px 0 0;">
            در محیط بازبینی هیچ ایمیل یا پیامکی ارسال نمی‌شود — نه به‌صورت آزمایشی و نه
            به‌صورت واقعی. حتی اگر این دکمه پیاده‌سازی شود، در این محیط غیرفعال می‌ماند.
        </p>
    </div>
</div>

@endsection
