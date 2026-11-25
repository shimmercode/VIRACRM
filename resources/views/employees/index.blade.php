@extends('layouts.app')

@section('title', 'پرسنل')

@section('content')
    @php use App\Support\Money; @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">پرسنل</h1>
            <p class="v-pagehead__desc">
                {{ Money::toPersianDigits((string) $counts['total']) }} پروندهٔ پرسنلی،
                {{ Money::toPersianDigits((string) $counts['active']) }} نفر شاغل.
                حقوق از این پرونده نمی‌آید؛ از فیش ماهانهٔ «حقوق و دستمزد» محاسبه می‌شود.
            </p>
        </div>

        @if (auth()->user()->hasPermission('personnel.create'))
            <div class="v-pagehead__actions">
                <a href="{{ route('employees.create') }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> کارمند جدید
                </a>
            </div>
        @endif
    </header>

    <form class="v-filterbar" method="GET" action="{{ route('employees.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="نام، کد پرسنلی، کد ملی یا موبایل…"
                   aria-label="جست‌وجوی کارمند">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-status">وضعیت</label>
            <select id="f-status" name="status" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-dept">واحد</label>
            <select id="f-dept" name="department_id" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}"
                        @selected($filters['department_id'] === (string) $department->id)>
                        {{ $department->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-position">سِمت</label>
            <input id="f-position" type="text" name="position" value="{{ $filters['position'] }}"
                   class="v-input" placeholder="مثلاً کارشناس">
        </div>

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['position'] !== ''
            || $filters['department_id'] !== '')
            <a href="{{ route('employees.index') }}" class="v-btn v-btn--ghost v-btn--sm">پاک‌کردن</a>
        @endif
    </form>

    <div class="v-card">
        @if ($employees->isEmpty())
            @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['position'] !== ''
            || $filters['department_id'] !== '')
                <x-empty-state title="کارمندی با این فیلترها پیدا نشد"
                    description="عبارت جست‌وجو یا فیلترها را تغییر دهید." icon="search">
                    <a href="{{ route('employees.index') }}" class="v-btn v-btn--ghost v-btn--sm">حذف فیلترها</a>
                </x-empty-state>
            @else
                <x-empty-state title="هنوز پروندهٔ پرسنلی ثبت نشده است"
                    description="پروندهٔ هر کارمند پیش‌نیاز فیش حقوقی ماهانه است؛ بدون آن، دورهٔ حقوق خالی می‌ماند."
                    icon="briefcase">
                    @if (auth()->user()->hasPermission('personnel.create'))
                        <a href="{{ route('employees.create') }}" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="plus" :size="16" /> ثبت اولین کارمند
                        </a>
                    @endif
                </x-empty-state>
            @endif
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">کارمند</th>
                            <th scope="col">کد پرسنلی</th>
                            <th scope="col">سِمت</th>
                            <th scope="col">واحد</th>
                            <th scope="col">تماس</th>
                            <th scope="col">شروع همکاری</th>
                            <th scope="col">وضعیت</th>
                            @if ($canSeePayroll)
                                <th scope="col" class="v-table__num">فیش‌ها</th>
                            @endif
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr>
                                <td data-label="کارمند">
                                    <a class="v-table__primary" href="{{ route('employees.show', $employee) }}">
                                        {{ $employee->displayName() }}
                                    </a>
                                    @if ($employee->national_id)
                                        <div class="v-table__sub">
                                            کد ملی: {{ Money::toPersianDigits($employee->national_id) }}
                                        </div>
                                    @endif
                                </td>
                                <td data-label="کد پرسنلی" class="v-table__nowrap">
                                    {{ $employee->code ? Money::toPersianDigits($employee->code) : '—' }}
                                </td>
                                <td data-label="سِمت">{{ $employee->position ?: '—' }}</td>
                                <td data-label="واحد">{{ $employee->department?->title ?? '—' }}</td>
                                <td data-label="تماس" class="v-table__nowrap" dir="ltr">
                                    {{ $employee->mobile ? Money::toPersianDigits($employee->mobile) : '—' }}
                                </td>
                                <td data-label="شروع همکاری" class="v-table__nowrap">
                                    <x-fa-date :value="$employee->start_date" />
                                </td>
                                <td data-label="وضعیت">
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
                                </td>
                                @if ($canSeePayroll)
                                    <td data-label="فیش‌ها" class="v-table__num">
                                        {{ Money::toPersianDigits((string) $employee->payslips_count) }}
                                    </td>
                                @endif
                                <td class="v-table__actions">
                                    <a class="v-iconbtn" href="{{ route('employees.show', $employee) }}"
                                       aria-label="مشاهدهٔ پروندهٔ {{ $employee->displayName() }}">
                                        <x-icon name="eye" :size="16" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="v-card__foot">
                <x-pagination :paginator="$employees" />
            </footer>
        @endif
    </div>
@endsection
