<?php
/**
 * VIRA CRM — D-2 reminder day-math verifier
 * -----------------------------------------
 * Proves that "two calendar days before due date" is computed WITHOUT any Jalali conversion,
 * so the reminder engine cannot be affected by the calendar-policy decision (Q-06).
 *
 * Design under test (REQ-12.01, REQ-12.08):
 *   due_date     : DATE (Gregorian, no time component)
 *   timezone     : Asia/Tehran (fixed +03:30, no DST since 2022)
 *   fire_date    : due_date - lead_days  (pure date subtraction)
 *   run guard    : job may only create the event when today's Tehran calendar day == fire_date
 *   idempotency  : unique_key = sha256(event_type|subject_id|period|fire_date)
 *
 * Scope note: this verifies the *day arithmetic*, not the scheduler itself (no Laravel/Artisan
 * in this sandbox — see blocked test T-SCH-01).
 *
 * Run: node docs/tools/php-cli.mjs docs/src/d2-daymath-verify.php
 */

declare(strict_types=1);

final class DayMath
{
    public const TZ = 'Asia/Tehran';

    /** fire_date = due_date - lead_days, computed on calendar days only. */
    public static function fireDate(string $dueDate, int $leadDays): string
    {
        $d = new DateTimeImmutable($dueDate . ' 00:00:00', new DateTimeZone(self::TZ));
        return $d->modify(sprintf('-%d days', $leadDays))->format('Y-m-d');
    }

