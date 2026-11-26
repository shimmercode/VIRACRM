<?php

namespace App\Models;

use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-05.02 — SEO-specific fields for a project.
 *
 * These live in their own table so `projects` stays the shared header for both
 * project types. A design project simply has no row here.
 *
 * Why a separate row at all: §5 requires the SEO form to ask for an SEO package
 * and a keyword count, and the design form to ask for a domain and site type.
 * Making every column nullable on one wide table would let an SEO project carry
 * a domain and a design project carry a keyword count, with nothing to prevent
 * it. A row that simply does not exist cannot hold a nonsense value.
 */
class SeoProjectDetail extends Model
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
            'recurrence' => 'RECURRENCES',
        ];
    }

    protected $fillable = [
        'project_id', 'seo_package_id', 'keyword_count',
        'recurrence', 'reminder_offset_days',
    ];

    protected function casts(): array
    {
        return [
            'keyword_count' => 'integer',
            'reminder_offset_days' => 'integer',
        ];
    }

    /** §5-1: how often the invoice/report work items repeat. */
    public const RECURRENCES = [
        'monthly' => 'ماهانه',
        'quarterly' => 'فصلی',
        'semiannual' => 'شش‌ماهه',
        'yearly' => 'سالانه',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SeoPackage::class, 'seo_package_id');
    }
}
