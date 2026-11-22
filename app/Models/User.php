<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * REQ-10.01–10.07 — application user.
 *
 * The client document §10 is explicit that the first release has one
 * superadmin plus internal users whose access to financial and password data
 * is granted per area. Authorisation therefore asks "does this user hold
 * permission X", never "is this user an admin".
 */
class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile',
        'password',
        'is_superadmin',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'failed_login_attempts' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * A superadmin implicitly holds every permission, so the permission table
     * does not need to be kept in sync with a role that can do everything.
     */
    public function hasPermission(string $code): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->is_superadmin) {
            return true;
        }

        return $this->permissions()->contains('code', $code);
    }

    /** @return \Illuminate\Support\Collection<int, Permission> */
    public function permissions()
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->flatMap->permissions->unique('id')->values();
        }

        return Permission::query()
            ->whereIn('id', function ($query) {
                $query->select('permission_id')
                    ->from('role_permissions')
                    ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
                    ->where('user_roles.user_id', $this->id);
            })
            ->get();
    }

    public function hasAnyOf(array $codes): bool
    {
        foreach ($codes as $code) {
            if ($this->hasPermission($code)) {
                return true;
            }
        }

        return false;
    }

    /** §10: a deactivated user must be unable to authenticate at all. */
    public function isBlocked(): bool
    {
        if (! $this->is_active) {
            return true;
        }

        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function displayName(): string
    {
        return $this->name !== '' ? $this->name : $this->username;
    }
}
