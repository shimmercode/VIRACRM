<?php

namespace Tests\Unit;

use App\Services\Reminders\ReminderSchedule;
use Carbon\CarbonImmutable;
use Tests\ViraTestCase;

/**
 * REQ-12.01–12.08 and the §14 acceptance criteria.
 *
 * The client document states the D-2 rule twice (§5-2 and §14) and makes it an
 * acceptance criterion. These tests are the executable form of that sentence.
 */
final class ReminderScheduleTest extends ViraTestCase
{
    public function run(): void
    {
        $this->firesExactlyTwoCalendarDaysBefore();
        $this->calendarNotSeconds();
        $this->monthBoundaries();
        $this->overdueNeverDisappears();
        $this->buckets();
        $this->idempotency();
        $this->dateChangeDoesNotDuplicate();
        $this->recurrence();
        $this->configuration();
    }

    private function firesExactlyTwoCalendarDaysBefore(): void
    {
        $schedule = new ReminderSchedule([2]);

        $this->assertSame(['2026-03-19'], $schedule->fireDatesFor(CarbonImmutable::parse('2026-03-21')));
        $this->assertSame(['2026-01-29'], $schedule->fireDatesFor(CarbonImmutable::parse('2026-01-31')));
        $this->assertSame(['2025-12-30'], $schedule->fireDatesFor(CarbonImmutable::parse('2026-01-01')));
    }

    private function calendarNotSeconds(): void
    {
        // The client document says "روز تقویمی" (calendar days), not 48 hours.
        $schedule = new ReminderSchedule([2]);

        // 2026-03-20 is the Iranian new year; the reminder must be a plain
        // date arithmetic result regardless of any clock change around it.
        $this->assertTrue($schedule->shouldFireOn(
            CarbonImmutable::parse('2026-03-21'),
            CarbonImmutable::parse('2026-03-19')
        ));

        $this->assertFalse($schedule->shouldFireOn(
            CarbonImmutable::parse('2026-03-21'),
            CarbonImmutable::parse('2026-03-20')
        ));

        // Time-of-day on the "today" value must not matter.
        $this->assertTrue($schedule->shouldFireOn(
            CarbonImmutable::parse('2026-03-21'),
            CarbonImmutable::parse('2026-03-19 23:59:59')
        ));
    }

    private function monthBoundaries(): void
    {
        $schedule = new ReminderSchedule([2]);

        // Crossing month and year boundaries.
        $this->assertSame(['2026-02-27'], $schedule->fireDatesFor(CarbonImmutable::parse('2026-03-01')));
        $this->assertSame(['2025-12-31'], $schedule->fireDatesFor(CarbonImmutable::parse('2026-01-02')));

        // Leap day.
        $this->assertSame(['2024-02-27'], $schedule->fireDatesFor(CarbonImmutable::parse('2024-02-29')));
        $this->assertSame(['2025-02-26'], $schedule->fireDatesFor(CarbonImmutable::parse('2025-02-28')));
    }

    private function overdueNeverDisappears(): void
    {
        $schedule = new ReminderSchedule([2]);
        $today = CarbonImmutable::parse('2026-04-10');

        // 40 days overdue and still reported as overdue, not dropped.
        $this->assertSame('overdue', $schedule->bucket(CarbonImmutable::parse('2026-03-01'), $today));
        $this->assertSame(-40, $schedule->daysUntilDue(CarbonImmutable::parse('2026-03-01'), $today));

        // One day overdue is still overdue.
        $this->assertSame('overdue', $schedule->bucket(CarbonImmutable::parse('2026-04-09'), $today));
        $this->assertSame(-1, $schedule->daysUntilDue(CarbonImmutable::parse('2026-04-09'), $today));
    }

    private function buckets(): void
    {
        $schedule = new ReminderSchedule([2]);
        $today = CarbonImmutable::parse('2026-06-15');

        $this->assertSame('today', $schedule->bucket($today, $today));
        $this->assertSame('future', $schedule->bucket($today->addDay(), $today));
        $this->assertSame('overdue', $schedule->bucket($today->subDay(), $today));
        $this->assertSame('done', $schedule->bucket($today->subDay(), $today, true),
            'a completed item leaves the list even if it was overdue');
        $this->assertSame(0, $schedule->daysUntilDue($today, $today));
        $this->assertSame(7, $schedule->daysUntilDue($today->addDays(7), $today));
    }

