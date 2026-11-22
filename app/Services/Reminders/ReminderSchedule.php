<?php

namespace App\Services\Reminders;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * REQ-12.01–12.08 — the automatic invoice/report reminder engine.
 *
 * THE RULE FROM THE CLIENT DOCUMENT (§5-2, §14):
 *   "یادآوری فاکتور و گزارش دقیقاً دو روز تقویمی قبل از موعد"
 *   — exactly two *calendar* days before the due date, and the acceptance
 *     criterion is that this is exact.
 *
 * WHY THIS CLASS IS PURE: the correctness of this rule is the single most
 * testable business fact in the system, and it must not depend on a running
 * scheduler, a timezone database or a database row to be verified. Given a due
 * date and a configuration it returns the exact set of fire dates. Everything
 * else (queues, notifications, the کارتابل) consumes that answer.
 *
 * CALENDAR INDEPENDENCE: offsets are counted in days, not seconds. The offset
 * of 2 days is applied with Carbon's addDays/subDays on a date-only value, so
 * a DST change or a 31-day month cannot shift the reminder by a day.
 */
final class ReminderSchedule
{
    /** @var list<int> */
    private array $offsets;

    /**
     * @param  list<int>  $offsets  days before the due date on which to fire.
     *                              Default [2] per the client document §15.
     */
    public function __construct(array $offsets = [2])
    {
        if ($offsets === []) {
            throw new InvalidArgumentException('At least one reminder offset is required.');
        }

        foreach ($offsets as $offset) {
            if (! is_int($offset) || $offset < 0) {
                throw new InvalidArgumentException('Reminder offsets must be non-negative integers.');
            }
        }

        sort($offsets);
        $this->offsets = array_values(array_unique($offsets));
    }

    /** @return list<int> */
    public function offsets(): array
    {
        return $this->offsets;
    }

    /**
     * The dates on which a reminder should fire for one due date.
     *
     * Returns date strings (Y-m-d) so results are directly comparable with
     * database DATE columns and cannot be confused by time-of-day.
     *
     * @return list<string>
     */
    public function fireDatesFor(CarbonImmutable $dueDate): array
    {
        $due = $dueDate->startOfDay();
        $dates = [];

        foreach ($this->offsets as $offset) {
            $dates[] = $due->subDays($offset)->toDateString();
        }

        sort($dates);

        return array_values(array_unique($dates));
    }

    /**
     * Is a reminder due to fire on $today for a given due date?
     * Used by the scheduler to decide what to enqueue for today.
     */
    public function shouldFireOn(CarbonImmutable $dueDate, CarbonImmutable $today): bool
    {
        return in_array($today->toDateString(), $this->fireDatesFor($dueDate), true);
    }

    /**
     * Days between today and the due date. Negative means overdue.
     *
     * The client document is emphatic that overdue items must never be hidden
     * (§5-2, §14): an overdue invoice keeps returning to the کارتابل until
     * somebody records an outcome.
     */
    public function daysUntilDue(CarbonImmutable $dueDate, CarbonImmutable $today): int
    {
        return (int) $today->startOfDay()->diffInDays($dueDate->startOfDay(), false);
    }

    /**
     * Classify the reminder for the کارتابل buckets required by §2:
     * today / future / overdue / done.
     */
    public function bucket(CarbonImmutable $dueDate, CarbonImmutable $today, bool $done = false): string
    {
        if ($done) {
            return 'done';
        }

        $days = $this->daysUntilDue($dueDate, $today);

        return match (true) {
            $days < 0 => 'overdue',
            $days === 0 => 'today',
            default => 'future',
        };
    }

    /**
     * Idempotency key for one reminder event.
     *
     * REQ-12.07: running the scheduler twice must not create duplicates, and
     * REQ-12.06: changing a date must recompute without duplicating.
     *
     * Including the fire date means:
     *   - the same due date on the same day yields the same key → deduplicated;
     *   - if the due date moves, the period and computed fire date change, so a
     *     genuinely new reminder is allowed while the old one keeps its history.
     */
    public function idempotencyKey(
        string $eventType,
        int|string $subjectId,
        string $period,
        CarbonImmutable $dueDate,
        int $offset,
    ): string {
        $fireDate = $dueDate->startOfDay()->subDays($offset)->toDateString();

        return hash('sha256', implode('|', [
            $eventType,
            (string) $subjectId,
            $period,
            $fireDate,
            (string) $offset,
        ]));
    }

    /**
     * Next occurrence of a recurring invoice/report after a given date.
     * REQ-12.05: the document allows recurring periods (e.g. monthly) and
     * requires invoice and report reminders to be independent of each other.
     *
     * Month-stepping is clamped: a due date on the 31st recurring monthly lands
     * on the last day of a shorter month instead of silently overflowing into
     * the following month (which is how billing dates drift).
     */
    public function nextOccurrence(CarbonImmutable $from, string $period): CarbonImmutable
    {
        return match ($period) {
            'monthly' => $this->addMonthsClamped($from, 1),
            'quarterly' => $this->addMonthsClamped($from, 3),
            'semiannual' => $this->addMonthsClamped($from, 6),
            'yearly' => $this->addMonthsClamped($from, 12),
            default => throw new InvalidArgumentException("Unsupported recurrence period: {$period}"),
        };
    }

    private function addMonthsClamped(CarbonImmutable $date, int $months): CarbonImmutable
    {
        $day = $date->day;
        $target = $date->startOfDay()->addMonthsNoOverflow($months);

        // addMonthsNoOverflow already clamps to the last valid day; assert it so
        // a future Carbon change cannot silently alter billing dates.
        return $target->day === $day || $target->day === $target->daysInMonth
            ? $target
            : $target->setDay(min($day, $target->daysInMonth));
    }
}
