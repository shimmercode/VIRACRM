@extends('layouts.app')

@section('title', 'گزارش فاکتورها و گزارش‌های موعددار')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">گزارش فاکتورها و گزارش‌های موعددار</h1>
            <p class="v-pagehead__desc">
                اقدامات زمان‌بندی‌شده — صورت‌حساب‌ها و گزارش‌ها — بر اساس موعدشان
                دسته‌بندی شده‌اند. هر ردیف در دقیقاً یک دسته قرار می‌گیرد و جمع
                دسته‌ها با تعداد کل برابر است. «گذشته از موعد» از «سررسید امروز»
                جدا نگه داشته شده است، چون اولی مشکل است و دومی یادآوری.
            </p>
        </div>
    </header>

    @unless ($reconciles)
        <div class="v-card">
            <div class="v-card__body">
                <p class="v-error">
                    جمع دسته‌ها ({{ $pd($sumOfBuckets) }}) با تعداد کل
                    ({{ $pd($counts['total']) }}) برابر نیست. این گزارش ردیفی را در
                    هیچ دسته‌ای یا در دو دسته شمرده است؛ اعداد زیر قابل اتکا نیستند.
                </p>
            </div>
        </div>
    @endunless

    <div class="v-stats">
        @foreach ([
            'overdue' => ['گذشته از موعد', 'موعد گذشته و هنوز باز'],
            'due_today' => ['سررسید امروز', 'موعد همان امروز است'],
            'upcoming' => ['پیش‌رو', 'موعد در آینده'],
            'unscheduled' => ['بدون موعد', 'باز، ولی تاریخی ندارد'],
            'done' => ['انجام‌شده', 'ثبت شده به‌عنوان انجام‌شده'],
            'cancelled' => ['لغوشده', 'از چرخه خارج شده'],
        ] as $key => [$label, $hint])
            <a class="v-stat{{ $category === $key ? ' v-stat--accent' : '' }}"
               href="{{ route('reports.work-items', array_merge($filters, ['category' => $key])) }}"
               @if ($category === $key) aria-current="page" @endif>
                <span class="v-stat__label">{{ $label }}</span>
                <span class="v-stat__value">{{ $pd($counts[$key]) }}</span>
                <span class="v-stat__hint">{{ $hint }}</span>
            </a>
        @endforeach
    </div>

    <form class="v-filterbar" method="GET" action="{{ route('reports.work-items') }}">
        {{-- The bucket is part of the filter state, so changing a filter keeps
             the user on the tile they were reading instead of snapping back. --}}
        <input type="hidden" name="category" value="{{ $category }}">

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-kind">نوع</label>
            <select id="f-kind" name="kind" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($kinds as $value => $label)
                    <option value="{{ $value }}" @selected($filters['kind'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-from">از تاریخ</label>
            <input id="f-from" name="from" type="text" class="v-input" dir="ltr"
                   value="{{ $filters['from'] }}" placeholder="۱۴۰۴/۰۱/۰۱">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-to">تا تاریخ</label>
            <input id="f-to" name="to" type="text" class="v-input" dir="ltr"
                   value="{{ $filters['to'] }}" placeholder="۱۴۰۴/۱۲/۲۹">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-sort">مرتب‌سازی</label>
            <select id="f-sort" name="sort" class="v-select v-select--sm">
                <option value="due_date" @selected($filters['sort'] === 'due_date')>تاریخ سررسید</option>
                <option value="state" @selected($filters['sort'] === 'state')>وضعیت</option>
                <option value="kind" @selected($filters['sort'] === 'kind')>نوع</option>
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-dir">جهت</label>
            <select id="f-dir" name="dir" class="v-select v-select--sm">
                <option value="asc" @selected($filters['dir'] === 'asc')>صعودی</option>
                <option value="desc" @selected($filters['dir'] === 'desc')>نزولی</option>
            </select>
        </div>

        <div class="v-filterbar__group">
            <button type="submit" class="v-btn v-btn--sm v-btn--primary">اعمال فیلتر</button>
        </div>
    </form>

    <section class="v-card">
        <header class="v-card__head">
            <h2 class="v-card__title">
                فهرست —
                @php
                    $titles = [
                        'overdue' => 'گذشته از موعد',
                        'due_today' => 'سررسید امروز',
                        'upcoming' => 'پیش‌رو',
                        'unscheduled' => 'بدون موعد',
                        'done' => 'انجام‌شده',
                        'cancelled' => 'لغوشده',
                    ];
                @endphp
                {{ $titles[$category] ?? $category }}
            </h2>
        </header>
        <div class="v-card__body">
            @if ($items->isEmpty())
                <x-empty-state
                    title="موردی در این دسته نیست"
                    description="دستهٔ دیگری را از آمار بالا انتخاب کنید یا فیلتر را بردارید."
                    icon="document"
                />
            @else
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>اقدام</th>
                            <th>پروژه</th>
                            <th>مشتری</th>
                            <th>نوع</th>
                            <th>تاریخ سررسید</th>
                            <th>وضعیت</th>
                            @if ($canSeeAmounts)<th>مبلغ صورت‌حساب</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php
                                /* diffInDays is signed end-minus-start, so an item
                                   whose due date has passed reads negative. */
                                $days = $item->due_date === null
                                    ? null
                                    : (int) $today->copy()->diffInDays($item->due_date->copy()->startOfDay(), false);
                            @endphp
                            <tr>
                                <td class="v-table__primary">
                                    <a href="{{ route('work-items.show', $item) }}">
                                        {{ $item->kind === 'invoice' ? 'صورت‌حساب' : 'گزارش' }}
                                        {{ $item->period ? '— ' . $item->period : '' }}
                                    </a>
                                    @if ($item->invoice_number)
                                        <span class="v-table__sub" dir="ltr">{{ $item->invoice_number }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->project)
                                        <a href="{{ route('projects.show', $item->project) }}" dir="ltr">
                                            {{ $item->project->code }}
                                        </a>
                                        <span class="v-table__sub">{{ $item->project->title }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $item->project?->customer?->display_name ?? '—' }}</td>
                                <td>{{ $kinds[$item->kind] ?? $item->kind }}</td>
                                <td>
                                    <x-fa-date :value="$item->due_date" />
                                    @if ($days !== null && $item->state === 'pending')
                                        <span class="v-table__sub{{ $days < 0 ? ' v-money--negative' : '' }}">
                                            {{ $days < 0 ? $pd(abs($days)) . ' روز گذشته' : ($days === 0 ? 'امروز' : $pd($days) . ' روز مانده') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    {{-- Not x-status-badge: that component knows the
                                         customer, project and contract domains only, and an
                                         unknown domain falls through to CustomerStatus and
                                         would print «سرنخ» for every pending action. --}}
                                    @if ($item->state === 'done')
                                        <span class="v-badge v-badge--success">انجام‌شده</span>
                                    @elseif ($item->state === 'cancelled')
                                        <span class="v-badge v-badge--neutral">لغو شده</span>
                                    @elseif ($item->isOverdue())
                                        <span class="v-badge v-badge--danger">عقب‌افتاده</span>
                                    @else
                                        <span class="v-badge v-badge--warning">در انتظار</span>
                                    @endif
                                </td>
                                @if ($canSeeAmounts)
                                    <td class="v-table__num v-money" dir="ltr">
                                        {{ $item->invoice_amount === null
                                            ? '—'
                                            : \App\Support\Money::fromRial((int) $item->invoice_amount)->formatPersian() }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-pagination :paginator="$items" />
            @endif
        </div>
    </section>
@endsection
