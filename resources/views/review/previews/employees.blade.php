@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'فهرست پرسنل و پروفایل کارمند',
    'why' => 'هیچ مسیر و هیچ کنترلری برای پرسنل وجود ندارد. جدول employees هست و داده دارد، اما هیچ صفحه‌ای آن را نمایش نمی‌دهد؛ این صفحه طراحی است.',
    'clickable' => 'هیچ کارمندی افزوده، ویرایش یا غیرفعال نمی‌شود. جست‌وجو و فیلترها کار نمی‌کنند.',
])

@php
    $staff = [
        ['RVW-E-0001', 'مینا صادقی',   'مدیر عامل',           'مدیریت',      'active',     'review.admin',    true,  '۱۴۰۰/۰۳/۰۱'],
        ['RVW-E-0002', 'علی رضایی',    'مدیر عملیات',         'عملیات',      'active',     'review.manager',  true,  '۱۴۰۱/۰۷/۱۵'],
        ['RVW-E-0003', 'نگار توکلی',   'کارشناس پروژه',        'پروژه‌ها',     'active',     'review.employee', true,  '۱۴۰۲/۰۱/۱۰'],
        ['RVW-E-0004', 'حسین مرادی',   'کارشناس سئو',          'پروژه‌ها',     'active',     null,              null,  '۱۴۰۲/۰۴/۰۲'],
        ['RVW-E-0005', 'زهرا اکبری',   'طراح گرافیک',          'طراحی',       'active',     null,              null,  '۱۴۰۲/۰۹/۲۰'],
        ['RVW-E-0006', 'محمد قاسمی',   'برنامه‌نویس بک‌اند',    'فنی',         'active',     null,              null,  '۱۴۰۳/۰۲/۱۱'],
        ['RVW-E-0007', 'فاطمه نوری',   'کارشناس مالی',         'مالی',        'inactive',   null,              null,  '۱۴۰۱/۱۱/۰۵'],
        ['RVW-E-0008', 'سعید بهرامی',  'کارشناس فروش',         'فروش',        'terminated', null,              null,  '۱۴۰۰/۰۵/۲۲'],
        ['RVW-E-0009', 'الهام شریفی',  'مدیر محتوا',           'محتوا',       'active',     null,              null,  '۱۴۰۳/۰۶/۰۱'],
        ['RVW-E-0010', 'بابک یزدانی',  'کارشناس پشتیبانی',      'پشتیبانی',    'active',     null,              null,  '۱۴۰۳/۰۸/۱۴'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۱۰</span><span class="v-review-stat__label">کل کارمندان</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۸</span><span class="v-review-stat__label">شاغل</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱</span><span class="v-review-stat__label">غیرفعال</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۳ / ۱۰</span><span class="v-review-stat__label">دارای حساب سامانه</span></div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h2 style="margin:0;">کارمندان</h2>
        <div style="display:flex;gap:8px;">
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">فیلتر واحد</span>
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">فیلتر وضعیت</span>
            <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">افزودن کارمند</span>
        </div>
    </div>
    <div class="v-table-wrap">
        <table class="v-table v-table--fixed">
            <thead>
                <tr>
                    <th style="width:20%">کد</th>
                    <th style="width:22%">نام</th>
                    <th style="width:20%">سمت</th>
                    <th style="width:14%">واحد</th>
                    <th style="width:14%">استخدام</th>
                    <th style="width:10%">سامانه</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($staff as [$code, $name, $position, $unit, $status, $username, $userActive, $since])
                    <tr>
                        <td class="v-small v-code">{{ $code }}</td>
                        <td>
                            <div class="v-table__primary">{{ $name }}</div>
                            <div class="v-table__sub">از {{ $since }}</div>
                        </td>
                        <td>{{ $position }}</td>
                        <td class="v-small">{{ $unit }}</td>
                        <td>
                            @if ($status === 'active')
                                <span class="v-badge v-badge--success">شاغل</span>
                            @elseif ($status === 'inactive')
                                <span class="v-badge v-badge--warning">غیرفعال</span>
                            @else
                                <span class="v-badge v-badge--neutral">پایان همکاری</span>
                            @endif
                        </td>
                        <td>
                            @if ($username === null)
                                <span class="v-badge v-badge--neutral">ندارد</span>
                            @else
                                <span class="v-badge v-badge--success">فعال</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── employee profile, shown as the design the module will use ────────── --}}
