<?php

namespace App\Models;

use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OD-41 §19.6 — an invoice.
 *
 * THE TWO-COLUMN RULE
 * -------------------
 * `status` says whether the invoice is a legal document (draft/issued/void).
 * `payment_state` says whether money arrived (unpaid/partial/paid).
 * They are independent: an issued invoice can be unpaid, and a void invoice can
 * never be paid. Collapsing them into one column destroys the ability to report
 * "billed" versus "collected" separately — §19.4.
 */
class Invoice extends Model
{
    use HasDomainConstraints;

    /**
     * Columns whose value must come from the model's own constant.
     *
     * @return array<string, string>
     */
    protected function domainColumns(): array
    {
        return [
            'status' => 'STATUSES',
            'payment_state' => 'PAYMENT_STATES',
        ];
    }

    protected $fillable = [
        'number', 'customer_id', 'contract_id', 'project_id', 'service_type',
        'issue_date', 'due_date', 'subtotal', 'discount', 'tax_rate_bp',
        'tax_amount', 'total', 'paid_amount', 'status', 'payment_state',
        'title', 'notes', 'void_reason', 'voided_at', 'work_item_id',
        'journal_entry_id', 'created_by', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'voided_at' => 'datetime',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax_rate_bp' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'issued' => 'صادرشده',
        'void' => 'ابطال‌شده',
    ];

    public const PAYMENT_STATES = [
        'unpaid' => 'پرداخت‌نشده',
        'partial' => 'پرداخت جزئی',
        'paid' => 'تسویه‌شده',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function remaining(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isOverdue(): bool
    {
        return $this->isIssued()
            && $this->payment_state !== 'paid'
            && $this->due_date !== null
            && $this->due_date->lt(now()->startOfDay());
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeOverdue($query)
    {
        return $query->issued()
            ->whereIn('payment_state', ['unpaid', 'partial'])
            ->whereDate('due_date', '<', now()->startOfDay());
    }

    /**
     * Recompute totals from the item lines.
     *
     * Called before issue only. After issue the stored totals are what the
     * customer received; recomputing them would let a later item edit silently
     * change the amount on a document that is already in someone's inbox.
     */
    public function recalculate(): void
    {
        $subtotal = (int) $this->items()->sum('line_total');
        // `discount` is NOT NULL in the schema, but a freshly-built model can
        // carry null in memory before its first save. Coalescing here is not
        // defensive noise: without it, issuing any invoice created without an
        // explicit discount throws a NOT NULL violation, which is exactly the
        // bug this line was written to fix.
        $discount = min((int) ($this->discount ?? 0), $subtotal);
        $base = $subtotal - $discount;

        // Basis points, rounded once, at the invoice level. Rounding per line
        // and summing produces a total that disagrees with the printed invoice.
        $tax = \App\Support\Money::divideRounded($base * $this->tax_rate_bp, 10000);

        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_amount' => $tax,
            'total' => $base + $tax,
        ]);
    }

    /** Derive payment_state from paid_amount. Never set by hand. */
    public function syncPaymentState(): void
    {
        $effectivePaid = (int) $this->allocations()->sum('amount');

        $state = match (true) {
            $effectivePaid <= 0 => 'unpaid',
            $effectivePaid >= $this->total => 'paid',
            default => 'partial',
        };

        $this->forceFill([
            'paid_amount' => min($effectivePaid, $this->total),
            'payment_state' => $state,
        ])->save();
    }

    public function displayName(): string
    {
        return $this->number !== null ? $this->number : ('پیش‌نویس #' . $this->id);
    }
}
