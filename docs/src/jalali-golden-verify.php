<?php
/**
 * VIRA CRM — Golden test set verifier for the Jalali calendar
 * ------------------------------------------------------------
 * Purpose
 *   Prove, on a real PHP runtime, that the calendar algorithm proposed for the product
 *   (morilog/jalali v3.5.0 — Borkowski "breaks" algorithm) reproduces the independently
 *   verified golden set in docs/fixtures/jalali-golden-set.json.
 *
 * What is actually implemented here
 *   A faithful PHP port of the *published source* of morilog/jalali v3.5.0
 *   (src/CalendarUtils.php: jalaliCal / g2d / d2g / j2d / d2j). It is NOT a hand-written
 *   approximation, so a PASS is evidence about the real package algorithm.
 *
 * Negative control (must FAIL)
 *   The exploratory 2820-year arithmetic algorithm used in docs/src/jalali-smoke-test.php
 *   is re-implemented here and MUST disagree with the golden set (1404 and 1437).
 *   If it ever agrees, this verifier fails — because that would mean our "control" is broken.
 *
 * Scope note
 *   This verifies the *algorithm*. It does not install morilog/jalali via Composer (no
 *   Composer in this sandbox). The Composer-level check remains BLOCKED (see test T-INF-02).
 *
 * Run
 *   node docs/tools/php-cli.mjs docs/src/jalali-golden-verify.php
 */

declare(strict_types=1);

final class MorilogJalaliV350
{
    /** Published break table — src/CalendarUtils.php of morilog/jalali v3.5.0 */
    private const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
                            1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    private static function div(int $a, int $b): int
    {
        return intdiv($a, $b);
    }

    private static function mod(int $a, int $b): int
    {
        return $a - intdiv($a, $b) * $b;
    }

    /** jalaliCal: returns ['leap'=>int,'gy'=>int,'march'=>int] */
    public static function jalaliCal(int $jy): array
    {
        $breaks = self::BREAKS;
        $breaksCount = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        if ($jy < $jp || $jy >= $breaks[$breaksCount - 1]) {
            throw new InvalidArgumentException("Invalid Jalali year: {$jy}");
        }
        $jump = 0;
        for ($i = 1; $i < $breaksCount; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ = $leapJ + self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
            $jp = $jm;
        }
        $n = $jy - $jp;
        $leapJ = $leapJ + self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);
        if (self::mod($jump, 33) === 4 && $jump - $n === 4) {
            $leapJ += 1;
        }
        $leapG = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;
        if ($jump - $n < 6) {
            $n = $n - $jump + self::div($jump + 4, 33) * 33;
        }
        $leap = self::mod(self::mod($n + 1, 33) - 1, 4);
        if ($leap === -1) {
            $leap = 4;
        }
        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    public static function g2d(int $gy, int $gm, int $gd): int
    {
        return (self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4)
            + self::div(153 * self::mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408)
            - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;
    }

    public static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j += self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::div(self::mod($j, 1461), 4) * 5 + 308;
        $gd = self::div(self::mod($i, 153), 5) + 1;
        $gm = self::mod(self::div($i, 153), 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);
        return [$gy, $gm, $gd];
    }

    public static function j2d(int $jy, int $jm, int $jd): int
    {
        $jCal = self::jalaliCal($jy);
        return self::g2d($jCal['gy'], 3, $jCal['march'])
            + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
    }

    public static function d2j(int $jdn): array
    {
        $gy = self::d2g($jdn)[0];
        $jy = $gy - 621;
        $jCal = self::jalaliCal($jy);
        $jdn1f = self::g2d($gy, 3, $jCal['march']);
        $k = $jdn - $jdn1f;
        if ($k >= 0) {
            if ($k <= 185) {
                return [$jy, 1 + self::div($k, 31), self::mod($k, 31) + 1];
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($jCal['leap'] === 1) {
                $k += 1;
            }
        }
        return [$jy, 7 + self::div($k, 30), self::mod($k, 30) + 1];
    }

    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        // esfand: month length from JDN difference (avoids relying on a hand-rolled leap flag)
        $start = self::j2d($jy, 12, 1);
        $next = self::j2d($jy + 1, 1, 1);
        return $next - $start;
    }

    public static function isLeap(int $jy): bool
    {
        return self::monthLength($jy, 12) === 30;
    }
}

/** Negative control: the exploratory 2820-year arithmetic algorithm. MUST fail the golden set. */
final class Arithmetic2820
{
    private static function mod(int $a, int $m): int
    {
        $r = $a % $m;
        return $r >= 0 ? $r : $r + $m;
    }

    /** JDN of 1 Farvardin of a Jalali year, 2820-year cycle, anchor 1948320 */
    public static function farvardin1Jdn(int $jy): int
    {
        $epbase = $jy - ($jy >= 0 ? 474 : 473);
        $epyear = 474 + self::mod($epbase, 2820);
        return 1 + intdiv($epyear * 682 - 110, 2816) + ($epyear - 1) * 365
            + intdiv($epbase, 2820) * 1029983 + 1948320;
    }

