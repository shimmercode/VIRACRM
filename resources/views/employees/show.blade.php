@extends('layouts.app')

@section('title', $employee->displayName())

@section('content')
    @php use App\Support\Money; @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('employees.index') }}">پرسنل</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $employee->displayName() }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $employee->displayName() }}</h1>
            <p class="v-pagehead__desc">
                {{ $employee->position ?: 'بدون سِمت ثبت‌شده' }}
                @if ($employee->code)
                    · کد پرسنلی {{ Money::toPersianDigits($employee->code) }}
                @endif
            </p>
        </div>

        <div class="v-pagehead__actions">
            @php
                $tone = match ($employee->status) {
                    'active' => 'success',
                    'on_leave' => 'warn',
                    'suspended' => 'danger',
                    default => 'muted',
                };
            @endphp
            <x-badge :tone="$tone" :dot="true">
                {{ \App\Models\Employee::STATUSES[$employee->status] ?? $employee->status }}
            </x-badge>

            @if (auth()->user()->hasPermission('personnel.update'))
                <a href="{{ route('employees.edit', $employee) }}" class="v-btn v-btn--ghost v-btn--sm">
                    <x-icon name="edit" :size="16" /> ویرایش
                </a>
            @endif
        </div>
    </header>

    <div class="v-grid v-grid--2">
        {{-- ── Personnel file ─────────────────────────────────────────── --}}
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">مشخصات</h2>
            </header>

            <div class="v-card__body">
                <dl class="v-dl v-dl--2">
                    <div><dt>کد ملی</dt>
                        <dd dir="ltr">{{ $employee->national_id ? Money::toPersianDigits($employee->national_id) : '—' }}</dd></div>
                    <div><dt>موبایل</dt>
                        <dd dir="ltr">{{ $employee->mobile ? Money::toPersianDigits($employee->mobile) : '—' }}</dd></div>
                    <div><dt>ایمیل</dt>
                        <dd dir="ltr">{{ $employee->email ?: '—' }}</dd></div>
                    <div><dt>حساب کاربری</dt>
                        <dd>{{ $employee->user?->name ?? 'بدون حساب' }}</dd></div>
                    <div><dt>شروع همکاری</dt>
                        <dd><x-fa-date :value="$employee->start_date" /></dd></div>
                    <div><dt>پایان همکاری</dt>
                        <dd><x-fa-date :value="$employee->end_date" /></dd></div>
                    <div class="v-dl__span"><dt>نشانی</dt>
                        <dd>{{ $employee->address ?: '—' }}</dd></div>
                </dl>

                @if ($employee->notes)
                    <div class="v-fsection__hint" style="margin-top: var(--vira-space-4)">
                        <strong>یادداشت:</strong> {{ $employee->notes }}
                    </div>
                @endif
            </div>
        </section>

        {{-- ── Employment contracts ───────────────────────────────────── --}}
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">قراردادهای همکاری</h2>
            </header>

            @if ($employee->contracts->isEmpty())
                <x-empty-state title="قرارداد همکاری ثبت نشده"
                    description="قرارداد کاری جدا از فیش حقوقی است و مبلغ توافقی را نگه می‌دارد."
                    icon="document" />
            @else
                <div class="v-card__body">
                <ul class="v-timeline">
                    @foreach ($employee->contracts as $contract)
                        <li class="v-timeline__item">
                            <span class="v-timeline__dot" aria-hidden="true"></span>
                            <div class="v-timeline__body">
                                <p class="v-timeline__title">{{ $contract->title ?: 'قرارداد همکاری' }}</p>
                                <p class="v-timeline__meta">
                                    <x-fa-date :value="$contract->start_date" />
                                    @if ($contract->end_date)
                                        — <x-fa-date :value="$contract->end_date" />
                                    @endif
                                    @if ($contract->agreed_amount)
                                        · مبلغ توافقی {{ Money::fromRial((int) $contract->agreed_amount)->formatPersian() }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                </div>
            @endif
        </section>
    </div>

    {{-- ── Salary history: only when the user may read payroll ───────── --}}
    @if ($canSeePayroll)
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">سابقهٔ فیش حقوقی</h2>
            </header>

            @if ($payslips->isEmpty())
                <x-empty-state title="هنوز فیش حقوقی صادر نشده است"
                    description="فیش‌ها از صفحهٔ «حقوق و دستمزد» برای هر ماه ساخته می‌شوند."
                    icon="banknote" />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">دوره</th>
                                <th scope="col" class="v-table__num">حقوق پایه</th>
                                <th scope="col" class="v-table__num">جمع دریافتی</th>
                                <th scope="col" class="v-table__num">کسورات</th>
                                <th scope="col" class="v-table__num">خالص پرداختی</th>
                                <th scope="col">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payslips as $payslip)
                                <tr>
                                    <td data-label="دوره">
                                        <a class="v-table__primary" href="{{ route('payroll.payslip', $payslip) }}">
                                            {{ $payslip->period?->title() ?? '—' }}
                                        </a>
                                    </td>
                                    <td data-label="حقوق پایه" class="v-table__num">{{ Money::fromRial((int) $payslip->base_salary)->formatPersian() }}</td>
                                    <td data-label="جمع دریافتی" class="v-table__num">{{ Money::fromRial((int) $payslip->total_earnings)->formatPersian() }}</td>
                                    <td data-label="کسورات" class="v-table__num">{{ Money::fromRial((int) $payslip->total_deductions)->formatPersian() }}</td>
                                    <td data-label="خالص پرداختی" class="v-table__num">
                                        <strong>{{ Money::fromRial((int) $payslip->net_payable)->formatPersian() }}</strong>
                                    </td>
                                    <td data-label="وضعیت">
                                        @if ($payslip->period?->isPublished())
                                            <x-badge tone="success" :dot="true">منتشرشده</x-badge>
                                        @else
                                            <x-badge tone="neutral" :dot="true">پیش‌نویس</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- ── Audit trail ───────────────────────────────────────────────── --}}
    <section class="v-card">
        <header class="v-card__head">
            <h2 class="v-card__title">گزارش رویدادها</h2>
        </header>

        @if ($auditTrail->isEmpty())
            <p class="v-card__body v-muted">رویدادی برای این پرونده ثبت نشده است.</p>
        @else
            <div class="v-card__body">
            <ul class="v-timeline">
                @foreach ($auditTrail as $log)
                    <li class="v-timeline__item">
                        <span class="v-timeline__dot" aria-hidden="true"></span>
                        <div class="v-timeline__body">
                            <p class="v-timeline__title">{{ $log->description ?: $log->event }}</p>
                            <p class="v-timeline__meta">
                                {{ $log->user?->name ?? 'سامانه' }} ·
                                <x-fa-date :value="$log->created_at" :with-time="true" />
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
            </div>
        @endif
    </section>

    @if (auth()->user()->hasPermission('personnel.delete'))
        <section class="v-card v-card--danger">
            <header class="v-card__head">
                <h2 class="v-card__title">بایگانی پرونده</h2>
            </header>
            <div class="v-card__body">
                <p class="v-muted" style="margin-top:0">
                    بایگانی، پرونده را از فهرست فعال خارج می‌کند اما حذف نمی‌کند. سابقهٔ حقوق و
                    گزارش رویدادها برای حسابرسی باقی می‌ماند.
                </p>
                <form method="POST" action="{{ route('employees.destroy', $employee) }}"
                      data-confirm="پروندهٔ «{{ $employee->displayName() }}» بایگانی شود؟">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="v-btn v-btn--danger v-btn--sm">
                        <x-icon name="trash" :size="16" /> بایگانی پرونده
                    </button>
                </form>
            </div>
        </section>
    @endif
@endsection
