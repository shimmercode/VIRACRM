<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-05.04 — design-project specifics (§5-3).
 *
 * The counterpart to SeoProjectDetail. A design project records its live domain
 * and the kind of site being built; the scope of work goes in `services`.
 */
class DesignProjectDetail extends Model
{
    protected $fillable = [
        'project_id', 'domain', 'site_type', 'services', 'notes',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function siteTypeLabel(): string
    {
        return $this->site_type ?? '—';
    }
}
