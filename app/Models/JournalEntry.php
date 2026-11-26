<?php

namespace App\Models;

use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OD-41 — a journal entry (سند حسابداری), the header of a balanced posting.
 *
 * A posted entry is IMMUTABLE. §19.2 is explicit: "اسناد قطعی‌شده حذف یا ویرایش
 * بی‌ردپا نشوند. اصلاح سند از طریق سند اصلاحی یا برگشتی انجام شود."
 *
 * That rule is enforced in three places rather than one, because a single guard
 * is a single bug away from silently rewriting history:
 *   1. `updating`/`deleting` model events throw when status is `posted`
 *   2. the service layer never calls update() on a posted entry
 *   3. the controller exposes no edit route for posted entries at all
 */
class JournalEntry extends Model
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
            'source' => 'SOURCES',
        ];
    }

    protected $fillable = [
        'number', 'fiscal_period_id', 'entry_date', 'memo', 'source_type',
        'source_id', 'status', 'posted_at', 'posted_by', 'reverses_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public const SOURCES = [
        'invoice' => 'فاکتور',
        'payment' => 'دریافت/پرداخت',
        'payroll' => 'حقوق و دستمزد',
        'expense' => 'هزینه',
        'opening' => 'سند افتتاحیه',
        'manual' => 'سند دستی',
    ];

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'posted' => 'قطعی',
        'reversed' => 'برگشت‌خورده',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if ($entry->getOriginal('status') === 'posted') {
                throw new \RuntimeException(
                    'سند قطعی‌شده قابل ویرایش نیست؛ برای اصلاح از سند برگشتی استفاده کنید.'
                );
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->status === 'posted' || $entry->status === 'reversed') {
                throw new \RuntimeException('سند قطعی‌شده قابل حذف نیست.');
            }
        });
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function totalDebit(): int
    {
        return (int) $this->lines()->sum('debit');
    }

    public function totalCredit(): int
    {
        return (int) $this->lines()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit() === $this->totalCredit();
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }
}
