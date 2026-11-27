<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    use HasDomainConstraints;

    /**
     * Columns whose value must come from the model's own constant.
     *
     * @return array<string, string>
     */
    protected function domainColumns(): array
    {
        return [
            /*
             * Not 'TYPES'. §15 makes the project type list something the owner
             * defines in `project_types`, and ProjectType::options() is the same
             * source the form and the request validator use — so the model
             * guard, the dropdown and the validation rule cannot disagree.
             * TYPES stays as the behavioural map (which detail fieldset, which
             * accesses block finalisation) and as the fallback inside options().
             */
            'type' => [ProjectType::class, 'options'],
            'status' => 'STATUSES',
        ];
    }

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

    /**
     * REQ-05.05 — the experts assigned to this project.
     *
     * @return HasMany<ProjectAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function seoDetails(): HasOne
    {
        return $this->hasOne(SeoProjectDetail::class);
    }

    public function designDetails(): HasOne
    {
        return $this->hasOne(DesignProjectDetail::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(ProjectKeyword::class);
    }

    /**
     * §6 — the project's accesses (WordPress, host, domain, …).
     *
     * Added for the finalisation gate, which has to ask "which mandatory kinds
     * does this project already have?" — a question the credential controller
     * was answering with its own ad-hoc query.
     *
     * @return HasMany<ProjectCredential>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(ProjectCredential::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /**
     * §9 — filter the project list by free text, customer, type, status and
     * date range.
     *
     * The search covers the project title, its code, and the customer name,
     * because "find the SEO project for X" is the common lookup and the user
     * does not always remember which of the two they named it after.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters): void {
                $term = '%' . $filters['q'] . '%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('company_name', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when(filled($filters['customer_id'] ?? null), fn (Builder $q) => $q
                ->where('customer_id', $filters['customer_id']))
            ->when(filled($filters['type'] ?? null), fn (Builder $q) => $q
                ->where('type', $filters['type']))
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q
                ->where('status', $filters['status']))
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q
                ->whereDate('start_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q
                ->whereDate('start_date', '<=', $filters['to']));
    }

    /** Projects that still need work — used by the dashboard. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'active', 'paused']);
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
