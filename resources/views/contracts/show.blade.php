@extends('layouts.app')

@section('title', $contract->title)

@section('content')
    @php
        use App\Support\JalaliDate;
        use App\Support\Money;

        $pd = fn ($v) => Money::toPersianDigits((string) $v);
        $faDate = function ($date): string {
            if ($date === null) {
                return '—';
            }
            return JalaliDate::fromCarbon(\Carbon\CarbonImmutable::parse($date))->formatPersian();
        };

        $canEdit = auth()->user()->hasPermission('contracts.update');
        $canDelete = auth()->user()->hasPermission('contracts.delete');
        $displayStatus = $contract->displayStatus();
    @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('contracts.index') }}">قراردادها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $contract->title }}</span>
    </nav>

    <header class="v-pagehead v-pagehead--detail">
        <div class="v-pagehead__text">
            <div class="v-pagehead__id">
                <span class="v-docicon" aria-hidden="true"><x-icon name="document" :size="22" /></span>
                <div>
                    <h1 class="v-pagehead__title">{{ $contract->title }}</h1>
                    <div class="v-pagehead__meta">
                        <x-status-badge :status="$displayStatus" domain="contract" />
                        @if ($contract->number)
                            <span class="v-muted" dir="ltr">{{ $contract->number }}</span>
                        @endif
                        @if ($contract->contractType)
                            <span class="v-muted">{{ $contract->contractType->title }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="v-pagehead__actions">
            @if ($contract->finalized_at && auth()->user()->hasPermission('contracts.view_financials'))
                {{--
                    REQ-09.05 / T-PDF-01 — the printable contract.
                    Only offered once finalised: a draft has no permanent number
                    and no frozen body, and the export refuses it. Offering a
                    button that always refuses is worse than not offering it.
                --}}
                <a href="{{ route('contracts.pdf', $contract) }}" class="v-btn v-btn--ghost"
                   data-export="pdf">
                    <x-icon name="download" :size="16" /> دانلود PDF
                </a>
            @endif
            @if ($canEdit)
                <a href="{{ route('contracts.edit', $contract) }}" class="v-btn v-btn--ghost">
                    <x-icon name="edit" :size="16" /> ویرایش
                </a>
            @endif
            @if ($canDelete)
                <form method="POST" action="{{ route('contracts.destroy', $contract) }}"
                      data-confirm="این قرارداد حذف می‌شود. مطمئن هستید؟">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="v-iconbtn v-iconbtn--danger" aria-label="حذف قرارداد" title="حذف">
                        <x-icon name="trash" :size="16" />
                    </button>
                </form>
            @endif
        </div>
    </header>

    {{-- Expiry warning, computed from the real end date and the configured
         threshold — never a hard-coded reminder (§2, §4). --}}
    @if ($isExpiring)
        <x-alert type="warning">
            این قرارداد تا
            <strong>{{ $pd($contract->days_to_expiry) }} روز</strong>
            دیگر ({{ $faDate($contract->end_date) }}) به پایان می‌رسد.
            برای تمدید به‌موقع برنامه‌ریزی کنید.
        </x-alert>
    @endif

    @if ($contract->status === \App\Enums\ContractStatus::Expired)
        <x-alert type="danger">
            این قرارداد منقضی شده است. اگر تمدید شده، وضعیت را به «تمدیدشده» تغییر دهید.
        </x-alert>
    @endif

    <div class="v-detail-grid">
        <div class="v-detail-grid__main">

            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">مشخصات قرارداد</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl v-dl--2">
                        <div>
                            <dt>مشتری</dt>
                            <dd>
                                @if ($contract->customer)
                                    <a class="v-link" href="{{ route('customers.show', $contract->customer) }}">
                                        {{ $contract->customer->display_name }}
                                    </a>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>نوع قرارداد</dt>
                            <dd>{{ $contract->contractType?->title ?? 'بدون دسته‌بندی' }}</dd>
                        </div>
                        <div>
                            <dt>تاریخ شروع</dt>
                            <dd>{{ $faDate($contract->start_date) }}</dd>
                        </div>
                        <div>
                            <dt>تاریخ پایان</dt>
                            <dd>{{ $faDate($contract->end_date) }}</dd>
                        </div>

                        {{-- Money is only rendered when the user holds the
                             financial permission (§10). It is not hidden with CSS;
                             the value never reaches the HTML. --}}
                        @if ($canSeeAmounts)
                            <div class="v-dl__span">
                                <dt>مبلغ قرارداد</dt>
                                <dd class="v-dl__money">
                                    {{ $contract->amountAsMoney()->formatPersian() }}
                                    <span class="v-muted v-small">ریال</span>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($contract->description)
                        <div class="v-prose">
                            <h3 class="v-prose__title">شرح موضوع</h3>
                            <p>{{ $contract->description }}</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Parties: real columns, auto-filled from the customer (§4) --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">طرفین</h2>
                </header>
                <div class="v-card__body">
                    <div class="v-parties">
                        <div class="v-parties__side">
                            <span class="v-parties__label">طرف اول</span>
                            <span class="v-parties__name">{{ $contract->party_one_name ?: 'وِیرا آژانس' }}</span>
                            @if ($contract->party_one_address)
                                <span class="v-parties__addr">{{ $contract->party_one_address }}</span>
                            @endif
                        </div>
                        <div class="v-parties__divider" aria-hidden="true"></div>
                        <div class="v-parties__side">
                            <span class="v-parties__label">طرف دوم</span>
                            <span class="v-parties__name">
                                {{ $contract->party_two_name ?: ($contract->customer?->display_name ?? '—') }}
                            </span>
                            @if ($contract->party_two_address)
                                <span class="v-parties__addr">{{ $contract->party_two_address }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- REQ-04.07 — the frozen document. This is what the client was
                 shown; editing the template afterwards cannot change it. --}}
            @if ($contract->rendered_body)
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">متن نهایی‌شدهٔ قرارداد</h2>
                        <span class="v-card__meta">
                            نهایی‌شده در <x-fa-date :value="$contract->finalized_at" with-time />
                        </span>
                    </header>
                    <div class="v-card__body">
                        <div class="v-document">{{ $contract->rendered_body }}</div>
                        @if ($contract->finalizedBy)
                            <p class="v-hint" style="margin-top: var(--vira-space-3)">
                                نهایی‌شده توسط {{ $contract->finalizedBy->name }}
                            </p>
                        @endif
                    </div>
                </section>
            @endif

            {{-- REQ-04.05 — finalise / renew. Shown only to a user who holds
                 `contracts.finalize`; the route re-checks it regardless. --}}
            @if ($canFinalize)
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">
                            {{ $contract->finalized_at ? 'تمدید قرارداد' : 'نهایی‌سازی قرارداد' }}
                        </h2>
                    </header>
                    <div class="v-card__body">
                        @if (! $contract->finalized_at)
                            <p class="v-muted" style="margin-top:0">
                                با نهایی‌سازی: متن از قالب ساخته و ذخیره می‌شود، شمارهٔ دائمی صادر
                                می‌گردد و نسخه‌ای تغییرناپذیر از قرارداد نگه داشته می‌شود.
                            </p>
                            <form method="POST" action="{{ route('contracts.finalize', $contract) }}" class="v-inlineform"
                                  data-confirm="قرارداد نهایی شود؟ پس از این، متن آن تغییرناپذیر است.">
                                @csrf
                                <div class="v-field">
                                    <label class="v-label" for="template_id">قالب</label>
                                    <select id="template_id" name="template_id" class="v-select" style="min-width: 200px">
                                        <option value="">— بدون قالب (از توضیحات) —</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}">
                                                {{ $template->title }} (نسخهٔ {{ $template->version }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                                    <x-icon name="lock" :size="16" /> نهایی‌سازی
                                </button>
                            </form>
                            @if ($templates->isEmpty())
                                <p class="v-hint">
                                    قالبی ثبت نشده است. بدون قالب، متن «توضیحات» به‌عنوان سند نهایی
                                    ذخیره می‌شود. <a href="{{ route('contracts.templates.create') }}">ساخت قالب</a>
                                </p>
                            @endif
                        @else
                            <p class="v-muted" style="margin-top:0">
                                تمدید، قرارداد جدیدی با شمارهٔ تازه می‌سازد و این قرارداد را دست‌نخورده
                                نگه می‌دارد؛ چون این سند، تعهدی است که طرفین در طول مدت آن عمل کرده‌اند.
                            </p>
                            <form method="POST" action="{{ route('contracts.renew', $contract) }}" novalidate
                                  class="v-form">
                                @csrf
                                <div class="v-grid v-grid--2">
                                    <div class="v-field">
                                        <label class="v-label" for="r-title">عنوان قرارداد تمدیدشده</label>
                                        <input id="r-title" name="title" type="text" class="v-input"
                                               value="{{ old('title', $contract->title) }}" required>
                                    </div>
                                    <div class="v-field">
                                        <label class="v-label" for="r-amount">مبلغ (ریال)</label>
                                        <input id="r-amount" name="amount" type="text" class="v-input"
                                               value="{{ old('amount', $canSeeAmounts ? number_format((int) $contract->amount) : '') }}"
                                               inputmode="numeric" data-money>
                                    </div>
                                    <div class="v-field">
                                        <label class="v-label" for="r-start">تاریخ شروع</label>
                                        <input id="r-start" name="start_date" type="text" class="v-input"
                                               value="{{ old('start_date') }}" placeholder="۱۴۰۵/۰۱/۰۱"
                                               data-jalali required>
                                    </div>
                                    <div class="v-field">
                                        <label class="v-label" for="r-end">تاریخ پایان</label>
                                        <input id="r-end" name="end_date" type="text" class="v-input"
                                               value="{{ old('end_date') }}" placeholder="۱۴۰۵/۱۲/۲۹"
                                               data-jalali>
                                    </div>
                                </div>
                                <div class="v-field">
                                    <label class="v-label" for="r-reason">دلیل تمدید</label>
                                    <input id="r-reason" name="reason" type="text" class="v-input"
                                           value="{{ old('reason') }}" required maxlength="500">
                                </div>
                                <div class="v-formactions">
                                    <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                                        <x-icon name="plus" :size="16" /> ایجاد قرارداد تمدیدی
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Files: honest empty state until uploads are wired (2-D) --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">نسخهٔ امضاشده و فایل‌ها</h2>
                </header>
                <div class="v-card__body v-card__body--flush">
                    @if ($contract->files->isEmpty())
                        <x-empty-state
                            title="فایلی بارگذاری نشده"
                            description="بارگذاری نسخهٔ امضاشده در مرحلهٔ بعد فعال می‌شود. فایل‌ها در فضای خصوصی سرور نگهداری خواهند شد."
                            icon="file"
                        />
                    @else
                        <ul class="v-list">
                            @foreach ($contract->files as $file)
                                <li class="v-list__item">
                                    <span class="v-list__main">
                                        <span class="v-list__title">{{ $file->label ?: $file->attachment?->original_name }}</span>
                                        <span class="v-list__meta">{{ $file->attachment?->humanSize() ?? '—' }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- REQ-04.08 — version history. Each row is a full snapshot of the
                 contract as it stood; the reason says why it changed. --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">نسخه‌ها</h2>
                </header>
                <div class="v-card__body v-card__body--flush">
                    @if ($contract->versions->isEmpty())
                        <x-empty-state
                            title="نسخه‌ای ثبت نشده"
                            description="با نهایی‌سازی قرارداد، از متن آن نسخهٔ تغییرناپذیر ذخیره می‌شود."
                            icon="file"
                        />
                    @else
                        <ul class="v-list">
                            @foreach ($contract->versions as $version)
                                <li class="v-list__item">
                                    <span class="v-list__main">
                                        <span class="v-list__title">
                                            نسخهٔ {{ $pd($version->version) }} — {{ $version->reason }}
                                        </span>
                                        <span class="v-list__meta">
                                            <x-fa-date :value="$version->created_at" with-time />
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <div class="v-detail-grid__aside">
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">جزئیات</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl">
                        <div><dt>شماره قرارداد</dt><dd dir="ltr">{{ $contract->number ?? '—' }}</dd></div>
                        <div><dt>کارشناس مسئول</dt><dd>{{ $contract->owner?->displayName() ?? 'تعیین نشده' }}</dd></div>
                        <div><dt>تاریخ ثبت</dt><dd>{{ $faDate($contract->created_at) }}</dd></div>
                        <div><dt>آخرین به‌روزرسانی</dt><dd>{{ $faDate($contract->updated_at) }}</dd></div>
                    </dl>
                </div>
            </section>

            @if ($contract->internal_notes)
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">یادداشت داخلی</h2>
                    </header>
                    <div class="v-card__body">
                        <p class="v-prose">{{ $contract->internal_notes }}</p>
                    </div>
                </section>
            @endif

            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">تاریخچه</h2>
                </header>
                <div class="v-card__body">
                    @if ($auditTrail->isEmpty())
                        <p class="v-muted v-small">هنوز رویدادی ثبت نشده است.</p>
                    @else
                        <ol class="v-timeline">
                            @foreach ($auditTrail as $entry)
                                <li class="v-timeline__item">
                                    <span class="v-timeline__dot" aria-hidden="true"></span>
                                    <div class="v-timeline__body">
                                        <span class="v-timeline__title">
                                            {{ match ($entry->event) {
                                                'created' => 'قرارداد ایجاد شد',
                                                'updated' => 'قرارداد ویرایش شد',
                                                'deleted' => 'قرارداد حذف شد',
                                                'revealed' => 'رمز دسترسی مشاهده شد',
                                                'exported' => 'خروجی گرفته شد',
                                                default => $entry->event,
                                            } }}
                                        </span>
                                        <span class="v-timeline__meta">
                                            {{ $entry->user?->displayName() ?? 'سامانه' }}
                                            · <x-fa-date :value="$entry->created_at" with-time />
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
