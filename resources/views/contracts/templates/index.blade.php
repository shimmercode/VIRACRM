@extends('layouts.app')

@section('title', 'قالب‌های قرارداد')

@section('content')
    @php
        use App\Support\Money;

        // Literal braces for the helper text; see the note in form.blade.php.
        $lb = '{' . '{';
        $rb = '}' . '}';
    @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('contracts.index') }}">قراردادها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">قالب‌ها</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">قالب‌های قرارداد</h1>
            <p class="v-pagehead__desc">
                متن قرارداد از این قالب‌ها ساخته می‌شود. جای‌نگهدارها با
                <code dir="ltr">{{ $lb }}contract.title{{ $rb }}</code>
                نوشته می‌شوند و هنگام نهایی‌سازی با مقدار واقعی پر می‌شوند.
            </p>
        </div>

        <div class="v-pagehead__actions">
            <a href="{{ route('contracts.templates.create') }}" class="v-btn v-btn--primary">
                <x-icon name="plus" :size="16" /> قالب جدید
            </a>
        </div>
    </header>

    <div class="v-detail-grid">
        <div class="v-detail-grid__main">
            <div class="v-card">
                @if ($templates->isEmpty())
                    <x-empty-state title="هنوز قالبی ثبت نشده است"
                        description="بدون قالب، نهایی‌سازی قرارداد از متن توضیحات استفاده می‌کند. برای متن حقوقی یکسان، یک قالب بسازید."
                        icon="document">
                        <a href="{{ route('contracts.templates.create') }}" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="plus" :size="16" /> ثبت اولین قالب
                        </a>
                    </x-empty-state>
                @else
                    <div class="v-tree">
                        @foreach ($byTitle as $title => $versions)
                            <div class="v-tree__group">
                                @foreach ($versions as $template)
                                    <div class="v-tree__row {{ $template->is_active ? '' : 'v-tree__row--child' }}">
                                        <div class="v-tree__main">
                                            <span class="v-tree__title">{{ $template->title }}</span>
                                            <x-badge tone="neutral">نسخهٔ {{ Money::toPersianDigits((string) $template->version) }}</x-badge>
                                            @if ($template->is_active)
                                                <x-badge tone="success" :dot="true">فعال</x-badge>
                                            @else
                                                <x-badge tone="muted" :dot="true">بایگانی</x-badge>
                                            @endif
                                            @if ($template->is_default)
                                                <x-badge tone="gold">پیش‌فرض</x-badge>
                                            @endif
                                        </div>
                                        <div class="v-tree__meta">
                                            <span>{{ $template->contractType?->title ?? 'همهٔ انواع' }}</span>
                                            <a class="v-iconbtn" href="{{ route('contracts.templates.edit', $template) }}"
                                               aria-label="ویرایش {{ $template->title }} نسخهٔ {{ $template->version }}">
                                                <x-icon name="edit" :size="16" />
                                            </a>
                                            <form method="POST" action="{{ route('contracts.templates.destroy', $template) }}"
                                                  data-confirm="قالب «{{ $template->title }}» نسخهٔ {{ $template->version }} حذف شود؟">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="v-iconbtn v-iconbtn--danger"
                                                        aria-label="حذف {{ $template->title }}">
                                                    <x-icon name="trash" :size="16" />
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <aside class="v-detail-grid__aside">
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">جای‌نگهدارهای مجاز</h2>
                </header>
                <div class="v-card__body">
                    {{--
                        Count only, not the palette itself.
                        Fourteen chips in a ~300px aside is fourteen rows: beside a
                        one-row table the page reads as broken, and on this page
                        nobody is typing a template yet. The full labelled palette
                        lives on the edit form, where the reader is actually
                        inserting a placeholder.
                    --}}
                    <p class="v-muted" style="margin-top:0">
                        {{-- `dir` is redundant with .v-code's isolation, but it
                             keeps the two adjacent examples from being treated as
                             one long LTR run separated by "و". --}}
                        {{ \App\Support\Money::toPersianDigits((string) count($tokens)) }}
                        جای‌نگهدار در دسترس است، مانند
                        <code dir="ltr" class="v-code">{{ $lb }}contract.title{{ $rb }}</code>
                        و
                        <code dir="ltr" class="v-code">{{ $lb }}contract.amount_words{{ $rb }}</code>.
                    </p>
                    <p class="v-hint">
                        فهرست کامل همراه با توضیح هر جای‌نگهدار، هنگام ویرایش قالب نمایش
                        داده می‌شود.
                    </p>
                    <p class="v-hint">
                        هر جای‌نگهدار دیگری هنگام ذخیره رد می‌شود؛ این کار جلوی خطای تایپی را
                        می‌گیرد که در غیر این صورت هنگام نهایی‌سازی و مقابل مشتری پیدا می‌شد.
                    </p>
                </div>
            </section>
        </aside>
    </div>
@endsection
