<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-03.02 — contact persons at a legal entity. */
class CustomerContact extends Model
{
    protected $fillable = [
        'customer_id', 'full_name', 'position', 'mobile', 'phone', 'email', 'notes', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
