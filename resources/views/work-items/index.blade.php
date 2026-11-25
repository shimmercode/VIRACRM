@extends('layouts.app')

@section('title', 'کارتابل')

@section('content')
    @php use App\Support\Money; $pd = fn ($v) => Money::toPersianDigits((string) $v); @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">کارتابل</h1>
            <p class="v-pagehead__desc">
                تعهدهای دوره‌ای پروژه‌ها: صورتحساب‌هایی که باید صادر شوند و گزارش‌هایی که باید ارسال شوند.
            </p>
        </div>

        @if (auth()->user()->hasPermission('work_items.create'))
            <div class="v-pagehead__actions">
                <a href="#new-work-item" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> مورد جدید
                </a>
            </div>
        @endif
    </header>

    {{-- Counts cover the whole open set, not the visible page: a badge that
         counts only page one is a lie past twenty-five items. --}}
    <section class="v-stats v-stats--compact" aria-label="خلاصه کارتابل">
        <a class="v-stat v-stat--danger" href="{{ route('work-items.index', ['overdue' => 1, 'state' => 'open']) }}">
            <span class="v-stat__label">سررسید گذشته</span>
            <span class="v-stat__value">{{ $pd($summary['overdue']) }}</span>
        </a>
        <div class="v-stat v-stat--warning">
            <span class="v-stat__label">سررسید امروز</span>
            <span class="v-stat__value">{{ $pd($summary['due_today']) }}</span>
        </div>
        <div class="v-stat v-stat--static">
            <span class="v-stat__label">کل باز</span>
            <span class="v-stat__value">{{ $pd($summary['open']) }}</span>
        </div>
    </section>

    <form class="v-filterbar" method="GET" action="{{ route('work-items.index') }}">
        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-state">وضعیت</label>
            <select id="f-state" name="state" class="v-select v-select--sm">
                <option value="open" @selected($filters['state'] === 'open')>باز</option>
                <option value="pending" @selected($filters['state'] === 'pending')>در انتظار</option>
                <option value="done" @selected($filters['state'] === 'done')>انجام‌شده</option>
                <option value="cancelled" @selected($filters['state'] === 'cancelled')>لغو شده</option>
                <option value="all" @selected($filters['state'] === 'all')>همه</option>
            </select>
        </div>

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
            <label class="v-filterbar__label" for="f-project">پروژه</label>
            <select id="f-project" name="project_id" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($filters['project_id'] === (string) $project->id)>
                        {{ $project->title }}
                    </option>
                @endforeach
            </select>
        </div>

        @if ($filters['overdue'] === '1')
            <input type="hidden" name="overdue" value="1">
        @endif

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($filters['kind'] !== '' || $filters['project_id'] !== '' || $filters['overdue'] === '1' || $filters['state'] !== 'open')
            <a href="{{ route('work-items.index') }}" class="v-btn v-btn--link v-btn--sm">پاک کردن</a>
        @endif
    </form>

    <div class="v-card">
        @if ($items->isEmpty())
            <x-empty-state
                title="موردی در کارتابل نیست"
                description="با ثبت مورد جدید، صورتحساب یا گزارش ماهانه‌ای برای پیگیری اضافه کنید."
                icon="check"
            />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">مورد</th>
                            <th scope="col">پروژه / مشتری</th>
                            <th scope="col">سررسید</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <a class="v-table__primary" href="{{ route('work-items.show', $item) }}">
                                        {{ $item->title }}
                                    </a>
                                    <div class="v-table__sub">{{ $kinds[$item->kind] ?? $item->kind }}</div>
                                </td>
                                <td>
                                    @if ($item->project)
                                        <a href="{{ route('projects.show', $item->project) }}">
                                            {{ $item->project->title }}
                                        </a>
                                        @if ($item->project->customer)
                                            <div class="v-table__sub">{{ $item->project->customer->display_name }}</div>
                                        @endif
                                    @else
                                        <span class="v-dl__value--empty">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-fa-date :value="$item->due_date" />
                                    @if ($item->isOverdue())
                                        <div class="v-table__sub" style="color:var(--v-danger)">سررسید گذشته</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->state === 'done')
                                        <span class="v-badge v-badge--success">انجام‌شده</span>
                                    @elseif ($item->state === 'cancelled')
                                        <span class="v-badge v-badge--neutral">لغو شده</span>
                                    @elseif ($item->isOverdue())
                                        <span class="v-badge v-badge--danger">عقب‌افتاده</span>
                                    @else
                                        <span class="v-badge v-badge--warn">در انتظار</span>
                                    @endif
                                </td>
                                <td class="v-table__actions">
                                    <a href="{{ route('work-items.show', $item) }}" class="v-btn v-btn--ghost v-btn--sm">
                                        جزئیات
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$items" />
        @endif
    </div>

    @if (auth()->user()->hasPermission('work_items.create'))
        <section class="v-card" id="new-work-item">
            <h2 class="v-card__title">ثبت مورد جدید</h2>

            <form method="POST" action="{{ route('work-items.store') }}" class="v-form">
                @csrf

                <div class="v-form-grid">
                    <div class="v-field">
                        <label for="new-project" class="v-field__label">پروژه</label>
                        <select id="new-project" name="project_id" class="v-select" required>
                            <option value="">— انتخاب پروژه —</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                                    {{ $project->title }}@if ($project->code) ({{ $project->code }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                    </div>

                    <div class="v-field">
                        <label for="new-kind" class="v-field__label">نوع</label>
                        <select id="new-kind" name="kind" class="v-select" required>
                            @foreach ($kinds as $value => $label)
                                <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="v-field">
                        <label for="new-due" class="v-field__label">سررسید (شمسی)</label>
                        <input id="new-due" type="text" name="due_date" class="v-input"
                               placeholder="۱۴۰۵/۰۹/۰۱" inputmode="numeric" required
                               value="{{ old('due_date') }}">
                        <p class="v-hint">مثال: ۱۴۰۵/۰۹/۰۱</p>
                        @error('due_date')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                    </div>

                    <div class="v-field">
                        <label for="new-period" class="v-field__label">دوره (اختیاری)</label>
                        <input id="new-period" type="text" name="period" class="v-input"
                               placeholder="۱۴۰۵/۰۹" value="{{ old('period') }}">
                    </div>
                </div>

                <div class="v-formactions">
                    <button type="submit" class="v-btn v-btn--primary">ثبت</button>
                </div>
            </form>
        </section>
    @endif
@endsection
