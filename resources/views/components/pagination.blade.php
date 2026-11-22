@props(['paginator'])

{{-- Server-side pagination over real query results (§3, §4). --}}
@if ($paginator->hasPages())
    <nav class="v-pagination" aria-label="صفحه‌بندی">
        @if ($paginator->onFirstPage())
            <span class="v-pagination__btn is-disabled" aria-disabled="true">
                <x-icon name="chevron" :size="16" /> قبلی
            </span>
        @else
            <a class="v-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                <x-icon name="chevron" :size="16" /> قبلی
            </a>
        @endif

        <span class="v-pagination__info">
            صفحهٔ {{ \App\Support\Money::toPersianDigits((string) $paginator->currentPage()) }}
            از {{ \App\Support\Money::toPersianDigits((string) $paginator->lastPage()) }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="v-pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">
                بعدی <x-icon name="chevron" :size="16" />
            </a>
        @else
            <span class="v-pagination__btn is-disabled" aria-disabled="true">
                بعدی <x-icon name="chevron" :size="16" />
            </span>
        @endif
    </nav>
@endif
