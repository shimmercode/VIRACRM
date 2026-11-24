@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'ساختار سازمانی — نقش، دپارتمان، گروه و تیم پروژه',
    'why' => 'هیچ جدول departments یا file_groups وجود ندارد. Model A طراحی و در نه سناریو بررسی شده، اما تصمیم‌های OD-104 و OD-105 هنوز تأیید نشده‌اند.',
    'clickable' => 'هیچ عضویتی ذخیره نمی‌شود و هیچ گروهی ساخته نمی‌شود. اعداد این صفحه از جدول employees و project_assignments همین محیط خوانده شده‌اند.',
])

@if (! empty($dataError))
    <div class="v-alert v-alert--danger" style="margin-bottom:var(--vira-space-4);">{{ $dataError }}</div>
@endif

{{-- ── provenance: this page reads a real table, and says so ───────────── --}}
@isset($provenance)
    <div class="v-review-provenance v-review-provenance--{{ $provenance['kind'] }}">
        <x-icon name="info" :size="18" />
        <div>
            <strong>{{ $provenance['label'] }}</strong>
            <span class="v-small">{{ $provenance['detail'] }}</span>
        </div>
        @if ($provenance['rows'] !== null)
            <span class="v-badge v-badge--neutral">{{ number_format($provenance['rows']) }} ردیف</span>
        @endif
    </div>
@endisset

{{-- ── the four concepts, kept visibly separate ────────────────────────── --}}
<div class="v-card" style="margin-bottom:var(--vira-space-5);">
    <div class="v-card__head"><h2 style="margin:0;">چهار مفهوم که هرگز با هم ادغام نمی‌شوند</h2></div>
    <div class="v-table-wrap">
        <table class="v-table">
            <thead>
                <tr>
                    <th>مفهوم</th>
                    <th>به چه سؤالی پاسخ می‌دهد</th>
                    <th>منبع در Model A</th>
                    <th>دادهٔ امروز</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>نقش (Role)</strong></td>
                    <td>چه کاری <em>مجاز</em> است انجام دهد؟</td>
                    <td class="v-small">جدول <code>roles</code> + <code>role_permissions</code></td>
                    <td class="v-small">{{ number_format($roleCounts->sum('n')) }} تخصیص نقش</td>
                    <td><x-review-status status="Functional" compact /></td>
                </tr>
                <tr>
                    <td><strong>دپارتمان (Department)</strong></td>
                    <td>در سازمان <em>کجاست</em>؟</td>
                    <td class="v-small">Model A: <code>departments</code> + <code>user_departments</code></td>
                    <td class="v-small"><span class="v-badge v-badge--danger">جدول وجود ندارد</span></td>
                    <td><x-review-status status="Not Implemented" compact /></td>
                </tr>
                <tr>
                    <td><strong>گروه (Group)</strong></td>
                    <td><em>دستی</em> چه کسانی را کنار هم می‌گذاریم؟</td>
                    <td class="v-small">Model A: <code>file_groups</code> + <code>file_group_members</code></td>
                    <td class="v-small"><span class="v-badge v-badge--danger">جدول وجود ندارد</span></td>
                    <td><x-review-status status="Not Implemented" compact /></td>
                </tr>
                <tr>
                    <td><strong>تیم پروژه (Project Team)</strong></td>
                    <td>روی <em>چه پروژه‌ای</em> کار می‌کند؟</td>
                    <td class="v-small">موجود: <code>project_assignments</code></td>
                    <td class="v-small">{{ number_format($rows->count()) }} کارمند، {{ number_format($roleCounts->count()) }} نقش</td>
                    <td><x-review-status status="Partial" compact /></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="v-card-row">
        <span class="v-small v-muted">
            «تیم» در محصول با نام دقیق <strong>تیم پروژه</strong> معرفی می‌شود تا با گروه دستی اشتباه نشود.
            ادغام این چهار مفهوم یک تصمیم باز است (OD-118)، نه یک نتیجهٔ گرفته‌شده.
        </span>
    </div>
</div>

