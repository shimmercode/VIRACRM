@extends('layouts.app')

@section('title', 'فیش حقوقی ' . ($payslip->employee?->displayName() ?? ''))

@section('content')
    @php
        use App\Support\Money;
        use App\Models\PayrollItem;

        $employee = $payslip->employee;
        $period = $payslip->period;
        $locked = $isLocked || ! $canManage;
    @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('payroll.index') }}">حقوق و دستمزد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('payroll.show', $period) }}">{{ $period?->title() }}</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $employee?->displayName() }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">فیش حقوقی {{ $employee?->displayName() }}</h1>
            <p class="v-pagehead__desc">
                دورهٔ {{ $period?->title() }}
                · حقوق پایه، مزایا و کسورات را دستی وارد کنید؛ جمع‌ها خودکار محاسبه می‌شوند.
            </p>
        </div>

        <div class="v-pagehead__actions">
            @if ($isLocked)
                <x-badge tone="success" :dot="true">دورهٔ منتشرشده</x-badge>
            @endif
        </div>
    </header>

    @if ($isLocked && $canManage)
        <div class="v-alert v-alert--warning" role="status">
            این دوره منتشر شده است. تغییر فقط در قالب «اصلاح» و با ذکر دلیل ممکن است؛
            نسخهٔ قبلی در تاریخچه نگه داشته می‌شود.
        </div>
    @endif

    <div class="v-detail-grid">
        <div class="v-detail-grid__main">
            <form method="POST" action="{{ route('payroll.payslip.update', $payslip) }}" novalidate data-guard>
                @csrf
                @method('PUT')
                @if ($isLocked)
                    <input type="hidden" name="amend" value="1">
                @endif

                <fieldset class="v-fsection" @if (! $canManage) disabled @endif>
                    <legend class="v-fsection__legend">اقلام اصلی ماه</legend>

                    <div class="v-grid v-grid--2">
                        <div class="v-field">
                            <label class="v-label" for="base_salary">
                                حقوق پایه (ریال) <span class="v-req" aria-hidden="true">*</span>
                            </label>
                            <input id="base_salary" name="base_salary" type="text" class="v-input"
                                   value="{{ old('base_salary', Money::toPersianDigits(number_format((int) $payslip->base_salary))) }}"
                                   inputmode="numeric" data-money required>
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="worked_days">روزهای کارکرد</label>
                            <input id="worked_days" name="worked_days" type="number" class="v-input"
                                   value="{{ old('worked_days', $payslip->worked_days) }}" min="0" max="31">
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="bonus">پاداش (ریال)</label>
                            <input id="bonus" name="bonus" type="text" class="v-input"
                                   value="{{ old('bonus', Money::toPersianDigits(number_format((int) $payslip->bonus))) }}"
                                   inputmode="numeric" data-money>
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="overtime">اضافه‌کاری (ریال)</label>
                            <input id="overtime" name="overtime" type="text" class="v-input"
                                   value="{{ old('overtime', Money::toPersianDigits(number_format((int) $payslip->overtime))) }}"
                                   inputmode="numeric" data-money>
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="advance">مساعدهٔ پرداخت‌شده (ریال)</label>
                            <input id="advance" name="advance" type="text" class="v-input"
                                   value="{{ old('advance', Money::toPersianDigits(number_format((int) $payslip->advance))) }}"
                                   inputmode="numeric" data-money>
                            <p class="v-hint">مساعده از خالص این ماه کسر می‌شود.</p>
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="deductions">سایر کسورات (ریال)</label>
                            <input id="deductions" name="deductions" type="text" class="v-input"
                                   value="{{ old('deductions', Money::toPersianDigits(number_format((int) $payslip->deductions))) }}"
                                   inputmode="numeric" data-money>
                        </div>
                    </div>

                    <div class="v-field">
                        <label class="v-label" for="input_notes">یادداشت محاسبه</label>
                        <textarea id="input_notes" name="input_notes" class="v-textarea" rows="2">{{ old('input_notes', $payslip->input_notes) }}</textarea>
                    </div>

                    @if ($isLocked)
                        <div class="v-field">
                            <label class="v-label" for="amend_reason">
                                دلیل اصلاح <span class="v-req" aria-hidden="true">*</span>
                            </label>
                            <input id="amend_reason" name="amend_reason" type="text" class="v-input"
                                   value="{{ old('amend_reason') }}" required maxlength="500"
                                   placeholder="مثلاً اصلاح روزهای کارکرد آبان">
                        </div>
                    @endif

                    @if ($canManage)
                        <div class="v-form__actions">
                            <button type="submit" class="v-btn v-btn--primary">
                                {{ $isLocked ? 'ثبت اصلاح و نسخهٔ جدید' : 'ذخیره و محاسبه' }}
                            </button>
                        </div>
                    @endif
                </fieldset>
            </form>

            {{-- ── Manual lines ───────────────────────────────────────── --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">اقلام دستی این ماه</h2>
                    <span class="v-card__meta">
                        {{ Money::toPersianDigits((string) $payslip->items->count()) }} قلم
                    </span>
                </header>

                @if ($payslip->items->isEmpty())
                    <x-empty-state title="قلم دستی ثبت نشده است"
                        description="مزایا و کسورات این ماه را می‌توانید به‌صورت قلم‌های مستقل با عنوان و اتصال اختیاری به پروژه ثبت کنید."
                        icon="banknote" />
                @else
                    <div class="v-tablewrap">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th scope="col">عنوان</th>
                                    <th scope="col">پروژه</th>
                                    <th scope="col">جهت</th>
                                    <th scope="col" class="v-table__num">مبلغ</th>
                                    <th scope="col" class="v-table__num">ضریب</th>
                                    @if ($canManage && ! $isLocked)
                                        <th scope="col" class="v-table__actions">حذف</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payslip->items as $item)
                                    <tr>
                                        <td data-label="عنوان">{{ $item->label ?: '—' }}</td>
                                        <td data-label="پروژه">
                                            @if ($item->project)
                                                <a href="{{ route('projects.show', $item->project) }}">
                                                    {{ $item->project->title }}
                                                </a>
                                            @else
                                                <span class="v-muted">—</span>
                                            @endif
                                        </td>
                                        <td data-label="جهت">
                                            <x-badge :tone="$item->isEarning() ? 'success' : 'danger'" :dot="true">
                                                {{ PayrollItem::DIRECTIONS[$item->direction] ?? $item->direction }}
                                            </x-badge>
                                        </td>
                                        <td data-label="مبلغ" class="v-table__num">{{ Money::fromRial((int) $item->amount)->formatPersian() }}</td>
                                        <td data-label="ضریب" class="v-table__num">
                                            {{ Money::toPersianDigits((string) $item->coefficient) }}٪
                                        </td>
                                        @if ($canManage && ! $isLocked)
                                            <td class="v-table__actions">
                                                <form method="POST" action="{{ route('payroll.items.destroy', $item) }}"
                                                      data-confirm="قلم «{{ $item->label }}» حذف شود؟">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-iconbtn v-iconbtn--danger"
                                                            aria-label="حذف قلم {{ $item->label }}">
                                                        <x-icon name="trash" :size="16" />
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($canManage && ! $isLocked)
                    <div class="v-card__body">
                        <form method="POST" action="{{ route('payroll.items.store', $payslip) }}" class="v-inlineform">
                            @csrf
                            <div class="v-field">
                                <label class="v-label" for="item-label">عنوان قلم</label>
                                <input id="item-label" name="label" type="text" class="v-input" required
                                       placeholder="حق مسکن، بن، قسط وام…" style="min-width: 180px">
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="item-direction">جهت</label>
                                <select id="item-direction" name="direction" class="v-select" required style="min-width: 150px">
                                    @foreach (PayrollItem::DIRECTIONS as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="item-amount">مبلغ (ریال)</label>
                                <input id="item-amount" name="amount" type="text" class="v-input" required
                                       inputmode="numeric" data-money style="min-width: 140px">
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="item-project">پروژه (اختیاری)</label>
                                <select id="item-project" name="project_id" class="v-select" style="min-width: 170px">
                                    <option value="">— بدون پروژه —</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}">{{ $project->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
                                <x-icon name="plus" :size="16" /> افزودن قلم
                            </button>
                        </form>
                        <p class="v-hint">
                            مبلغ همیشه مثبت است؛ «جهت» تعیین می‌کند به دریافتی اضافه شود یا از آن کسر.
                            اتصال به پروژه فقط برای گزارش است و در محاسبه ضرب نمی‌شود.
                        </p>
                    </div>
                @endif
            </section>

            {{-- ── Version history ─────────────────────────────────────── --}}
            @if ($payslip->versions->isNotEmpty())
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">تاریخچهٔ نسخه‌ها</h2>
                        <span class="v-card__meta">
                            {{ Money::toPersianDigits((string) $payslip->versions->count()) }} نسخهٔ قبلی
                        </span>
                    </header>
                    <div class="v-card__body">
                        <ul class="v-timeline">
                            @foreach ($payslip->versions as $version)
                                <li class="v-timeline__item">
                                    <span class="v-timeline__dot" aria-hidden="true"></span>
                                    <div class="v-timeline__body">
                                        <p class="v-timeline__title">
                                            نسخهٔ {{ Money::toPersianDigits((string) $version->version) }} — {{ $version->reason }}
                                        </p>
                                        <p class="v-timeline__meta">
                                            {{ $version->creator?->name ?? 'سامانه' }} ·
                                            <x-fa-date :value="$version->created_at" :with-time="true" />
                                            · خالص پیش از اصلاح:
                                            {{ Money::fromRial((int) ($version->snapshot['net_payable'] ?? 0)->formatPersian()) }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>

        {{-- ── Aside: the computed result ─────────────────────────────── --}}
        <aside class="v-detail-grid__aside">
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">نتیجهٔ محاسبه</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl">
                        <div><dt>حقوق پایه</dt>
                            <dd>{{ Money::fromRial((int) $payslip->base_salary)->formatPersian() }}</dd></div>
                        <div><dt>پاداش</dt>
                            <dd>{{ Money::fromRial((int) $payslip->bonus)->formatPersian() }}</dd></div>
                        <div><dt>اضافه‌کاری</dt>
                            <dd>{{ Money::fromRial((int) $payslip->overtime)->formatPersian() }}</dd></div>
                        <div><dt>اقلام دریافتی</dt>
                            <dd>
                                {{ Money::fromRial((int) $payslip->items->filter->isEarning()->sum('amount'))->formatPersian() }}
                            </dd></div>
                        
                        <div><dt><strong>جمع دریافتی</strong></dt>
                            <dd><strong>{{ Money::fromRial((int) $payslip->total_earnings)->formatPersian() }}</strong></dd></div>
                        <div><dt>سایر کسورات</dt>
                            <dd>{{ Money::fromRial((int) $payslip->deductions)->formatPersian() }}</dd></div>
                        <div><dt>مساعده</dt>
                            <dd>{{ Money::fromRial((int) $payslip->advance)->formatPersian() }}</dd></div>
                        <div><dt>اقلام کسورات</dt>
                            <dd>
                                {{ Money::fromRial((int) $payslip->items->reject->isEarning()->sum('amount'))->formatPersian() }}
                            </dd></div>
                        <div><dt><strong>جمع کسورات</strong></dt>
                            <dd><strong>{{ Money::fromRial((int) $payslip->total_deductions)->formatPersian() }}</strong></dd></div>
                    </dl>

                    <div class="v-netbox">
                        <span class="v-netbox__label">خالص پرداختی</span>
                        <span class="v-netbox__value">{{ Money::fromRial((int) $payslip->net_payable)->formatPersian() }}</span>
                    </div>

                    @if ($payslip->computed_at)
                        <p class="v-hint">
                            آخرین محاسبه:
                            <x-fa-date :value="$payslip->computed_at" :with-time="true" />
                            @if ($payslip->computedBy)
                                توسط {{ $payslip->computedBy->name }}
                            @endif
                        </p>
                    @else
                        <p class="v-hint">این فیش هنوز محاسبه نشده است.</p>
                    @endif
                </div>
            </section>

            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">اطلاعات کارمند</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl">
                        <div><dt>کد پرسنلی</dt>
                            <dd>{{ $employee?->code ? Money::toPersianDigits($employee->code) : '—' }}</dd></div>
                        <div><dt>سِمت</dt><dd>{{ $employee?->position ?: '—' }}</dd></div>
                        <div><dt>پروژه‌های مرتبط این ماه</dt>
                            <dd>
                                @php $linked = $payslip->items->pluck('project')->filter()->unique('id'); @endphp
                                @if ($linked->isEmpty())
                                    <span class="v-muted">—</span>
                                @else
                                    {{ $linked->pluck('title')->join('، ') }}
                                @endif
                            </dd></div>
                    </dl>
                    @if ($employee)
                        <a href="{{ route('employees.show', $employee) }}" class="v-btn v-btn--ghost v-btn--sm">
                            پروندهٔ پرسنلی
                        </a>
                    @endif
                </div>
            </section>
        </aside>
    </div>
@endsection
