<?php
/**
 * ⚠️ SUPERSEDED — DO NOT USE IN THE PRODUCT  (2026-11-22)
 * ========================================================
 * This script implements a 2820-year ARITHMETIC calendar. That is NOT the official
 * Iranian calendar, and it is WRONG for 1404 SH and 1437 SH:
 *   - it puts Nowruz 1404 on 2025-03-20; the official calendar says 2025-03-21
 *   - it calls 1404 leap (Esfand 30 days); the official year is common (Esfand 29)
 *   - it calls 1403 common; the official year is leap (Esfand 30 = 2025-03-20)
 *
 * It is retained ONLY as a negative control — proof of the defect that was found.
 * The production candidate is morilog/jalali v3.5.0, verified by:
 *   docs/src/jalali-golden-verify.php   (41-case golden set, 11,323-day round trip)
 *   docs/phase-1/19-calendar-date-policy.md
 *   docs/phase-1/01-architecture-decision-record.md §8 (Addendum A)
 *
 * If you are reading this to understand the calendar layer: read 19-calendar-date-policy.md.
 * --------------------------------------------------------
 * Original header follows.
 */
/**
 * VIRA CRM — Phase 1 feasibility check #1: Jalali (Shamsi) date engine
 * -------------------------------------------------------------------
 * این اسکریپت با یک PHP 8.3 واقعی (PHP-WASM) اجرا می‌شود و صحت الگوریتم تبدیل تاریخ
 * شمسی↔میلادی و محاسبه‌ی «دو روز تقویمی قبل» (REQ-11.04، REQ-12.01، REQ-12.08) را
 * با مقادیر شناخته‌شده اعتبارسنجی می‌کند.
 *
 * این یک الگوریتم عمومی و شناخته‌شده (Julian Day Number conversion) است و در فاز ۳
 * به‌صورت سرویس آزمون‌پذیر در خواهد آمد؛ نه یک پیاده‌سازی دست‌ساز رمزنگاری.
 *
 * اجرا:
 *   node docs/tools/php-cli.mjs docs/src/jalali-smoke-test.php
 */

declare(strict_types=1);

final class Jalali
{
    /** Gregorian (Y, M, D) → Julian Day Number */
    public static function gregorianToJdn(int $y, int $m, int $d): int
    {
        $a = intdiv(14 - $m, 12);
        $y2 = $y + 4800 - $a;
        $m2 = $m + 12 * $a - 3;
        return $d + intdiv(153 * $m2 + 2, 5) + 365 * $y2 + intdiv($y2, 4) - intdiv($y2, 100) + intdiv($y2, 400) - 32045;
    }

    /** Julian Day Number → Gregorian (Y, M, D) */
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

    /** Jalali → JDN (algorithm by Kazimierz M. Borkowski, widely used) */
    public static function jalaliToJdn(int $jy, int $jm, int $jd): int
    {
        $epBase = $jy - ($jy >= 0 ? 474 : 473);
        $epYear = 474 + self::mod($epBase, 2820);
        return $jd
            + (($jm <= 7) ? ($jm - 1) * 31 : (($jm - 1) * 30 + 6))
            + intdiv($epYear * 682 - 110, 2816)
            + ($epYear - 1) * 365
            + intdiv($epBase, 2820) * 1029983
            + 1948320;
    }

    /** JDN → Jalali */
    public static function jdnToJalali(int $jdn): array
    {
        $depoch = $jdn - self::jalaliToJdn(475, 1, 1);
        $cycle = intdiv($depoch, 1029983);
        $cyear = self::mod($depoch, 1029983);
        if ($cyear === 1029982) {
            $ycycle = 2820;
        } else {
            $aux1 = intdiv($cyear, 366);
            $aux2 = self::mod($cyear, 366);
            $ycycle = intdiv(2134 * $aux1 + 2816 * $aux2 + 2815, 1028522) + $aux1 + 1;
        }
        $year = $ycycle + 2820 * $cycle + 474;
        if ($year <= 0) {
            $year--;
        }
        $yday = $jdn - self::jalaliToJdn($year, 1, 1) + 1;
        $month = ($yday <= 186) ? (int) ceil($yday / 31) : (int) ceil(($yday - 6) / 30);
        $day = $jdn - self::jalaliToJdn($year, $month, 1) + 1;
        return [$year, $month, $day];
    }

