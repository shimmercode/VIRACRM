{{-- Shared section nav. Four read surfaces of one module; making them tabs
     rather than separate sidebar entries keeps the sidebar about *modules*. --}}
<nav class="v-tabs" aria-label="بخش‌های حسابداری">
    <a href="{{ route('accounting.chart') }}"
       class="v-tabs__link {{ $active === 'chart' ? 'is-active' : '' }}"
       @if ($active === 'chart') aria-current="page" @endif>نمودار حساب‌ها</a>

    <a href="{{ route('accounting.trial-balance') }}"
       class="v-tabs__link {{ $active === 'trial' ? 'is-active' : '' }}"
       @if ($active === 'trial') aria-current="page" @endif>تراز آزمایشی</a>

    <a href="{{ route('accounting.profit-loss') }}"
       class="v-tabs__link {{ $active === 'pl' ? 'is-active' : '' }}"
       @if ($active === 'pl') aria-current="page" @endif>سود و زیان</a>

    <a href="{{ route('accounting.balance-sheet') }}"
       class="v-tabs__link {{ $active === 'bs' ? 'is-active' : '' }}"
       @if ($active === 'bs') aria-current="page" @endif>ترازنامه</a>

    <a href="{{ route('accounting.journal') }}"
       class="v-tabs__link {{ $active === 'journal' ? 'is-active' : '' }}"
       @if ($active === 'journal') aria-current="page" @endif>اسناد</a>

    <a href="{{ route('accounting.payments') }}"
       class="v-tabs__link {{ $active === 'payments' ? 'is-active' : '' }}"
       @if ($active === 'payments') aria-current="page" @endif>دریافت و پرداخت</a>

    <a href="{{ route('accounting.periods') }}"
       class="v-tabs__link {{ $active === 'periods' ? 'is-active' : '' }}"
       @if ($active === 'periods') aria-current="page" @endif>دوره‌های مالی</a>
</nav>
