{{--
    The banner every preview page carries.
    It states three things and refuses to soften any of them: what this screen
    is, why it has no backend, and what the buttons will not do.
--}}
<div class="v-review-banner">
    <span class="v-review-banner__icon"><x-icon name="eye" :size="18" /></span>
    <div class="v-review-banner__body">
        <strong>پیش‌نمایش طراحی — {{ $what }}</strong><br>
        {{ $why }}<br>
        <span class="v-small">{{ $clickable }}</span>
    </div>
</div>
