<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OD-41 §19.2 — a fiscal period (دوره مالی).
 *
 * Closing is a sensitive, auditable act: closed periods reject new postings and
 * reopening one needs its own permission and its own audit row. The two events
 * are deliberately not the same permission.
 */
class FiscalPeriod extends Model
{
    protected $fillable = [
        'code', 'title', 'starts_on', 'ends_on', 'state', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public const STATES = ['open' => 'باز', 'closed' => 'بسته'];

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->state === 'open';
    }

    /** Which period contains a given date, if any. */
    public static function forDate(string $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }
}
