<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** REQ-07.01–07.04 — notes/tasks, optionally attached to a customer, contract or project. */
class Note extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'due_date', 'priority', 'status',
        'customer_id', 'contract_id', 'project_id',
        'done_at', 'done_by', 'created_by', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'done_at' => 'datetime',
        ];
    }

    public const PRIORITIES = ['low' => 'کم', 'normal' => 'عادی', 'high' => 'زیاد', 'urgent' => 'فوری'];

    public const STATUSES = ['open' => 'باز', 'done' => 'انجام‌شده', 'cancelled' => 'لغو شده'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
