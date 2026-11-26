<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-15.01 — every setting change is attributable. */
class SettingsHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * The migration creates `settings_history`. Laravel would infer
     * `settings_histories` from the class name, so every read AND write through
     * this model was hitting a table that does not exist. `Setting` declares
     * its table explicitly for the same reason; this one was missed, and because
     * `Setting::set()` had no callers the insert side was never exercised.
     */
    protected $table = 'settings_history';

    protected $fillable = ['key', 'old_value', 'new_value', 'changed_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * Who made the change. Without this relation a screen showing the history
     * has to look the user up per row, which is exactly the N+1 the query
     * suite is written to catch.
     *
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
