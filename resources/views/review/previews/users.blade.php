@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'مدیریت کاربران',
    'why' => 'هیچ مسیر /users وجود ندارد — این آدرس در همین لحظه ۴۰۴ برمی‌گرداند (تست T-ROLE-missing-route). جدول users هست ولی صفحهٔ مدیریتی‌اش ساخته نشده.',
    'clickable' => 'هیچ کاربری ساخته، غیرفعال یا حذف نمی‌شود. «بازنشانی رمز» هیچ ایمیلی نمی‌فرستد.',
])

@php
    $users = [
        ['review.admin',    'مدیر سامانه (Demo)',  'manager',         true,  'active',   '۱۴۰۵/۰۸/۲۶ ۰۹:۱۲', '127.0.0.1'],
        ['review.manager',  'مدیر عملیات (Demo)',  'manager',         false, 'active',   '۱۴۰۵/۰۸/۲۶ ۰۸:۴۰', '127.0.0.1'],
        ['review.employee', 'کارشناس پروژه (Demo)', 'account_manager', false, 'active',   '۱۴۰۵/۰۸/۲۵ ۱۷:۰۵', '127.0.0.1'],
        ['review.viewer',   'بازدیدکننده (Demo)',   'viewer',          false, 'active',   '۱۴۰۵/۰۸/۲۵ ۱۱:۳۳', '127.0.0.1'],
        ['admin',           'مدیر سامانه',          'manager',         true,  'active',   '۱۴۰۵/۰۸/۲۶ ۱۰:۰۱', '127.0.0.1'],
        ['viewer1',         'کاربر مشاهده‌گر',      'viewer',          false, 'locked',   '۱۴۰۵/۰۸/۲۰ ۱۴:۲۲', '127.0.0.1'],
    ];
    $roleLabel = [
        'manager' => ['مدیر', 'primary'],
        'account_manager' => ['کارشناس حساب', 'info'],
        'viewer' => ['مشاهده‌گر', 'neutral'],
        'accountant' => ['حسابدار', 'warning'],
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">۶</span><span class="v-review-stat__label">کل کاربران</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۵</span><span class="v-review-stat__label">فعال</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۱</span><span class="v-review-stat__label">قفل‌شده</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">۲</span><span class="v-review-stat__label">Superadmin</span></div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;">کاربران سامانه</h2>
        <div style="display:flex;gap:8px;">
            <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">فیلتر نقش</span>
            <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">کاربر جدید</span>
        </div>
    </div>
    <div class="v-table-wrap">
        <table class="v-table v-table--fixed">
            <thead>
                <tr>
                    <th style="width:22%">نام کاربری</th>
                    <th style="width:24%">نام</th>
                    <th style="width:16%">نقش</th>
                    <th style="width:12%">وضعیت</th>
                    <th style="width:16%">آخرین ورود</th>
                    <th style="width:10%">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as [$username, $name, $role, $super, $state, $last, $ip])
                    <tr>
                        <td>
                            <div class="v-code">{{ $username }}</div>
                            @if ($super)
                                <span class="v-badge v-badge--gold">Superadmin</span>
                            @endif
                        </td>
                        <td>{{ $name }}</td>
                        <td><span class="v-badge v-badge--{{ $roleLabel[$role][1] }}">{{ $roleLabel[$role][0] }}</span></td>
                        <td>
                            @if ($state === 'active')
                                <span class="v-badge v-badge--success">فعال</span>
                            @else
                                <span class="v-badge v-badge--danger">قفل‌شده</span>
                            @endif
                        </td>
                        <td class="v-small">
                            <div>{{ $last }}</div>
                            <div class="v-muted v-code">{{ $ip }}</div>
                        </td>
                        <td>
                            <span class="v-iconbtn v-iconbtn--sm" aria-disabled="true" title="ویرایش"><x-icon name="pencil" :size="14" /></span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="v-detail-grid" style="margin-top:var(--vira-space-5);">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">کاربر جدید — فرم</h3></div>
            <div class="v-card__body">
                <div class="v-form-grid">
                    <div class="v-field">
                        <label class="v-label">نام و نام خانوادگی <span class="v-label__required">*</span></label>
                        <input class="v-input" type="text" placeholder="مثلاً مریم رستمی" disabled>
                    </div>
                    <div class="v-field">
                        <label class="v-label">نام کاربری <span class="v-label__required">*</span></label>
                        <input class="v-input" type="text" placeholder="فقط حروف لاتین و نقطه" disabled dir="ltr">
                    </div>
                    <div class="v-field">
                        <label class="v-label">نقش <span class="v-label__required">*</span></label>
                        <select class="v-select" disabled><option>مدیر</option></select>
                    </div>
                    <div class="v-field">
                        <label class="v-label">وضعیت</label>
                        <select class="v-select" disabled><option>فعال</option></select>
                    </div>
                    <div class="v-field v-field--span2">
                        <label class="v-label">تغییر رمز در اولین ورود</label>
                        <label class="v-switch"><input type="checkbox" checked disabled> <span>الزامی باشد</span></label>
                        <p class="v-hint">پیش‌فرض محصول فعال است. در این صفحه فقط نمایش داده می‌شود.</p>
                    </div>
                </div>
            </div>
            <div class="v-card__foot" style="display:flex;gap:8px;justify-content:flex-start;">
                <span class="v-btn v-btn--primary" aria-disabled="true">ذخیره</span>
                <span class="v-btn v-btn--ghost" aria-disabled="true">انصراف</span>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">حساب ادمین: جدا از محیط بازبینی</h3></div>
            <div class="v-card__body">
                <div class="v-alert v-alert--warning" style="margin:0;">
                    <div class="v-alert__body">
                        <p class="v-small" style="margin:0;">
                            حساب <code>admin</code> رکورد دیتابیس توسعه است، نه یک حساب Production.
                            هیچ اعتبارنامهٔ Production در این محیط وجود ندارد.
                            حساب‌های <code>review.*</code> فقط در دیتابیس بازبینی معنا دارند.
                        </p>
                    </div>
                </div>
                <div class="v-dl" style="margin-top:12px;">
                    <div class="v-dl__item"><span class="v-dl__term">مسیر کاربران</span><span class="v-dl__value"><code>/users</code> → ۴۰۴</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">مسیر نقش‌ها</span><span class="v-dl__value"><code>/roles</code> → ۴۰۴</span></div>
                </div>
            </div>
        </div>

        <div class="v-card" style="margin-top:var(--vira-space-4);">
            <div class="v-card__head"><h3 class="v-card__title">عملیات حساس</h3></div>
            <div class="v-card__body" style="display:grid;gap:8px;">
                <span class="v-btn v-btn--secondary v-btn--block" aria-disabled="true">بازنشانی رمز</span>
                <span class="v-btn v-btn--secondary v-btn--block" aria-disabled="true">بازگشایی قفل ورود</span>
                <span class="v-btn v-btn--danger v-btn--block" aria-disabled="true">غیرفعال‌کردن کاربر</span>
                <p class="v-small v-muted" style="margin:4px 0 0;">
                    هیچ‌کدام اجرا نمی‌شوند. «غیرفعال‌کردن» به قاعدهٔ دسترسی مؤثر گره خورده
                    است که تعریف نشده (OD-119).
                </p>
            </div>
        </div>
    </aside>
</div>

@endsection
