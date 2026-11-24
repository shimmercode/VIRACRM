@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('build/css/review.css') }}?v={{ @filemtime(public_path('build/css/review.css')) }}">
@endpush

@section('content')

<div class="v-review-hero">
    <div class="v-review-hero__brand">
        <img class="v-review-hero__logo" src="{{ asset('build/img/vira-logo.png') }}" alt="VIRA">
        <div>
            <h1>محیط بازبینی VIRA CRM</h1>
            <p>همهٔ صفحات در یک فهرست. هر ردیف وضعیت واقعی خودش را دارد و با یک کلیک باز می‌شود.</p>
        </div>
    </div>

    <div class="v-review-counts">
        @foreach ([
            'Functional' => 'عملیاتی',
            'Partial' => 'بخشی',
            'UI Preview' => 'پیش‌نمایش طراحی',
            'Not Implemented' => 'ساخته نشده',
        ] as $key => $label)
            <div class="v-review-counts__item">
                <span class="v-review-counts__n">{{ $counts[$key] ?? 0 }}</span>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="v-review-banner">
    <span class="v-review-banner__icon"><x-icon name="info" :size="18" /></span>
    <div class="v-review-banner__body">
        <strong>این محیط فقط برای بازبینی است.</strong>
        فقط صفحات «عملیاتی» به Backend واقعی وصل‌اند. صفحات «پیش‌نمایش طراحی» عمداً دکمه‌شان عملیات
        انجام نمی‌دهد و هیچ داده‌ای ذخیره نمی‌شود. این محیط به دیتابیس Production وصل نیست و
        هیچ ایمیل یا پیامکی ارسال نمی‌کند.
        <a class="v-link" href="{{ route('review.health') }}">وضعیت محیط</a>
    </div>
</div>

@php
    $groups = [
        'core' => ['هستهٔ محصول', 'مشتریان، قراردادها، پروژه‌ها و داشبورد — با Backend واقعی'],
        'operations' => ['عملیات', 'پرسنل، حقوق، یادداشت‌ها، جست‌وجو و گزارش‌ها'],
        'finance' => ['حسابداری و مالی', 'در فاز ۳ طراحی شده؛ هنوز صفحه ندارد'],
        'admin' => ['مدیریت و تنظیمات', 'کاربران، نقش‌ها، تنظیمات و دسترسی‌ها'],
        'drive' => ['Vira Drive', 'ماژول فایل — در F0 فقط طراحی شد، هیچ کدی نوشته نشده'],
    ];
@endphp

@foreach ($groups as $key => $meta)
    @php [$title, $subtitle] = $meta; @endphp
    @php $group = $pages->where('group', $key); @endphp
    @if ($group->count())
        <section class="v-review-group">
            <div class="v-review-group__head">
                <h2>{{ $title }}</h2>
                <span>{{ $subtitle }}</span>
            </div>

            <div class="v-card">
                @foreach ($group as $page)
                    <div class="v-review-row">
                        <div class="v-review-row__name">
                            <strong>{{ $page['title'] }}</strong>
                            <small>{{ $page['module'] }}</small>
                        </div>

                        <x-review-status :status="$page['status']" compact />

                        @if ($page['reachable'] && $page['url'])
                            <span class="v-review-row__path">{{ parse_url($page['url'], PHP_URL_PATH) }}</span>
                            <div class="v-review-row__actions">
                                <a class="v-btn v-btn--secondary v-btn--sm" href="{{ $page['url'] }}">
                                    <x-icon name="external" :size="14" />
                                    <span>باز کردن</span>
                                </a>
                            </div>
                        @else
                            <span class="v-review-row__path">—</span>
                            <div class="v-review-row__actions">
                                <span class="v-small v-muted">صفحه‌ای وجود ندارد</span>
                            </div>
                        @endif
                    </div>

                    @if (! empty($page['note']))
                        <div class="v-review-note">{{ $page['note'] }}</div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
@endforeach

@endsection
