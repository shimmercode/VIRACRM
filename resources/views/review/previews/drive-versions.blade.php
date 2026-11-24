@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'نسخه‌ها، قفل‌ها و گزارش فعالیت فایل',
    'why' => 'قفل و نسخه‌بندی طراحی شده‌اند، اما sabre/dav نسخه‌بندی RFC 3253 را پشتیبانی نمی‌کند و هیچ آزمون ویندوزی اجرا نشده است.',
    'clickable' => 'دکمه‌های «بازگردانی» و «شکستن قفل» عملیات انجام نمی‌دهند؛ هیچ قفلی واقعاً باز نمی‌شود.',
])

<div class="v-breadcrumb" style="margin-bottom:14px;">
    <span>فضای فایل</span><span class="v-breadcrumb__sep">/</span>
    <span>قراردادها</span><span class="v-breadcrumb__sep">/</span>
    <span>گروه صنعتی پارس نوین</span><span class="v-breadcrumb__sep">/</span>
    <strong>نسخه‌ها و قفل‌ها</strong>
</div>

<div class="v-grid v-grid--2">

    {{-- ── versions ────────────────────────────────────────────────────── --}}
    <div class="v-card">
        <div class="v-card__head">
            <h2 style="margin:0;">تاریخچهٔ نسخه‌ها</h2>
            <span class="v-badge v-badge--neutral">۱۰ نسخه نگه‌داری می‌شود</span>
        </div>

        <div class="v-timeline">
            @foreach ([
                ['v3', '۲ روز پیش', 'علی رضایی', 'اصلاح بند ۴ — مبلغ پشتیبانی', 'current', '۴۸۲ KB'],
                ['v2', '۵ روز پیش', 'سارا محمدی', 'افزودن پیوست الف', '', '۴۷۰ KB'],
                ['v1', '۱۲ روز پیش', 'سارا محمدی', 'بارگذاری اولیه', '', '۴۵۱ KB'],
            ] as [$ver, $when, $who, $what, $state, $size])
                <div class="v-timeline__item">
                    <span class="v-timeline__dot {{ $state === 'current' ? 'v-timeline__dot--active' : '' }}"></span>
                    <div class="v-timeline__body">
                        <div class="v-timeline__title">
                            <strong>{{ $ver }}</strong>
                            @if ($state === 'current')
                                <span class="v-badge v-badge--success">فعلی</span>
                            @endif
                            <span class="v-small v-muted">{{ $when }} · {{ $who }} · {{ $size }}</span>
                        </div>
                        <p class="v-small" style="margin:4px 0 8px;">{{ $what }}</p>
                        <div class="v-actions">
                            <button class="v-btn v-btn--link v-btn--sm" disabled>دانلود</button>
                            <button class="v-btn v-btn--link v-btn--sm" disabled>بازگردانی</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="v-card-row">
            <span class="v-small v-muted">
                «بازگردانی» یک نسخهٔ جدید می‌سازد؛ نسخه‌ها هرگز بازنویسی نمی‌شوند.
            </span>
        </div>
    </div>

    {{-- ── locks + activity ────────────────────────────────────────────── --}}
    <div>
        <div class="v-card" style="margin-bottom:var(--vira-space-5);">
            <div class="v-card__head">
                <h2 style="margin:0;">قفل فعلی</h2>
                <span class="v-badge v-badge--warning">قفل موقت</span>
            </div>
            <div class="v-dl" style="padding:var(--vira-space-4) var(--vira-space-5);">
                <div class="v-dl__item"><span class="v-dl__term">دارنده</span><span class="v-dl__value">علی رضایی</span></div>
                <div class="v-dl__item"><span class="v-dl__term">مسیر</span><span class="v-dl__value v-small">WebDAV (Word)</span></div>
                <div class="v-dl__item"><span class="v-dl__term">انقضا</span><span class="v-dl__value">۸ دقیقهٔ دیگر</span></div>
                <div class="v-dl__item"><span class="v-dl__term">TTL</span><span class="v-dl__value v-small">۱۰ دقیقه (DAV)</span></div>
            </div>
            <div class="v-card-row">
                <span class="v-small v-muted">شکستن قفل نیازمند مجوز <code>files.hard_delete</code> و ثبت در گزارش است.</span>
                <button class="v-btn v-btn--ghost v-btn--sm" disabled>شکستن قفل</button>
            </div>
        </div>

        <div class="v-card">
            <div class="v-card__head"><h2 style="margin:0;">فعالیت اخیر</h2></div>
            <div class="v-list">
                @foreach ([
                    ['دانلود', 'سارا محمدی', '۱۰ دقیقه پیش'],
                    ['قفل شد', 'علی رضایی', '۱۲ دقیقه پیش'],
                    ['نسخهٔ v3 ساخته شد', 'علی رضایی', '۲ روز پیش'],
                    ['دسترسی گروه تغییر کرد', 'مدیر سیستم', '۴ روز پیش'],
                    ['فایل بازگردانی شد به v1', 'سارا محمدی', '۶ روز پیش'],
                ] as [$action, $who, $when])
                    <div class="v-list__item">
                        <div>
                            <strong style="font-weight:600;font-size:12.5px;">{{ $action }}</strong>
                            <div class="v-small v-muted">{{ $who }}</div>
                        </div>
                        <span class="v-small v-muted">{{ $when }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@endsection
