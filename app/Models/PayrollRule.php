<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OD-16 — a payroll rule.
 *
 * SCOPE WARNING, AND IT IS THE POINT OF THIS CLASS
 * ------------------------------------------------
 * The client decision is explicit: payroll is FIXED SALARY + MANUALLY ENTERED
 * LINE ITEMS. There is no commission formula, no invented bonus coefficient,
 * and no automatic "percentage of project revenue" — the directive forbids
 * inventing one.
 *
 * This table therefore exists to describe handling of the *approved* additions
 * (overtime, deductions, advances) and to be an auditable, approved record of
 * any future formula the CLIENT defines. `is_approved` must be true before a
 * rule may be used to compute anything, so a well-meaning developer cannot
 * quietly add an unapproved formula and have it affect real pay.
 */
class PayrollRule extends Model
{
    protected $fillable = [
        'code', 'title', 'method', 'params', 'is_active', 'is_approved',
        'approved_by', 'approved_at', 'approved_version_of',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /** Only method the client approved: base salary plus manual lines. */
    public const METHOD_MANUAL = 'manual_lines';

    public const METHODS = [
        self::METHOD_MANUAL => 'حقوق ثابت + اقلام دستی (مصوب OD-16)',
    ];

    public function isUsable(): bool
    {
        return $this->is_active && $this->is_approved;
    }

    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
