@extends('layouts.app')

@section('title', 'قراردادها')

@section('content')
    @php use App\Support\Money; @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">قراردادها</h1>
            <p class="v-pagehead__desc">
                {{ Money::toPersianDigits((string) $total) }} قرارداد در سامانه ثبت شده است.
            </p>
        </div>

        @if (auth()->user()->hasPermission('contracts.create'))
            {{-- Wrap in the actions slot so the header flex can place it;
                 an unwrapped child becomes a third flex item and drops to
                 its own row below 1024px. --}}
            <div class="v-pagehead__actions">
                <a href="{{ route('contracts.create') }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> قرارداد جدید
                </a>
            </div>
        @endif
    </header>

    <form class="v-filterbar" method="GET" action="{{ route('contracts.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="عنوان قرارداد، شماره یا نام مشتری…"
                   aria-label="جست‌وجوی قرارداد">
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

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-type">نوع قرارداد</label>
            <select id="f-type" name="type" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($types as $type)
                    <option value="{{ $type->code }}" @selected($filters['type'] === $type->code)>{{ $type->title }}</option>
                @endforeach
            </select>
        </div>

        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '')
            <a href="{{ route('contracts.index') }}" class="v-btn v-btn--link v-btn--sm">پاک کردن</a>
        @endif
    </form>

    <div class="v-card">
        @if ($contracts->isEmpty())
            @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '')
                <x-empty-state title="قراردادی با این فیلترها پیدا نشد"
                    description="عبارت جست‌وجو یا فیلترها را تغییر دهید." icon="search">
                    <a href="{{ route('contracts.index') }}" class="v-btn v-btn--ghost v-btn--sm">حذف فیلترها</a>
                </x-empty-state>
            @else
                <x-empty-state title="هنوز قراردادی ثبت نشده است"
                    description="قراردادها به یک مشتری متصل می‌شوند و مبنای پروژه‌ها و پیگیری انقضا هستند." icon="document">
                    @if (auth()->user()->hasPermission('contracts.create'))
                        <a href="{{ route('contracts.create') }}" class="v-btn v-btn--primary v-btn--sm">
                            <x-icon name="plus" :size="16" /> ثبت اولین قرارداد
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
                                    قرارداد @if ($a)<x-icon name="down" :size="12" class="{{ $d === 'asc' ? 'is-flipped' : '' }}" />@endif
                                </a>
                            </th>
                            <th scope="col">مشتری</th>
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
                        @foreach ($contracts as $contract)
                            <tr>
                                <td>
                                    <a class="v-table__primary" href="{{ route('contracts.show', $contract) }}">
                                        {{ $contract->title }}
                                    </a>
                                    <div class="v-table__sub">
                                        {{ $contract->number }}
                                        @if ($contract->contractType)
                                            · {{ $contract->contractType->title }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($contract->customer)
                                        <a class="v-link" href="{{ route('customers.show', $contract->customer) }}">
                                            {{ $contract->customer->display_name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="v-table__nowrap">
                                    <span dir="ltr" class="v-small">
                                        <x-fa-date :value="$contract->start_date" />
                                        @if ($contract->end_date)
                                            — <x-fa-date :value="$contract->end_date" />
                                        @endif
                                    </span>
                                </td>
                                <td><x-status-badge :status="$contract->displayStatus()" domain="contract" /></td>
                                @if ($canSeeAmounts)
                                    <td class="v-table__num">{{ $contract->amountAsMoney()->formatPersian() }}</td>
                                @endif
                                <td class="v-table__actions">
                                    <div class="v-actions">
                                        <a class="v-iconbtn v-iconbtn--sm" href="{{ route('contracts.show', $contract) }}"
                                           aria-label="مشاهدهٔ {{ $contract->title }}" title="مشاهده">
                                            <x-icon name="eye" :size="16" />
                                        </a>
                                        @if (auth()->user()->hasPermission('contracts.update'))
                                            <a class="v-iconbtn v-iconbtn--sm" href="{{ route('contracts.edit', $contract) }}"
                                               aria-label="ویرایش {{ $contract->title }}" title="ویرایش">
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
                <x-pagination :paginator="$contracts" />
            </footer>
        @endif
    </div>
@endsection
