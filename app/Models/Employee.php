<?php

namespace App\Models;

use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * §15 — an employee record.
 *
 * The table already existed and held nothing. Note what is NOT here: there is no
 * `salary` column. Per OD-16 the payroll figure comes from a payslip's fixed
 * base plus manually entered lines, and an employee-level "salary" field would
 * be a second, competing source of truth that silently goes stale.
 *
 * Personal data (national id, address, mobile) is restricted at the policy layer,
 * not by hiding columns in the view.
 */
class Employee extends Model
{
    use HasDomainConstraints;

    /**
     * Columns whose value must come from the model's own constant.
     *
     * @return array<string, string>
     */
    protected function domainColumns(): array
    {
        return [
            'status' => 'STATUSES',
        ];
    }

    use SoftDeletes;

    protected $fillable = [
        'code', 'user_id', 'department_id', 'first_name', 'last_name',
        'national_id', 'mobile', 'email', 'address', 'position',
        'manager_id', 'start_date', 'end_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public const STATUSES = [
        'active' => 'شاغل',
        'on_leave' => 'در مرخصی',
        'suspended' => 'تعلیق',
        'terminated' => 'پایان همکاری',
    ];

    /**
     * REQ-05.05 / REQ-09.07 — this expert's project assignments.
     *
     * @return HasMany<ProjectAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function displayName(): string
    {
        $name = $this->fullName();

        return $name !== '' ? $name : ($this->code ?? 'کارمند #' . $this->id);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
