@extends('layouts.app')

@section('content')

@include('review.partials.preview-banner', [
    'what' => 'یادداشت‌ها و یادآوری‌ها',
    'why' => 'جدول notes وجود دارد و سیدر محیط بازبینی ۸ ردیف در آن گذاشته، اما هیچ مسیر /notes وجود ندارد. این صفحه طرح است و آن ۸ ردیف را نمی‌خواند.',
    'clickable' => 'هیچ یادداشتی ساخته، تیک‌خورده یا حذف نمی‌شود. کشیدن بین ستون‌ها کار نمی‌کند.',
])

@php
    $columns = [
        ['باز', 'info', [
            ['تماس پیگیری تمدید قرارداد', 'شرکت آرمان داده پرداز', 'high', '۲ روز پیش', false],
            ['ارسال پیش‌فاکتور بازطراحی', 'گروه صنعتی پارس نوین', 'normal', '۴ روز آینده', false],
            ['پیگیری فاکتور معوق', 'فروشگاه اینترنتی دیبا', 'urgent', '۵ روز پیش', false],
            ['جلسهٔ بررسی رضایت مشتری', 'هلدینگ فناوری نیلگون', 'low', '۱۵ روز آینده', false],
        ]],
        ['در جریان', 'warning', [
            ['پیگیری امضای قرارداد', 'پاکس دارویی سلامت گستر', 'high', 'فردا', false],
            ['معرفی به تیم پشتیبانی', 'سارا محمدی', 'low', '۲۰ روز آینده', false],
        ]],
        ['انجام‌شده', 'success', [
            ['تحویل مدارک هویتی', 'شرکت آرمان داده پرداز', 'normal', '۱۸ روز پیش', true],
            ['درخواست گزارش عملکرد', 'صنایع غذایی زرین دانه', 'normal', '۹ روز پیش', true],
        ]],
    ];
    $priority = [
        'urgent' => ['فوری', 'danger'],
        'high' => ['بالا', 'warning'],
        'normal' => ['عادی', 'neutral'],
        'low' => ['پایین', 'info'],
    ];
@endphp

<div class="v-card" style="margin-bottom:var(--vira-space-4);">
    <div class="v-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <div>
            <h2 style="margin:0;">یادداشت و یادآوری</h2>
            <p class="v-small v-muted" style="margin:4px 0 0;">نمای تابلویی — چهار ستون وضعیت</p>
        </div>
        <span class="v-btn v-btn--primary v-btn--sm" aria-disabled="true">یادداشت جدید</span>
    </div>
    <div class="v-card__body">
        <div class="v-review-board">
            @foreach ($columns as [$title, $tone, $items])
                <section class="v-review-board__col">
                    <header class="v-review-board__head">
                        <span>{{ $title }}</span>
                        <span class="v-badge v-badge--{{ $tone }}">{{ count($items) }}</span>
                    </header>
                    @foreach ($items as [$t, $who, $prio, $when, $done])
                        <article class="v-review-board__card">
                            <div class="v-review-board__title">{{ $t }}</div>
                            <div class="v-small v-muted">{{ $who }}</div>
                            <div class="v-review-board__meta">
                                <span class="v-badge v-badge--{{ $priority[$prio][1] }}">{{ $priority[$prio][0] }}</span>
                                <span class="v-small v-muted">{{ $when }}</span>
                            </div>
                        </article>
                    @endforeach
                </section>
            @endforeach
        </div>
    </div>
</div>

<div class="v-detail-grid">
    <div class="v-detail-grid__main">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">نمای فهرست (همان جدول محصول)</h3></div>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead><tr><th>عنوان</th><th>مرتبط با</th><th>اولویت</th><th>سررسید</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="v-table__primary">تماس پیگیری تمدید قرارداد</td>
                            <td class="v-small">مشتری</td>
                            <td><span class="v-badge v-badge--warning">بالا</span></td>
                            <td class="v-small">۲ روز پیش</td>
                            <td><span class="v-badge v-badge--danger">عقب‌افتاده</span></td>
                        </tr>
                        <tr>
                            <td class="v-table__primary">پیگیری فاکتور معوق</td>
                            <td class="v-small">مشتری + پروژه</td>
                            <td><span class="v-badge v-badge--danger">فوری</span></td>
                            <td class="v-small">۵ روز پیش</td>
                            <td><span class="v-badge v-badge--danger">عقب‌افتاده</span></td>
                        </tr>
                        <tr>
                            <td class="v-table__primary">تحویل مدارک هویتی</td>
                            <td class="v-small">مشتری</td>
                            <td><span class="v-badge v-badge--neutral">عادی</span></td>
                            <td class="v-small">۱۸ روز پیش</td>
                            <td><span class="v-badge v-badge--success">انجام‌شده</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="v-detail-grid__aside">
        <div class="v-card">
            <div class="v-card__head"><h3 class="v-card__title">یادآوری خودکار</h3></div>
            <div class="v-card__body">
                <p class="v-small">
                    در طراحی، یادآوری برای <strong>D-2</strong> تعریف شده است: دو روز
                    <em>تقویمی</em> پیش از سررسید، نه ۴۸ ساعت. این قاعده در فاز F0 با
                    ۲۲ بررسی واقعی تأیید شد.
                </p>
                <div class="v-dl" style="margin-top:10px;">
                    <div class="v-dl__item"><span class="v-dl__term">پنجره</span><span class="v-dl__value">D-2 تقویمی</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">منطقهٔ زمانی</span><span class="v-dl__value">تهران، +۰۳:۳۰</span></div>
                    <div class="v-dl__item"><span class="v-dl__term">وضعیت در محصول</span><span class="v-dl__value"><span class="v-badge v-badge--warning">صفحه ندارد</span></span></div>
                </div>
            </div>
        </div>

        <div class="v-alert v-alert--info" style="margin-top:var(--vira-space-4);">
            <div class="v-alert__body">
                <strong>۸ ردیف در جدول، ۰ صفحه</strong>
                <p class="v-small" style="margin:6px 0 0;">
                    سیدر محیط بازبینی ۸ یادداشت ساخت (برای اینکه جدول خالی نماند).
                    شمارش واقعی از <code>/review/health</code> قابل دیدن است. این صفحه
                    آن‌ها را نمی‌خواند — چون کنترلری وجود ندارد که بخواند.
                </p>
            </div>
        </div>
    </aside>
</div>

@endsection
