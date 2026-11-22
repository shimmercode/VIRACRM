<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REQ-04.01–04.10 — contract.
 *
 * `expiring` is derived, never stored (see ContractStatus for the reasoning).
 * The scope below is the one place that decides whether a contract is in the
 * warning window, so the dashboard, the list filter and the expiry badge can
 * never disagree.
 */
class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number', 'customer_id', 'contract_type_id', 'template_id',
        'title', 'description', 'party_one_name', 'party_two_name',
        'party_one_address', 'party_two_address', 'amount',
        'start_date', 'end_date', 'status', 'rendered_body', 'finalized_at',
        'finalized_by', 'renewed_from_id', 'owner_user_id', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'finalized_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class)->orderByDesc('version');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ContractFile::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    // ── Derived ──────────────────────────────────────────────────────────

    /** Days until the end date. Negative means already past. */
    protected function daysToExpiry(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->end_date === null) {
                return null;
            }

            return (int) CarbonImmutable::now()->startOfDay()
                ->diffInDays($this->end_date->startOfDay(), false);
        });
    }

    /**
     * Is this contract inside the configured warning window?
     * The thresholds come from settings (§2 and §15), defaulting to 30 days.
     */
    public function isExpiringSoon(?int $days = null): bool
    {
        if (! $this->status->isLive() || $this->end_date === null) {
            return false;
        }

        $days ??= (int) (Setting::get('contracts.expiry_warning_days') ?? 30);
        $toExpiry = $this->days_to_expiry;

        return $toExpiry !== null && $toExpiry >= 0 && $toExpiry <= $days;
    }

    public function amountAsMoney(): Money
    {
        return Money::fromRial((int) $this->amount);
    }

    /**
     * The status to *display*, which folds in the derived expiring state.
     * The database status stays untouched.
     */
    public function displayStatus(): ContractStatus
    {
        return $this->isExpiringSoon() ? ContractStatus::Expiring : $this->status;
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** §9: filter the contract bank by customer, type, status and date range. */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters): void {
                $term = '%' . $filters['q'] . '%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('number', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('company_name', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when(filled($filters['customer_id'] ?? null), fn (Builder $q) => $q
                ->where('customer_id', $filters['customer_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q
                ->where('status', $filters['status']))
            ->when(filled($filters['type'] ?? null), fn (Builder $q) => $q
                ->whereHas('contractType', fn (Builder $t) => $t->where('code', $filters['type'])))
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q
                ->whereDate('start_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q
                ->whereDate('start_date', '<=', $filters['to']));
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value]);
    }

    /** Contracts whose end date falls inside the warning window. */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->live()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->startOfDay())
            ->whereDate('end_date', '<=', now()->startOfDay()->addDays($days));
    }
}
