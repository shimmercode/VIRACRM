<?php

namespace Database\Seeders;

use App\Models\FiscalPeriod;
use Illuminate\Database\Seeder;

/**
 * OD-41 §19.2 — fiscal periods.
 *
 * Seeded for the current Jalali year and the two around it, because a ledger with
 * no open period accepts no postings at all. Dates are computed, not written as
 * literals, so the seeder does not rot.
 */
class FiscalPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $current = \App\Support\JalaliDate::fromCarbon(\Carbon\CarbonImmutable::instance(now()))->year;

        foreach (range($current - 1, $current + 1) as $year) {
            // A Jalali year starts on 1 Farvardin. Rather than hard-code the
            // Gregorian equivalent, convert through the project's own calendar
            // code — the same one the rest of the app trusts.
            $start = \App\Support\JalaliDate::toGregorian($year, 1, 1);
            $end = \App\Support\JalaliDate::toGregorian($year, 12, \App\Support\JalaliDate::monthLength($year, 12));

            FiscalPeriod::updateOrCreate(
                ['code' => (string) $year],
                [
                    'title' => 'سال مالی ' . \App\Support\Money::toPersianDigits((string) $year),
                    'starts_on' => sprintf('%04d-%02d-%02d', $start[0], $start[1], $start[2]),
                    'ends_on' => sprintf('%04d-%02d-%02d', $end[0], $end[1], $end[2]),
                    // An existing (possibly closed) period must never be silently
                    // reopened by re-seeding.
                    'state' => FiscalPeriod::query()->where('code', (string) $year)->value('state') ?? 'open',
                ],
            );
        }
    }
}
