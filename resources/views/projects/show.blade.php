@extends('layouts.app')

@section('title', $project->title)

@section('content')
    @php use App\Support\Money; @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.index') }}">پروژه‌ها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $project->title }}</span>
    </nav>

    <header class="v-pagehead v-pagehead--detail">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $project->title }}</h1>
            <p class="v-pagehead__meta">
                <span class="v-pagehead__id" dir="ltr">{{ $project->code }}</span>
                · {{ $project->typeLabel() }}
                · <x-status-badge :status="$project->status" domain="project" />
            </p>
        </div>

        <div class="v-pagehead__actions">
            {{-- REQ-06.01: credentials live on the project page because that is
                 where the person needing them is already standing. --}}
            @if (auth()->user()->hasPermission('credentials.view'))
                <a href="{{ route('credentials.index', $project) }}" class="v-btn v-btn--ghost">
                    <x-icon name="key" :size="16" /> دسترسی‌ها
                </a>
            @endif
            {{-- REQ-06.06 / T-PDF-08 — the public project document. Contains no
                 credential data by construction (ProjectPdf::PUBLIC_FIELDS). --}}
            <a href="{{ route('projects.pdf', $project) }}" class="v-btn v-btn--ghost"
               data-export="pdf">
                <x-icon name="download" :size="16" /> PDF عمومی
            </a>
            @if (auth()->user()->hasPermission('projects.update'))
                <a href="{{ route('projects.edit', $project) }}" class="v-btn v-btn--primary">
                    <x-icon name="edit" :size="16" /> ویرایش
                </a>
            @endif
        </div>
    </header>

    <div class="v-detail-grid">
        <div class="v-detail-grid__main">

            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">مشخصات پروژه</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl v-dl--2">
                        <div>
                            <dt>مشتری</dt>
                            <dd>
                                @if ($project->customer)
                                    <a class="v-link" href="{{ route('customers.show', $project->customer) }}">
                                        {{ $project->customer->display_name }}
                                    </a>
                                @else
                                    <span class="v-muted">—</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt>قرارداد</dt>
                            <dd>
                                @if ($project->contract)
                                    <a class="v-link" href="{{ route('contracts.show', $project->contract) }}">
                                        {{ $project->contract->title }}
                                    </a>
                                    <span class="v-muted v-small" dir="ltr">({{ $project->contract->number }})</span>
                                @else
                                    <span class="v-muted">متصل نشده</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt>مسئول پروژه</dt>
                            <dd>{{ $project->owner?->name ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt>بازهٔ زمانی</dt>
                            <dd dir="ltr">
                                @if ($project->start_date || $project->end_date)
                                    <x-fa-date :value="$project->start_date" />
                                    @if ($project->end_date)
                                        — <x-fa-date :value="$project->end_date" />
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        {{-- The amount is not rendered at all without the
                             permission — hiding it in CSS would still ship it. --}}
                        @if ($canSeeAmounts)
                            <div class="v-dl__span">
                                <dt>مبلغ پروژه</dt>
                                <dd class="v-dl__money">
                                    @if ((int) $project->amount > 0)
                                        {{ Money::fromRial((int) $project->amount)->formatPersian() }}
                                        <span class="v-muted v-small">ریال</span>
                                    @else
                                        <span class="v-muted">تعیین نشده</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if (filled($project->notes))
                        <div class="v-prose">
                            <h3 class="v-prose__title">یادداشت داخلی</h3>
                            <p>{{ $project->notes }}</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- ── Type-specific ─────────────────────────────────────── --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">
                        {{ $project->type === 'seo' ? 'مشخصات سئو' : 'مشخصات طراحی' }}
                    </h2>
                </header>
                <div class="v-card__body">
                    @if ($project->type === 'seo')
                        <dl class="v-dl v-dl--2">
                            <div>
                                <dt>پکیج سئو</dt>
                                <dd>{{ $project->seoDetails?->package?->title ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>کلمات کلیدی قراردادی</dt>
                                <dd>{{ Money::toPersianDigits((string) ($project->seoDetails?->keyword_count ?? 0)) }} کلمه</dd>
                            </div>
                            <div>
                                <dt>کلمات ثبت‌شده در سامانه</dt>
                                <dd>{{ Money::toPersianDigits((string) $project->keywords->count()) }} کلمه</dd>
                            </div>
                            <div>
                                <dt>دورهٔ تکرار</dt>
                                <dd>
                                    @if ($project->seoDetails?->recurrence)
                                        {{ \App\Models\SeoProjectDetail::RECURRENCES[$project->seoDetails->recurrence] ?? $project->seoDetails->recurrence }}
                                    @else
                                        <span class="v-muted">بدون تکرار</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>یادآوری پیش از موعد</dt>
                                <dd>
                                    @if ($project->seoDetails)
                                        {{ Money::toPersianDigits((string) $project->seoDetails->reminder_offset_days) }} روز تقویمی
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @else
                        <dl class="v-dl v-dl--2">
                            <div>
                                <dt>دامنه</dt>
                                <dd dir="ltr">{{ $project->designDetails?->domain ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>نوع سایت</dt>
                                <dd>{{ $project->designDetails?->site_type ?? '—' }}</dd>
                            </div>
                            <div class="v-dl__span">
                                <dt>دامنهٔ خدمات</dt>
                                <dd>
                                    @if (filled($project->designDetails?->services))
                                        {{ $project->designDetails->services }}
                                    @else
                                        <span class="v-muted">تعیین نشده</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @endif
                </div>
            </section>

            {{-- ── Keywords (SEO only) ───────────────────────────────── --}}
            @if ($project->type === 'seo')
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">کلمات کلیدی</h2>
                        <span class="v-card__meta">
                            {{ Money::toPersianDigits((string) $project->keywords->count()) }} کلمه
                        </span>
                    </header>

                    @if ($project->keywords->isEmpty())
                        <div class="v-card__body">
                            <x-empty-state title="کلمه‌ای ثبت نشده است"
                                description="کلمات کلیدی به‌صورت ساختارمند ثبت می‌شوند تا قابل شمارش و پیگیری باشند."
                                icon="search" />
                        </div>
                    @else
                        <div class="v-tablewrap">
                            <table class="v-table">
                                <thead>
                                    <tr>
                                        <th scope="col">کلمهٔ کلیدی</th>
                                        <th scope="col">وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($project->keywords as $keyword)
                                        <tr>
                                            <td>{{ $keyword->keyword }}</td>
                                            <td>
                                                <x-badge :tone="match ($keyword->status) {
                                                    'working' => 'info',
                                                    'done' => 'success',
                                                    'removed' => 'muted',
                                                    default => 'neutral',
                                                }">{{ $keyword->statusLabel() }}</x-badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ── Work items ────────────────────────────────────────── --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">کارهای دوره‌ای</h2>
                    <span class="v-card__meta">
                        {{ Money::toPersianDigits((string) $project->workItems->count()) }} مورد
                    </span>
                </header>

                @if ($project->workItems->isEmpty())
                    <div class="v-card__body">
                        <x-empty-state title="کاری برنامه‌ریزی نشده است"
                            description="فاکتور و گزارش‌های دوره‌ای در مرحلهٔ زمان‌بندی ساخته می‌شوند."
                            icon="calendar" />
                    </div>
                @else
                    <div class="v-tablewrap">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th scope="col">نوع</th>
                                    <th scope="col">موعد</th>
                                    <th scope="col">دوره</th>
                                    <th scope="col">وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($project->workItems as $item)
                                    <tr>
                                        <td>{{ $item->kind === 'invoice' ? 'فاکتور' : 'گزارش' }}</td>
                                        <td><x-fa-date :value="$item->due_date" /></td>
                                        <td dir="ltr">{{ $item->period ?? '—' }}</td>
                                        <td>
                                            @if ($item->done_at)
                                                <x-badge tone="success">انجام‌شده</x-badge>
                                            @elseif ($item->due_date && $item->due_date->isPast())
                                                <x-badge tone="danger">عقب‌افتاده</x-badge>
                                            @else
                                                <x-badge tone="neutral">در انتظار</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- ── Aside ─────────────────────────────────────────────────── --}}
        <div class="v-detail-grid__aside">
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">جزئیات</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl">
                        <div><dt>شماره پروژه</dt><dd dir="ltr">{{ $project->code ?? '—' }}</dd></div>
                        <div><dt>نوع</dt><dd>{{ $project->typeLabel() }}</dd></div>
                        <div><dt>وضعیت</dt><dd><x-status-badge :status="$project->status" domain="project" /></dd></div>
                        <div>
                            <dt>تاریخ ثبت</dt>
                            <dd><x-fa-date :value="$project->created_at" /></dd>
                        </div>
                        <div>
                            <dt>آخرین تغییر</dt>
                            <dd><x-fa-date :value="$project->updated_at" /></dd>
                        </div>
                    </dl>
                </div>
            </section>

            @if ($auditTrail->isNotEmpty())
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">تاریخچهٔ تغییرات</h2>
                    </header>
                    <div class="v-card__body">
                        <ul class="v-timeline">
                            @foreach ($auditTrail as $entry)
                                <li class="v-timeline__item">
                                    <span class="v-timeline__dot" aria-hidden="true"></span>
                                    <div class="v-timeline__body">
                                        <p class="v-timeline__title">{{ $entry->description ?? $entry->action }}</p>
                                        <p class="v-timeline__meta">
                                            {{ $entry->user?->name ?? 'سامانه' }}
                                            · <x-fa-date :value="$entry->created_at" />
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
