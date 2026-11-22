<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** REQ-04.05 — contract templates (§4, §15). */
class ContractTemplate extends Model
{
    protected $fillable = ['code', 'title', 'body', 'contract_type_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
