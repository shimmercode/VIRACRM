<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-04.05 — contract templates (§4, §15). */
class ContractTemplate extends Model
{
    protected $fillable = [
        'title', 'body', 'contract_type_id', 'version',
        'is_active', 'is_default', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }
}
