<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OD-16 — one payroll month.
 *
 * The lifecycle is deliberate: draft → computed → published. Only `published`
 * payslips are final and only they produce a ledger entry. A month can be
 * recomputed while it is draft; after publishing it must be corrected by a new
 * version (payslip_versions keeps the old snapshot), never by silently editing
 * a figure someone was already paid.
 */
class PayrollPeriod extends Model
{
    protected $fillable = [
        'jalali_year', 'jalali_month', 'state', 'payroll_rule_id',
        'published_at', 'published_by',
    ];

    protected function casts(): array
    {
        return [
            'jalali_year' => 'integer',
            'jalali_month' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public const STATES = [
        'draft' => 'پیش‌نویس',
        'computed' => 'محاسبه‌شده',
        'published' => 'منتشرشده',
    ];

    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PayrollRule::class, 'payroll_rule_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** Month names, for the create form. 1 = فروردین. */
    public function monthOptions(): array
    {
        return self::MONTHS;
    }

    public static function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? (string) $month;
    }

    public function title(): string
    {
        return self::monthName((int) $this->jalali_month)
            . ' ' . \App\Support\Money::toPersianDigits((string) $this->jalali_year);
    }

    public function isPublished(): bool
    {
        return $this->state === 'published';
    }

    public function code(): string
    {
        return sprintf('PR-%d-%02d', $this->jalali_year, $this->jalali_month);
    }
}