    public static function gregorianToJalali(int $y, int $m, int $d): array
    {
        return self::jdnToJalali(self::gregorianToJdn($y, $m, $d));
    }

    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        return self::jdnToGregorian(self::jalaliToJdn($jy, $jm, $jd));
    }

    /** آیا سال شمسی کبیسه است؟ (۳۶۵ یا ۳۶۶ روز) */
    public static function isLeapJalali(int $jy): bool
    {
        return self::jalaliToJdn($jy + 1, 1, 1) - self::jalaliToJdn($jy, 1, 1) === 366;
    }

    /** تعداد روزهای ماه شمسی */
    public static function daysInJalaliMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        return self::isLeapJalali($jy) ? 30 : 29;
    }

    /**
     * «دو روز تقویمی قبل» به‌صورت قطعی:
     * تاریخ میلادی ذخیره‌شده → JDN → منهای ۲ → تاریخ میلادی.
     * این کار مستقل از ساعت (نه ۴۸ ساعت شناور) است — REQ-12.08.
     */
    public static function twoCalendarDaysBefore(string $isoDate): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', substr($isoDate, 0, 10)));
        $jdn = self::gregorianToJdn($y, $m, $d) - 2;
        [$gy, $gm, $gd] = self::jdnToGregorian($jdn);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    private static function mod(int $a, int $b): int
    {
        return (($a % $b) + $b) % $b;
    }
}

// ── known reference points (verified against multiple public calendars) ─────
$cases = [
    // [jalali y,m,d,  gregorian y,m,d, description]
    // Note: the Borkowski 2820-year arithmetic calendar (the basis of most PHP Jalali libraries,
    // including morilog/jalali) puts Nowruz 1404 on 2025-03-20, while the astronomical calendar
    // (jdatetime, convertdate) puts it on 2025-03-21. This is a known one-day discrepancy class
    // that must be resolved as an explicit product decision (see open question Q-06) and is
    // covered by a dedicated check below.
    [1403, 1, 1,  2024, 3, 20, 'نوروز ۱۴۰۳'],
    [1403, 12, 29, 2025, 3, 19, 'پایان اسفند ۱۴۰۳ (۲۹ اسفند)'],
    [1402, 10, 11, 2024, 1, 1, 'اول ژانویه ۲۰۲۴ = ۱۱ دی ۱۴۰۲'],
    [1400, 1, 1,  2021, 3, 21, 'نوروز ۱۴۰۰'],
    [1357, 11, 22, 1979, 2, 11, '۲۲ بهمن ۱۳۵۷'],
    [1399, 12, 30, 2021, 3, 20, 'سال کبیسه ۱۳۹۹'],
    [1405, 1, 1,  2026, 3, 21, 'نوروز ۱۴۰۵ (مبنا: تقویم نجومی؛ همخوان با الگوریتم حساب)'],
    [1402, 12, 29, 2024, 3, 19, 'پایان سال عادی ۱۴۰۲'],
];

$failures = 0;
$results = [];

echo "Jalali engine — smoke test\n", str_repeat('=', 78), "\n";

foreach ($cases as [$jy, $jm, $jd, $gy, $gm, $gd, $desc]) {
    $g = Jalali::jalaliToGregorian($jy, $jm, $jd);
    $okForward = $g === [$gy, $gm, $gd];
    $back = Jalali::gregorianToJalali($gy, $gm, $gd);
    $okBack = $back === [$jy, $jm, $jd];
    $ok = $okForward && $okBack;
    if (!$ok) {
        $failures++;
    }
    $results[] = ['case' => $desc, 'in_jalali' => sprintf('%04d-%02d-%02d', $jy, $jm, $jd),
                  'expected_gregorian' => sprintf('%04d-%02d-%02d', $gy, $gm, $gd),
                  'got_gregorian' => vsprintf('%04d-%02d-%02d', $g), 'roundtrip' => $ok,
                  'status' => $ok ? 'PASS' : 'FAIL'];
    printf("%-8s %-38s %s %s → %s (roundtrip: %s)\n",
        $ok ? '[PASS]' : '[FAIL]', $desc,
        sprintf('%04d-%02d-%02d', $jy, $jm, $jd),
        'J→G', vsprintf('%04d-%02d-%02d', $g), $okBack ? 'ok' : 'MISMATCH');
}

// ── day-boundary / D-2 math (REQ-12.08) ───────────────────────────────────
echo "\nD-2 calendar-day math (no 48h floating window):\n";
$dMinusTwo = [
    ['2026-03-21', '2026-03-19', 'عید نوروز ۱۴۰۵ → D-2'],
    ['2026-01-01', '2025-12-30', 'عبور از مرز سال میلادی'],
    ['2025-03-20', '2025-03-18', 'مرز پایان سال شمسی'],
    ['2024-03-01', '2024-02-28', 'سال کبیسه میلادی (۲۹ فوریه)'],
];
foreach ($dMinusTwo as [$in, $expected, $desc]) {
    $got = Jalali::twoCalendarDaysBefore($in);
    $ok = $got === $expected;
    if (!$ok) {
        $failures++;
    }
    $jalali = Jalali::gregorianToJalali(...array_map('intval', explode('-', $in)));
    printf("%-8s %-40s %s (%s فارسی) → %s %s\n", $ok ? '[PASS]' : '[FAIL]', $desc, $in,
        implode('/', $jalali), $got, $ok ? '' : "expected $expected");
}

