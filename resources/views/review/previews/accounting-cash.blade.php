@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'نقد و بانک — حساب‌ها و تراکنش‌ها',
    'why' => 'فاز ۳ حسابداری. هیچ جدول حساب بانکی و هیچ مسیری وجود ندارد.',
    'clickable' => 'هیچ تراکنشی ثبت نمی‌شود و هیچ صورت‌حساب بانکی بارگذاری نمی‌شود. مغایرت‌گیری (reconciliation) عملیات واقعی نمی‌کند.',
])

@php
    $accounts = [
        ['بانک ملت — جاری ۱۲۳۴', '۱۱۰۱-۰۱', 842_000_000, 'success', 'آخرین تطبیق: ۱۴۰۵/۰۸/۲۵'],
        ['بانک صادرات — جاری ۶۷۸۹', '۱۱۰۱-۰۲', 248_000_000, 'success', 'آخرین تطبیق: ۱۴۰۵/۰۸/۲۵'],
        ['تنخواه گردان دفتر', '۱۱۰۲-۰۱', 34_500_000, 'warning', 'آخرین تطبیق: ۱۴۰۵/۰۸/۱۰'],
        ['صندوق نقدی', '۱۱۰۳-۰۱', 12_000_000, 'neutral', 'شمارش دستی — طرح'],
    ];
    $tx = [
        ['۱۴۰۵/۰۸/۲۵', 'واریز مشتری — سلامت گستر', '۱۱۰۱-۰۱', 250_000_000, 'in', 'matched'],
        ['۱۴۰۵/۰۸/۲۴', 'پرداخت به تأمین‌کنندهٔ تجهیزات', '۱۱۰۱-۰۲', 98_000_000, 'out', 'matched'],
        ['۱۴۰۵/۰۸/۲۳', 'کارمزد بانکی', '۱۱۰۱-۰۱', 1_200_000, 'out', 'pending'],
        ['۱۴۰۵/۰۸/۲۲', 'دریافت وجه — پارس نوین', '۱۱۰۱-۰۲', 120_000_000, 'in', 'matched'],
        ['۱۴۰۵/۰۸/۲۰', 'هزینهٔ پذیرایی', '۱۱۰۲-۰۱', 4_800_000, 'out', 'unmatched'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۱٬۱۳۶</span><span class="v-review-stat__label">موجودی کل (میلیون ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۳</span><span class="v-review-stat__label">حساب با تطبیق کامل</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۲</span><span class="v-review-stat__label">تراکنش بدون تطبیق</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱۴۰۵/۰۸/۲۵</span><span class="v-review-stat__label">آخرین تطبیق بانکی</span></div>
</div>

<div class="v-review-grid-2">
    @foreach ($accounts as [$name, $code, $balance, $tone, $meta])
        <div class="v-card">
            <div class="v-card__body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                    <div>
                        <div class="v-table__primary">{{ $name }}</div>
                        <div class="v-code v-small">{{ $code }}</div>
                    </div>
                    <span class="v-badge v-badge--{{ $tone }}">{{ $tone === 'success' ? 'تطبیق‌شده' : ($tone === 'warning' ? 'نیازمند بررسی' : 'دستی') }}</span>
                </div>
                <div style="margin-top:12px;font-size:20px;font-weight:700;color:var(--vira-primary);">
                    {{ number_format($balance) }} <span class="v-small v-muted">ریال</span>
                </div>
                <div class="v-small v-muted" style="margin-top:4px;">{{ $meta }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="v-card" style="margin-top:var(--vira-space-4);">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h3 class="v-card__title" style="margin:0;">تراکنش‌های اخیر</h3>
        <div style="display:flex;gap:8px;">
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">بارگذاری صورت‌حساب</span>
            <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">مغایرت‌گیری</span>
        </div>
    </div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>تاریخ</th><th>شرح</th><th>حساب</th><th class="v-table__num">مبلغ</th><th>تطبیق</th></tr></thead>
            <tbody>
                @foreach ($tx as [$date, $desc, $acc, $amount, $dir, $state])
                    <tr>
                        <td class="v-small v-table__nowrap">{{ $date }}</td>
                        <td>
                            <div class="v-table__primary">{{ $desc }}</div>
                            <div class="v-table__sub">{{ $dir === 'in' ? 'واریز' : 'برداشت' }}</div>
                        </td>
                        <td class="v-code v-small">{{ $acc }}</td>
                        <td class="v-table__num" style="color:{{ $dir === 'in' ? 'var(--vira-success)' : 'var(--vira-danger)' }};">
                            {{ $dir === 'in' ? '+' : '−' }}{{ number_format($amount) }}
                        </td>
                        <td>
                            @if ($state === 'matched')
                                <span class="v-badge v-badge--success">تطبیق‌شده</span>
                            @elseif ($state === 'pending')
                                <span class="v-badge v-badge--warning">در انتظار</span>
                            @else
                                <span class="v-badge v-badge--danger">بدون تطبیق</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="v-alert v-alert--danger" style="margin-top:var(--vira-space-4);">
    <div class="v-alert__body">
        <strong>اتصال به بانک وجود ندارد و در F1 هم نخواهد بود</strong>
        <p class="v-small" style="margin:6px 0 0;">
            هیچ API بانکی در محدوده تعریف نشده است. ورود داده دستی یا بارگذاری فایل
            صورت‌حساب است — و هیچ‌کدام پیاده‌سازی نشده. «مغایرت‌گیری خودکار» در این
            صفحه یک برچسب طراحی است، نه قابلیت.
        </p>
    </div>
</div>

@endsection
