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

    /**
     * The selectable project types, as `code => title`.
     *
     * This is what the project form offers and what the request validates
     * against, so retiring a type here really does stop new projects of that
     * kind. `Project::TYPES` stays the behavioural map — it is what decides
     * which detail fieldset a type has and which accesses block finalisation —
     * and it is also the fallback below.
     *
     * The fallback matters: if every type were deactivated at once the project
     * form would render an empty required select and no project could be
     * created at all, with no way back except the database. Falling back to the
     * two built-in kinds keeps the product usable after that mistake, while a
     * partial deactivation (the normal case) takes effect immediately.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $rows = static::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('title', 'code')
            ->all();

        return $rows === [] ? Project::TYPES : $rows;
    }
}
