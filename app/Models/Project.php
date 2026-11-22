<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REQ-05.01 — project.
 *
 * Two specialised detail tables (`seo_project_details`, `design_project_details`)
 * carry the type-specific fields, so this model stays the shared header. That
 * split already exists in the schema from 2-B and is kept.
 */
class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'customer_id', 'contract_id', 'type', 'title', 'status',
        'amount', 'start_date', 'end_date', 'owner_user_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'integer',
        ];
    }

    public const TYPES = ['seo' => 'سئو', 'design' => 'طراحی'];

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'active' => 'فعال',
        'paused' => 'متوقف',
        'completed' => 'پایان‌یافته',
        'cancelled' => 'لغو شده',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
