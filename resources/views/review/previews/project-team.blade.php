@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'تیم پروژه — چه کسی روی چه پروژه‌ای کار می‌کند',
    'why' => 'تنها جای مدل که انسان را به پروژه وصل می‌کند project_assignments است. اگر «تیم» در محصول از همین جدول مشتق شود، نامش باید «تیم پروژه» باشد و مجوزها هم از همین‌جا حل شوند — این تصمیم باز است (OD-118).',
    'clickable' => 'هیچ عضویتی افزوده یا حذف نمی‌شود. اعداد از جدول project_assignments همین محیط خوانده شده‌اند.',
])

@if (! empty($dataError))
    <div class="v-alert v-alert--danger" style="margin-bottom:var(--vira-space-4);">{{ $dataError }}</div>
@endif

@isset($provenance)
    <div class="v-review-provenance v-review-provenance--{{ $provenance['kind'] }}">
        <x-icon name="info" :size="18" />
        <div>
            <strong>{{ $provenance['label'] }}</strong>
            <span class="v-small">{{ $provenance['detail'] }}</span>
        </div>
        @if ($provenance['rows'] !== null)
            <span class="v-badge v-badge--neutral">{{ number_format($provenance['rows']) }} تخصیص</span>
        @endif
    </div>
@endisset

<div class="v-review-stats" style="margin-bottom:var(--vira-space-5);">
    <div class="v-review-stat">
        <span class="v-review-stat__value">{{ number_format($teams->count()) }}</span>
        <span class="v-review-stat__label">پروژه دارای تیم</span>
    </div>
    <div class="v-review-stat">
        <span class="v-review-stat__value">{{ number_format($assignmentRows) }}</span>
        <span class="v-review-stat__label">کل تخصیص‌ها</span>
    </div>
    <div class="v-review-stat">
        <span class="v-review-stat__value">{{ number_format($teams->sum(fn ($t) => count(array_filter($t['members'], fn ($m) => $m['active'])))) }}</span>
        <span class="v-review-stat__label">عضویت فعال</span>
    </div>
    <div class="v-review-stat">
        <span class="v-review-stat__value">{{ number_format($teams->sum(fn ($t) => count(array_filter($t['members'], fn ($m) => ! $m['active'])))) }}</span>
        <span class="v-review-stat__label">عضویت پایان‌یافته</span>
    </div>
</div>

@forelse ($teams as $code => $team)
    <div class="v-card" style="margin-bottom:var(--vira-space-4);">
        <div class="v-card__head" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <div>
                <h2 style="margin:0;">{{ $team['title'] }}</h2>
                <div class="v-small v-muted v-code">{{ $code }}</div>
            </div>
            @if ($team['status'] === 'active')
                <span class="v-badge v-badge--success">فعال</span>
            @elseif ($team['status'] === 'paused')
                <span class="v-badge v-badge--warning">متوقف</span>
            @elseif ($team['status'] === 'completed')
                <span class="v-badge v-badge--neutral">پایان‌یافته</span>
            @else
                <span class="v-badge v-badge--info">پیش‌نویس</span>
            @endif
        </div>
        <div class="v-table-wrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th>عضو</th>
                        <th>نقش در پروژه</th>
                        <th>ضریب</th>
                        <th>بازه</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($team['members'] as $member)
                        <tr>
                            <td>
                                <div>{{ $member['name'] }}</div>
                                <div class="v-small v-muted v-code">{{ $member['code'] }}</div>
                            </td>
                            <td>{{ $member['role'] }}</td>
                            <td class="v-code">{{ number_format($member['coefficient'], 1) }}</td>
                            <td class="v-small v-muted">{{ $member['period'] }}</td>
                            <td>
                                @if ($member['active'])
                                    <span class="v-badge v-badge--success">فعال</span>
                                @else
                                    <span class="v-badge v-badge--neutral">پایان‌یافته</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="v-card">
        <div class="v-empty" style="padding:34px 20px;">
            <x-icon name="info" :size="26" />
            <p class="v-empty__title">هیچ تخصیص پروژه‌ای ثبت نشده است</p>
            <p class="v-empty__desc">
                جدول <code>project_assignments</code> خالی است. برای دیدن این صفحه با داده،
                <code>node tools/review-env.mjs seed</code> را اجرا کنید.
            </p>
        </div>
    </div>
@endforelse

{{-- ── the unresolved question, stated plainly ─────────────────────────── --}}
<div class="v-card" style="margin-top:var(--vira-space-5);">
    <div class="v-card__head">
        <h2 style="margin:0;">سؤال باز: «تیم» یعنی همین؟</h2>
    </div>
    <div class="v-card__body">
        <p class="v-small">
            این جدول فقط می‌گوید <strong>چه کسی روی چه پروژه‌ای کار می‌کند</strong>.
            نمی‌گوید آن شخص به فایل‌های پروژه دسترسی دارد یا نه، و به هیچ دپارتمانی وصل نیست.
        </p>
        <div class="v-table-wrap" style="margin-top:12px;">
            <table class="v-table">
                <thead>
                    <tr><th>پرسش</th><th>پاسخ امروز</th><th>تصمیم</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>عضویت در تیم پروژه، مجوز فایل می‌دهد؟</td>
                        <td class="v-small">خیر — هیچ کدی این اتصال را ندارد.</td>
                        <td><span class="v-badge v-badge--warning">باز (OD-118)</span></td>
                    </tr>
                    <tr>
                        <td>عضویت پروژه می‌تواند محرمانگی دپارتمان را دور بزند؟</td>
                        <td class="v-small">نباید — ولی قاعدهٔ تقدم تعریف نشده تا اجرا شود.</td>
                        <td><span class="v-badge v-badge--warning">باز (OD-120)</span></td>
                    </tr>
                    <tr>
                        <td>عضویت پایان‌یافته دسترسی را قطع می‌کند؟</td>
                        <td class="v-small">فقط اگر کد مجوز چنین چیزی را بخواند؛ امروز نمی‌خواند.</td>
                        <td><span class="v-badge v-badge--warning">باز (OD-121)</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="v-preview-note">
    <strong>توجه:</strong> نمایش ۱۵ تخصیص واقعی ثابت می‌کند صفحه به داده وصل است — نه اینکه مجوزها کار می‌کنند.
    هیچ‌کدام از سه پرسش بالا پاسخ ندارد.
</p>

@endsection
