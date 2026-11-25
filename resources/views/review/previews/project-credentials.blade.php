@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'رمزهای دسترسی پروژه — مخزن رمزها',
    'why' => 'جدول‌های credential_types، credential_requirements و credential_values در اسکیما وجود دارند ولی هیچ مسیری آن‌ها را نمی‌خواند. هیچ رمزی در دیتابیس بازبینی ذخیره نشده است.',
    'clickable' => 'هیچ رمزی نمایش کامل، کپی یا افشا نمی‌شود. دکمهٔ «نمایش» عمداً کار نمی‌کند.',
])

@php
    $projects = [
        ['پنل مشتریان هلدینگ نیلگون', 'RVW-0003', 4, 4, 'complete'],
        ['پشتیبانی سه‌سالهٔ سلامت گستر', 'RVW-0005', 3, 3, 'complete'],
        ['بازطراحی وب‌سایت پارس نوین', 'RVW-0002', 3, 2, 'missing'],
        ['پشتیبانی فنی آرمان داده پرداز', 'RVW-0001', 2, 2, 'complete'],
        ['سئوی فروشگاه دیبا', 'RVW-0006', 2, 0, 'empty'],
    ];
    $creds = [
        ['دسترسی پنل هاست', 'میزبانی', 'پروندهٔ پروژه', 'complete'],
        ['دسترسی پنل دامنه', 'دامنه', 'پروندهٔ پروژه', 'complete'],
        ['دسترسی Google Analytics', 'تحلیل', 'در انتظار دریافت از مشتری', 'pending'],
        ['دسترسی سرچ کنسول', 'SEO', 'در انتظار دریافت', 'pending'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۵</span><span class="v-review-stat__label">پروژه دارای نیاز رمز</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱۴</span><span class="v-review-stat__label">نیاز تعریف‌شده</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱۱</span><span class="v-review-stat__label">کامل</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۳</span><span class="v-review-stat__label">در انتظار مشتری</span></div>
</div>

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__head"><h2 style="margin:0;">وضعیت رمزها بر اساس پروژه</h2></div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>پروژه</th><th>کد</th><th class="v-table__num">تکمیل</th><th>وضعیت</th></tr></thead>
            <tbody>
                @foreach ($projects as [$name, $code, $have, $need, $state])
                    <tr>
                        <td class="v-table__primary">{{ $name }}</td>
                        <td class="v-code v-small">{{ $code }}</td>
                        <td class="v-table__num">{{ $have }} / {{ $need }}</td>
                        <td>
                            @if ($state === 'complete')
                                <span class="v-badge v-badge--success">کامل</span>
                            @elseif ($state === 'missing')
                                <span class="v-badge v-badge--warning">ناقص</span>
                            @else
                                <span class="v-badge v-badge--danger">بدون رمز</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <h3 class="v-card__title" style="margin:0;">رمزهای پنل مشتریان هلدینگ نیلگون</h3>
        <span class="v-badge v-badge--gold">محرمانه</span>
    </div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead><tr><th>عنوان</th><th>دسته</th><th>محل نگهداری</th><th>مقدار</th><th>عملیات</th></tr></thead>
            <tbody>
                @foreach ($creds as [$title, $cat, $where, $state])
                    <tr>
                        <td class="v-table__primary">{{ $title }}</td>
                        <td class="v-small">{{ $cat }}</td>
                        <td class="v-small">{{ $where }}</td>
                        <td>
                            @if ($state === 'complete')
                                <span class="v-code v-muted" dir="ltr">••••••••</span>
                            @else
                                <span class="v-badge v-badge--warning">خالی</span>
                            @endif
                        </td>
                        <td>
                            <span class="v-btn v-btn--ghost v-btn--sm" aria-disabled="true">نمایش</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="v-card-row">
        <span class="v-small v-muted">
            مقادیر رمز در این صفحه حتی نمایشی هم پر نشده‌اند — چون هیچ رمزی در
            دیتابیس بازبینی ذخیره نشده و هیچ رمز واقعی نباید ذخیره شود.
        </span>
    </div>
</div>

<div class="v-alert v-alert--danger" style="margin-top:var(--vira-space-4);">
    <div class="v-alert__body">
        <strong>ذخیره و نمایش رمز، تصمیم امنیتی باز است</strong>
        <p class="v-small" style="margin:6px 0 0;">
            اگر رمزها با کلید متقارن رمزنگاری شوند، مدیریت کلید، چرخش کلید و
            مجوز «نمایش رمز» باید تعریف شوند — و هر نمایش باید در audit ثبت شود.
            هیچ‌کدام از این‌ها تصمیم گرفته نشده، و به همین دلیل این صفحه
            عمداً هیچ رمزی نشان نمی‌دهد.
        </p>
    </div>
</div>

@endsection
