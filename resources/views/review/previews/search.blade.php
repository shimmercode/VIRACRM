@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'جست‌وجوی سراسری — نتایج ترکیبی',
    'why' => 'کادر جست‌وجو در Topbar هست ولی به هیچ مسیری وصل نیست. هیچ اندیس جست‌وجو و هیچ کنترلری وجود ندارد؛ این صفحه طرح است.',
    'clickable' => 'تایپ در کادر جست‌وجو هیچ نتیجه‌ای برنمی‌گرداند. فیلترهای سمت راست هم کار نمی‌کنند.',
])

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__body">
        <div class="v-form-grid">
            <div class="v-field v-field--span2">
                <label class="v-label">عبارت جست‌وجو</label>
                <div class="v-input-group">
                    <span class="v-input-group__icon"><x-icon name="search" :size="16" /></span>
                    <input class="v-input" type="text" value="آرمان" readonly aria-readonly="true">
                </div>
                <p class="v-hint">طرح — این کادر به هیچ کوئری‌ای وصل نیست.</p>
            </div>
            <div class="v-field">
                <label class="v-label">نوع موجودیت</label>
                <select class="v-select" disabled>
                    <option>همه</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="v-review-split">
    <aside class="v-review-split__side">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">محدودکردن</h3></div>
            <div class="v-card__body" style="display:grid;gap:10px;">
                <label class="v-check"><input type="checkbox" checked disabled> مشتریان <span class="v-muted">(۴)</span></label>
                <label class="v-check"><input type="checkbox" checked disabled> قراردادها <span class="v-muted">(۳)</span></label>
                <label class="v-check"><input type="checkbox" disabled> پروژه‌ها <span class="v-muted">(۰)</span></label>
                <label class="v-check"><input type="checkbox" disabled> یادداشت‌ها <span class="v-muted">(۰)</span></label>
                <label class="v-check"><input type="checkbox" disabled> فایل‌ها <span class="v-muted">(۰)</span></label>
                <hr class="v-nav__divider">
                <div class="v-small v-muted">فیلتر بر اساس وضعیت، مالک و تاریخ — طرح.</div>
            </div>
        </div>
    </aside>

    <div class="v-review-split__main">
        <div class="v-card">
            <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 class="v-card__title" style="margin:0;">۷ نتیجه برای «آرمان»</h3>
                <span class="v-small v-muted"> مرتب‌سازی: بیشترین تطابق</span>
            </div>
            <div class="v-list">
                <div class="v-list__item">
                    <div class="v-list__main">
                        <div class="v-list__title">شرکت آرمان داده پرداز</div>
                        <div class="v-list__meta">مشتری حقوقی · تهران · وضعیت: فعال</div>
                    </div>
                    <span class="v-badge v-badge--info">مشتری</span>
                </div>
                <div class="v-list__item">
                    <div class="v-list__main">
                        <div class="v-list__title">قرارداد پشتیبانی سالانهٔ سامانهٔ فروش</div>
                        <div class="v-list__meta">RVW-C-0001 · ۴۸۰٬۰۰۰٬۰۰۰ ریال · فعال</div>
                    </div>
                    <span class="v-badge v-badge--primary">قرارداد</span>
                </div>
                <div class="v-list__item">
                    <div class="v-list__main">
                        <div class="v-list__title">پشتیبانی فنی آرمان داده پرداز</div>
                        <div class="v-list__meta">RVW-0001 · در حال اجرا</div>
                    </div>
                    <span class="v-badge v-badge--success">پروژه</span>
                </div>
                <div class="v-list__item">
                    <div class="v-list__main">
                        <div class="v-list__title">تماس پیگیری تمدید قرارداد</div>
                        <div class="v-list__meta">یادداشت · اولویت بالا · ۲ روز عقب‌افتاده</div>
                    </div>
                    <span class="v-badge v-badge--neutral">یادداشت</span>
                </div>
            </div>
            <div class="v-card-row">
                <span class="v-small v-muted">۳ نتیجهٔ دیگر — نمایش داده نشده</span>
                <div class="v-card-row__actions">
                    <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">نمایش بیشتر</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="v-alert v-alert--warning" style="margin-top:var(--vira-space-4);">
    <div class="v-alert__body">
        <strong>چرا این صفحه وجود دارد ولی جست‌وجو کار نمی‌کند</strong>
        <p class="v-small" style="margin:6px 0 0;">
            جست‌وجو در CRM نیازمند تصمیم دربارهٔ <em>دامنهٔ</em> جست‌وجو است: آیا فایل‌ها هم
            جست‌وجو می‌شوند؟ آیا محتوای داخل فایل‌ها؟ آیا نتیجه بر اساس مجوز فیلتر می‌شود؟
            تا این تصمیم گرفته نشود، هر پیاده‌سازی ممکن است نشت اطلاعات بسازد —
            چون جست‌وجوی سراسری به‌طور طبیعی همه‌چیز را می‌بیند.
        </p>
    </div>
</div>

@endsection
