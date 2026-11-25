@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'تنظیمات سیستم',
    'why' => 'جدول settings هست و ۴ ردیف دارد، اما هیچ مسیر /settings وجود ندارد. این صفحه طرح است.',
    'clickable' => 'هیچ تنظیمی ذخیره نمی‌شود. تغییر هر فیلد فقط ظاهری است و پس از بازخوانی صفحه برمی‌گردد.',
])

@php
    $sections = [
        ['general', 'عمومی', 'setting', [
            ['نام سازمان', 'text', 'ویرا'],
            ['منطقهٔ زمانی', 'select', 'Asia/Tehran (UTC+03:30)'],
            ['تقویم پیش‌فرض', 'select', 'شمسی (جلالی)'],
            ['زبان رابط', 'select', 'فارسی'],
        ]],
        ['numbers', 'شماره‌گذاری', 'setting', [
            ['پیش‌شمارهٔ مشتری', 'text', 'RVW-'],
            ['پیش‌شمارهٔ قرارداد', 'text', 'C-'],
            ['شمارهٔ بعدی قرارداد', 'text', '۰۰۰۹'],
        ]],
        ['security', 'امنیت', 'setting', [
            ['حداکثر تلاش ورود', 'text', '۵'],
            ['مدت قفل پس از خطا', 'text', '۱۵ دقیقه'],
            ['انقضای نشست', 'text', '۱۲۰ دقیقه'],
            ['الزام تغییر رمز در اولین ورود', 'switch', true],
        ]],
        ['files', 'فایل‌ها (F1)', 'blocked', [
            ['حداکثر حجم آپلود', 'text', 'در انتظار تصمیم'],
            ['حداکثر تعداد نسخه', 'text', 'در انتظار تصمیم'],
            ['اسکن بدافزار', 'text', 'OD-127 — باز'],
        ]],
    ];
@endphp

<div class="v-review-accordion">
    @foreach ($sections as [$id, $title, $kind, $fields])
        <section class="v-card v-review-accordion__section">
            <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2 style="margin:0;">{{ $title }}</h2>
                @if ($kind === 'blocked')
                    <span class="v-badge v-badge--warning">وابسته به تصمیم باز</span>
                @endif
            </div>
            <div class="v-card__body">
                <div class="v-form-grid">
                    @foreach ($fields as $field)
                        @php [$label, $type, $value] = $field; @endphp
                        <div class="v-field">
                            <label class="v-label">{{ $label }}</label>
                            @if ($type === 'select')
                                <select class="v-select" disabled><option>{{ $value }}</option></select>
                            @elseif ($type === 'switch')
                                <label class="v-switch"><input type="checkbox" checked disabled> <span>فعال</span></label>
                            @else
                                <input class="v-input" type="text" value="{{ $value }}" disabled>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($kind === 'blocked')
                    <p class="v-small v-muted" style="margin:12px 0 0;">
                        این چهار تنظیم معنا ندارند تا ماژول فایل ساخته شود. نمایش آن‌ها
                        به‌عنوان «قابل تنظیم» گمراه‌کننده می‌بود.
                    </p>
                @endif
            </div>
        </section>
    @endforeach
</div>

<div class="v-card" style="margin-top:var(--vira-space-4);">
    <div class="v-card__body">
        <div class="v-alert v-alert--info" style="margin:0;">
            <div class="v-alert__body">
                <strong>تنظیمات فعلی در جدول <code>settings</code> است</strong>
                <p class="v-small" style="margin:6px 0 0;">
                    چهار ردیف کلید/مقدار وجود دارد و توسط سیدر ساخته شده‌اند. این صفحه
                    آن‌ها را نمی‌خواند — چون کنترلری وجود ندارد که بخواند. برای دیدن شمارش
                    واقعی، <code>/review/health</code> را ببینید.
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
