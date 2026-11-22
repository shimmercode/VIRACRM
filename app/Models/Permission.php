<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * REQ-10.03 — permission scoped to one area of the system.
 *
 * The `is_sensitive` flag marks financial areas and secret-access, which §10
 * requires to be grantable independently of ordinary record access.
 */
class Permission extends Model
{
    protected $fillable = ['code', 'area', 'action', 'title', 'is_sensitive'];

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
