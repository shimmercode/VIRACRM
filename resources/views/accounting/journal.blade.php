@extends('layouts.app')

@section('title', 'اسناد حسابداری')

@section('content')
    @php $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian(); @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">اسناد حسابداری</h1>
            <p class="v-pagehead__desc">هر سند دوطرفه است و از یک منبع معتبر ساخته می‌شود؛ سند دستی بدون منبع ثبت نمی‌شود.</p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'journal'])

    <form class="v-filterbar" method="GET" action="{{ route('accounting.journal') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="شماره سند یا شرح…" aria-label="جست‌وجوی سند">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-status">وضعیت</label>
            <select id="f-status" name="status" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-source">منبع</label>
            <select id="f-source" name="source" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>
    </form>

    <div class="v-card">
        @if ($entries->isEmpty())
            <x-empty-state title="سندی یافت نشد" description="با صدور فاکتور، اولین سند به‌صورت خودکار ثبت می‌شود." icon="document" />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">شماره</th>
                            <th scope="col">تاریخ</th>
                            <th scope="col">شرح</th>
                            <th scope="col">منبع</th>
                            <th scope="col" class="v-table__num">مبلغ</th>
                            <th scope="col">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td>
                                    <a class="v-table__primary" href="{{ route('accounting.journal.show', $entry) }}">
                                        {{ $entry->number }}
                                    </a>
                                </td>
                                <td><x-fa-date :value="$entry->entry_date" /></td>
                                <td>{{ \Illuminate\Support\Str::limit($entry->memo, 60) }}</td>
                                <td><span class="v-badge v-badge--neutral">{{ $sources[$entry->source_type] ?? $entry->source_type }}</span></td>
                                <td class="v-table__num">
                                    <span class="v-money">{{ $money((int) ($entry->total_debit_sum ?? 0)) }}</span>
                                </td>
                                <td>
                                    @if ($entry->status === 'posted')
                                        <span class="v-badge v-badge--success">قطعی</span>
                                    @elseif ($entry->status === 'reversed')
                                        <span class="v-badge v-badge--danger">برگشت‌خورده</span>
                                    @else
                                        <span class="v-badge v-badge--warn">پیش‌نویس</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$entries" />
        @endif
    </div>
@endsection
