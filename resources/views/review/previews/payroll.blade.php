@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'دوره‌های حقوق و فیش حقوقی',
    'why' => 'جدول‌های payroll_periods، payroll_items و payslips در اسکیما وجود دارند ولی هیچ مسیری آن‌ها را نمی‌خواند. این صفحه طراحی است.',
    'clickable' => 'هیچ دوره‌ای محاسبه، تأیید یا پرداخت نمی‌شود. دکمهٔ «محاسبه» فقط طرح است.',
])

@php
    $periods = [
        ['۱۴۰۵/۰۸', 'آبان ۱۴۰۵', 'draft',     10, 412_000_000, null],
        ['۱۴۰۵/۰۷', 'مهر ۱۴۰۵',  'approved',  10, 408_500_000, '۱۴۰۵/۰۸/۰۲'],
        ['۱۴۰۵/۰۶', 'شهریور ۱۴۰۵','paid',      10, 405_200_000, '۱۴۰۵/۰۷/۰۱'],
        ['۱۴۰۵/۰۵', 'مرداد ۱۴۰۵', 'paid',       9, 371_800_000, '۱۴۰۵/۰۶/۰۳'],
        ['۱۴۰۵/۰۴', 'تیر ۱۴۰۵',   'paid',       9, 369_400_000, '۱۴۰۵/۰۵/۰۲'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۵</span><span class="v-review-stat__label">دورهٔ نمایش‌داده‌شده</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱</span><span class="v-review-stat__label">در انتظار تأیید</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۳</span><span class="v-review-stat__label">پرداخت‌شده</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">—</span><span class="v-review-stat__label">جمع سال (طرح)</span></div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h2 style="margin:0;">دوره‌های حقوق</h2>
        <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">ایجاد دوره</span>
    </div>
    <div class="v-table-wrap">
        <table class="v-table v-table--fixed">
            <thead>
                <tr>
                    <th style="width:16%">دوره</th>
                    <th style="width:18%">ماه</th>
                    <th style="width:14%">وضعیت</th>
                    <th style="width:12%">تعداد نفر</th>
                    <th style="width:22%">جمع خالص</th>
                    <th style="width:18%">تأیید</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as [$key, $label, $status, $count, $total, $approvedAt])
                    <tr>
                        <td class="v-code v-small">{{ $key }}</td>
                        <td class="v-table__primary">{{ $label }}</td>
                        <td>
                            @if ($status === 'draft')
                                <span class="v-badge v-badge--info">پیش‌نویس</span>
                            @elseif ($status === 'approved')
                                <span class="v-badge v-badge--warning">تأییدشده</span>
                            @else
                                <span class="v-badge v-badge--success">پرداخت‌شده</span>
                            @endif
                        </td>
                        <td class="v-table__num">{{ $count }}</td>
                        <td class="v-table__num">{{ number_format($total) }} ریال</td>
                        <td class="v-small">{{ $approvedAt ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="v-detail-grid" style="margin-top:var(--vira-space-5);">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">فیش حقوقی — نمونه</h3></div>
            <div class="v-card__body">
                <div class="v-dl v-dl--2">
                    <div class="v-dl__item"><span class="v-dl__term">کارمند</span><span class="v-dl__value">نگار توکلی</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">دوره</span><span class="v-dl__value">مهر ۱۴۰۵</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">حقوق پایه</span><span class="v-dl__value v-dl__money">۲۸٬۵۰۰٬۰۰۰ ریال</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">حق مسکن</span><span class="v-dl__value v-dl__money">۹٬۰۰۰٬۰۰۰ ریال</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">حق اولاد</span><span class="v-dl__value v-dl__money">۷٬۲۰۰٬۰۰۰ ریال</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">بیمه (۷٪)</span><span class="v-dl__value v-dl__money">−۳٬۱۲۹٬۰۰۰ ریال</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">مالیات</span><span class="v-dl__value v-dl__money">−۲٬۴۰۰٬۰۰۰ ریال</span></div>
                    <div class="v-dl__item"><span class="v-dl__term"><strong>خالص پرداختی</strong></span><span class="v-dl__value v-dl__money"><strong>۳۹٬۱۷۱٬۰۰۰ ریال</strong></span></div>
                </div>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">مسیر تأیید</h3></div>
            <div class="v-card__body">
                <div class="v-timeline">
                    <div class="v-timeline__item">
                        <span class="v-timeline__dot"></span>
                        <div class="v-timeline__body">
                            <div class="v-timeline__title">محاسبهٔ خودکار</div>
                            <div class="v-timeline__meta">۱۴۰۵/۰۸/۰۱ — سامانه (طرح)</div>
                        </div>
                    </div>
                    <div class="v-timeline__item">
                        <span class="v-timeline__dot"></span>
                        <div class="v-timeline__body">
                            <div class="v-timeline__title">بازبینی حسابدار</div>
                            <div class="v-timeline__meta">در انتظار — هیچ اقدام واقعی انجام نمی‌شود</div>
                        </div>
                    </div>
                    <div class="v-timeline__item">
                        <span class="v-timeline__dot"></span>
                        <div class="v-timeline__body">
                            <div class="v-timeline__title">تأیید مدیر</div>
                            <div class="v-timeline__meta">مرحلهٔ بعد — طرح</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="v-alert v-alert--danger" style="margin-top:var(--vira-space-4);">
            <div class="v-alert__body">
                <strong>دادهٔ حقوق در محیط بازبینی تولید نمی‌شود</strong>
                <p class="v-small" style="margin:6px 0 0;">
                    حتی دادهٔ ساختگی حقوق هم در این محیط ساخته نشده است، چون هیچ صفحه‌ای آن را نمی‌خواند.
                    اعداد این صفحه برای نشان‌دادن چیدمان است و در هیچ جدولی ذخیره نمی‌شوند.
                </p>
            </div>
        </div>
    </aside>
</div>

@endsection