    private function idempotency(): void
    {
        $schedule = new ReminderSchedule([2]);
        $due = CarbonImmutable::parse('2026-05-10');

        $a = $schedule->idempotencyKey('invoice', 42, '1405-02', $due, 2);
        $b = $schedule->idempotencyKey('invoice', 42, '1405-02', $due, 2);

        $this->assertSame($a, $b, 'the same event must always produce the same key');
        $this->assertSame(64, strlen($a), 'key is a sha256 hex digest');

        // Different subjects, periods or days must not collide.
        $this->assertFalse($a === $schedule->idempotencyKey('invoice', 43, '1405-02', $due, 2));
        $this->assertFalse($a === $schedule->idempotencyKey('report', 42, '1405-02', $due, 2));
        $this->assertFalse($a === $schedule->idempotencyKey('invoice', 42, '1405-03', $due, 2));
        $this->assertFalse($a === $schedule->idempotencyKey('invoice', 42, '1405-02', $due->addDay(), 2));

        // Invoice and report reminders for the same project/date are independent (REQ-12.05).
        $this->assertFalse(
            $schedule->idempotencyKey('invoice', 42, '1405-02', $due, 2)
            === $schedule->idempotencyKey('report', 42, '1405-02', $due, 2),
            'invoice and report reminders must never share a key'
        );
    }

    private function dateChangeDoesNotDuplicate(): void
    {
        $schedule = new ReminderSchedule([2]);
        $original = CarbonImmutable::parse('2026-05-10');
        $moved = CarbonImmutable::parse('2026-05-17');

        $before = $schedule->idempotencyKey('invoice', 7, '1405-02', $original, 2);
        $after = $schedule->idempotencyKey('invoice', 7, '1405-02', $moved, 2);

        // Moving a due date must yield a new key (so the new reminder fires once)…
        $this->assertFalse($before === $after, 'moving the due date must not reuse the old key');

        // …while recomputing the moved date twice stays stable (no duplicate storm).
        $this->assertSame(
            $after,
            $schedule->idempotencyKey('invoice', 7, '1405-02', $moved, 2),
            'recomputing the same moved date is idempotent'
        );

        // And the new fire date follows the new due date.
        $this->assertSame(['2026-05-15'], $schedule->fireDatesFor($moved));
    }

    private function recurrence(): void
    {
        $schedule = new ReminderSchedule([2]);
        $start = CarbonImmutable::parse('2026-01-31');

        // Month-end clamping: 31 Jan + 1 month must not overflow into March.
        $this->assertSame('2026-02-28', $schedule->nextOccurrence($start, 'monthly')->toDateString());
        $this->assertSame('2026-04-30', $schedule->nextOccurrence(CarbonImmutable::parse('2026-03-31'), 'monthly')->toDateString());

        $this->assertSame('2026-04-30', $schedule->nextOccurrence(CarbonImmutable::parse('2026-01-31'), 'quarterly')->toDateString());
        $this->assertSame('2026-07-31', $schedule->nextOccurrence(CarbonImmutable::parse('2026-01-31'), 'semiannual')->toDateString());
        $this->assertSame('2027-01-31', $schedule->nextOccurrence(CarbonImmutable::parse('2026-01-31'), 'yearly')->toDateString());

        // Leap day + 1 year clamps to 28 February.
        $this->assertSame('2025-02-28', $schedule->nextOccurrence(CarbonImmutable::parse('2024-02-29'), 'yearly')->toDateString());

        $this->assertThrows(
            \InvalidArgumentException::class,
            fn () => $schedule->nextOccurrence($start, 'weekly')
        );
    }

    private function configuration(): void
    {
        // §15: the offset must be configurable, defaulting to 2.
        $custom = new ReminderSchedule([7, 3, 1]);
        $this->assertSame([1, 3, 7], $custom->offsets(), 'offsets are normalised and sorted');
        $this->assertSame(
            ['2026-05-03', '2026-05-07', '2026-05-09'],
            $custom->fireDatesFor(CarbonImmutable::parse('2026-05-10'))
        );

        // Duplicates collapse.
        $this->assertSame([2, 5], (new ReminderSchedule([5, 2, 2]))->offsets());

        // A zero offset means "on the due date", which the document allows to
        // be configured; it must not be confused with "no reminder".
        $sameDay = new ReminderSchedule([0]);
        $this->assertSame(['2026-05-10'], $sameDay->fireDatesFor(CarbonImmutable::parse('2026-05-10')));

        $this->assertThrows(\InvalidArgumentException::class, fn () => new ReminderSchedule([]));
        $this->assertThrows(\InvalidArgumentException::class, fn () => new ReminderSchedule([-1]));
    }
}
