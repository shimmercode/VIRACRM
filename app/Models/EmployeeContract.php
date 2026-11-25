<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** §15 — an employment contract attached to an employee. */
class EmployeeContract extends Model
{
    protected $fillable = [
        'employee_id', 'attachment_id', 'title', 'start_date', 'end_date',
        'agreed_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'agreed_amount' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isActive(): bool
    {
        return $this->end_date === null || $this->end_date->isFuture();
    }
}
