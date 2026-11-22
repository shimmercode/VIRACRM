<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-04.08 — immutable snapshots; renewals preserve the old version. */
class ContractVersion extends Model
{
    protected $fillable = ['contract_id', 'version', 'body', 'snapshot', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
