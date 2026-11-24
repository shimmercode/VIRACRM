<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * §15 — project type dictionary.
 *
 * `projects.type` stores the code (`seo` | `design`). Keeping the dictionary in
 * a table means the owner can add a project type later without a migration or
 * a code change, which is what §15 asks for. Project::TYPES remains the
 * trusted list used for validation.
 */
class ProjectType extends Model
{
    protected $fillable = ['code', 'title', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
