@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('build/css/review.css') }}?v={{ @filemtime(public_path('build/css/review.css')) }}">
@endpush

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'فضای فایل (Vira Drive)',
    'why' => 'در فاز F0 فقط مستندات و طراحی انجام شد. هیچ کد، Migration یا جدول فایلی وجود ندارد.',
    'clickable' => 'هیچ دکمه‌ای در این صفحه عملیات واقعی انجام نمی‌دهد؛ آپلود، دانلود و مجوزها فقط برای ارزیابی چیدمان‌اند.',
])

<div class="v-breadcrumb" style="margin-bottom:14px;">
    <span>فضای فایل</span>
    <span class="v-breadcrumb__sep">/</span>
    <span>قراردادها</span>
    <span class="v-breadcrumb__sep">/</span>
    <strong>گروه صنعتی پارس نوین</strong>
</div>

<div class="v-review-split">

    {{-- ── folder tree ─────────────────────────────────────────────────── --}}
    <aside class="v-card v-review-split__aside">
        <div class="v-card__head">
            <h2>پوشه‌ها</h2>
            <button class="v-iconbtn v-iconbtn--sm" disabled title="در نسخهٔ نهایی">+</button>
        </div>

        <div class="v-tree">
            @foreach ([
                ['قراردادها', 'folder', true, false],
                ['  پارس نوین', 'folder', false, true],
                ['  آرمان داده پرداز', 'folder', false, false],
                ['  گروه صنعتی پارس نوین', 'folder', false, false],
                ['پروژه‌ها', 'folder', true, false],
                ['  سئو — پارس نوین', 'folder', false, false],
                ['  وب‌سایت — آرمان', 'folder', false, false],
                ['مالی', 'folder', true, false],
                ['  فاکتورها ۱۴۰۵', 'folder', false, false],
                ['منابع انسانی', 'folder', true, false],
                ['آرشیو', 'folder', true, false],
            ] as [$name, $icon, $root, $selected])
                <div class="v-tree__row {{ $selected ? 'is-selected' : '' }}">
                    <span class="v-tree__icon"><x-icon :name="$icon" :size="15" /></span>
                    <span class="v-tree__name" style="{{ $root ? 'font-weight:700' : '' }}">{{ trim($name) }}</span>
                    @if ($selected)
                        <span class="v-badge v-badge--primary">جاری</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="v-nav__divider"></div>

        <div class="v-tree">
            <div class="v-tree__row">
                <span class="v-tree__icon"><x-icon name="trash" :size="15" /></span>
                <span class="v-tree__name">سطل بازیافت</span>
                <span class="v-tree__meta">۳ مورد</span>
            </div>
            <div class="v-tree__row">
                <span class="v-tree__icon"><x-icon name="clock" :size="15" /></span>
                <span class="v-tree__name">اخیراً بازشده</span>
            </div>
            <div class="v-tree__row">
                <span class="v-tree__meta">فضای مصرفی: ۱۲.۴ گیگابایت از ۶۵۰</span>
            </div>
        </div>
    </aside>

    {{-- ── file list ───────────────────────────────────────────────────── --}}
    <div class="v-card v-review-split__main">
        <div class="v-card__head">
            <div>
                <h2 style="margin:0;">گروه صنعتی پارس نوین</h2>
                <span class="v-small v-muted">۸ مورد · ۲.۱ گیگابایت · آخرین به‌روزرسانی ۲ روز پیش</span>
            </div>
            <div class="v-actions">
                <button class="v-btn v-btn--secondary v-btn--sm" disabled title="پیش‌نمایش طراحی">
                    <x-icon name="filter" :size="14" /><span>فیلتر</span>
                </button>
                <button class="v-btn v-btn--primary v-btn--sm" disabled title="پیش‌نمایش طراحی">
                    <x-icon name="plus" :size="14" /><span>آپلود</span>
                </button>
            </div>
        </div>

        <div class="v-table-wrap">
            <table class="v-table v-filetable">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" class="v-check" disabled></th>
                        <th>نام</th>
                        <th>حجم</th>
                        <th>نسخه</th>
                        <th>دسترسی</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['قرارداد پشتیبانی ۱۴۰۵.pdf', 'PDF', '۴۸۲ کیلوبایت', 'v3', '۲ روز پیش', 'محرمانه', 'danger'],
                        ['پیوست الف — SLA.pdf', 'PDF', '۱.۲ مگابایت', 'v1', '۲ روز پیش', 'محرمانه', 'danger'],
                        ['صورت‌جلسه ۱۴۰۵-۰۸-۱۲.docx', 'DOCX', '۹۶ کیلوبایت', 'v2', '۵ روز پیش', 'داخلی', 'info'],
                        ['فاکتور ۱۴۰۵-۰۹-۰۱.xlsx', 'XLSX', '۳۴ کیلوبایت', 'v1', '۸ روز پیش', 'مالی', 'primary'],
                        ['لوگوی مشتری.png', 'PNG', '۲۴۰ کیلوبایت', 'v1', '۱۲ روز پیش', 'عمومی داخلی', 'neutral'],
                        ['سند نیازمندی — پیش‌نویس.pdf', 'PDF', '۷۸۰ کیلوبایت', 'v5', '۱۴ روز پیش', 'داخلی', 'info'],
                        ['— پوشه: تصاویر کمپین', 'پوشه', '۱۸ مورد', '—', '۳ هفته پیش', 'داخلی', 'info'],
                    ] as [$name, $type, $size, $ver, $ago, $access, $tone])
                        <tr>
                            <td><input type="checkbox" class="v-check" disabled></td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:8px;">
                                    <x-icon :name="$type === 'پوشه' ? 'folder' : 'file'" :size="15" />
                                    <strong>{{ $name }}</strong>
                                </span>
                                <div class="v-small v-muted">{{ $type }} · {{ $ago }}</div>
                            </td>
                            <td class="v-muted">{{ $size }}</td>
                            <td><span class="v-badge v-badge--neutral">{{ $ver }}</span></td>
                            <td><span class="v-badge v-badge--{{ $tone }}">{{ $access }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="v-card-row" style="border-top:1px solid var(--vira-border);">
            <span class="v-small v-muted">۷ مورد نمایش داده شد. اگر پوشه بیش از ۵۰۰ آیتم داشته باشد،
                هشدار نمایش داده می‌شود (OD-117).</span>
        </div>
    </div>

    {{-- ── details panel ───────────────────────────────────────────────── --}}
    <aside class="v-card v-review-split__side">
        <div class="v-card__head"><h2>جزئیات فایل</h2></div>

        <div class="v-dl">
            <div class="v-dl__item"><span class="v-dl__term">نام</span><span class="v-dl__value">قرارداد پشتیبانی ۱۴۰۵.pdf</span></div>
            <div class="v-dl__item"><span class="v-dl__term">مالک</span><span class="v-dl__value">سارا محمدی</span></div>
            <div class="v-dl__item"><span class="v-dl__term">آخرین تغییر</span><span class="v-dl__value">۲ روز پیش — علی رضایی</span></div>
            <div class="v-dl__item"><span class="v-dl__term">اثر انگشت</span><span class="v-dl__value v-small" dir="ltr">a3f9…7b21</span></div>
            <div class="v-dl__item"><span class="v-dl__term">قفل</span><span class="v-dl__value"><span class="v-badge v-badge--warning">قفل موقت — ۸ دقیقه</span></span></div>
        </div>

        <div class="v-nav__divider"></div>

        <h3 class="v-form-section__title">دسترسی فعلی</h3>
        <div class="v-list">
            <div class="v-list__item"><span>دپارتمان مالی</span><span class="v-badge v-badge--primary">ویرایش</span></div>
            <div class="v-list__item"><span>تیم پروژهٔ پارس نوین</span><span class="v-badge v-badge--info">مشاهده</span></div>
            <div class="v-list__item"><span>گروه: کمیتهٔ قراردادها</span><span class="v-badge v-badge--info">مشاهده</span></div>
            <div class="v-list__item"><span>سایرین</span><span class="v-badge v-badge--neutral">بدون دسترسی</span></div>
        </div>

        <p class="v-preview-note" style="margin-top:12px;">
            قاعدهٔ اعمال‌شده: <strong>Deny مقدم بر Allow</strong> و پیش‌فرض «بدون دسترسی».
        </p>
    </aside>
</div>

@endsection
