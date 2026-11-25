@extends('layouts.app')

@section('title', 'فاکتور جدید')

@section('content')
    @php $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v); @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('invoices.index') }}">فاکتورها</a>
        <x-icon name="chevron" :size="12" class="rtl-mirror" />
        <span aria-current="page">فاکتور جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">فاکتور جدید</h1>
            <p class="v-pagehead__desc">
                شمارهٔ فاکتور در زمان صدور تعیین می‌شود، نه حالا. تا آن لحظه می‌توانید ردیف‌ها را ویرایش کنید.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('invoices.store') }}" class="v-form" id="invoice-form">
        @csrf

        <section class="v-card">
            <h2 class="v-card__title">مشخصات</h2>

            <div class="v-form-grid">
                <div class="v-field">
                    <label for="customer_id" class="v-field__label">مشتری</label>
                    <select id="customer_id" name="customer_id" class="v-select" required>
                        <option value="">— انتخاب مشتری —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}"
                                @selected((string) old('customer_id', $preset['customer_id']) === (string) $customer->id)>
                                {{ $customer->display_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')<p class="v-hint" style="color:var(--vira-danger)">{{ $message }}</p>@enderror
                </div>

                <div class="v-field">
                    <label for="service_type" class="v-field__label">نوع خدمت</label>
                    <select id="service_type" name="service_type" class="v-select" required>
                        @foreach ($services as $key => $label)
                            <option value="{{ $key }}" @selected(old('service_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="v-hint">درآمد به این حساب منظور می‌شود.</p>
                </div>

                <div class="v-field">
                    <label for="contract_id" class="v-field__label">قرارداد (اختیاری)</label>
                    <select id="contract_id" name="contract_id" class="v-select">
                        <option value="">— بدون قرارداد —</option>
                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}" @selected((string) old('contract_id') === (string) $contract->id)>
                                {{ $contract->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="v-field">
                    <label for="project_id" class="v-field__label">پروژه (اختیاری)</label>
                    <select id="project_id" name="project_id" class="v-select">
                        <option value="">— بدون پروژه —</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}"
                                @selected((string) old('project_id', $preset['project_id']) === (string) $project->id)>
                                {{ $project->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="v-field">
                    <label for="issue_date" class="v-field__label">تاریخ صدور (شمسی)</label>
                    <input id="issue_date" type="text" name="issue_date" class="v-input" inputmode="numeric"
                           value="{{ old('issue_date') }}" placeholder="۱۴۰۵/۰۹/۰۱" required>
                    @error('issue_date')<p class="v-hint" style="color:var(--vira-danger)">{{ $message }}</p>@enderror
                </div>

                <div class="v-field">
                    <label for="due_date" class="v-field__label">سررسید (شمسی)</label>
                    <input id="due_date" type="text" name="due_date" class="v-input" inputmode="numeric"
                           value="{{ old('due_date') }}" placeholder="۱۴۰۵/۰۹/۳۰" required>
                    @error('due_date')<p class="v-hint" style="color:var(--vira-danger)">{{ $message }}</p>@enderror
                </div>

                <div class="v-field">
                    <label for="title" class="v-field__label">عنوان (اختیاری)</label>
                    <input id="title" type="text" name="title" class="v-input" value="{{ old('title') }}" maxlength="190">
                </div>

                <div class="v-field">
                    <label for="tax_rate_bp" class="v-field__label">نرخ مالیات بر ارزش افزوده</label>
                    <select id="tax_rate_bp" name="tax_rate_bp" class="v-select">
                        @foreach ([0 => 'بدون مالیات', 600 => '۶٪', 900 => '۹٪', 1000 => '۱۰٪'] as $bp => $label)
                            <option value="{{ $bp }}" @selected((int) old('tax_rate_bp', $defaultTaxBp) === $bp)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="v-hint">نرخ در تنظیمات قابل تغییر است و روی فاکتورهای قبلی اثر ندارد.</p>
                </div>
            </div>
        </section>

        <section class="v-card">
            <h2 class="v-card__title">ردیف‌ها</h2>

            @error('items')<div class="v-notice v-notice--danger">{{ $message }}</div>@enderror

            <div class="v-tablewrap">
                <table class="v-table" id="items-table">
                    <thead>
                        <tr>
                            <th scope="col" style="min-width:220px">شرح</th>
                            <th scope="col" style="width:100px">تعداد</th>
                            <th scope="col" style="width:160px">مبلغ واحد (ریال)</th>
                            <th scope="col" style="width:160px">جمع ردیف</th>
                            <th scope="col" class="v-table__actions" style="width:70px"> </th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        {{-- One starting row. Rows are cloned by app.js; the template
                             lives here so there is exactly one definition of a row. --}}
                        <tr class="item-row">
                            <td><input type="text" name="items[0][description]" class="v-input" required maxlength="500" value="{{ old('items.0.description') }}"></td>
                            <td><input type="text" name="items[0][quantity]" class="v-input" inputmode="decimal" value="{{ old('items.0.quantity', '1') }}" required></td>
                            <td><input type="text" name="items[0][unit_price]" class="v-input" inputmode="numeric" value="{{ old('items.0.unit_price') }}" required placeholder="۰"></td>
                            <td class="v-table__num"><span class="v-money line-total">—</span></td>
                            <td class="v-table__actions">
                                <button type="button" class="v-btn v-btn--ghost v-btn--sm" data-remove-row aria-label="حذف ردیف">
                                    <x-icon name="trash" :size="14" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="v-formactions" style="justify-content:flex-start">
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" data-add-row>
                    <x-icon name="plus" :size="14" /> افزودن ردیف
                </button>
            </div>

            <div class="v-form-grid" style="margin-block-start:var(--vira-space-5)">
                <div class="v-field">
                    <label for="discount" class="v-field__label">تخفیف (ریال)</label>
                    <input id="discount" type="text" name="discount" class="v-input" inputmode="numeric"
                           value="{{ old('discount', '0') }}">
                    <p class="v-hint">تخفیف از درآمد کسر می‌شود، نه اینکه هزینه ثبت شود.</p>
                </div>
            </div>
        </section>

        <div class="v-card">
            <div class="v-formactions" style="justify-content:flex-end">
                <a href="{{ route('invoices.index') }}" class="v-btn v-btn--ghost">انصراف</a>
                <button type="submit" name="issue_now" value="0" class="v-btn v-btn--ghost">ذخیره پیش‌نویس</button>
                <button type="submit" name="issue_now" value="1" class="v-btn v-btn--primary">
                    <x-icon name="check" :size="16" /> ذخیره و صدور
                </button>
            </div>
        </div>
    </form>
@endsection
