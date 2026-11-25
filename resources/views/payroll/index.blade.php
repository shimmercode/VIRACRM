@extends('layouts.app')

@section('title', 'حقوق و دستمزد')

@section('content')
    @php use App\Support\Money; use App\Models\PayrollPeriod; @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">حقوق و دستمزد</h1>
            <p class="v-pagehead__desc">
                حقوق هر ماه از «حقوق پایه» به‌علاوهٔ اقلام دستی همان ماه ساخته می‌شود
                (تصمیم OD-16). هیچ فرمول پورسانتی خودکار وجود ندارد.
            </p>
        </div>
    </header>

    @if ($canManage)
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">ایجاد دورهٔ حقوق</h2>
            </header>
            <div class="v-card__body">
                <form method="POST" action="{{ route('payroll.periods.store') }}" class="v-inlineform">
                    @csrf
                    <div class="v-field">
                        <label class="v-label" for="jalali_year">سال</label>
                        <input id="jalali_year" name="jalali_year" type="number" class="v-input"
                               value="{{ old('jalali_year', \App\Support\JalaliDate::fromCarbon(now()->toImmutable())->year) }}"
                               min="1390" max="1500" required style="max-width: 110px">
                        @error('jalali_year')<p class="v-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="v-field">
                        <label class="v-label" for="jalali_month">ماه</label>
                        <select id="jalali_month" name="jalali_month" class="v-select" required style="max-width: 170px">
                            @foreach ((new \App\Models\PayrollPeriod())->monthOptions() as $num => $name)
                                <option value="{{ $num }}"
                                    @selected((string) old('jalali_month', \App\Support\JalaliDate::fromCarbon(now()->toImmutable())->month) === (string) $num)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('jalali_month')<p class="v-error">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                        <x-icon name="plus" :size="16" /> ایجاد دوره
                    </button>
                </form>
                <p class="v-hint">
                    هر ماه فقط یک بار ساخته می‌شود؛ اگر دوره وجود داشته باشد به همان هدایت می‌شوید.
                </p>
            </div>
        </section>
    @endif

    <div class="v-card">
        @if ($periods->isEmpty())
            <x-empty-state title="هنوز دورهٔ حقوقی ساخته نشده است"
                description="با ایجاد اولین دوره، فیش هر کارمند شاغل ساخته می‌شود و پس از محاسبه و تأیید، سند آن در دفتر کل ثبت می‌گردد."
                icon="banknote" />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">دوره</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col" class="v-table__num">تعداد فیش</th>
                            <th scope="col" class="v-table__num">جمع ناخالص</th>
                            <th scope="col" class="v-table__num">جمع خالص</th>
                            <th scope="col">انتشار</th>
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($periods as $period)
                            @php $total = $totals[$period->id] ?? null; @endphp
                            <tr>
                                <td data-label="دوره">
                                    <a class="v-table__primary" href="{{ route('payroll.show', $period) }}">
                                        {{ $period->title() }}
                                    </a>
                                    <div class="v-table__sub">{{ $period->code() }}</div>
                                </td>
                                <td data-label="وضعیت">
                                    @php
                                        $tone = match ($period->state) {
                                            'published' => 'success',
                                            'computed' => 'info',
                                            default => 'neutral',
                                        };
                                    @endphp
                                    <x-badge :tone="$tone" :dot="true">
                                        {{ PayrollPeriod::STATES[$period->state] ?? $period->state }}
                                    </x-badge>
                                </td>
                                <td data-label="تعداد فیش" class="v-table__num">
                                    {{ Money::toPersianDigits((string) $period->payslips_count) }}
                                </td>
                                <td data-label="جمع ناخالص" class="v-table__num">
                                    {{ $total ? Money::fromRial((int) $total->gross)->formatPersian() : '—' }}
                                </td>
                                <td data-label="جمع خالص" class="v-table__num">
                                    {{ $total ? Money::fromRial((int) $total->net)->formatPersian() : '—' }}
                                </td>
                                <td data-label="انتشار">
                                    @if ($period->published_at)
                                        <x-fa-date :value="$period->published_at" />
                                    @else
                                        <span class="v-muted">—</span>
                                    @endif
                                </td>
                                <td class="v-table__actions">
                                    <a class="v-iconbtn" href="{{ route('payroll.show', $period) }}"
                                       aria-label="مشاهدهٔ دورهٔ {{ $period->title() }}">
                                        <x-icon name="eye" :size="16" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($rules->isNotEmpty())
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">قواعد حقوق</h2>
            </header>
            <div class="v-card__body">
                <ul class="v-list">
                    @foreach ($rules as $rule)
                        <li class="v-list__item">
                            <span class="v-list__title">{{ $rule->title }}</span>
                            <span class="v-list__meta">
                                {{ \App\Models\PayrollRule::METHODS[$rule->method] ?? $rule->method }}
                            </span>
                            @if ($rule->is_approved)
                                <x-badge tone="success" :dot="true">مصوب</x-badge>
                            @else
                                <x-badge tone="warning" :dot="true">در انتظار تأیید</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
