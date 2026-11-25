@extends('layouts.app')

@section('title', 'ترازنامه')

@section('content')
    @php $money = fn (int $v) => \App\Support\Money::fromRial($v)->formatPersian(); @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ترازنامه</h1>
            <p class="v-pagehead__desc">دارایی، بدهی و حقوق مالکانه در یک لحظه. رابطهٔ اساسی: دارایی = بدهی + حقوق مالکانه.</p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'bs'])

    <form class="v-filterbar" method="GET" action="{{ route('accounting.balance-sheet') }}">
        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-asof">تا تاریخ (شمسی)</label>
            <input id="f-asof" type="text" name="as_of" class="v-input" style="max-width:160px"
                   value="{{ $asOf }}" placeholder="خالی = تا امروز" inputmode="numeric">
        </div>
        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>
    </form>

    @if (! $sheet['has_data'])
        <x-empty-state
            title="هنوز سندی ثبت نشده است"
            description="ترازنامه پس از ثبت اولین اسناد قابل محاسبه است."
            icon="chart"
        />
    @else
        @if (! $sheet['balanced'])
            <div class="v-notice v-notice--danger" role="alert">
                <div class="v-notice__title">ترازنامه تراز نیست</div>
                دارایی {{ $money($sheet['assets']) }} در برابر بدهی + حقوق مالکانه
                {{ $money($sheet['liabilities'] + $sheet['equity']) }} ریال.
                این وضعیت نشانهٔ خطا در دفتر است، نه یک عدد عادی.
            </div>
        @endif

        <div class="v-grid v-grid--2">
            <section class="v-card">
                <h2 class="v-card__title">دارایی‌ها</h2>
                <dl class="v-dl">
                    <div>
                        <dt>جمع دارایی</dt>
                        <dd><span class="v-money v-money--total">{{ $money($sheet['assets']) }}</span></dd>
                    </div>
                </dl>
            </section>

            <section class="v-card">
                <h2 class="v-card__title">بدهی و حقوق مالکانه</h2>
                <dl class="v-dl">
                    <div>
                        <dt>بدهی‌ها</dt>
                        <dd><span class="v-money">{{ $money($sheet['liabilities']) }}</span></dd>
                    </div>
                    <div>
                        <dt>حقوق مالکانه</dt>
                        <dd><span class="v-money">{{ $money($sheet['equity']) }}</span></dd>
                    </div>
                    <div>
                        <dt>جمع</dt>
                        <dd><span class="v-money v-money--total">{{ $money($sheet['liabilities'] + $sheet['equity']) }}</span></dd>
                    </div>
                </dl>
            </section>
        </div>
    @endif
@endsection
