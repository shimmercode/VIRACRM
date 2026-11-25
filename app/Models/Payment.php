<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** OD-41 §19.7 — a receipt (in) or disbursement (out). */
class Payment extends Model
{
    protected $fillable = [
        'number', 'direction', 'customer_id', 'invoice_id', 'amount', 'paid_on',
        'method', 'reference', 'bank_account', 'notes', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
        ];
    }

    public const DIRECTIONS = ['in' => 'دریافت', 'out' => 'پرداخت'];

    public const METHODS = [
        'cash' => 'نقد',
        'transfer' => 'انتقال بانکی',
        'cheque' => 'چک',
        'online' => 'درگاه آنلاین',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
