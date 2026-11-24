@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('build/css/review.css') }}?v={{ @filemtime(public_path('build/css/review.css')) }}">
@endpush

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'مدیریت مجوزهای فایل',
    'why' => 'مدل مجوز در F0 طراحی شده، ولی تصمیم‌های OD-104 (دپارتمان) و OD-105 (گروه) هنوز بازند. تا آن‌ها تأیید نشوند، این صفحه فقط چیدمان را نشان می‌دهد.',
    'clickable' => 'هیچ تغییری در دسترسی ذخیره نمی‌شود. این صفحه برای ارزیابی وضوح و خوانایی طراحی است.',
])

<div class="v-breadcrumb" style="margin-bottom:14px;">
    <span>فضای فایل</span><span class="v-breadcrumb__sep">/</span>
    <span>قراردادها</span><span class="v-breadcrumb__sep">/</span>
    <span>گروه صنعتی پارس نوین</span><span class="v-breadcrumb__sep">/</span>
    <strong>قرارداد پشتیبانی ۱۴۰۵.pdf</strong>
</div>

<div class="v-grid v-grid--2">

    <div class="v-card">
        <div class="v-card__head">
            <h2 style="margin:0;">دسترسی‌های فعلی</h2>
            <button class="v-btn v-btn--primary v-btn--sm" disabled>
                <x-icon name="plus" :size="14" /><span>افزودن دسترسی</span>
            </button>
        </div>

        <div class="v-table-wrap">
            <table class="v-table">
                <thead>
                    <tr><th>واگذارشده به</th><th>نوع</th><th>سطح</th><th>منبع</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>دپارتمان مالی</strong></td>
                        <td><span class="v-badge v-badge--primary">دپارتمان</span></td>
                        <td><span class="v-badge v-badge--success">ویرایش</span></td>
                        <td class="v-muted">مستقیم</td>
                    </tr>
                    <tr>
                        <td><strong>تیم پروژهٔ پارس نوین</strong></td>
                        <td><span class="v-badge v-badge--info">تیم پروژه</span></td>
                        <td><span class="v-badge v-badge--info">مشاهده</span></td>
                        <td class="v-muted">از پروژه</td>
                    </tr>
                    <tr>
                        <td><strong>کمیتهٔ قراردادها</strong></td>
                        <td><span class="v-badge v-badge--neutral">گروه</span></td>
                        <td><span class="v-badge v-badge--info">مشاهده</span></td>
                        <td class="v-muted">مستقیم</td>
                    </tr>
                    <tr>
                        <td><strong>علی رضایی</strong></td>
                        <td><span class="v-badge v-badge--neutral">کاربر</span></td>
                        <td><span class="v-badge v-badge--danger">منع</span></td>
                        <td class="v-muted">استثنا</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="v-card-row">
            <span class="v-small v-muted">
                ردیف «منع» برندهٔ همهٔ ردیف‌های «اجازه» است — Deny مقدم بر Allow.
            </span>
        </div>
    </div>

    <div class="v-card">
        <div class="v-card__head"><h2 style="margin:0;">چرا این تصمیم‌ها هنوز بازند؟</h2></div>

        <div class="v-list">
            <div class="v-list__item">
                <div>
                    <strong>OD-104 — دپارتمان</strong>
                    <div class="v-small v-muted">جدول departments وجود ندارد. تا تأیید، «دپارتمان» یک طرح است.</div>
                </div>
                <span class="v-badge v-badge--warning">باز</span>
            </div>
            <div class="v-list__item">
                <div>
                    <strong>OD-105 — گروه</strong>
                    <div class="v-small v-muted">جدول file_groups وجود ندارد. گروه هرگز نقش نمی‌شود.</div>
                </div>
                <span class="v-badge v-badge--warning">باز</span>
            </div>
            <div class="v-list__item">
                <div>
                    <strong>OD-106 — ماتریس پیش‌فرض</strong>
                    <div class="v-small v-muted">کدام نقش به‌طور پیش‌فرض چه سطحی دارد.</div>
                </div>
                <span class="v-badge v-badge--warning">باز</span>
            </div>
        </div>

        <div class="v-nav__divider"></div>

        <h3 class="v-form-section__title">قاعدهٔ حل تعارض</h3>
        <ol class="v-small" style="line-height:2.1;padding-inline-start:18px;">
            <li>هویت کاربر و وضعیت فعال‌بودن بررسی می‌شود.</li>
            <li>مجوز ماژول در CRM بررسی می‌شود.</li>
            <li>کلاسیفیکیشن فایل (محرمانه / داخلی / عمومی داخلی).</li>
            <li>مسیر والدها پیمایش و <code>inherit</code> اعمال می‌شود.</li>
            <li><strong>Deny برنده است</strong> حتی اگر یک Allow هم وجود داشته باشد.</li>
            <li>هیچ قاعده‌ای پیدا نشد = <strong>منع</strong>.</li>
        </ol>
    </div>
</div>

@endsection
