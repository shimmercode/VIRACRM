@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'داشبورد حسابداری',
    'why' => 'حسابداری در فاز ۳ برنامه‌ریزی شده است. هیچ جدول سند، هیچ جدول حساب و هیچ کنترلری وجود ندارد — این صفحه یک طراحی برای بازبینی است.',
    'clickable' => 'هیچ سندی ثبت یا تأیید نمی‌شود. انتخاب سال مالی نمودارها را تغییر نمی‌دهد. دکمهٔ «بستن دوره» غیرفعال است.',
])

@php
    $accounts = [
        ['۱۱۰۱', 'موجودی نقد و بانک',        1_240_000_000, 'asset'],
        ['۱۲۰۱', 'حساب‌های دریافتنی',        2_180_000_000, 'asset'],
        ['۲۱۰۱', 'حساب‌های پرداختنی',        -850_000_000, 'liability'],
        ['۴۱۰۱', 'درآمد خدمات',              4_620_000_000, 'income'],
        ['۵۱۰۱', 'هزینهٔ حقوق و دستمزد',       -1_930_000_000, 'expense'],
        ['۵۲۰۱', 'هزینهٔ اجاره و جاری',        -380_000_000, 'expense'],
    ];
    $typeLabels = [
        'asset' => ['دارایی', 'primary'],
        'liability' => ['بدهی', 'warning'],
        'income' => ['درآمد', 'success'],
        'expense' => ['هزینه', 'danger'],
    ];
    $journals = [
        ['۱۴۰۵/۰۸/۲۴', 'ثبت فاکتور فروش — سلامت گستر', '۲۵۰٬۰۰۰٬۰۰۰', 'draft'],
        ['۱۴۰۵/۰۸/۲۲', 'دریافت وجه از پارس نوین', '۱۲۰٬۰۰۰٬۰۰۰', 'posted'],
        ['۱۴۰۵/۰۸/۲۰', 'پرداخت حقوق مهر', '۴۰۸٬۵۰۰٬۰۰۰', 'posted'],
        ['۱۴۰۵/۰۸/۱۸', 'هزینهٔ اجارهٔ دفتر — آبان', '۳۵٬۰۰۰٬۰۰۰', 'posted'],
        ['۱۴۰۵/۰۸/۱۵', 'فاکتور خرید تجهیزات', '۹۸٬۰۰۰٬۰۰۰', 'void'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۴٫۶۲</span><span class="v-review-stat__label">درآمد سال (میلیارد ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۲٫۳۱</span><span class="v-review-stat__label">هزینهٔ سال (میلیارد ریال)</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۲٫۱۸</span><span class="v-review-stat__label">مطالبات وصول‌نشده</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">دورهٔ ۰۸</span><span class="v-review-stat__label">دورهٔ باز — بسته نشده</span></div>
</div>

<div class="v-detail-grid">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2 style="margin:0;">اسناد حسابداری اخیر</h2>
                <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">سند جدید</span>
            </div>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead><tr><th>تاریخ</th><th>شرح</th><th class="v-table__num">مبلغ</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        @foreach ($journals as [$date, $desc, $amount, $state])
                            <tr>
                                <td class="v-small v-table__nowrap">{{ $date }}</td>
                                <td class="v-table__primary">{{ $desc }}</td>
                                <td class="v-table__num">{{ $amount }}</td>
                                <td>
                                    @if ($state === 'draft')
                                        <span class="v-badge v-badge--info">پیش‌نویس</span>
                                    @elseif ($state === 'posted')
                                        <span class="v-badge v-badge--success">ثبت‌شده</span>
                                    @else
                                        <span class="v-badge v-badge--neutral">ابطال‌شده</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="v-card" style="margin-top:var(--vira-space-4);">
            <div class="v-card__head"><h3 class="v-card__title">ترازنامهٔ خلاصه</h3></div>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead><tr><th>کد</th><th>حساب</th><th>نوع</th><th class="v-table__num">مانده (ریال)</th></tr></thead>
                    <tbody>
                        @foreach ($accounts as [$code, $name, $amount, $type])
                            <tr>
                                <td class="v-code v-small">{{ $code }}</td>
                                <td>{{ $name }}</td>
                                <td><span class="v-badge v-badge--{{ $typeLabels[$type][1] }}">{{ $typeLabels[$type][0] }}</span></td>
                                <td class="v-table__num v-dl__money">{{ number_format(abs($amount)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">وضعیت دوره</h3></div>
            <div class="v-card__body">
                <div class="v-dl">
                    <div class="v-dl__item"><span class="v-dl__term">سال مالی</span><span class="v-dl__value">۱۴۰۵</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">دورهٔ جاری</span><span class="v-dl__value">آبان (۰۸)</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">اسناد ثبت‌نشده</span><span class="v-dl__value"><span class="v-badge v-badge--warning">۱ پیش‌نویس</span></span></div>
                    <div class="v-dl__item"><span class="v-dl__term">بستن دوره</span><span class="v-dl__value"><span class="v-badge v-badge--danger">غیرفعال</span></span></div>
                </div>
                <p class="v-small v-muted" style="margin:12px 0 0;">
                    بستن دوره عمداً غیرفعال است: بستن یک دورهٔ مالی برگشت‌ناپذیر است و
                    نباید در یک صفحهٔ نمایشی حتی قابل کلیک باشد.
                </p>
            </div>
        </div>

        <div class="v-card" style="margin-top:var(--vira-space-4);">
            <div class="v-card__head"><h3 class="v-card__title">زیرصفحه‌ها</h3></div>
            <div class="v-card__body" style="display:grid;gap:6px;">
                <a class="v-btn v-btn--ghost v-btn--block" href="{{ route('review.preview', ['key' => 'accounting-income']) }}">درآمد و هزینه</a>
                <a class="v-btn v-btn--ghost v-btn--block" href="{{ route('review.preview', ['key' => 'accounting-cash']) }}">نقد و بانک</a>
                <a class="v-btn v-btn--ghost v-btn--block" href="{{ route('review.preview', ['key' => 'accounting-receivables']) }}">مطالبات و بدهی‌ها</a>
                <a class="v-btn v-btn--ghost v-btn--block" href="{{ route('review.preview', ['key' => 'accounting-reports']) }}">گزارش‌های مالی</a>
            </div>
        </div>

        <div class="v-alert v-alert--danger" style="margin-top:var(--vira-space-4);">
            <div class="v-alert__body">
                <strong>هیچ جدول حسابداری وجود ندارد</strong>
                <p class="v-small" style="margin:6px 0 0;">
                    نه <code>accounts</code>، نه <code>journal_entries</code>، نه <code>fiscal_periods</code>.
                    اعداد این صفحه نمونه‌اند. طراحی برای تصمیم‌گیری است، نه اثبات قابلیت.
                </p>
            </div>
        </div>
    </aside>
</div>

@endsection
