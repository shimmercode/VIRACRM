@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'نقش‌ها و ماتریس مجوز',
    'why' => 'جدول‌های roles، permissions و role_permissions وجود دارند و ۳۹ مجوز در ۴ نقش توزیع شده‌اند، اما هیچ صفحهٔ مدیریتی برای ویرایش آن‌ها نیست. این ماتریس از دادهٔ واقعی همین محیط ساخته شده است.',
    'clickable' => 'هیچ تیکی تغییر نمی‌کند و هیچ مجوزی ذخیره نمی‌شود. ماتریس فقط خواندنی است.',
])

@php
    // Read the real matrix so the preview cannot drift from the product.
    $perms = \Illuminate\Support\Facades\DB::table('permissions')
        ->orderBy('area')->orderBy('action')
        ->get(['id', 'code', 'area', 'action', 'title', 'is_sensitive']);

    $roles = \Illuminate\Support\Facades\DB::table('roles')->orderBy('id')->get(['id', 'code', 'title']);

    $granted = [];
    foreach (\Illuminate\Support\Facades\DB::table('role_permissions')->get(['role_id', 'permission_id']) as $rp) {
        $granted[$rp->permission_id][$rp->role_id] = true;
    }

    $byArea = $perms->groupBy('area');
    $areaLabels = [
        'customers' => 'مشتریان', 'contracts' => 'قراردادها', 'projects' => 'پروژه‌ها',
        'credentials' => 'رمز دسترسی‌ها', 'notes' => 'یادداشت‌ها', 'reports' => 'گزارش‌ها',
        'files' => 'فایل‌ها', 'payroll' => 'حقوق', 'users' => 'کاربران',
        'settings' => 'تنظیمات', 'personnel' => 'پرسنل', 'audit' => 'ممیزی',
    ];
@endphp

<div class="v-review-stats" style="margin-bottom:var(--vira-space-4);">
    <div class="v-review-stat"><span class="v-review-stat__value">{{ $roles->count() }}</span><span class="v-review-stat__label">نقش</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">{{ $perms->count() }}</span><span class="v-review-stat__label">مجوز</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">{{ $byArea->count() }}</span><span class="v-review-stat__label">ناحیه</span></div>
    <div class="v-review-stat"><span class="v-review-stat__value">{{ $perms->where('is_sensitive', 1)->count() }}</span><span class="v-review-stat__label">مجوز حساس</span></div>
</div>

<div class="v-card">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">ماتریس نقش × مجوز</h2>
            <p class="v-small v-muted" style="margin:4px 0 0;">خوانده‌شده از <code>role_permissions</code> — {{ $perms->count() }} مجوز در {{ $byArea->count() }} ناحیه</p>
        </div>
        <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">نقش جدید</span>
    </div>
    <div class="v-table-wrap">
        <table class="v-table v-review-matrix">
            <thead>
                <tr>
                    <th style="width:38%">مجوز</th>
                    @foreach ($roles as $role)
                        <th style="text-align:center;">{{ $role->title }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($byArea as $area => $items)
                    <tr class="v-review-matrix__group">
                        <td colspan="{{ $roles->count() + 1 }}">{{ $areaLabels[$area] ?? $area }}</td>
                    </tr>
                    @foreach ($items as $perm)
                        <tr>
                            <td>
                                <div>{{ $perm->title }}</div>
                                <div class="v-code v-small v-muted">{{ $perm->code }}</div>
                                @if ($perm->is_sensitive)
                                    <span class="v-badge v-badge--gold">حساس</span>
                                @endif
                            </td>
                            @foreach ($roles as $role)
                                <td style="text-align:center;">
                                    @if (isset($granted[$perm->id][$role->id]))
                                        <span class="v-review-check v-review-check--on" aria-label="دارد"><x-icon name="check" :size="13" /></span>
                                    @else
                                        <span class="v-review-check" aria-label="ندارد">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="v-alert v-alert--warning" style="margin-top:var(--vira-space-4);">
    <div class="v-alert__body">
        <strong>این ماتریس واقعی است — ویرایش آن ممکن نیست</strong>
        <p class="v-small" style="margin:6px 0 0;">
            تیک‌ها از جدول <code>role_permissions</code> خوانده شده‌اند، پس دقیقاً همان چیزی
            را نشان می‌دهند که سرور اجرا می‌کند. اما هیچ صفحه‌ای برای تغییرشان وجود ندارد
            و این صفحه فقط خواندنی است — نباید با یک صفحهٔ ویرایش اشتباه گرفته شود.
        </p>
    </div>
</div>

<div class="v-card" style="margin-top:var(--vira-space-4);">
    <div class="v-card__head"><h3 class="v-card__title">چهار مفهوم که در این ماتریس نیستند</h3></div>
    <div class="v-card__body">
        <p class="v-small">
            این جدول فقط <strong>نقش</strong> را نشان می‌دهد. <strong>دپارتمان</strong>
            (جایگاه سازمانی)، <strong>گروه</strong> (انتخاب دستی) و
            <strong>تیم پروژه</strong> (عضویت در پروژه) سه مفهوم دیگرند و هیچ‌کدام
            در این ماتریس نیستند — چون هیچ جدولی برایشان وجود ندارد.
        </p>
    </div>
</div>

@endsection