    public static function toGregorian(int $jy): array
    {
        $jdn = self::farvardin1Jdn($jy);
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
}

// ── harness ─────────────────────────────────────────────────────────────────────
$goldenFile = __DIR__ . '/../fixtures/jalali-golden-set.json';
if (!is_file($goldenFile)) {
    fwrite(STDERR, "golden set not found: {$goldenFile}\n");
    exit(2);
}
$golden = json_decode((string) file_get_contents($goldenFile), true, 512, JSON_THROW_ON_ERROR);
$cases = $golden['cases'];

$results = [];
$fail = 0;
$fmt = static fn (array $g): string => sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);

echo "VIRA CRM — Jalali golden set verifier\n";
echo "PHP ", PHP_VERSION, " | algorithm: morilog/jalali v3.5.0 (port of published source)\n";
echo "golden set: ", count($cases), " cases, generated ", $golden['generated_at'], "\n";
echo str_repeat('=', 96), "\n\n";

// 1) forward: Jalali → Gregorian
echo "1) Jalali → Gregorian (forward)\n";
$fwdFail = 0;
foreach ($cases as $c) {
    [$jy, $jm, $jd] = array_map('intval', explode('-', $c['j']));
    $got = $fmt(MorilogJalaliV350::toGregorian($jy, $jm, $jd));
    $ok = $got === $c['g'];
    if (!$ok) { $fwdFail++; printf("   [FAIL] %s -> %s (expected %s)\n", $c['j'], $got, $c['g']); }
}
printf("   %s  %d/%d correct\n\n", $fwdFail === 0 ? '[PASS]' : '[FAIL]', count($cases) - $fwdFail, count($cases));
$fail += $fwdFail;

// 2) reverse: Gregorian → Jalali
echo "2) Gregorian → Jalali (reverse)\n";
$revFail = 0;
foreach ($cases as $c) {
    [$gy, $gm, $gd] = array_map('intval', explode('-', $c['g']));
    [$jy, $jm, $jd] = MorilogJalaliV350::toJalali($gy, $gm, $gd);
    $got = sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
    if ($got !== $c['j']) { $revFail++; printf("   [FAIL] %s -> %s (expected %s)\n", $c['g'], $got, $c['j']); }
}
printf("   %s  %d/%d correct\n\n", $revFail === 0 ? '[PASS]' : '[FAIL]', count($cases) - $revFail, count($cases));
$fail += $revFail;

// 3) round-trip over every day of 1390..1420 (must be lossless)
echo "3) Round-trip over every day of 1390..1420\n";
$rtFail = 0; $days = 0;
$start = MorilogJalaliV350::j2d(1390, 1, 1);
$end   = MorilogJalaliV350::j2d(1421, 1, 1);
for ($jdn = $start; $jdn < $end; $jdn++) {
    $days++;
    [$jy, $jm, $jd] = MorilogJalaliV350::d2j($jdn);
    if (MorilogJalaliV350::j2d($jy, $jm, $jd) !== $jdn) { $rtFail++; }
    [$gy, $gm, $gd] = MorilogJalaliV350::d2g($jdn);
    if (MorilogJalaliV350::g2d($gy, $gm, $gd) !== $jdn) { $rtFail++; }
}
printf("   %s  %d days checked, %d failures\n\n", $rtFail === 0 ? '[PASS]' : '[FAIL]', $days, $rtFail);
$fail += $rtFail;

// 4) leap-year pattern and month lengths
echo "4) Leap years & Esfand length (1390..1420)\n";
$expectedLeaps = [1391, 1395, 1399, 1403, 1408, 1412, 1416, 1420];
$mismatch = [];
for ($y = 1390; $y <= 1420; $y++) {
    $leap = MorilogJalaliV350::isLeap($y);
    $shouldBeLeap = in_array($y, $expectedLeaps, true);
    if ($leap !== $shouldBeLeap) { $mismatch[] = "{$y}: got " . ($leap ? 'leap' : 'common') . " expected " . ($shouldBeLeap ? 'leap' : 'common'); }
    $sum = 0;
    for ($m = 1; $m <= 12; $m++) { $sum += MorilogJalaliV350::monthLength($y, $m); }
    if ($sum !== ($leap ? 366 : 365)) { $mismatch[] = "{$y}: months sum {$sum}"; }
}
printf("   %s  %s\n\n", $mismatch === [] ? '[PASS]' : '[FAIL]', $mismatch === [] ? 'leap pattern matches reference' : implode('; ', $mismatch));
$fail += count($mismatch);

// 5) 1404 — the disputed year. This is the single most important assertion.
echo "5) Disputed year 1404 (the reason the exploratory algorithm was discarded)\n";
$nowruz1404 = MorilogJalaliV350::toGregorian(1404, 1, 1);
$leap1404 = MorilogJalaliV350::isLeap(1404);
$esfand1404 = MorilogJalaliV350::monthLength(1404, 12);
$ok1404 = $fmt($nowruz1404) === '2025-03-21' && $leap1404 === false && $esfand1404 === 29;
printf("   Nowruz 1404 = %s | leap = %s | Esfand length = %d\n",
    $fmt($nowruz1404), $leap1404 ? 'yes' : 'no', $esfand1404);
