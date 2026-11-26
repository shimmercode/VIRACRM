<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-05.05 / REQ-09.07 — which expert works on which project.
 *
 * WHY THIS MODEL EXISTED ONLY AS A TABLE
 * --------------------------------------
 * `project_assignments` was created by the migration and populated by the
 * review-environment demo seeder, and `ReviewController` read it with a raw
 * query builder. The production application had no model for it, no route, and
 * no screen — so §5-5 «اتصال پروژه به کارشناس و مشاهده تعداد پروژه‌های فعال هر
 * کارشناس» had a table and nothing else.
 *
 * WHAT THE COLUMNS MEAN
 * ---------------------
 * `amount` is the per-project fee for this expert on this project, and
 * `coefficient` is a percentage in basis points (100 = 100%). Both feed payroll,
 * which is why the report that reads them treats them as sensitive.
 *
 * `is_active` distinguishes a current assignment from a historical one. An
 * expert who left a project in Mehr must not still be counted against their
 * workload in Aban, but the row has to survive so the payroll period that used
 * it stays reconstructible.
 */
class ProjectAssignment extends Model
{
    protected $fillable = [
        'project_id', 'employee_id', 'role', 'amount', 'coefficient',
        'started_on', 'ended_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'coefficient' => 'integer',
            'is_active' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /** @return BelongsTo<Project, self> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Employee, self> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Assignments that count towards a workload figure. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The fee this expert earns on this project, in rials.
     *
     * `amount` is the agreed per-project figure; `coefficient` scales it when an
     * expert is on a project part-time or at a negotiated share. Applying it
     * here rather than in the view keeps one definition of the number.
     */
    public function effectiveAmount(): int
    {
        return (int) round($this->amount * max(0, $this->coefficient) / 100);
    }
}