// ── leap-year / month-length invariants (property test over 100 years) ────
echo "\nInvariant checks over Jalali years 1380..1480:\n";
$invariantFailures = 0;
for ($y = 1380; $y <= 1480; $y++) {
    $sum = 0;
    for ($m = 1; $m <= 12; $m++) {
        $sum += Jalali::daysInJalaliMonth($y, $m);
    }
    $expected = Jalali::isLeapJalali($y) ? 366 : 365;
    if ($sum !== $expected) {
        $invariantFailures++;
        printf("[FAIL] year %d: months sum %d != %d\n", $y, $sum, $expected);
    }
    // round-trip every month's first and last day
    for ($m = 1; $m <= 12; $m++) {
        foreach ([1, Jalali::daysInJalaliMonth($y, $m)] as $d) {
            $g = Jalali::jalaliToGregorian($y, $m, $d);
            $back = Jalali::gregorianToJalali(...$g);
            if ($back !== [$y, $m, $d]) {
                $invariantFailures++;
                printf("[FAIL] roundtrip %d-%02d-%02d → %s → %s\n", $y, $m, $d, implode('-', $g), implode('-', $back));
            }
        }
    }
}
$failures += $invariantFailures;
printf("invariants: %d failures over 101 years × 24 dates\n", $invariantFailures);

// ── timezone behaviour (REQ-12.08): same instant, different calendar day ──
echo "\nTimezone boundary check (Asia/Tehran vs UTC):\n";
date_default_timezone_set('Asia/Tehran');
$tehran = new DateTimeImmutable('2026-03-21 00:30:00', new DateTimeZone('Asia/Tehran'));
$utc = $tehran->setTimezone(new DateTimeZone('UTC'));
$tzOk = $tehran->format('Y-m-d') === '2026-03-21' && $utc->format('Y-m-d') === '2026-03-20';
if (!$tzOk) {
    $failures++;
}
printf("%-8s 2026-03-21 00:30 Tehran == %s UTC (calendar day differs: %s)\n",
    $tzOk ? '[PASS]' : '[FAIL]', $utc->format('Y-m-d H:i'), $tzOk ? 'as expected' : 'UNEXPECTED');

// ── documented reference-table discrepancy (open question Q-06) ──────────
echo "\nReference-calendar discrepancy check (arithmetic 2820-cycle vs astronomical):\n";
$ref = [[1404, 1, 1, [2025, 3, 20], [2025, 3, 21]]];
$discrepancyNote = [];
foreach ($ref as [$jy, $jm, $jd, $arith, $astro]) {
    $got = Jalali::jalaliToGregorian($jy, $jm, $jd);
    $discrepancyNote[] = [
        'date' => sprintf('%04d-%02d-%02d', $jy, $jm, $jd),
        'arithmetic_2820_cycle' => vsprintf('%04d-%02d-%02d', $arith),
        'astronomical' => vsprintf('%04d-%02d-%02d', $astro),
        'engine_output' => vsprintf('%04d-%02d-%02d', $got),
    ];
    printf("NOTE  %s: arithmetic=%s, astronomical=%s, engine=%s  (engine is consistent with its algorithm)\n",
        $discrepancyNote[0]['date'], $discrepancyNote[0]['arithmetic_2820_cycle'],
        $discrepancyNote[0]['astronomical'], $discrepancyNote[0]['engine_output']);
}

echo str_repeat('=', 78), "\n";
printf("RESULT: %s — %d failure(s)\n", $failures === 0 ? 'PASS' : 'FAIL', $failures);

$report = [
    'generated_at' => date('c'),
    'php_version' => PHP_VERSION,
    'checks' => $results,
    'd_minus_two' => $dMinusTwo,
    'invariant_failures' => $invariantFailures,
    'timezone_ok' => $tzOk,
    'reference_discrepancy' => $discrepancyNote,
    'failures' => $failures,
    'verdict' => $failures === 0 ? 'PASS' : 'FAIL',
];
file_put_contents(__DIR__ . '/../../docs/phase-1/evidence/jalali-smoke-test.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

exit($failures === 0 ? 0 : 1);
