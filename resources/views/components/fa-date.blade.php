@props(['value' => null, 'withTime' => false, 'fallback' => '—'])

{{-- Jalali date, always.
     Use this for every date shown to a user; never call ->format('Y-m-d') on a
     Carbon instance in a view. That is how Gregorian dates leaked into the
     dashboard and the customer detail panel — both looked plausible in a
     screenshot, and a Persian-speaking reviewer reads ۱۲۰۲۶ as a typo rather
     than as "wrong calendar". Formatting lives here so the calendar decision is
     made once, in one place. --}}
@php
    use App\Support\JalaliDate;

    $isEmpty = $value === null || $value === '';

    if (! $isEmpty) {
        $carbon = $value instanceof \DateTimeInterface
            ? \Carbon\CarbonImmutable::instance($value)
            : \Carbon\CarbonImmutable::parse($value);

        $formatted = JalaliDate::fromCarbon($carbon)->formatPersian();

        if ($withTime) {
            // Clock time is not a calendar question, so it is safe to use the
            // same digits as the date.
            $formatted .= ' · ' . \App\Support\Money::toPersianDigits($carbon->format('H:i'));
        }
    }
@endphp

@if ($isEmpty)
    <span {{ $attributes->class(['v-date--empty']) }}>{{ $fallback }}</span>
@else
    <span {{ $attributes }}>{{ $formatted }}</span>
@endif
