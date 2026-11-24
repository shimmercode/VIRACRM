@extends('layouts.app')

@section('content')

<div class="v-review-banner">
    <span class="v-review-banner__icon"><x-icon name="info" :size="18" /></span>
    <div class="v-review-banner__body">
        <strong>این صفحه فقط شمارش ردیف‌ها را نشان می‌دهد، نه محتوای آن‌ها.</strong>
        هیچ ردیفی از داده در این صفحه نمایش داده نمی‌شود تا این محیط نتواند به مسیر خروج داده تبدیل شود.
    </div>
</div>

<div class="v-stats-grid" style="margin-bottom:22px;">
    @foreach ($counts as $table => $n)
        <div @class(['v-stat', 'v-stat--danger' => $n === null])>
            <span class="v-stat__label">{{ $table }}</span>
            <span class="v-stat__value">{{ $n === null ? '—' : number_format($n) }}</span>
            <span class="v-stat__hint">{{ $n === null ? 'جدول در دسترس نیست' : 'ردیف' }}</span>
        </div>
    @endforeach
</div>

<div class="v-card">
    <div class="v-card-row"><span class="v-muted">محیط برنامه</span><strong dir="ltr">{{ $env }}</strong></div>
    <div class="v-card-row"><span class="v-muted">دیتابیس</span><span dir="ltr" class="v-small">{{ $dbPath }}</span></div>
</div>

<p class="v-preview-note" style="margin-top:16px;">
    اگر مقدار «محیط برنامه» چیزی جز <code>local</code>، <code>review</code> یا <code>testing</code> بود،
    این محیط باید فوراً خاموش شود.
</p>

@endsection
