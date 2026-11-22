<?php

namespace Tests\Unit;

use App\Support\JalaliDate;
use Tests\ViraTestCase;

/**
 * REQ-11.04 — Jalali display with Gregorian storage.
 * T-CAL-01..06 — calendar correctness.
 *
 * The golden pairs below were fixed in Phase 1 against morilog/jalali and
 * Iranian civil-calendar sources; they are the contract this class must meet.
 */
final class JalaliDateTest extends ViraTestCase
{
    /** ZWNJ family — Persian text must not be mangled by digit conversion. */
    public function run(): void
    {
        $this->goldenConversions();
        $this->leapYears();
        $this->monthLengths();
        $this->roundTrip();
        $this->parsing();
        $this->rejectsInvalidDates();
        $this->dayArithmetic();
        $this->formatting();
    }

    private function goldenConversions(): void
    {
        $pairs = [
            ['1403/01/01', '2024-03-20'],
            ['1403/12/30', '2025-03-20'],
            ['1404/01/01', '2025-03-21'],
            ['1402/12/29', '2024-03-19'],
            ['1400/01/01', '2021-03-21'],
            ['1399/12/30', '2021-03-20'],
            ['1408/12/30', '2030-03-20'],
            ['1408/12/29', '2030-03-19'],
            ['1402/06/31', '2023-09-22'],
            ['1401/07/01', '2022-09-23'],
        ];

        foreach ($pairs as [$jalali, $gregorian]) {
            $date = JalaliDate::parse($jalali);
            $this->assertNotNull($date, "parse {$jalali}");
            $this->assertSame($gregorian, $date->toDateString(), "{$jalali} → Gregorian");
        }
    }

    private function leapYears(): void
    {
        foreach ([1399, 1403, 1408, 1375, 1391] as $year) {
            $this->assertTrue(JalaliDate::isLeap($year), "{$year} should be a leap year");
        }
        foreach ([1400, 1401, 1402, 1404, 1405] as $year) {
            $this->assertFalse(JalaliDate::isLeap($year), "{$year} should not be a leap year");
        }
    }

    private function monthLengths(): void
    {
        $this->assertSame(31, JalaliDate::monthLength(1403, 1));
        $this->assertSame(31, JalaliDate::monthLength(1403, 6));
        $this->assertSame(30, JalaliDate::monthLength(1403, 7));
        $this->assertSame(30, JalaliDate::monthLength(1403, 11));
        $this->assertSame(30, JalaliDate::monthLength(1403, 12), 'Esfand 1403 is 30 days (leap)');
        $this->assertSame(29, JalaliDate::monthLength(1404, 12), 'Esfand 1404 is 29 days (common)');
    }

    private function roundTrip(): void
    {
        $cursor = \Carbon\CarbonImmutable::parse('2023-01-01');
        $mismatches = 0;

        // A full Gregorian leap-year span plus a common year, crossing two Nowruz boundaries.
        for ($i = 0; $i < 800; $i++) {
            $day = $cursor->addDays($i);
            $jalali = JalaliDate::fromGregorian($day);
            if ($jalali->toDateString() !== $day->toDateString()) {
                $mismatches++;
            }
        }

        $this->assertSame(0, $mismatches, '800-day Gregorian↔Jalali round trip must be lossless');
    }

    private function parsing(): void
    {
        $this->assertSame('1403/05/12', JalaliDate::parse('1403/05/12')?->formatAscii());
        $this->assertSame('1403/05/12', JalaliDate::parse('1403-05-12')?->formatAscii());
        $this->assertSame('1403/05/12', JalaliDate::parse('۱۴۰۳/۰۵/۱۲')?->formatAscii());
        $this->assertSame('1403/05/12', JalaliDate::parse('  1403/5/12  ')?->formatAscii());
        $this->assertNull(JalaliDate::parse('not a date'));
        $this->assertNull(JalaliDate::parse(''));
        $this->assertNull(JalaliDate::parse(null));
    }

    private function rejectsInvalidDates(): void
    {
        $this->assertNull(JalaliDate::parse('1403/13/01'), 'month 13 must be rejected');
        $this->assertNull(JalaliDate::parse('1403/00/01'), 'month 0 must be rejected');
        $this->assertNull(JalaliDate::parse('1404/12/30'), '1404/12/30 does not exist (1404 is common)');
        $this->assertNull(JalaliDate::parse('1403/07/31'), 'month 7 has 30 days');

        $this->assertThrows(
            \InvalidArgumentException::class,
            fn () => new JalaliDate(1403, 13, 1)
        );
        $this->assertThrows(
            \InvalidArgumentException::class,
            fn () => new JalaliDate(1404, 12, 30)
        );
    }

    private function dayArithmetic(): void
    {
        // The D-2 reminder rule: two calendar days before the due date.
        $due = JalaliDate::parse('1404/01/01');
        $this->assertSame('1403/12/29', $due->subDays(2)->formatAscii(), '1404/01/01 − 2 days = 1403/12/29 (2025-03-19)');

        // Crossing Esfand in a leap year must land on 12/30.
        $nowruz = JalaliDate::parse('1403/12/30');
        $this->assertSame('1404/01/01', $nowruz->addDays(1)->formatAscii());

        // Crossing a common-year Esfand.
        $endOf1404 = JalaliDate::parse('1404/12/29');
        $this->assertSame('1405/01/01', $endOf1404->addDays(1)->formatAscii());
    }

    private function formatting(): void
    {
        $date = JalaliDate::parse('1403/05/12');
        $this->assertSame('1403/05/12', $date->formatAscii());
        $this->assertSame('۱۴۰۳/۰۵/۱۲', $date->formatPersian());
        $this->assertSame('۱۲ مرداد ۱۴۰۳', $date->formatLongPersian());
    }
}
