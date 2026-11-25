<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Jalali (Solar Hijri) calendar, implemented against the arithmetic
 * (Birashk-free, 33-year cycle) algorithm validated in Phase 1.
 *
 * WHY THIS EXISTS rather than pulling a package: the host may run PHP 8.2,
 * dependency weight matters on shared hosting, and the algorithm is small and
 * fully testable. The break-year table below is the one used by morilog/jalali
 * v3.5.0, so results match the most widely used Persian library.
 *
 * STORAGE RULE (client requirement §11): every date is stored in the database
 * as a Gregorian DATE/DATETIME. Jalali exists only at the presentation and
 * input-validation edges. This class never touches the database directly.
 */
final class JalaliDate
{
    /**
     * Years in which the 33-year cycle breaks.
     * Source: morilog/jalali v3.5.0 CalendarUtils::$breaks (validated in Phase 1).
     */
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
        if ($year < -61 || $year > 3177) {
            throw new InvalidArgumentException("Jalali year out of supported range: {$year}");
        }
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid Jalali month: {$month}");
        }
        if ($day < 1 || $day > self::monthLength($year, $month)) {
            throw new InvalidArgumentException(
                "Invalid Jalali day {$day} for {$year}/{$month} (month has "
                . self::monthLength($year, $month) . ' days)'
            );
        }
    }

    public static function fromJalali(int $year, int $month, int $day): self
    {
        return new self($year, $month, $day);
    }

    public static function fromGregorian(CarbonImmutable $date): self
    {
        [$jy, $jm, $jd] = self::toJalali($date->year, $date->month, $date->day);

        return new self($jy, $jm, $jd);
    }

    /**
     * Accept either Carbon flavour.
     *
     * The narrow CarbonImmutable signature looked correct and was wrong in
     * practice: every Eloquent `date` cast hands back a MUTABLE Carbon, so
     * `JalaliDate::fromCarbon($model->start_date)` threw a TypeError from
     * ordinary model data. Laravel's own `CarbonImmutable::instance()` is the
     * documented bridge between the two.
     */
    public static function fromCarbon(CarbonImmutable|\Carbon\Carbon $date): self
    {
        return self::fromGregorian(CarbonImmutable::instance($date));
    }

    /**
     * Parse a user-entered Jalali date.
     * Accepts "1403/05/12", "1403-05-12", "۱۴۰۳/۰۵/۱۲".
     */
    public static function parse(?string $input): ?self
    {
        if ($input === null) {
            return null;
        }

        $normalized = Money::toAsciiDigits(trim($input));
        $normalized = str_replace(['-', '.', '٬', '،'], '/', $normalized);

        if (! preg_match('#^(\d{1,4})/(\d{1,2})/(\d{1,2})$#', $normalized, $m)) {
            return null;
        }

        try {
            return new self((int) $m[1], (int) $m[2], (int) $m[3]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The timezone used when a Jalali date becomes an instant.
     *
     * Kept as a static rather than reading config() so this value object stays
     * usable in pure unit tests and in CLI commands before the container is
     * booted. Application code sets it once in AppServiceProvider.
     */
    private static string $timezone = 'UTC';

    public static function setTimezone(string $timezone): void
    {
        self::$timezone = $timezone;
    }

    public function toCarbon(): CarbonImmutable
    {
        [$gy, $gm, $gd] = self::toGregorian($this->year, $this->month, $this->day);

        return CarbonImmutable::create($gy, $gm, $gd, 0, 0, 0, self::$timezone);
    }

    /** ISO Gregorian date, the only form that goes into the database. */
    public function toDateString(): string
    {
        return $this->toCarbon()->toDateString();
    }

    /** "1403/05/12" with Latin digits — for form values and CSV. */
    public function formatAscii(): string
    {
        return sprintf('%04d/%02d/%02d', $this->year, $this->month, $this->day);
    }

    /** "۱۴۰۳/۰۵/۱۲" — for display in the RTL interface. */
    public function formatPersian(): string
    {
        return Money::toPersianDigits($this->formatAscii());
    }

    /** "۱۲ مرداد ۱۴۰۳" — human date for PDFs and headings. */
    public function formatLongPersian(): string
    {
        $month = self::MONTH_NAMES[$this->month - 1];

        return sprintf('%s %s %s', Money::toPersianDigits((string) $this->day), $month, Money::toPersianDigits((string) $this->year));
    }

    public const MONTH_NAMES = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    public function isLeapYear(): bool
    {
        return self::isLeap($this->year);
    }

    public function daysInMonth(): int
    {
        return self::monthLength($this->year, $this->month);
    }

    /** Calendar days, not seconds — the D-2 rule must survive DST-free months. */
    public function addDays(int $days): self
    {
        return self::fromGregorian($this->toCarbon()->addDays($days));
    }

    public function subDays(int $days): self
    {
        return self::fromGregorian($this->toCarbon()->subDays($days));
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year
            && $this->month === $other->month
            && $this->day === $other->day;
    }

    public function toArray(): array
    {
        return ['year' => $this->year, 'month' => $this->month, 'day' => $this->day];
    }

    // ── Arithmetic core ────────────────────────────────────────────────────

    public static function isLeap(int $jy): bool
    {
        return self::jalCal($jy)['leap'] === 0;
    }

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }

        return self::isLeap($jy) ? 30 : 29;
    }

    public static function daysInYear(int $jy): int
    {
        return self::isLeap($jy) ? 366 : 365;
    }

    /**
     * Jalali → Gregorian.
     *
     * DAY-ACCURATE: derived from the Julian Day Number, so leap-year
     * transitions cannot drift by one day.
     *
     * @return array{0:int,1:int,2:int} [year, month, day]
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jdn = self::jalaliToJdn($jy, $jm, $jd);

        return self::jdnToGregorian($jdn);
    }

    /**
     * Gregorian → Jalali.
     *
     * @return array{0:int,1:int,2:int} [year, month, day]
     */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $jdn = self::gregorianToJdn($gy, $gm, $gd);

        return self::jdnToJalali($jdn);
    }

    public static function gregorianToJdn(int $gy, int $gm, int $gd): int
    {
        $a = intdiv(14 - $gm, 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;

        return $gd + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return [$year, $month, $day];
    }

    public static function jalaliToJdn(int $jy, int $jm, int $jd): int
    {
        $r = self::jalCal($jy);

        return self::gregorianToJdn($r['gy'], 3, $r['march'])
            + ($jm - 1) * 31
            - intdiv($jm, 7) * ($jm - 7)
            + $jd
            - 1;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function jdnToJalali(int $jdn): array
    {
        $gy = self::jdnToGregorian($jdn)[0];
        $jy = $gy - 621;
        $r = self::jalCal($jy);
        $jdn1f = self::gregorianToJdn($gy, 3, $r['march']);
        $k = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                return [$jy, 1 + intdiv($k, 31), ($k % 31) + 1];
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        return [$jy, 7 + intdiv($k, 30), ($k % 30) + 1];
    }

    /**
     * Calendar coefficients for a Jalali year: the Gregorian year that starts
     * it, the March day of Nowruz, and the leap flag.
     *
     * @return array{gy:int, march:int, leap:int}
     */
    private static function jalCal(int $jy): array
    {
        $breaks = self::BREAKS;
        $count = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        $jump = 0;

        if ($jy < $jp || $jy >= $breaks[$count - 1]) {
            throw new InvalidArgumentException("Jalali year out of supported range: {$jy}");
        }

        for ($i = 1; $i < $count; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ += intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ += intdiv($n, 33) * 8 + intdiv($n % 33 + 3, 4);

        if ($jump % 33 === 4 && $jump - $n === 4) {
            $leapJ += 1;
        }

        $leapG = intdiv($gy, 4) - intdiv((intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }

        $leap = (($n + 1) % 33 - 1) % 4;
        if ($leap === -1) {
            $leap = 4;
        }

        return ['gy' => $gy, 'march' => $march, 'leap' => $leap];
    }
}
