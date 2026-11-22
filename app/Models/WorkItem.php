<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-05.01–05.06 — کارتابل item.
 *
 * §5 defines these as the recurring operational obligations of a project:
 * a monthly invoice and a monthly report. A row therefore has a `kind` and a
 * `period`, not a free-text title.
 *
 * The `title` accessor derives a human label instead of storing one. Storing it
 * would let the label drift from the kind, which is the field the scheduler
 * actually acts on.
 */
class WorkItem extends Model
{
    protected $fillable = [
        'project_id', 'kind', 'due_date', 'period', 'state', 'invoice_number',
        'invoice_amount', 'invoice_issue_date', 'document_attachment_id',
        'done_at', 'done_by', 'completion_notes', 'recurrence',
        'generated_from_id', 'reminder_offset_days',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'invoice_issue_date' => 'date',
            'done_at' => 'datetime',
            'invoice_amount' => 'integer',
        ];
    }

    public const KINDS = [
        'invoice' => 'صورتحساب',
        'report' => 'گزارش ماهانه',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    protected function title(): Attribute
    {
        return Attribute::get(function (): string {
            $kind = self::KINDS[$this->kind] ?? $this->kind;
            $period = $this->period;

            if ($period === null) {
                return $kind;
            }

            // Stored as 1405-02 (Jalali); shown as «اردیبهشت ۱۴۰۵» so the list
            // reads like a calendar rather than a database dump.
            //
            // GUARD: a four-digit year above 1600 is a Gregorian year that was
            // written into this column by mistake. Rendering it with Persian
            // month names would silently produce "بهمن ۲۰۲۶" — wrong twice.
            // Returning the raw value makes the mistake visible instead.
            // Accept both separators: the schema comment shows "1405-02" but
            // JalaliDate::formatAscii() produces "1405/02". Normalising here
            // means neither producer has to care.
            [$year, $month] = array_pad(preg_split('~[-/]~', $period), 2, null);

            if ((int) $year > 1600) {
                return $kind . ' ' . $period;
            }
            $monthNames = [
                1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
                5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
                9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
            ];

            $monthLabel = $monthNames[(int) $month] ?? $month;

            return $kind . ' ' . $monthLabel . ' ' . \App\Support\Money::toPersianDigits((string) $year);
        });
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('done_at');
    }

    public function scopeOverdue($query)
    {
        return $query->open()->whereDate('due_date', '<', now()->startOfDay());
    }

    public function isOverdue(): bool
    {
        return $this->done_at === null && $this->due_date?->lt(now()->startOfDay());
    }
}
