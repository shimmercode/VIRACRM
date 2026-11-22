<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * REQ-10.02 — role. The client document defers the exact role list to the owner
 * ("نقش‌های نمونه آینده، فعال‌سازی با تأیید کارفرما"), so roles are data, not an
 * enum. Seeds create a conservative baseline; the owner can rename or add.
 */
class Role extends Model
{
    protected $fillable = ['code', 'title', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Replace this role's permissions, given permission *codes*.
     *
     * Codes rather than ids because the seeder and any future permission editor
     * think in codes ('contracts.view_financials'), and resolving them here
     * keeps id lookups out of every call site. Unknown codes are ignored but
     * reported, so a typo in the seeder is visible rather than silent.
     *
     * @param  list<string>  $codes
     * @return list<string>  codes that did not resolve to a permission
     */
    public function syncPermissions(array $codes): array
    {
        $permissions = Permission::query()->whereIn('code', $codes)->get();
        $found = $permissions->pluck('code')->all();

        $this->permissions()->sync($permissions->pluck('id')->all());

        return array_values(array_diff($codes, $found));
    }
}
