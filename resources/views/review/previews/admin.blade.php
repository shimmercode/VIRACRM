@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'مدیریت سیستم — سلامت، صف، بکاپ، لاگ',
    'why' => 'هیچ مسیر /admin وجود ندارد. این صفحه طرح چهار ابزار عملیاتی است که یک مدیر سامانه انتظار دارد.',
    'clickable' => 'هیچ بکاپی گرفته یا بازیابی نمی‌شود. «تست بکاپ» یک عملیات واقعی نیست و عمداً غیرفعال است.',
])

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">—</span><span class="v-review-stat__label">وضعیت سلامت</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۰</span><span class="v-review-stat__label">کار در صف</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۰</span><span class="v-review-stat__label">کار ناموفق</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۲۳۲</span><span class="v-review-stat__label">رویداد audit</span></div>
</div>

<div class="v-review-grid-2">
    <div class="v-card">
        <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 class="v-card__title" style="margin:0;">سلامت اجزا</h3>
            <span class="v-badge v-badge--neutral">طرح</span>
        </div>
        <div class="v-card__body">
            <div class="v-dl">
                <div class="v-dl__item"><span class="v-dl__term">پایگاه داده</span><span class="v-dl__value"><span class="v-badge v-badge--success">متصل</span></span></div>
                <div class="v-dl__item"><span class="v-dl__term">ذخیره‌سازی فایل</span><span class="v-dl__value"><span class="v-badge v-badge--neutral">وجود ندارد</span></span></div>
                <div class="v-dl__item"><span class="v-dl__term">WebDAV</span><span class="v-dl__value"><span class="v-badge v-badge--danger">پیکربندی نشده</span></span></div>
                <div class="v-dl__item"><span class="v-dl__term">صف</span><span class="v-dl__value"><span class="v-badge v-badge--neutral">استفاده نمی‌شود</span></span></div>
                <div class="v-dl__item"><span class="v-dl__term">پشتیبان‌گیری</span><span class="v-dl__value"><span class="v-badge v-badge--danger">تست نشده</span></span></div>
            </div>
        </div>
    </div>

    <div class="v-card">
        <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 class="v-card__title" style="margin:0;">پشتیبان‌گیری</h3>
            <span class="v-badge v-badge--warning">آماده نیست</span>
        </div>
        <div class="v-card__body">
            <div class="v-dl">
                <div class="v-dl__item"><span class="v-dl__term">آخرین بکاپ</span><span class="v-dl__value">—</span></div>
                <div class="v-dl__item"><span class="v-dl__term">مقصد</span><span class="v-dl__value">تعریف نشده (OD-133)</span></div>
                <div class="v-dl__item"><span class="v-dl__term">RPO / RTO</span><span class="v-dl__value">تصمیم باز (OD-131)</span></div>
                <div class="v-dl__item"><span class="v-dl__term">آخرین تست بازیابی</span><span class="v-dl__value">هرگز</span></div>
            </div>
            <p class="v-small v-muted" style="margin:12px 0 0;">
                دکمهٔ «تست بکاپ» عمداً وجود ندارد: یک بکاپ تست‌نشده، بکاپ نیست — یک فرض است.
            </p>
        </div>
    </div>
</div>

<div class="v-card" style="margin-top:var(--vira-space-4);">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h3 class="v-card__title" style="margin:0;">صف کارها</h3>
        <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">اجرای دستی</span>
    </div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>کار</th><th>صف</th><th class="v-table__num">تلاش</th><th>وضعیت</th></tr></thead>
            <tbody>
                <tr>
                    <td colspan="4">
                        <div class="v-empty" style="padding:24px 12px;">
                            <span class="v-empty__icon"><x-icon name="inbox" :size="22" /></span>
                            <p class="v-empty__title">صفحه‌ای خالی</p>
                            <p class="v-empty__desc">هیچ کاری در صف نیست — و در این محیط هیچ صفی اجرا نمی‌شود.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="v-card" style="margin-top:var(--vira-space-4);">
    <div class="v-card__head"><h3 class="v-card__title">آخرین رویدادهای audit</h3></div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>زمان</th><th>کاربر</th><th>رویداد</th><th>توضیح</th></tr></thead>
            <tbody>
                <tr><td class="v-small v-table__nowrap">۱۴۰۵/۰۸/۲۶ ۱۰:۰۱</td><td class="v-code v-small">admin</td><td><span class="v-badge v-badge--info">login</span></td><td class="v-small">ورود موفق</td></tr>
                <tr><td class="v-small v-table__nowrap">۱۴۰۵/۰۸/۲۶ ۰۹:۱۲</td><td class="v-code v-small">review.admin</td><td><span class="v-badge v-badge--info">login</span></td><td class="v-small">ورود موفق</td></tr>
                <tr><td class="v-small v-table__nowrap">۱۴۰۵/۰۸/۲۵ ۱۷:۰۵</td><td class="v-code v-small">review.employee</td><td><span class="v-badge v-badge--info">login</span></td><td class="v-small">ورود موفق</td></tr>
            </tbody>
        </table>
    </div>
    <div class="v-card-row">
        <span class="v-small v-muted">۲۳۲ رویداد در جدول audit_logs — این سه ردیف نمونه‌اند.</span>
    </div>
</div>

@endsection
