<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** REQ-15.01 — every setting change is attributable. */
class SettingsHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['key', 'old_value', 'new_value', 'changed_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
