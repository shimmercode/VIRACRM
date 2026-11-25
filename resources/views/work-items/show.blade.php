@extends('layouts.app')

@section('title', $item->title)

@section('content')
    @php
        use App\Support\Money;
        $pd = fn ($v) => Money::toPersianDigits((string) $v);
        $tone = $item->state === 'done' ? 'success'
            : ($item->state === 'cancelled' ? 'neutral'
            : ($item->isOverdue() ? 'danger' : 'warn'));
    @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('work-items.index') }}">کارتابل</a>
        <x-icon name="chevron" :size="12" class="rtl-mirror" />
        <span aria-current="page">{{ $item->title }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $item->title }}</h1>
            <p class="v-pagehead__desc">
                @if ($item->project)
                    پروژه
                    <a href="{{ route('projects.show', $item->project) }}">{{ $item->project->title }}</a>
                    @if ($item->project->customer)
                        · {{ $item->project->customer->display_name }}
                    @endif
                @else
                    بدون پروژه
                @endif
            </p>
        </div>

        <div class="v-pagehead__actions">
            <span class="v-badge v-badge--{{ $tone }}">
                {{ \App\Models\WorkItem::STATES[$item->state] ?? $item->state }}
            </span>
        </div>
    </header>

    <div class="v-grid v-grid--2">
        {{-- ── Facts ──────────────────────────────────────────────────── --}}
        <section class="v-card">
            <h2 class="v-card__title">مشخصات</h2>

            <dl class="v-dl v-dl--2">
                <div>
                    <dt>نوع</dt>
                    <dd>{{ \App\Models\WorkItem::KINDS[$item->kind] ?? $item->kind }}</dd>
                </div>
                <div>
                    <dt>دوره</dt>
                    <dd>{{ $item->period ?: '—' }}</dd>
                </div>
                <div>
                    <dt>سررسید</dt>
                    <dd><x-fa-date :value="$item->due_date" /></dd>
                </div>
                <div>
                    <dt>یادآوری</dt>
                    <dd>{{ $pd($item->reminder_offset_days) }} روز قبل</dd>
                </div>

                @if ($item->done_at)
                    <div>
                        <dt>بسته‌شده در</dt>
                        <dd><x-fa-date :value="$item->done_at" :with-time="true" /></dd>
                    </div>
                    <div>
                        <dt>توسط</dt>
                        <dd>{{ $item->completedBy?->name ?? '—' }}</dd>
                    </div>
                @endif

                @if ($item->invoice_number)
                    <div>
                        <dt>شماره صورتحساب</dt>
                        <dd dir="ltr">{{ $item->invoice_number }}</dd>
                    </div>
                @endif

                @if ($item->invoice_amount !== null)
                    <div>
                        <dt>مبلغ صورتحساب</dt>
                        <dd class="v-dl__money">{{ Money::fromRial($item->invoice_amount)->formatPersian() }} ریال</dd>
                    </div>
                @endif

                @if ($item->invoice_issue_date)
                    <div>
                        <dt>تاریخ صدور</dt>
                        <dd><x-fa-date :value="$item->invoice_issue_date" /></dd>
                    </div>
                @endif
            </dl>

            @if ($item->completion_notes)
                <p class="v-hint"><strong>یادداشت:</strong> {{ $item->completion_notes }}</p>
            @endif
        </section>

        {{-- ── Actions ────────────────────────────────────────────────── --}}
        <section class="v-card">
            @if ($item->done_at !== null)
                <h2 class="v-card__title">این مورد بسته شده است</h2>
                <p class="v-hint">
                    بستن ثبت شده و در گزارش رویدادها قابل بازبینی است. برای اصلاح اشتباه می‌توانید آن را باز کنید.
                </p>

                @if (auth()->user()->hasPermission('work_items.complete'))
                    <form method="POST" action="{{ route('work-items.reopen', $item) }}" class="v-formactions">
                        @csrf
                        <button type="submit" class="v-btn v-btn--ghost">بازکردن مورد</button>
                    </form>
                @endif
            @elseif ($item->state === 'cancelled')
                <h2 class="v-card__title">این مورد لغو شده است</h2>
                @if ($item->completion_notes)
                    <p class="v-hint">دلیل: {{ $item->completion_notes }}</p>
                @endif
            @else
                @if (auth()->user()->hasPermission('work_items.complete'))
                    <h2 class="v-card__title">بستن مورد</h2>

                    <form method="POST" action="{{ route('work-items.complete', $item) }}" class="v-form">
                        @csrf

                        @if ($item->kind === 'invoice')
                            {{-- §5: once the invoice is issued, its number, amount
                                 and date are recorded against the item. Required
                                 here because these are the only figures the
                                 accounting module will ever see for this period. --}}
                            <div class="v-field">
                                <label for="invoice_number" class="v-field__label">شماره صورتحساب</label>
                                <input id="invoice_number" type="text" name="invoice_number"
                                       class="v-input @error('invoice_number') is-invalid @enderror"
                                       value="{{ old('invoice_number') }}" required dir="ltr">
                                @error('invoice_number')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                            </div>

                            <div class="v-field">
                                <label for="invoice_amount" class="v-field__label">مبلغ (ریال)</label>
                                <input id="invoice_amount" type="text" name="invoice_amount"
                                       class="v-input @error('invoice_amount') is-invalid @enderror"
                                       value="{{ old('invoice_amount') }}" inputmode="numeric"
                                       placeholder="۱۲٬۵۰۰٬۰۰۰" required>
                                @error('invoice_amount')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                                <p class="v-hint">مبلغ را همان‌طور که در صورتحساب است وارد کنید.</p>
                            </div>

                            <div class="v-field">
                                <label for="invoice_issue_date" class="v-field__label">تاریخ صدور (شمسی)</label>
                                <input id="invoice_issue_date" type="text" name="invoice_issue_date"
                                       class="v-input @error('invoice_issue_date') is-invalid @enderror"
                                       value="{{ old('invoice_issue_date') }}" placeholder="۱۴۰۵/۰۸/۲۵">
                                @error('invoice_issue_date')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div class="v-field">
                            <label for="completion_notes" class="v-field__label">
                                یادداشت @if ($item->kind !== 'invoice') (اختیاری) @endif
                            </label>
                            <textarea id="completion_notes" name="completion_notes" class="v-input" rows="3"
                                      placeholder="توضیح کوتاه درباره نتیجه">{{ old('completion_notes') }}</textarea>
                            @error('completion_notes')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                        </div>

                        <div class="v-formactions">
                            <button type="submit" class="v-btn v-btn--primary">
                                <x-icon name="check" :size="16" /> ثبت به‌عنوان انجام‌شده
                            </button>
                        </div>
                    </form>
                @else
                    <h2 class="v-card__title">دسترسی بستن مورد را ندارید</h2>
                    <p class="v-hint">برای ثبت نتیجه با مدیر سیستم تماس بگیرید.</p>
                @endif

                @if (auth()->user()->hasPermission('work_items.cancel'))
                    <details>
                        <summary class="v-btn v-btn--ghost v-btn--sm">لغو این مورد</summary>
                        <form method="POST" action="{{ route('work-items.cancel', $item) }}" class="v-form">
                            @csrf
                            <div class="v-field">
                                <label for="cancel_notes" class="v-field__label">دلیل لغو</label>
                                <textarea id="cancel_notes" name="completion_notes" class="v-input" rows="2" required></textarea>
                                @error('completion_notes')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
                            </div>
                            <div class="v-formactions">
                                <button type="submit" class="v-btn v-btn--danger v-btn--sm">ثبت لغو</button>
                            </div>
                        </form>
                    </details>
                @endif
            @endif
        </section>
    </div>
@endsection
