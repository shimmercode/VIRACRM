@extends('layouts.app')

@section('title', 'گزارش بار کاری کارشناسان')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">بار کاری کارشناسان</h1>
            <p class="v-pagehead__desc">
                تعداد پروژه‌های هر کارشناس و اقلام لازم برای محاسبهٔ حقوق.
                معیار شمارش: انتساب فعال روی پروژه‌ای با وضعیت
                <strong>{{ $filters['status'] === 'active' ? 'در حال اجرا' : $filters['status'] }}</strong>.
                پروژه‌ای که چند کارشناس دارد، برای هر کدام یک بار شمرده می‌شود؛
                بنابراین جمع ستون زیر از تعداد کل پروژه‌ها بیشتر است.
            </p>
        </div>
    </header>

    <form class="v-filterbar" method="GET" action="{{ route('reports.expert-workload') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="نام کارشناس یا کد پرسنلی…" aria-label="جست‌وجوی کارشناس">
        </div>

        <label class="v-filterbar__label" for="f-status">وضعیت پروژه</label>
        <select id="f-status" name="status" class="v-select v-select--sm">
            @foreach ($statusOptions as $option)
                <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <label class="v-filterbar__label" for="f-estatus">وضعیت همکاری</label>
        <select id="f-estatus" name="employee_status" class="v-select v-select--sm">
            @foreach ($employeeStatuses as $option)
                <option value="{{ $option }}" @selected($filters['employee_status'] === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <label class="v-filterbar__label" for="f-sort">مرتب‌سازی</label>
        <select id="f-sort" name="sort" class="v-select v-select--sm">
            <option value="projects" @selected($filters['sort'] === 'projects')>بیشترین پروژه</option>
            <option value="name" @selected($filters['sort'] === 'name')>نام کارشناس</option>
            <option value="all" @selected($filters['sort'] === 'all')>همهٔ انتساب‌ها</option>
        </select>

        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <button type="submit" class="v-btn v-btn--sm">اعمال فیلتر</button>
    </form>

    {{-- Two figures, so the counting basis is checkable on the page itself:
         the sum of the column below is deliberately larger than the project
         total whenever a project has more than one expert. --}}
    <div class="v-stats">
        <div class="v-stat">
            <span class="v-stat__label">کارشناسان</span>
            <span class="v-stat__value">{{ $pd($totals['experts']) }}</span>
        </div>
        <div class="v-stat">
            <span class="v-stat__label">پروژه‌های یکتا</span>
            <span class="v-stat__value">{{ $pd($totals['projects']) }}</span>
            <span class="v-stat__hint">بدون شمارش چندکارشناس</span>
        </div>
    </div>

    @if ($rows->isEmpty())
        <x-empty-state
            title="انتسابی با این فیلترها یافت نشد"
            description="هیچ کارشناسی با وضعیت همکاری انتخاب‌شده روی پروژه‌ای با این وضعیت کار نمی‌کند. برای دیدن سوابق، وضعیت همکاری یا وضعیت پروژه را تغییر دهید." />
    @else
        <div class="v-tablewrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th>کارشناس</th>
                        <th>سمت</th>
                        <th class="v-table__num">پروژه‌های {{ $filters['status'] }}</th>
                        <th class="v-table__num">همهٔ انتساب‌ها</th>
                        @if ($canSeeAmounts)
                            <th class="v-table__num">مبلغ مؤثر (ریال)</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <span class="v-table__primary">{{ $row->employee_name }}</span>
                                <span class="v-table__sub" dir="ltr">{{ $row->employee_code }}</span>
                            </td>
                            <td>{{ $row->employee_position ?? '—' }}</td>
                            <td class="v-table__num">{{ $pd($row->active_projects) }}</td>
                            <td class="v-table__num">{{ $pd($row->total_assignments) }}</td>
                            @if ($canSeeAmounts)
                                {{-- Money is gated on projects.view_financials, not on
                                     reports.view: workload is operational, pay is not. --}}
                                <td class="v-table__num v-money" dir="ltr">{{ $pd((int) $row->earned_amount) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$rows" />
    @endif
@endsection
