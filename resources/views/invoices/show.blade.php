@extends('layouts.app')

@section('title', 'فاکتور ' . $invoice->displayName())

@section('content')
    @php
        $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian();
        $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v);
    @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('invoices.index') }}">فاکتورها</a>
        <x-icon name="chevron" :size="12" class="rtl-mirror" />
        <span aria-current="page">{{ $invoice->displayName() }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $invoice->displayName() }}</h1>
            <p class="v-pagehead__desc">
                {{ $invoice->customer?->display_name ?? '—' }}
                @if ($invoice->project) · {{ $invoice->project->title }} @endif
            </p>
        </div>

        <div class="v-pagehead__actions">
            <span class="v-badge v-badge--{{ $invoice->status === 'issued' ? 'success' : ($invoice->status === 'void' ? 'danger' : 'neutral') }}">
                {{ $statuses[$invoice->status] ?? $invoice->status }}
            </span>
            <span class="v-badge v-badge--{{ $invoice->payment_state === 'paid' ? 'success' : ($invoice->payment_state === 'partial' ? 'warn' : 'neutral') }}">
                {{ $paymentStates[$invoice->payment_state] ?? $invoice->payment_state }}
            </span>
        </div>
    </header>

    @if ($invoice->status === 'void')
        <div class="v-notice v-notice--danger">
            <div class="v-notice__title">این فاکتور ابطال شده است</div>
            {{ $invoice->void_reason }}
        </div>
    @endif

    <div class="v-grid v-grid--2">
        <section class="v-card">
            <h2 class="v-card__title">مشخصات</h2>
            <dl class="v-dl v-dl--2">
                <div><dt>تاریخ صدور</dt><dd><x-fa-date :value="$invoice->issue_date" /></dd></div>
                <div><dt>سررسید</dt><dd><x-fa-date :value="$invoice->due_date" /></dd></div>
                <div><dt>نوع خدمت</dt><dd>{{ \App\Support\ServiceType::label($invoice->service_type) }}</dd></div>
                <div><dt>نرخ مالیات</dt><dd>{{ $pd($invoice->tax_rate_bp / 100) }}٪</dd></div>
                @if ($invoice->contract)
                    <div><dt>قرارداد</dt><dd>{{ $invoice->contract->title }}</dd></div>
                @endif
                @if ($invoice->journalEntry)
                    <div>
                        <dt>سند حسابداری</dt>
                        <dd>
                            <a href="{{ route('accounting.journal.show', $invoice->journalEntry) }}">
                                {{ $invoice->journalEntry->number }}
                            </a>
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="v-card">
            <h2 class="v-card__title">مبالغ</h2>
            <dl class="v-dl">
                <div><dt>جمع ردیف‌ها</dt><dd><span class="v-money">{{ $money($invoice->subtotal) }}</span></dd></div>
                <div><dt>تخفیف</dt><dd><span class="v-money">{{ $money($invoice->discount) }}</span></dd></div>
                <div><dt>مالیات بر ارزش افزوده</dt><dd><span class="v-money">{{ $money($invoice->tax_amount) }}</span></dd></div>
                <div><dt><strong>مبلغ نهایی</strong></dt><dd><span class="v-money v-money--total">{{ $money($invoice->total) }}</span></dd></div>
                <div><dt>وصول‌شده</dt><dd><span class="v-money">{{ $money($invoice->paid_amount) }}</span></dd></div>
                <div><dt>مانده</dt><dd><span class="v-money v-money--total">{{ $money($invoice->remaining()) }}</span></dd></div>
            </dl>
        </section>
    </div>

    <section class="v-card">
        <h2 class="v-card__title">ردیف‌ها</h2>
        <div class="v-tablewrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">شرح</th>
                        <th scope="col" class="v-table__num">تعداد</th>
                        <th scope="col" class="v-table__num">مبلغ واحد</th>
                        <th scope="col" class="v-table__num">جمع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>{{ $pd($item->line_no) }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="v-table__num">{{ $item->quantityLabel() }}</td>
                            <td class="v-table__num"><span class="v-money">{{ $money($item->unit_price) }}</span></td>
                            <td class="v-table__num"><span class="v-money">{{ $money($item->line_total) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- ── Actions ────────────────────────────────────────────────────── --}}
    @if ($invoice->status === 'draft' && auth()->user()->hasPermission('invoices.issue'))
        <section class="v-card">
            <h2 class="v-card__title">صدور فاکتور</h2>
            <p class="v-hint">
                با صدور، شمارهٔ فاکتور تعیین می‌شود، مبالغ قفل می‌شوند و سند درآمد در دفتر ثبت می‌گردد.
                پس از صدور ویرایش ممکن نیست؛ فقط دریافت وجه یا ابطال.
            </p>
            <form method="POST" action="{{ route('invoices.issue', $invoice) }}" class="v-formactions">
                @csrf
                <button type="submit" class="v-btn v-btn--primary">
                    <x-icon name="check" :size="16" /> صدور فاکتور
                </button>
            </form>
        </section>
    @endif

    @if ($invoice->isIssued() && $invoice->remaining() > 0 && auth()->user()->hasPermission('invoices.record_payment'))
        <section class="v-card">
            <h2 class="v-card__title">ثبت دریافت</h2>

            <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="v-form">
                @csrf
                <div class="v-form-grid">
                    <div class="v-field">
                        <label for="amount" class="v-label">مبلغ (ریال)</label>
                        <input id="amount" type="text" name="amount" class="v-input" inputmode="numeric"
                               value="{{ old('amount', number_format($invoice->remaining())) }}" required>
                        @error('amount')<p class="v-hint" style="color:var(--vira-danger)">{{ $message }}</p>@enderror
                    </div>
                    <div class="v-field">
                        <label for="paid_on" class="v-label">تاریخ دریافت (شمسی)</label>
                        <input id="paid_on" type="text" name="paid_on" class="v-input" inputmode="numeric"
                               value="{{ old('paid_on') }}" placeholder="۱۴۰۵/۰۹/۱۰" required>
                        @error('paid_on')<p class="v-hint" style="color:var(--vira-danger)">{{ $message }}</p>@enderror
                    </div>
                    <div class="v-field">
                        <label for="method" class="v-label">روش</label>
                        <select id="method" name="method" class="v-select" required>
                            @foreach (\App\Models\Payment::METHODS as $key => $label)
                                <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="v-field">
                        <label for="reference" class="v-label">شماره پیگیری</label>
                        <input id="reference" type="text" name="reference" class="v-input" value="{{ old('reference') }}" dir="ltr">
                    </div>
                    <div class="v-field">
                        <label for="bank_account" class="v-label">حساب بانکی</label>
                        <input id="bank_account" type="text" name="bank_account" class="v-input" value="{{ old('bank_account') }}">
                    </div>
                </div>
                <div class="v-formactions">
                    <button type="submit" class="v-btn v-btn--primary">ثبت دریافت</button>
                </div>
            </form>
        </section>
    @endif

    @if ($invoice->isIssued() && $invoice->paid_amount === 0 && auth()->user()->hasPermission('invoices.void'))
        <section class="v-card">
            <h2 class="v-card__title">ابطال فاکتور</h2>
            <p class="v-hint">ابطال، سند برگشتی در دفتر ثبت می‌کند؛ سند اصلی حذف نمی‌شود.</p>
            <form method="POST" action="{{ route('accounting.invoices.void', $invoice) }}" class="v-form"
                  onsubmit="return confirm('فاکتور ابطال شود؟');">
                @csrf
                <div class="v-field">
                    <label for="void_reason" class="v-label">دلیل ابطال</label>
                    <input id="void_reason" type="text" name="void_reason" class="v-input" required maxlength="500">
                </div>
                <div class="v-formactions">
                    <button type="submit" class="v-btn v-btn--danger">ثبت ابطال</button>
                </div>
            </form>
        </section>
    @endif

    @if ($invoice->allocations->isNotEmpty())
        <section class="v-card">
            <h2 class="v-card__title">دریافت‌های این فاکتور</h2>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">شماره پرداخت</th>
                            <th scope="col">تاریخ</th>
                            <th scope="col">روش</th>
                            <th scope="col" class="v-table__num">مبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->allocations as $allocation)
                            <tr>
                                <td><span class="v-account-code">{{ $allocation->payment->number }}</span></td>
                                <td><x-fa-date :value="$allocation->payment->paid_on" /></td>
                                <td>{{ \App\Models\Payment::METHODS[$allocation->payment->method] ?? $allocation->payment->method }}</td>
                                <td class="v-table__num"><span class="v-money">{{ $money($allocation->amount) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
