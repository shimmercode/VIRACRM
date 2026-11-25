<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OD-16 — one employee's pay for one month.
 *
 * The formula, approved by the client and not invented here:
 *
 *   total_earnings   = base_salary + bonus + overtime + Σ(manual items with amount > 0)
 *   total_deductions = deductions + advance + Σ(|manual items with amount < 0|)
 *   net_payable      = total_earnings − total_deductions
 *
 * `bonus`, `overtime`, `advance` and `deductions` are MANUAL figures an
 * accountant types. None of them is computed from project revenue, because the
 * client explicitly refused an invented commission formula.
 *
 * `projects_total` is recorded for context only and is NEVER part of the
 * formula above. That distinction is enforced by the calculation living in
 * PayrollCalculator and not in this model.
 */
class Payslip extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'base_salary', 'worked_days',
        'bonus', 'overtime', 'advance', 'deductions', 'input_notes',
        'projects_total', 'total_earnings', 'total_deductions', 'net_payable',
        'payroll_rule_id', 'computed_at', 'computed_by', 'attachment_id',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'integer',
            'worked_days' => 'integer',
            'bonus' => 'integer',
            'overtime' => 'integer',
            'advance' => 'integer',
            'deductions' => 'integer',
            'projects_total' => 'integer',
            'total_earnings' => 'integer',
            'total_deductions' => 'integer',
            'net_payable' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PayslipVersion::class);
    }

    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }
}
