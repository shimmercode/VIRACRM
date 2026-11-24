<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * §15 — SEO package dictionary.
 *
 * A package is the commercial bundle an SEO project is sold on (keyword limit
 * plus a title). It is a configurable dictionary rather than a hard-coded enum
 * because §15 requires the agency to be able to add and rename packages without
 * a code change.
 */
class SeoPackage extends Model
{
    protected $fillable = [
        'code', 'title', 'description', 'keyword_limit', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'keyword_limit' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(SeoProjectDetail::class);
    }
}