{{-- ── roles: real rows from role_permissions ──────────────────────────── --}}
<div class="v-review-group__head">
    <h2>نقش‌ها و دامنهٔ واقعی‌شان</h2>
    <span>خوانده‌شده از <code>roles</code> و <code>role_permissions</code></span>
</div>

<div class="v-org-grid" style="margin-bottom:var(--vira-space-5);">
    @foreach ($roleCounts as $role)
        <div class="v-org-card">
            <h3 class="v-org-card__title">{{ $role->title }}</h3>
            <div class="v-org-card__meta">{{ number_format($role->n) }} کاربر در این محیط</div>
            <span class="v-badge v-badge--info">مجوز از جدول نقش، نه از دپارتمان</span>
        </div>
    @endforeach
</div>

{{-- ── employees: the real table ───────────────────────────────────────── --}}
<div class="v-review-group__head">
    <h2>کارمندان</h2>
    <span>{{ number_format($rows->count()) }} ردیف واقعی در این محیط · وضعیت کاربر سامانه هم نمایش داده می‌شود</span>
</div>

<div class="v-card" style="margin-bottom:var(--vira-space-5);">
    <div class="v-table-wrap">
        <table class="v-table v-table--fixed">
            <thead>
                <tr>
                    <th style="width:22%">کد</th>
                    <th style="width:30%">نام</th>
                    <th style="width:24%">سمت</th>
                    <th style="width:24%">کاربر سامانه</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="v-small v-code">{{ $row['code'] }}</td>
                        <td>
                            <div>{{ $row['name'] }}</div>
                            <div class="v-small v-muted">
                                @if ($row['status'] === 'active')
                                    <span class="v-badge v-badge--success">شاغل</span>
                                @elseif ($row['status'] === 'inactive')
                                    <span class="v-badge v-badge--warning">غیرفعال</span>
                                @else
                                    <span class="v-badge v-badge--neutral">پایان همکاری</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $row['position'] }}</td>
                        <td>
                            @if ($row['username'] === null)
                                <span class="v-small v-muted">بدون کاربر سامانه</span>
                            @else
                                <div class="v-code v-small">{{ $row['username'] }}</div>
                                @if ($row['user_active'])
                                    <span class="v-badge v-badge--success">فعال</span>
                                @else
                                    <span class="v-badge v-badge--danger">غیرفعال</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── the gap this page exists to expose ──────────────────────────────── --}}
<div class="v-card" style="margin-bottom:var(--vira-space-5);">
    <div class="v-card__head">
        <h2 style="margin:0;">شکافی که این صفحه عمداً نشان می‌دهد</h2>
    </div>
    <div class="v-card__body">
        <p class="v-small">
            <strong><code>users.is_active</code> و <code>employees.status</code> دو کلید مستقل‌اند.</strong>
            هیچ‌جای کد این دو را با هم همگام نمی‌کند. یعنی امروز ممکن است کارمندی «پایان همکاری» داشته باشد
            ولی حساب کاربری‌اش هنوز فعال بماند — و برعکس. هر دو حالت در این جدول قابل مشاهده است.
        </p>
        <ul class="v-small" style="margin:0; padding-inline-start:20px; line-height:2;">
            <li>کارمند پایان‌همکاری‌گرفته با کاربر فعال سامانه</li>
            <li>کارمند شاغل بدون هیچ کاربر سامانه</li>
            <li>کارمند با کاربر فعال — حالت عادی</li>
        </ul>
        <p class="v-small v-muted" style="margin-top:12px;">
            قاعدهٔ نهایی «دسترسی مؤثر» تعریف نشده است (OD-119). تا وقتی تعریف نشود،
            هیچ تصمیمی دربارهٔ ابطال نشست، توکن WebDAV یا اشتراک فایل قابل اتکا نیست.
        </p>
    </div>
</div>

<p class="v-preview-note">
    <strong>توجه:</strong> خالی بودن یک جدول <em>اثبات</em> عملکرد نیست — و پرشدن آن هم اثبات نیست.
    اعداد بالا فقط نشان می‌دهند صفحه به داده وصل است. هر تصمیم دربارهٔ مجوز دسترسی هنوز باز است.
</p>

@endsection
