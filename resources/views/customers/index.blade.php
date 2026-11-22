@extends('layouts.app')

@section('title', 'مشتریان')

@section('content')
    @php use App\Support\Money; @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">مشتریان</h1>
            <p class="v-pagehead__desc">
                {{ Money::toPersianDigits((string) $total) }} مشتری ثبت شده است.
            </p>
        </div>

        @if (auth()->user()->hasPermission('customers.create'))
            {{-- Wrap in the actions slot so the header flex can place it;
                 an unwrapped child becomes a third flex item and drops to
                 its own row below 1024px. --}}
            <div class="v-pagehead__actions">
                <a href="{{ route('customers.create') }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" />
                    مشتری جدید
                </a>
            </div>
        @endif
    </header>

    {{-- Filters operate on real columns through the `search`/`where` scopes,
         and every control keeps the other filters in the querystring. --}}
    <form class="v-filterbar" method="GET" action="{{ route('customers.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="نام، شرکت، کد ملی، دامنه یا شماره تماس…"
                   aria-label="جست‌وجوی مشتری">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-type">نوع</label>
            <select id="f-type" name="type" class="v-select v-select--sm">
                <option value="">همه</option>
                <option value="legal" @selected($filters['type'] === 'legal')>حقوقی</option>
                <option value="natural" @selected($filters['type'] === 'natural')>حقیقی</option>
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

        {{-- Preserve the current sort when filtering. --}}
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '')
            <a href="{{ route('customers.index') }}" class="v-btn v-btn--link v-btn--sm">پاک کردن</a>
        @endif
    </form>

    <div class="v-card">
        @if ($customers->isEmpty())
            @if ($filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '')
                <x-empty-state
                    title="مشتری‌ای با این فیلترها پیدا نشد"
                    description="عبارت جست‌وجو یا فیلترها را تغییر دهید."
                    icon="search"
                >
                    <a href="{{ route('customers.index') }}" class="v-btn v-btn--ghost v-btn--sm">حذف فیلترها</a>
                </x-empty-state>
            @else
                <x-empty-state
                    title="هنوز مشتری‌ای ثبت نشده است"
                    description="با ثبت اولین مشتری، پرونده‌ها، قراردادها و پروژه‌ها به او متصل می‌شوند."
                    icon="users"
                >
                    @if (auth()->user()->hasPermission('customers.create'))
                        <a href="{{ route('customers.create') }}" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="plus" :size="16" /> ثبت اولین مشتری
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
                                // Sortable headers build links that keep every
                                // active filter — a sort must not silently reset
                                // the user's search.
                                $sortLink = function (string $key, string $label) use ($filters) {
                                    $dir = ($filters['sort'] === $key && $filters['dir'] === 'asc') ? 'desc' : 'asc';
                                    $active = $filters['sort'] === $key;
                                    $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir]);
                                    return [$url, $label, $active, $dir];
                                };
                            @endphp

                            <th scope="col">
                                @php [$u, $l, $a, $d] = $sortLink('company_name', 'مشتری'); @endphp
                                <a class="v-thsort {{ $a ? 'is-active' : '' }}" href="{{ $u }}">
                                    {{ $l }} @if ($a)<x-icon name="down" :size="12" class="{{ $d === 'asc' ? 'is-flipped' : '' }}" />@endif
                                </a>
                            </th>
                            <th scope="col">نوع</th>
                            <th scope="col">راه تماس</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col" class="v-table__num">
                                @php [$u, $l, $a, $d] = $sortLink('contracts_count', 'قرارداد'); @endphp
                                <a class="v-thsort {{ $a ? 'is-active' : '' }}" href="{{ $u }}">{{ $l }}</a>
                            </th>
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td>
                                    <a class="v-table__primary" href="{{ route('customers.show', $customer) }}">
                                        {{ $customer->display_name }}
                                    </a>
                                    <div class="v-table__sub">
                                        @if ($customer->code){{ $customer->code }}@endif
                                        @if ($customer->primary_domain)
                                            · {{ $customer->primary_domain }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="v-muted">{{ $customer->type === \App\Enums\CustomerType::Legal ? 'حقوقی' : 'حقیقی' }}</span>
                                </td>
                                <td dir="ltr" class="v-table__ltr">
                                    {{ $customer->mobile ? Money::toPersianDigits($customer->mobile) : ($customer->phone ? Money::toPersianDigits($customer->phone) : '—') }}
                                </td>
                                <td>
                                    <x-status-badge :status="$customer->status" domain="customer" />
                                </td>
                                <td class="v-table__num">{{ Money::toPersianDigits((string) $customer->contracts_count) }}</td>
                                <td class="v-table__actions">
                                    <div class="v-actions">
                                        <a class="v-iconbtn v-iconbtn--sm" href="{{ route('customers.show', $customer) }}"
                                           aria-label="مشاهدهٔ {{ $customer->display_name }}" title="مشاهده">
                                            <x-icon name="eye" :size="16" />
                                        </a>
                                        @if (auth()->user()->hasPermission('customers.update'))
                                            <a class="v-iconbtn v-iconbtn--sm" href="{{ route('customers.edit', $customer) }}"
                                               aria-label="ویرایش {{ $customer->display_name }}" title="ویرایش">
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
                <x-pagination :paginator="$customers" />
            </footer>
        @endif
    </div>
@endsection
