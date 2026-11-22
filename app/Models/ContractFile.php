<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-04.07 — scanned signed copies (§4). */
class ContractFile extends Model
{
    protected $fillable = ['contract_id', 'attachment_id', 'label', 'kind', 'uploaded_by'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
