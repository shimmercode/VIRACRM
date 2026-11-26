@extends('layouts.app')

@section('title', $customer->display_name)

@section('content')
    @php
        use App\Support\Money;

        $canEdit = auth()->user()->hasPermission('customers.update');
        $canDelete = auth()->user()->hasPermission('customers.delete');
        $canSeeAmounts = auth()->user()->hasPermission('contracts.view_financials');
        $pd = fn ($v) => Money::toPersianDigits((string) $v);
    @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('customers.index') }}">مشتریان</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">{{ $customer->display_name }}</span>
    </nav>

    <header class="v-pagehead v-pagehead--detail">
        <div class="v-pagehead__text">
            <div class="v-pagehead__id">
                <x-avatar :name="$customer->display_name" :size="44" />
                <div>
                    <h1 class="v-pagehead__title">{{ $customer->display_name }}</h1>
                    <div class="v-pagehead__meta">
                        <x-status-badge :status="$customer->status" domain="customer" />
                        <span class="v-muted">{{ $customer->type === \App\Enums\CustomerType::Legal ? 'حقوقی' : 'حقیقی' }}</span>
                        @if ($customer->code)
                            <span class="v-muted" dir="ltr">{{ $customer->code }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="v-pagehead__actions">
            @if ($canEdit)
                <a href="{{ route('customers.edit', $customer) }}" class="v-btn v-btn--ghost">
                    <x-icon name="edit" :size="16" /> ویرایش
                </a>
            @endif

            @if (auth()->user()->hasPermission('contracts.create'))
                <a href="{{ route('contracts.create', ['customer_id' => $customer->id]) }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> قرارداد جدید
                </a>
            @endif

            @if ($canDelete)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                      data-confirm="این مشتری و ارتباط‌های آن حذف می‌شود. مطمئن هستید؟">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="v-iconbtn v-iconbtn--danger" aria-label="حذف مشتری" title="حذف">
                        <x-icon name="trash" :size="16" />
                    </button>
                </form>
            @endif
        </div>
    </header>

    {{-- Stats strip: only figures the user is permitted to see. --}}
    <section class="v-stats v-stats--compact" aria-label="خلاصه پرونده">
        <div class="v-stat v-stat--static">
            <span class="v-stat__label">قراردادها</span>
            <span class="v-stat__value">{{ $pd($stats['contracts']) }}</span>
            <span class="v-stat__hint">{{ $pd($stats['active_contracts']) }} فعال</span>
        </div>
        <div class="v-stat v-stat--static">
            <span class="v-stat__label">پروژه‌ها</span>
            <span class="v-stat__value">{{ $pd($stats['projects']) }}</span>
        </div>
        <div class="v-stat v-stat--static">
            <span class="v-stat__label">یادداشت‌های باز</span>
            <span class="v-stat__value">{{ $pd($stats['notes']) }}</span>
        </div>
        @if ($canSeeAmounts)
            <div class="v-stat v-stat--static">
                <span class="v-stat__label">ارزش قراردادهای فعال</span>
                <span class="v-stat__value v-stat__value--money">{{ $totalValue->formatPersian() }}</span>
                <span class="v-stat__hint">ریال</span>
            </div>
        @endif
    </section>

    <div class="v-detail-grid">
        {{-- ── Main column ───────────────────────────────────────────── --}}
        <div class="v-detail-grid__main">

            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">اطلاعات تماس</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl v-dl--2">
                        <div>
                            <dt>موبایل</dt>
                            <dd dir="ltr">{{ $customer->mobile ? $pd($customer->mobile) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>تلفن</dt>
                            <dd dir="ltr">{{ $customer->phone ? $pd($customer->phone) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>ایمیل</dt>
                            <dd dir="ltr">{{ $customer->email ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>کد پستی</dt>
                            <dd dir="ltr">{{ $customer->postal_code ? $pd($customer->postal_code) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>شهر</dt>
                            <dd>{{ $customer->city ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>استان</dt>
                            <dd>{{ $customer->province ?: '—' }}</dd>
                        </div>
                        @if ($customer->address)
                            <div class="v-dl__span">
                                <dt>نشانی</dt>
                                <dd>{{ $customer->address }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            {{-- Legal / natural identification --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">مشخصات هویتی</h2>
                </header>
                <div class="v-card__body">
                    @if ($customer->type === \App\Enums\CustomerType::Legal)
                        <dl class="v-dl v-dl--2">
                            <div><dt>شماره ثبت</dt><dd dir="ltr">{{ $customer->registration_number ? $pd($customer->registration_number) : '—' }}</dd></div>
                            <div><dt>کد اقتصادی</dt><dd dir="ltr">{{ $customer->economic_code ? $pd($customer->economic_code) : '—' }}</dd></div>
                            <div class="v-dl__span"><dt>نمایندهٔ قانونی</dt><dd>{{ $customer->legal_representative ?: '—' }}</dd></div>
                        </dl>
                    @else
                        <dl class="v-dl v-dl--2">
                            <div><dt>نام</dt><dd>{{ $customer->first_name ?: '—' }}</dd></div>
                            <div><dt>نام خانوادگی</dt><dd>{{ $customer->last_name ?: '—' }}</dd></div>
                            <div><dt>کد ملی</dt><dd dir="ltr">{{ $customer->national_id ? $pd($customer->national_id) : '—' }}</dd></div>
                            <div><dt>نام پدر</dt><dd>{{ $customer->father_name ?: '—' }}</dd></div>
                        </dl>
                    @endif
                </div>
            </section>

            {{-- Websites (§3) --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">وب‌سایت‌ها</h2>
                </header>
                <div class="v-card__body v-card__body--flush">
                    @if ($customer->websites->isEmpty())
                        <x-empty-state
                            title="وب‌سایتی ثبت نشده"
                            description="برای این مشتری هنوز دامنه‌ای ثبت نشده است.@if($canEdit) از دکمهٔ ویرایش می‌توانید اضافه کنید.@endif"
                            icon="globe"
                        />
                    @else
                        <ul class="v-list">
                            @foreach ($customer->websites as $site)
                                <li class="v-list__item">
                                    <a class="v-list__main" href="{{ $site->url }}" target="_blank" rel="noopener noreferrer nofollow">
                                        <span class="v-list__title" dir="ltr">{{ $site->domain }}</span>
                                        <span class="v-list__meta">{{ $site->label ?: 'بدون برچسب' }}</span>
                                    </a>
                                    <x-icon name="external" :size="14" class="v-muted" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Contracts --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">قراردادها</h2>
                    @if (auth()->user()->hasPermission('contracts.view') && $stats['contracts'] > 0)
                        <a class="v-card__link" href="{{ route('contracts.index', ['customer_id' => $customer->id]) }}">
                            همه <x-icon name="chevron" :size="14" />
                        </a>
                    @endif
                </header>
                <div class="v-card__body v-card__body--flush">
                    @if ($customer->contracts->isEmpty())
                        <x-empty-state title="قراردادی ثبت نشده" icon="document"
                            description="قراردادهای این مشتری پس از ثبت اینجا نمایش داده می‌شوند.">
                            @if (auth()->user()->hasPermission('contracts.create'))
                                <a href="{{ route('contracts.create', ['customer_id' => $customer->id]) }}"
                                   class="v-btn v-btn--ghost v-btn--sm">
                                    <x-icon name="plus" :size="14" /> ثبت قرارداد
                                </a>
                            @endif
                        </x-empty-state>
                    @else
                        <ul class="v-list">
                            @foreach ($customer->contracts as $contract)
                                <li class="v-list__item">
                                    <a class="v-list__main" href="{{ route('contracts.show', $contract) }}">
                                        <span class="v-list__title">{{ $contract->title }}</span>
                                        <span class="v-list__meta">
                                            @if ($contract->number){{ $contract->number }} · @endif
                                            <x-fa-date :value="$contract->start_date" />
                                        </span>
                                    </a>
                                    <x-status-badge :status="$contract->displayStatus()" domain="contract" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Projects (§3 — the dossier is the hub for everything related) --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">پروژه‌ها</h2>
                </header>
                <div class="v-card__body v-card__body--flush">
                    @if ($customer->projects->isEmpty())
                        {{--
                            This used to read «ماژول پروژه‌ها در مرحلهٔ بعدی فعال
                            می‌شود» — the projects module is live, so the message
                            told users a working feature did not exist. A stale
                            empty state is worse than none: it sends people to the
                            projects menu to look for something already here.
                        --}}
                        <x-empty-state
                            title="پروژه‌ای ثبت نشده"
                            description="پروژه‌های این مشتری پس از ثبت اینجا نمایش داده می‌شوند."
                            icon="folder"
                        >
                            @if (auth()->user()->hasPermission('projects.create'))
                                <a href="{{ route('projects.create', ['customer_id' => $customer->id]) }}"
                                   class="v-btn v-btn--ghost v-btn--sm">
                                    <x-icon name="plus" :size="14" /> ثبت پروژه
                                </a>
                            @endif
                        </x-empty-state>
                    @else
                        <ul class="v-list">
                            @foreach ($customer->projects as $project)
                                <li class="v-list__item">
                                    {{--
                                        A link, matching the contract rows above.
                                        This was a plain <div>, so the dossier
                                        listed the project's name but gave no way
                                        to open it — §3 makes this page the hub
                                        every related record hangs off, and a hub
                                        you cannot navigate out of is only half
                                        built. Found by the W-02 end-to-end
                                        journey, not by any per-module test.
                                    --}}
                                    <a class="v-list__main" href="{{ route('projects.show', $project) }}">
                                        <span class="v-list__title">{{ $project->title }}</span>
                                        <span class="v-list__meta">{{ $project->typeLabel() }}</span>
                                    </a>
                                    <x-badge :tone="$project->status === 'active' ? 'success' : 'neutral'">
                                        {{ $project->statusLabel() }}
                                    </x-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Notes --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">یادداشت‌ها</h2>
                </header>
                <div class="v-card__body v-card__body--flush">
                    @if ($customer->noteEntries->isEmpty())
                        <x-empty-state title="یادداشتی ثبت نشده" icon="file"
                            description="یادداشت‌های مرتبط با این مشتری اینجا نمایش داده می‌شوند." />
                    @else
                        <ul class="v-list">
                            @foreach ($customer->noteEntries as $note)
                                <li class="v-list__item">
                                    <div class="v-list__main">
                                        <span class="v-list__title">{{ $note->title }}</span>
                                        @if ($note->description)
                                            <span class="v-list__meta">{{ \Illuminate\Support\Str::limit($note->description, 90) }}</span>
                                        @endif
                                    </div>
                                    <x-badge :tone="$note->priority === 'urgent' ? 'danger' : 'neutral'">
                                        {{ $note->priorityLabel() }}
                                    </x-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        {{-- ── Side column ───────────────────────────────────────────── --}}
        <div class="v-detail-grid__aside">

            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">جزئیات پرونده</h2>
                </header>
                <div class="v-card__body">
                    <dl class="v-dl">
                        <div><dt>کارشناس مسئول</dt><dd>{{ $customer->owner?->displayName() ?? 'تعیین نشده' }}</dd></div>
                        <div><dt>نحوهٔ آشنایی</dt><dd>{{ $customer->acquaintance_source ?: '—' }}</dd></div>
                        <div><dt>تاریخ ثبت</dt><dd><x-fa-date :value="$customer->created_at" /></dd></div>
                        <div><dt>آخرین به‌روزرسانی</dt><dd><x-fa-date :value="$customer->updated_at" /></dd></div>
                    </dl>
                </div>
            </section>

            @if ($customer->notes)
                <section class="v-card">
                    <header class="v-card__head">
                        <h2 class="v-card__title">یادداشت داخلی</h2>
                    </header>
                    <div class="v-card__body">
                        <p class="v-prose">{{ $customer->notes }}</p>
                    </div>
                </section>
            @endif

            {{-- Timeline from the real audit log, not a fabricated feed (§3, §9). --}}
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">تاریخچه</h2>
                </header>
                <div class="v-card__body">
                    @if ($auditTrail->isEmpty())
                        <p class="v-muted v-small">هنوز رویدادی برای این پرونده ثبت نشده است.</p>
                    @else
                        <ol class="v-timeline">
                            @foreach ($auditTrail as $entry)
                                <li class="v-timeline__item">
                                    <span class="v-timeline__dot" aria-hidden="true"></span>
                                    <div class="v-timeline__body">
                                        <span class="v-timeline__title">
                                            {{ match ($entry->event) {
                                                'created' => 'پرونده ایجاد شد',
                                                'updated' => 'اطلاعات ویرایش شد',
                                                'deleted' => 'پرونده حذف شد',
                                                'denied' => 'دسترسی رد شد',
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