    /** The Tehran calendar day of a given instant (used by the job guard). */
    public static function tehranDay(string $instantUtc): string
    {
        return (new DateTimeImmutable($instantUtc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(self::TZ))
            ->format('Y-m-d');
    }

    /**
     * A reminder is due to fire at a given instant only if the Tehran calendar day of that
     * instant equals the computed fire_date. The hour of execution is therefore irrelevant.
     */
    public static function shouldFire(string $instantUtc, string $dueDate, int $leadDays): bool
    {
        return self::tehranDay($instantUtc) === self::fireDate($dueDate, $leadDays);
    }

    public static function eventKey(string $type, int $subjectId, string $period, string $fireDate): string
    {
        return hash('sha256', implode('|', [$type, $subjectId, $period, $fireDate]));
    }

    public static function dueStatus(string $todayTehran, string $dueDate): string
    {
        return match (true) {
            $todayTehran < $dueDate => 'upcoming',
            $todayTehran === $dueDate => 'due',
            default => 'overdue',
        };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
$checks = [];
$fail = 0;
$assert = static function (string $name, bool $ok, string $detail) use (&$checks, &$fail): void {
    $checks[] = ['check' => $name, 'status' => $ok ? 'PASS' : 'FAIL', 'detail' => $detail];
    if (!$ok) { $fail++; }
    printf("%-8s %-46s %s\n", $ok ? '[PASS]' : '[FAIL]', $name, $detail);
};

echo "VIRA CRM — D-2 day-math verifier (PHP ", PHP_VERSION, ")\n", str_repeat('=', 96), "\n";

// 1) Basic D-2 arithmetic
$cases = [
    ['2026-03-21', '2026-03-19', 'Nowruz 1405 boundary'],
    ['2026-01-01', '2025-12-30', 'Gregorian year boundary'],
    ['2024-03-01', '2024-02-28', 'Gregorian leap year (Feb 29)'],
    ['2025-03-21', '2025-03-19', 'Nowruz 1404 (astronomical)'],
    ['2026-01-31', '2026-01-29', 'month boundary'],
    ['2026-12-31', '2026-12-29', 'year end'],
];
foreach ($cases as [$due, $expected, $label]) {
    $got = DayMath::fireDate($due, 2);
    $assert("D-2 {$label}", $got === $expected, "due={$due} fire={$got} expected={$expected}");
}

// 2) Calendar-day semantics, not 48 floating hours
$assert(
    'D-2 is a date difference, not 48h',
    DayMath::fireDate('2026-03-21', 2) === '2026-03-19'
        && DayMath::shouldFire('2026-03-19T00:05:00+03:30', '2026-03-21', 2) === true
        && DayMath::shouldFire('2026-03-19T23:59:00+03:30', '2026-03-21', 2) === true
        && DayMath::shouldFire('2026-03-18T23:59:00+03:30', '2026-03-21', 2) === false,
    '00:05 and 23:59 on the fire day both fire; previous day never fires'
);

// 3) Midnight boundary: the same UTC instant maps to different Tehran days
$assert(
    'Tehran day of 00:30 local is still the same day',
    DayMath::tehranDay('2026-03-20T21:00:00Z') === '2026-03-21',
    '2026-03-20 21:00Z == 2026-03-21 00:30 Tehran'
);
$assert(
    'UTC day differs from Tehran day (proves why timezone must be pinned)',
    substr('2026-03-20T21:00:00Z', 0, 10) !== DayMath::tehranDay('2026-03-20T21:00:00Z'),
    'UTC says 2026-03-20, Tehran says 2026-03-21'
);

// 4) DST: Iran has had none since 2022 — offset must be constant
$tz = new DateTimeZone('Asia/Tehran');
$offsets = [];
foreach (['2026-01-15', '2026-03-21', '2026-07-15', '2026-12-15'] as $day) {
    $offsets[] = (new DateTimeImmutable($day . ' 12:00', $tz))->getOffset();
}
$assert('No DST: constant +03:30 offset all year', count(array_unique($offsets)) === 1 && $offsets[0] === 12600,
    'offsets=' . implode(',', array_unique($offsets)));

// 5) Scheduler idempotency — same event, many runs, one key
$k1 = DayMath::eventKey('invoice_due', 42, '1405-01', '2026-03-19');
$k2 = DayMath::eventKey('invoice_due', 42, '1405-01', '2026-03-19');
$k3 = DayMath::eventKey('report_due', 42, '1405-01', '2026-03-19');
$k4 = DayMath::eventKey('invoice_due', 42, '1405-02', '2026-03-19');
$assert('Same event yields identical unique key', $k1 === $k2, substr($k1, 0, 16) . '…');
$assert('Invoice vs report are independent keys', $k1 !== $k3, 'different event_type');
$assert('Different period yields different key', $k1 !== $k4, 'different period');
$assert('Unique key is 64 hex chars (sha256)', strlen($k1) === 64 && ctype_xdigit($k1), 'length=' . strlen($k1));

// 6) Status transitions
$assert('Status: upcoming', DayMath::dueStatus('2026-03-18', '2026-03-21') === 'upcoming', 'before due');
$assert('Status: due', DayMath::dueStatus('2026-03-21', '2026-03-21') === 'due', 'on due date');
$assert('Status: overdue', DayMath::dueStatus('2026-03-22', '2026-03-21') === 'overdue', 'after due');

// 7) Multiple periods per month must coexist
$periods = ['1405-01', '1405-02', '1405-03'];
$keys = array_map(static fn ($p) => DayMath::eventKey('invoice_due', 7, $p, '2026-03-19'), $periods);
$assert('Multiple periods -> distinct keys', count(array_unique($keys)) === 3, implode(', ', array_map(static fn ($k) => substr($k, 0, 8), $keys)));

// 8) Date change must produce a NEW key (no stale reminder reused)
$before = DayMath::eventKey('invoice_due', 9, '1405-01', DayMath::fireDate('2026-03-21', 2));
$after  = DayMath::eventKey('invoice_due', 9, '1405-01', DayMath::fireDate('2026-04-05', 2));
$assert('Changed due date -> new key (old reminder cannot resurface)', $before !== $after,
    DayMath::fireDate('2026-03-21', 2) . ' -> ' . DayMath::fireDate('2026-04-05', 2));

// 9) Leap-day safety for 2028 (Feb 29 exists)
$assert('2028-03-01 minus 2 days crosses Feb 29', DayMath::fireDate('2028-03-01', 2) === '2028-02-28', DayMath::fireDate('2028-03-01', 2));
$assert('2027-03-01 minus 2 days has no Feb 29', DayMath::fireDate('2027-03-01', 2) === '2027-02-27', DayMath::fireDate('2027-03-01', 2));

// 10) independence from any Jalali library — static check of the DayMath class body only
$src = (string) file_get_contents(__FILE__);
$bodyStart = strpos($src, 'final class DayMath');
$bodyEnd   = strpos($src, '// ─────────────────────────────────────────────────────────────────────────────');
$body      = ($bodyStart !== false && $bodyEnd !== false) ? substr($src, $bodyStart, $bodyEnd - $bodyStart) : '';
$jalaliCalls = preg_match_all('/MorilogJalali|jalaliCal|j2d\(|d2j\(|toJalali|toGregorian/', $body);
$assert('Day math contains no Jalali conversion calls', $jalaliCalls === 0,
    'Jalali call-sites inside DayMath class body: ' . (int) $jalaliCalls);

echo str_repeat('=', 96), "\n";
printf("RESULT: %s — %d failure(s)\n", $fail === 0 ? 'PASS' : 'FAIL', $fail);

$evidence = [
    'generated_at' => date('c'),
    'php_version' => PHP_VERSION,
    'timezone' => DayMath::TZ,
    'design' => 'fire_date = due_date - lead_days on Gregorian DATE values; job guard compares Tehran calendar day',
    'scope_note' => 'Verifies day arithmetic and keying. Does not verify the Laravel scheduler (no Artisan in sandbox; T-SCH-01 blocked).',
    'checks' => $checks,
    'failures' => $fail,
    'verdict' => $fail === 0 ? 'PASS' : 'FAIL',
];
@mkdir(__DIR__ . '/../phase-1/evidence', 0775, true);
file_put_contents(__DIR__ . '/../phase-1/evidence/d2-daymath-verify.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

exit($fail === 0 ? 0 : 1);
