<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OD-16 — a manual line on a payslip.
 *
 * DIRECTION IS A COLUMN, NOT A SIGN
 * ---------------------------------
 * `amount` is unsigned in the schema (a payslip figure is never negative), so
 * "is this an earning or a deduction" cannot be inferred from the number.
 * `direction` carries it explicitly: earning adds to total_earnings, deduction
 * adds to total_deductions. An earlier draft used negative amounts, which works
 * on SQLite and fails on MariaDB with an out-of-range error — the kind of bug
 * that only appears in production, so the design avoids it rather than testing
 * around it.
 */
class PayrollItem extends Model
{
    protected $fillable = ['payslip_id', 'project_id', 'label', 'direction', 'amount', 'coefficient'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'coefficient' => 'integer',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public const EARNING = 'earning';
    public const DEDUCTION = 'deduction';

    public const DIRECTIONS = [
        self::EARNING => 'دریافتی (افزایش حقوق)',
        self::DEDUCTION => 'کسورات (کاهش حقوق)',
    ];

    public function isEarning(): bool
    {
        return $this->direction !== self::DEDUCTION;
    }

    /** Signed value for display only — never used by the calculator. */
    public function signedAmount(): int
    {
        return $this->isEarning() ? (int) $this->amount : -1 * (int) $this->amount;
    }
}
