<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OD-41 — one debit-or-credit line of a journal entry.
 *
 * A line carries exactly one of `debit`/`credit`. Storing both as non-negative
 * amounts with the other at zero (rather than a signed `amount` column) means a
 * posting can never be silently inverted by a sign error: `debit = -100` is
 * impossible because the column is unsigned.
 */
class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'ledger_account_id', 'debit', 'credit', 'description',
        'customer_id', 'contract_id', 'project_id', 'employee_id',
        'department_id', 'cost_center_id', 'service_type', 'line_no',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
            'line_no' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** The signed amount in the entry's own direction: +debit, -credit. */
    public function signedAmount(): int
    {
        return $this->debit > 0 ? $this->debit : -$this->credit;
    }
}
