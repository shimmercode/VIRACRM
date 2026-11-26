@extends('layouts.app')

@section('title', 'گزارش قراردادها')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">گزارش قراردادها</h1>
            <p class="v-pagehead__desc">
                قراردادهای فعال، پایان‌یافته و نزدیک به انقضا.
                «در آستانه انقضا» یک وضعیت ذخیره‌شده نیست؛ از تاریخ پایان و بازهٔ
                هشدار محاسبه می‌شود، همان بازه‌ای که داشبورد استفاده می‌کند:
                <strong>{{ $pd($warningDays) }} روز</strong>.
                هر قرارداد در دقیقاً یک دسته قرار می‌گیرد و جمع دسته‌ها با تعداد کل
                قراردادها برابر است.
            </p>
        </div>
    </header>

    @unless ($reconciles)
        <div class="v-card">
            <div class="v-card__body">
                <p class="v-error">
                    جمع دسته‌ها ({{ $pd($sumOfBuckets) }}) با تعداد کل قراردادها
                    ({{ $pd($counts['total']) }}) برابر نیست. این گزارش یک قرارداد را
                    در هیچ دسته‌ای یا در دو دسته شمرده است؛ عدد زیر قابل اتکا نیست.
                </p>
            </div>
        </div>
    @endunless

    <div class="v-stats">
        @foreach ([
            'expiring' => ['در آستانه انقضا', 'پایان در ' . $pd($warningDays) . ' روز آینده'],
            'overdue' => ['گذشته از موعد', 'تاریخ پایان گذشته، هنوز فعال'],
            'active' => ['فعال', 'بیرون از بازهٔ هشدار'],
            'finished' => ['پایان‌یافته', 'منقضی، تمدیدشده یا لغوشده'],
            'draft' => ['پیش‌نویس', 'هنوز نهایی نشده'],
        ] as $key => [$label, $hint])
            <a class="v-stat{{ $category === $key ? ' v-stat--accent' : '' }}"
               href="{{ route('reports.contracts', ['category' => $key]) }}"
               @if ($category === $key) aria-current="page" @endif>
                <span class="v-stat__label">{{ $label }}</span>
                <span class="v-stat__value">{{ $pd($counts[$key]) }}</span>
                <span class="v-stat__hint">{{ $hint }}</span>
            </a>
        @endforeach
    </div>

    <section class="v-card">
        <header class="v-card__head">
            <h2 class="v-card__title">
                فهرست —
                @if ($category === 'expiring')
                    در آستانه انقضا
                @elseif ($category === 'overdue')
                    گذشته از موعد
                @elseif ($category === 'active')
                    فعال
                @elseif ($category === 'finished')
                    پایان‌یافته
                @else
                    پیش‌نویس
                @endif
            </h2>
        </header>
        <div class="v-card__body">
            @if ($contracts->isEmpty())
                <x-empty-state
                    title="موردی در این دسته نیست"
                    description="دستهٔ دیگری را از آمار بالا انتخاب کنید."
                    icon="document"
                />
            @else
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>قرارداد</th>
                            <th>مشتری</th>
                            <th>وضعیت</th>
                            <th>تاریخ پایان</th>
                            <th>روزهای باقیمانده</th>
                            @if ($canSeeAmounts)<th>مبلغ</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contracts as $contract)
                            @php
                                /* $today->diffInDays($end) is end minus today, so a
                                   contract still running reads positive and one already
                                   past its end date reads negative. */
                                $days = $contract->end_date === null
                                    ? null
                                    : (int) $today->copy()->diffInDays($contract->end_date->copy()->startOfDay(), false);
                            @endphp
                            <tr>
                                <td class="v-table__primary">
                                    <a href="{{ route('contracts.show', $contract) }}">{{ $contract->title }}</a>
                                    <span class="v-table__sub" dir="ltr">{{ $contract->number ?? '—' }}</span>
                                </td>
                                <td>{{ $contract->customer?->display_name ?? '—' }}</td>
                                <td><x-status-badge :status="$contract->status" domain="contract" /></td>
                                <td><x-fa-date :value="$contract->end_date" /></td>
                                <td class="v-table__num{{ $days !== null && $days < 0 ? ' v-money--negative' : '' }}">
                                    {{ $days === null ? '—' : $pd($days) }}
                                </td>
                                @if ($canSeeAmounts)
                                    <td class="v-table__num v-money" dir="ltr">
                                        {{ $contract->amountAsMoney()->formatPersian() }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-pagination :paginator="$contracts" />
            @endif
        </div>
    </section>
@endsection
