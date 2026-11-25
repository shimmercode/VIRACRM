{{--
    The banner every preview page carries.

    It states three things and refuses to soften any of them: what this screen
    is, why it has no backend, and what the buttons will not do.

    It also carries the way back. Every preview is reachable from the catalogue
    and the catalogue is reachable from every preview, so a reviewer who follows
    five links can still get home without the browser's back button — which is
    the difference between a navigable product surface and a set of pages.
--}}
<nav class="v-review-crumb" aria-label="مسیر بازگشت">
    <a href="{{ route('dashboard') }}">داشبورد</a>
    <span class="v-review-crumb__sep" aria-hidden="true">/</span>
    <a href="{{ route('review.index') }}">فهرست بازبینی</a>
    <span class="v-review-crumb__sep" aria-hidden="true">/</span>
    <span class="v-review-crumb__current" aria-current="page">{{ $what ?? 'پیش‌نمایش طراحی' }}</span>
</nav>

<div class="v-review-banner">
    <span class="v-review-banner__icon"><x-icon name="eye" :size="18" /></span>
    <div class="v-review-banner__body">
        <strong>پیش‌نمایش طراحی — {{ $what }}</strong><br>
        {{ $why }}<br>
        <span class="v-small">{{ $clickable }}</span>
    </div>
</div>
