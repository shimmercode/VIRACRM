@extends('layouts.app')

@section('title', 'داشبورد')

@section('content')
    @php
        use App\Support\Money;

        $persian = fn ($n) => $n === null ? null : Money::toPersianDigits((string) $n);
    @endphp

    {{-- §2: greeting and the day, both real values. The date comes from the
         Jalali converter, not from the browser clock. --}}
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $greeting }}، {{ auth()->user()->displayName() }}</h1>
            <p class="v-pagehead__desc">{{ $todayJalali }}</p>
        </div>
    </header>

    {{-- §2 The five counts. A count the user may not see is omitted entirely
         rather than shown as zero, which would be a lie. --}}
    <section class="v-stats" aria-label="خلاصه وضعیت">
        @if ($counts['customers'] !== null)
            <a class="v-stat" href="{{ route('customers.index') }}">
                <span class="v-stat__label">مشتریان</span>
                <span class="v-stat__value">{{ $persian($counts['customers']) }}</span>
                <span class="v-stat__hint">مجموع مشتریان ثبت‌شده</span>
            </a>
        @endif

        @if ($counts['active_contracts'] !== null)
            <a class="v-stat" href="{{ route('contracts.index', ['status' => 'active']) }}">
                <span class="v-stat__label">قراردادهای فعال</span>
                <span class="v-stat__value">{{ $persian($counts['active_contracts']) }}</span>
                <span class="v-stat__hint">در حال اجرا</span>
            </a>
        @endif

        @if ($counts['expiring_contracts'] !== null)
            <a class="v-stat {{ $counts['expiring_contracts'] > 0 ? 'v-stat--warn' : '' }}"
               href="{{ route('contracts.index', ['status' => 'active']) }}">
                <span class="v-stat__label">نزدیک به انقضا</span>
                <span class="v-stat__value">{{ $persian($counts['expiring_contracts']) }}</span>
                <span class="v-stat__hint">تا {{ $persian($warningDays) }} روز آینده</span>
            </a>
        @endif

        @if ($counts['active_projects'] !== null)
            <div class="v-stat v-stat--static">
                <span class="v-stat__label">پروژه‌های فعال</span>
                <span class="v-stat__value">{{ $persian($counts['active_projects']) }}</span>
                <span class="v-stat__hint">در حال انجام</span>
            </div>
        @endif

        @if ($counts['overdue_tasks'] !== null)
            <div class="v-stat {{ $counts['overdue_tasks'] > 0 ? 'v-stat--danger' : '' }} v-stat--static">
                <span class="v-stat__label">کارهای عقب‌افتاده</span>
                <span class="v-stat__value">{{ $persian($counts['overdue_tasks']) }}</span>
                <span class="v-stat__hint">مهلت گذشته</span>
            </div>
        @endif
    </section>

    <div class="v-dash-grid">
        {{-- §2 کارتابل — four buckets, real work items only. --}}
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">کارتابل</h2>
                <span class="v-card__meta">{{ $persian($doneThisMonth) }} انجام‌شده در این ماه</span>
            </header>

            <div class="v-card__body v-card__body--flush">
                @php
                    $groups = [
                        ['key' => 'overdue', 'label' => 'عقب‌افتاده', 'tone' => 'danger'],
                        ['key' => 'today', 'label' => 'امروز', 'tone' => 'primary'],
                        ['key' => 'future', 'label' => 'پیش رو', 'tone' => 'neutral'],
                    ];
                    $hasAny = $buckets['overdue']->isNotEmpty()
                        || $buckets['today']->isNotEmpty()
                        || $buckets['future']->isNotEmpty();
                @endphp

                @if (! $hasAny)
                    <x-empty-state
                        title="کاری در کارتابل نیست"
                        description="کارهای باز پروژه‌ها اینجا نمایش داده می‌شود."
                        icon="check"
                    />
                @else
                    @foreach ($groups as $group)
                        @php $items = $buckets[$group['key']]; @endphp
                        @continue($items->isEmpty())

                        <div class="v-taskgroup">
                            <div class="v-taskgroup__head">
                                <span class="v-taskgroup__label">{{ $group['label'] }}</span>
                                <x-badge :tone="$group['tone']">{{ $persian($items->count()) }}</x-badge>
                            </div>

                            <ul class="v-tasklist">
                                @foreach ($items->take(6) as $item)
                                    <li class="v-task">
                                        <span class="v-task__dot" aria-hidden="true"></span>
                                        <div class="v-task__body">
                                            <span class="v-task__title">{{ $item->title }}</span>
                                            @if ($item->project)
                                                <span class="v-task__meta">
                                                    {{ $item->project->customer?->display_name ?? '—' }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="v-task__date">
                                            {{ $item->due_date
                                                ? \App\Support\JalaliDate::fromCarbon(\Carbon\CarbonImmutable::parse($item->due_date))->formatPersian()
                                                : '—' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>

        {{-- §2 contract expiry warnings — the real warning list. --}}
        @if ($counts['active_contracts'] !== null)
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">قراردادهای نزدیک به انقضا</h2>
                    <a class="v-card__link" href="{{ route('contracts.index') }}">
                        همه قراردادها <x-icon name="chevron" :size="14" />
                    </a>
                </header>

                <div class="v-card__body v-card__body--flush">
                    @if ($expiring->isEmpty())
                        <x-empty-state
                            title="قراردادی در آستانه انقضا نیست"
                            description="قراردادهایی که تا {{ $persian($warningDays) }} روز آینده به پایان می‌رسند اینجا هشدار داده می‌شوند."
                            icon="check"
                        />
                    @else
                        <ul class="v-list">
                            @foreach ($expiring as $contract)
                                <li class="v-list__item">
                                    <a class="v-list__main" href="{{ route('contracts.show', $contract) }}">
                                        <span class="v-list__title">{{ $contract->title }}</span>
                                        <span class="v-list__meta">{{ $contract->customer?->display_name ?? '—' }}</span>
                                    </a>
                                    <span class="v-list__aside v-list__aside--warn">
                                        {{ $persian($contract->days_to_expiry) }} روز
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endif
    </div>

    {{-- Honest note about scope: the remaining modules are real parts of the
         document that are not built yet. Saying so is better than a dashboard
         tile that pretends otherwise. --}}
    <p class="v-note">
        ماژول‌های پروژه، دسترسی‌های پروژه، پرسنل، حقوق و دستمزد و گزارش‌ها در مرحلهٔ بعد فعال می‌شوند.
    </p>
@endsection
