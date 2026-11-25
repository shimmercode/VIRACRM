@extends('layouts.app')

@section('title', 'سند ' . $entry->number)

@section('content')
    @php $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian(); @endphp

    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('accounting.journal') }}">اسناد حسابداری</a>
        <x-icon name="chevron" :size="12" class="rtl-mirror" />
        <span aria-current="page">{{ $entry->number }}</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">{{ $entry->number }}</h1>
            <p class="v-pagehead__desc">{{ $entry->memo }}</p>
        </div>
        <div class="v-pagehead__actions">
            <span class="v-badge v-badge--{{ $entry->status === 'posted' ? 'success' : ($entry->status === 'reversed' ? 'danger' : 'warn') }}">
                {{ \App\Models\JournalEntry::STATUSES[$entry->status] ?? $entry->status }}
            </span>
        </div>
    </header>

    @if ($entry->status === 'reversed' && $entry->reversedBy)
        <div class="v-notice v-notice--warn">
            این سند با سند
            <a href="{{ route('accounting.journal.show', $entry->reversedBy) }}">{{ $entry->reversedBy->number }}</a>
            برگشت خورده است. اصل سند برای بازبینی حفظ شده است.
        </div>
    @endif

    <section class="v-card">
        <dl class="v-dl v-dl--2">
            <div><dt>تاریخ</dt><dd><x-fa-date :value="$entry->entry_date" /></dd></div>
            <div><dt>دوره مالی</dt><dd>{{ $entry->fiscalPeriod?->title ?? '—' }}</dd></div>
            <div><dt>منبع</dt><dd>{{ \App\Models\JournalEntry::SOURCES[$entry->source_type] ?? $entry->source_type }}
                @if ($entry->source_id) #{{ $entry->source_id }} @endif</dd></div>
            <div><dt>ثبت‌کننده</dt><dd>{{ $entry->postedBy?->name ?? '—' }}</dd></div>
        </dl>

        <div class="v-tablewrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">حساب</th>
                        <th scope="col">شرح</th>
                        <th scope="col" class="v-table__num">بدهکار</th>
                        <th scope="col" class="v-table__num">بستانکار</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entry->lines as $line)
                        <tr>
                            <td>{{ \App\Support\Money::toPersianDigits((string) $line->line_no) }}</td>
                            <td>
                                <span class="v-account-code">{{ $line->account->code }}</span>
                                {{ $line->account->title }}
                            </td>
                            <td>
                                {{ $line->description ?? '—' }}
                                @if ($line->customer)
                                    <div class="v-table__sub">{{ $line->customer->display_name }}</div>
                                @endif
                            </td>
                            <td class="v-table__num">
                                @if ($line->debit > 0)<span class="v-money">{{ $money($line->debit) }}</span>@else — @endif
                            </td>
                            <td class="v-table__num">
                                @if ($line->credit > 0)<span class="v-money">{{ $money($line->credit) }}</span>@else — @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3">جمع</th>
                        <th class="v-table__num"><span class="v-money v-money--total">{{ $money($entry->totalDebit()) }}</span></th>
                        <th class="v-table__num"><span class="v-money v-money--total">{{ $money($entry->totalCredit()) }}</span></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if (! $entry->isBalanced())
            <div class="v-notice v-notice--danger" role="alert">
                <div class="v-notice__title">این سند تراز نیست</div>
                این وضعیت نباید رخ دهد؛ دفتر نیازمند بازبینی است.
            </div>
        @endif
    </section>
@endsection
