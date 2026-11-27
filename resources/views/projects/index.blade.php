@extends('layouts.app')

@section('title', 'پروژه‌ها')

@section('content')
    @php use App\Support\Money; @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">پروژه‌ها</h1>
            <p class="v-pagehead__desc">
                {{ Money::toPersianDigits((string) $total) }} پروژه در سامانه ثبت شده است.
            </p>
        </div>

        @if (auth()->user()->hasPermission('projects.create'))
            <div class="v-pagehead__actions">
                <a href="{{ route('projects.create') }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> پروژه جدید
                </a>
            </div>
        @endif
    </header>

    <form class="v-filterbar" method="GET" action="{{ route('projects.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="عنوان پروژه، شماره یا نام مشتری…"
                   aria-label="جست‌وجوی پروژه">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-type">نوع</label>
            <select id="f-type" name="type" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-owner">مسئول</label>
            <select id="f-owner" name="owner_id" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($owners as $id => $name)
                    <option value="{{ $id }}" @selected((string) $filters['owner_id'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
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

        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '')
            <a href="{{ route('projects.index') }}" class="v-btn v-btn--link v-btn--sm">پاک کردن</a>
        @endif
    </form>

    @php
        $filterChips = [];

        if ($filters['q'] !== '') {
            $filterChips[] = ['key' => 'q', 'label' => 'جست‌وجو', 'value' => $filters['q']];
        }

        if ($filters['type'] !== '') {
            $filterChips[] = [
                'key' => 'type',
                'label' => 'نوع پروژه',
                'value' => $types[$filters['type']] ?? $filters['type'],
            ];
        }

        if ($filters['status'] !== '') {
            $filterChips[] = [
                'key' => 'status',
                'label' => 'وضعیت',
                'value' => $statuses[$filters['status']] ?? $filters['status'],
            ];
        }

        // customer_id / from / to have no controls here, but a link from a
        // customer page arrives with customer_id set. Without a chip the list
        // is filtered by something the user cannot see or clear.
        if ($filters['customer_id'] !== '') {
            $customerName = \App\Models\Customer::query()
                ->whereKey($filters['customer_id'])
                ->first()?->display_name;

            $filterChips[] = [
                'key' => 'customer_id',
                'label' => 'مشتری',
                'value' => $customerName ?? $filters['customer_id'],
            ];
        }

        $jalaliLabel = function (string $value): string {
            try {
                return \App\Support\JalaliDate::fromCarbon(
                    \Carbon\CarbonImmutable::parse($value)
                )->formatPersian();
            } catch (\Throwable) {
                return $value;
            }
        };

        if ($filters['from'] !== '') {
            $filterChips[] = ['key' => 'from', 'label' => 'از تاریخ', 'value' => $jalaliLabel($filters['from'])];
        }

        if ($filters['to'] !== '') {
            $filterChips[] = ['key' => 'to', 'label' => 'تا تاریخ', 'value' => $jalaliLabel($filters['to'])];
        }

        $anyFilter = $filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== ''
            || $filters['customer_id'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';
    @endphp

    <x-filter-chips :chips="$filterChips" :clear-href="$anyFilter ? route('projects.index') : null" />

    <div class="v-card">
        @if ($projects->isEmpty())
            @if ($anyFilter)
                <x-empty-state title="پروژه‌ای با این فیلترها پیدا نشد"
                    description="عبارت جست‌وجو یا فیلترها را تغییر دهید." icon="search">
                    <a href="{{ route('projects.index') }}" class="v-btn v-btn--ghost v-btn--sm">حذف فیلترها</a>
                </x-empty-state>
            @else
                <x-empty-state title="هنوز پروژه‌ای ثبت نشده است"
                    description="پروژه‌ها کار واقعی روی مشتری را نشان می‌دهند و فاکتور/گزارش‌های دوره‌ای از آن‌ها ساخته می‌شود."
                    icon="folder">
                    @if (auth()->user()->hasPermission('projects.create'))
                        <a href="{{ route('projects.create') }}" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="plus" :size="16" /> ثبت اولین پروژه
                        </a>
                    @endif
                </x-empty-state>
            @endif
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            @php
                                $sortLink = function (string $key) use ($filters) {
                                    $dir = ($filters['sort'] === $key && $filters['dir'] === 'asc') ? 'desc' : 'asc';
                                    return [request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir]), $filters['sort'] === $key, $dir];
                                };
                            @endphp

                            <th scope="col">
                                @php [$u, $a, $d] = $sortLink('title'); @endphp
                                <a class="v-thsort {{ $a ? 'is-active' : '' }}" href="{{ $u }}">
                                    پروژه @if ($a)<x-icon name="down" :size="12" class="{{ $d === 'asc' ? 'is-flipped' : '' }}" />@endif
                                </a>
                            </th>
                            <th scope="col">مشتری</th>
                            <th scope="col">نوع</th>
                            <th scope="col">
                                @php [$u, $a, $d] = $sortLink('start_date'); @endphp
                                <a class="v-thsort {{ $a ? 'is-active' : '' }}" href="{{ $u }}">
                                    بازهٔ زمانی @if ($a)<x-icon name="down" :size="12" class="{{ $d === 'asc' ? 'is-flipped' : '' }}" />@endif
                                </a>
                            </th>
                            <th scope="col">وضعیت</th>
                            @if ($canSeeAmounts)
                                <th scope="col" class="v-table__num">
                                    @php [$u, $a, $d] = $sortLink('amount'); @endphp
                                    <a class="v-thsort {{ $a ? 'is-active' : '' }}" href="{{ $u }}">مبلغ</a>
                                </th>
                            @endif
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td>
                                    <a class="v-table__primary" href="{{ route('projects.show', $project) }}">
                                        {{ $project->title }}
                                    </a>
                                    <div class="v-table__sub">
                                        {{ $project->code }}
                                        @if ($project->type === 'design' && $project->designDetails?->domain)
                                            · <span dir="ltr">{{ $project->designDetails->domain }}</span>
                                        @elseif ($project->type === 'seo' && $project->seoDetails?->package)
                                            · {{ $project->seoDetails->package->title }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($project->customer)
                                        <a class="v-link" href="{{ route('customers.show', $project->customer) }}">
                                            {{ $project->customer->display_name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $project->typeLabel() }}</td>
                                <td class="v-table__nowrap">
                                    <span dir="ltr" class="v-small">
                                        @if ($project->start_date)
                                            <x-fa-date :value="$project->start_date" />
                                        @endif
                                        @if ($project->end_date)
                                            — <x-fa-date :value="$project->end_date" />
                                        @endif
                                        @if (! $project->start_date && ! $project->end_date)
                                            —
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    {{-- One component owns the colour language for
                                         every domain, so a project's green cannot drift
                                         from a customer's green. Text is always present;
                                         colour is decoration, never the only signal. --}}
                                    <x-status-badge :status="$project->status" domain="project" />
                                </td>
                                @if ($canSeeAmounts)
                                    <td class="v-table__num">
                                        @if ((int) $project->amount > 0)
                                            {{ \App\Support\Money::fromRial((int) $project->amount)->formatPersian() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                <td class="v-table__actions">
                                    <div class="v-actions">
                                        <a class="v-iconbtn v-iconbtn--sm" href="{{ route('projects.show', $project) }}"
                                           aria-label="مشاهدهٔ {{ $project->title }}" title="مشاهده" data-tooltip="مشاهده">
                                            <x-icon name="eye" :size="16" />
                                        </a>
                                        @if (auth()->user()->hasPermission('projects.update'))
                                            <a class="v-iconbtn v-iconbtn--sm" href="{{ route('projects.edit', $project) }}"
                                               aria-label="ویرایش {{ $project->title }}" title="ویرایش" data-tooltip="ویرایش">
                                                <x-icon name="edit" :size="16" />
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="v-card__foot">
                <x-pagination :paginator="$projects" />
            </footer>
        @endif
    </div>
@endsection