printf("   %s  expected 2025-03-21, not leap, Esfand 29 days (official equinox rule)\n\n", $ok1404 ? '[PASS]' : '[FAIL]');
if (!$ok1404) { $fail++; }
$results[] = ['check' => 'disputed_year_1404', 'nowruz' => $fmt($nowruz1404), 'leap' => $leap1404,
              'esfand_days' => $esfand1404, 'status' => $ok1404 ? 'PASS' : 'FAIL'];

// 6) invalid dates must throw / be detectable
echo "6) Invalid date rejection\n";
$invalid = [];
foreach ([[1404, 12, 30], [1403, 7, 31], [1400, 13, 1], [1400, 0, 1], [1400, 1, 32]] as [$y, $m, $d]) {
    $rejected = ($m < 1 || $m > 12 || $d < 1 || $d > MorilogJalaliV350::monthLength($y, $m));
    if (!$rejected) { $invalid[] = sprintf('%d-%02d-%02d accepted', $y, $m, $d); }
}
printf("   %s  %s\n\n", $invalid === [] ? '[PASS]' : '[FAIL]',
    $invalid === [] ? 'all invalid dates detected (incl. 1404-12-30, 1403-07-31)' : implode('; ', $invalid));
$fail += count($invalid);

// 7) NEGATIVE CONTROL — the 2820 algorithm must NOT match the golden set
echo "7) Negative control — 2820-year arithmetic must NOT match the golden set\n";
$ctrlFail = 0;
$ctrlDisagree = [];
foreach ($cases as $c) {
    [$jy] = array_map('intval', explode('-', $c['j']));
    if (substr($c['j'], -5) !== '01-01') { continue; }   // only 1 Farvardin rows are comparable
    $got = $fmt(Arithmetic2820::toGregorian($jy));
    if ($got !== $c['g']) { $ctrlDisagree[] = "{$jy}: 2820 says {$got}, references say {$c['g']}"; }
    // the control is "correct" only if it DISAGREES on the known bad years
    if (in_array($jy, [1404, 1437], true) && $got === $c['g']) { $ctrlFail++; }
}
foreach ($ctrlDisagree as $d) { echo "   [expected] {$d}\n"; }
printf("   %s  control correctly disagrees on %d year(s); must include 1404\n\n",
    ($ctrlFail === 0 && in_array(1404, array_map(static fn ($s) => (int) explode(':', $s)[0], $ctrlDisagree), true)) ? '[PASS]' : '[FAIL]',
    count($ctrlDisagree));
$fail += $ctrlFail;
$results[] = ['check' => 'negative_control_2820', 'disagreements' => $ctrlDisagree, 'status' => $ctrlFail === 0 ? 'PASS' : 'FAIL'];

// 8) supported-range guard
echo "8) Supported range guard (1300..1450 declared for CRM)\n";
$guarded = [];
foreach ([1299, 1451] as $y) {
    try {
        MorilogJalaliV350::jalaliCal($y);
        $guarded[] = "{$y} accepted by algorithm (package supports a wider range)";
    } catch (Throwable $e) {
        $guarded[] = "{$y} rejected";
    }
}
echo "   note: morilog supports -61..3177; the CRM policy range is enforced at application level\n";
echo "   ", implode(' | ', $guarded), "\n\n";

// ── summary + evidence ─────────────────────────────────────────────────────────
echo str_repeat('=', 96), "\n";
printf("RESULT: %s — %d failure(s)\n", $fail === 0 ? 'PASS' : 'FAIL', $fail);

$evidence = [
    'generated_at' => date('c'),
    'php_version' => PHP_VERSION,
    'algorithm_under_test' => 'morilog/jalali v3.5.0 (port of published CalendarUtils source)',
    'golden_set' => ['file' => 'docs/fixtures/jalali-golden-set.json',
                     'cases' => count($cases), 'generated_at' => $golden['generated_at']],
    'checks' => [
        'forward_jalali_to_gregorian' => ['failures' => $fwdFail, 'total' => count($cases)],
        'reverse_gregorian_to_jalali' => ['failures' => $revFail, 'total' => count($cases)],
        'roundtrip_days' => ['failures' => $rtFail, 'days' => $days],
        'leap_and_month_length' => ['failures' => count($mismatch)],
        'invalid_date_rejection' => ['failures' => count($invalid)],
        'negative_control_2820' => ['failures' => $ctrlFail, 'disagreements' => $ctrlDisagree],
    ],
    'disputed_year_1404' => ['nowruz_gregorian' => $fmt($nowruz1404), 'is_leap' => $leap1404, 'esfand_days' => $esfand1404],
    'scope_note' => 'Verifies the algorithm. Composers install of the package is NOT performed in this sandbox and remains blocked (T-INF-02).',
    'failures' => $fail,
    'verdict' => $fail === 0 ? 'PASS' : 'FAIL',
];
@mkdir(__DIR__ . '/../phase-1/evidence', 0775, true);
file_put_contents(__DIR__ . '/../phase-1/evidence/jalali-golden-verify.json',
    json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

exit($fail === 0 ? 0 : 1);
