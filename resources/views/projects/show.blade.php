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

    {{-- §6/§14 — the finalisation gate, explained where the user can act on it.
         A refusal on save with no prior signal is how users end up retrying the
         same submit three times. --}}
    @if (!empty($missingAccesses))
        <x-alert type="warning">
            <strong>پروژه سئو هنوز قابل فعال‌سازی نیست.</strong>
            دسترسی‌های اجباری زیر ثبت نشده‌اند:
            <ul class="v-alert__list">
                @foreach ($missingAccesses as $accessType)
                    <li>{{ $accessType->title }}</li>
                @endforeach
            </ul>
            @if (auth()->user()->hasPermission('credentials.manage'))
                <a href="{{ route('credentials.create', $project) }}" class="v-btn v-btn--ghost v-btn--sm">
                    <x-icon name="key" :size="14" /> ثبت دسترسی
                </a>
            @endif
        </x-alert>
    @endif

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
                                <dt>آدرس وب‌سایت</dt>
                                {{-- §5-1. Its own column on seo_project_details, not
                                     design_project_details.domain. --}}
                                <dd dir="ltr">
                                    @if ($project->seoDetails?->website_url)
                                        <a href="{{ $project->seoDetails->website_url }}"
                                           target="_blank" rel="noopener noreferrer nofollow">{{ $project->seoDetails->website_url }}</a>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>کلمات کلیدی قراردادی</dt>
                                <dd>{{ Money::toPersianDigits((string) ($project->seoDetails?->keyword_count ?? 0)) }} کلمه</dd>
                            </div>
                            <div>
                                <dt>کلمات ثبت‌شده در سامانه</dt>
                                <dd>{{ Money::toPersianDigits((string) $project->keywords->count()) }} کلمه</dd>
                            </div>
                            @if ($project->seoDetails?->financial_notes)
                                <div>
                                    <dt>توضیحات مالی</dt>
                                    <dd>{{ $project->seoDetails->financial_notes }}</dd>
                                </div>
                            @endif
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
                {{-- REQ-05.03: the count is computed from real rows, never typed.
                     The package limit is checked against those rows, so a
                     manually entered number would enforce nothing. --}}
                <section class="v-card" id="keywords">
                    <header class="v-card__head">
                        <h2 class="v-card__title">کلمات کلیدی</h2>
                        <span class="v-card__meta">
                            {{ Money::toPersianDigits((string) $project->keywords->count()) }} کلمه
                            @if (($limit = (int) ($project->seoDetails?->package?->keyword_limit ?? 0)) > 0)
                                از {{ Money::toPersianDigits((string) $limit) }}
                            @endif
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
                                        @if ($canAssign)<th scope="col">تغییر وضعیت</th><th scope="col"></th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($project->keywords as $keyword)
                                        <tr>
                                            <td class="v-table__primary">
                                                {{ $keyword->keyword }}
                                                @if ($keyword->notes)
                                                    <span class="v-table__sub">{{ $keyword->notes }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <x-badge :tone="match ($keyword->status) {
                                                    'working' => 'info',
                                                    'done' => 'success',
                                                    'removed' => 'muted',
                                                    default => 'neutral',
                                                }">{{ $keyword->statusLabel() }}</x-badge>
                                            </td>
                                            @if ($canAssign)
                                                <td class="v-table__num">
                                                    <form method="POST" action="{{ route('projects.keywords.update', [$project, $keyword]) }}" class="v-inlineform">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="keyword" value="{{ $keyword->keyword }}">
                                                        <input type="hidden" name="notes" value="{{ $keyword->notes }}">
                                                        <select name="status" class="v-select v-select--sm"
                                                                aria-label="وضعیت {{ $keyword->keyword }}" onchange="this.form.submit()">
                                                            @foreach (\App\Models\ProjectKeyword::STATUSES as $value => $label)
                                                                <option value="{{ $value }}" @selected($keyword->status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                </td>
                                                <td class="v-table__num">
                                                    <form method="POST" action="{{ route('projects.keywords.destroy', [$project, $keyword]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="v-btn v-btn--ghost v-btn--sm v-btn--danger">حذف</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                
                    @if ($canAssign)
                        <div class="v-card__body">
                            <form method="POST" action="{{ route('projects.keywords.store', $project) }}" class="v-form">
                                @csrf
                                <div class="v-grid v-grid--2">
                                    <div class="v-field">
                                        <label class="v-label" for="keyword_text">کلمهٔ کلیدی</label>
                                        <input id="keyword_text" name="keyword" type="text" class="v-input{{ $errors->has('keyword') ? ' is-invalid' : '' }}"
                                               maxlength="191" required placeholder="طراحی سایت">
                                        @error('keyword')<p class="v-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="v-field">
                                        <label class="v-label" for="keyword_status">وضعیت</label>
                                        <select id="keyword_status" name="status" class="v-select{{ $errors->has('status') ? ' is-invalid' : '' }}">
                                            @foreach (\App\Models\ProjectKeyword::STATUSES as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        {{-- A select cannot produce this through the UI, but a stale
                                             tab or a hand-edited request can, and a refusal with
                                             nowhere to appear is a dead end for the user. --}}
                                        @error('status')<p class="v-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="v-field v-field--span2">
                                        <label class="v-label" for="keyword_notes">یادداشت</label>
                                        <input id="keyword_notes" name="notes" type="text" class="v-input" maxlength="2000">
                                    </div>
                                </div>
                                <div class="v-formactions">
                                    <button type="submit" class="v-btn v-btn--primary">افزودن کلمهٔ کلیدی</button>
                                </div>
                            </form>
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

            {{-- ── §5-3 / REQ-05.05 — who is on this project ─────────────
                 Attach an expert here, or the workload report at
                 /reports/expert-workload describes a table the product could
                 never fill. Every action is audited, because this is how a
                 person's fee reaches the payroll input.
            --}}
            <section class="v-card" id="assignments">
                <header class="v-card__head">
                    <h2 class="v-card__title">کارشناسان پروژه</h2>
                </header>
                <div class="v-card__body">
                    @if ($project->assignments->isEmpty())
                        <x-empty-state
                            title="هنوز کارشناسی تخصیص نیافته"
                            description="با افزودن کارشناس، تعداد پروژه‌های فعال او در گزارش بار کاری به‌روز می‌شود."
                            icon="users"
                        />
                    @else
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th>کارشناس</th>
                                    <th>نقش</th>
                                    @if ($canSeeAmounts)<th>ضریب</th><th>مبلغ مؤثر</th>@endif
                                    <th>بازه</th>
                                    <th>وضعیت</th>
                                    @if ($canAssign)<th></th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($project->assignments as $assignment)
                                    <tr>
                                        <td class="v-table__primary">
                                            {{ trim($assignment->employee?->first_name . ' ' . $assignment->employee?->last_name) ?: '—' }}
                                            <span class="v-table__sub">{{ $assignment->employee?->position }}</span>
                                        </td>
                                        <td>{{ $assignment->role ?? '—' }}</td>
                                        @if ($canSeeAmounts)
                                            <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $assignment->coefficient) }}٪</td>
                                            {{-- formatPersian, not toPersianDigits: the Arabic
                                                 thousands separator keeps the number one visual run
                                                 under the bidi algorithm, which an ASCII comma breaks. --}}
                                            <td class="v-table__num v-money" dir="ltr">{{ \App\Support\Money::fromRial((int) round($assignment->amount * $assignment->coefficient / 100))->formatPersian() }}</td>
                                        @endif
                                        <td>
                                            @if ($assignment->started_on || $assignment->ended_on)
                                                <x-fa-date :value="$assignment->started_on" />
                                                —
                                                <x-fa-date :value="$assignment->ended_on" />
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <span class="v-badge {{ $assignment->is_active ? 'v-badge--success' : 'v-badge--neutral' }}">
                                                {{ $assignment->is_active ? 'فعال' : 'غیرفعال' }}
                                            </span>
                                        </td>
                                        @if ($canAssign)
                                            <td class="v-table__num">
                                                <form method="POST" action="{{ route('projects.assignments.destroy', [$project, $assignment]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-btn v-btn--ghost v-btn--sm v-btn--danger">حذف</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($canAssign)
                        {{-- Only unassigned, employable staff are offered; the
                             unique index would reject a duplicate anyway, and
                             this way the reason is a shorter list, not an error. --}}
                        @php($remaining = $assignableEmployees->reject(fn ($e) => in_array($e->id, $assignedEmployeeIds, true)))
                        <form method="POST" action="{{ route('projects.assignments.store', $project) }}" class="v-form">
                            @csrf
                            <div class="v-grid v-grid--2">
                                <div class="v-field">
                                    <label class="v-label" for="employee_id">کارشناس</label>
                                    <select id="employee_id" name="employee_id" class="v-select{{ $errors->has('employee_id') ? ' is-invalid' : '' }}" required>
                                        <option value="">انتخاب کنید…</option>
                                        @foreach ($remaining as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}{{ $employee->position ? ' — ' . $employee->position : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')<p class="v-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="v-field">
                                    <label class="v-label" for="assignment_role">نقش در پروژه</label>
                                    <input id="assignment_role" name="role" type="text" class="v-input" maxlength="32" placeholder="کارشناس سئو">
                                </div>
                                @if ($canSeeAmounts)
                                    <div class="v-field">
                                        <label class="v-label" for="assignment_amount">مبلغ توافق‌شده (ریال)</label>
                                        <input id="assignment_amount" name="amount" type="text" class="v-input" inputmode="numeric" placeholder="۰">
                                        @error('amount')<p class="v-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="v-field">
                                        <label class="v-label" for="assignment_coefficient">ضریب (٪)</label>
                                        <input id="assignment_coefficient" name="coefficient" type="text" class="v-input" value="۱۰۰" inputmode="numeric">
                                        @error('coefficient')<p class="v-error">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                                <div class="v-field">
                                    <label class="v-label" for="assignment_started">تاریخ شروع</label>
                                    <input id="assignment_started" name="started_on" type="text" class="v-input" data-jalali inputmode="numeric" placeholder="۱۴۰۴/۰۱/۱۵">
                                    @error('started_on')<p class="v-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="v-field">
                                    <label class="v-label" for="assignment_ended">تاریخ پایان</label>
                                    <input id="assignment_ended" name="ended_on" type="text" class="v-input" data-jalali inputmode="numeric" placeholder="۱۴۰۴/۱۲/۲۹">
                                    @error('ended_on')<p class="v-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="v-formactions">
                                <button type="submit" class="v-btn v-btn--primary">افزودن کارشناس</button>
                            </div>
                        </form>
                    @endif
                </div>
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