<div class="v-review-group__head" style="margin-top:var(--vira-space-6);">
    <h2>پروفایل کارمند</h2>
    <span>طرح صفحهٔ جزئیات — همان کارت‌های محصول</span>
</div>

<div class="v-detail-grid">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">نگار توکلی — کارشناس پروژه</h3></div>
            <div class="v-card__body">
                <div class="v-dl v-dl--2">
                    <div class="v-dl__item"><span class="v-dl__term">کد پرسنلی</span><span class="v-dl__value v-code">RVW-E-0003</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">واحد</span><span class="v-dl__value">پروژه‌ها</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">تاریخ استخدام</span><span class="v-dl__value">۱۴۰۲/۰۱/۱۰</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">نوع قرارداد</span><span class="v-dl__value">تمام‌وقت</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">حساب سامانه</span><span class="v-dl__value v-code">review.employee</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">نقش</span><span class="v-dl__value">کارشناس حساب</span></div>
                </div>
            </div>
        </div>

        <div class="v-card" style="margin-top:var(--vira-space-4);">
            <div class="v-card__head"><h3 class="v-card__title">تخصیص پروژه</h3></div>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead><tr><th>پروژه</th><th>نقش</th><th>ضریب</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        <tr><td>پنل مشتریان هلدینگ نیلگون</td><td>کارشناس پروژه</td><td class="v-table__num">۰٫۷</td><td><span class="v-badge v-badge--success">فعال</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">دسترسی سامانه</h3></div>
            <div class="v-card__body">
                <div class="v-alert v-alert--warning" style="margin:0;">
                    <div class="v-alert__body">
                        <strong>قاعدهٔ نهایی وجود ندارد</strong>
                        <p class="v-small" style="margin:6px 0 0;">
                            <code>users.is_active</code> و <code>employees.status</code> مستقل‌اند.
                            این پروفایل نشان می‌دهد هر دو وضعیت باید کنار هم دیده شوند —
                            ولی هیچ کدی آن‌ها را هم‌گام نمی‌کند (OD-119).
                        </p>
                    </div>
                </div>
                <div class="v-dl" style="margin-top:12px;">
                    <div class="v-dl__item"><span class="v-dl__term">وضعیت کاربر</span><span class="v-dl__value"><span class="v-badge v-badge--success">فعال</span></span></div>
                    <div class="v-dl__item"><span class="v-dl__term">وضعیت استخدام</span><span class="v-dl__value"><span class="v-badge v-badge--success">شاغل</span></span></div>
                </div>
            </div>
        </div>

        <div class="v-card" style="margin-top:var(--vira-space-4);">
            <div class="v-card__head"><h3 class="v-card__title">اقدام‌ها</h3></div>
            <div class="v-card__body" style="display:grid;gap:8px;">
                <span class="v-btn v-btn--secondary v-btn--block" aria-disabled="true">ویرایش اطلاعات</span>
                <span class="v-btn v-btn--secondary v-btn--block" aria-disabled="true">ثبت پایان همکاری</span>
                <span class="v-btn v-btn--danger v-btn--block" aria-disabled="true">غیرفعال‌کردن دسترسی</span>
            </div>
        </div>
    </aside>
</div>

<p class="v-preview-note">
    <strong>توجه:</strong> این صفحه هیچ کوئری‌ای اجرا نمی‌کند. ده ردیف بالا برای
    نشان‌دادن چیدمان نوشته شده‌اند و از جدول <code>employees</code> خوانده نشده‌اند —
    برخلاف صفحهٔ ساختار سازمانی که واقعاً از جدول می‌خواند.
</p>

@endsection
