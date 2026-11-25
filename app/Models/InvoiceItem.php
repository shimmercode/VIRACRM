<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** OD-41 §19.6 — one invoice line. */
class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'line_no', 'description', 'quantity_milli',
        'unit_price', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity_milli' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Quantity as a display string: 1 → «۱», 1.5 → «۱٫۵». */
    public function quantityLabel(): string
    {
        $whole = intdiv($this->quantity_milli, 1000);
        $frac = $this->quantity_milli % 1000;

        if ($frac === 0) {
            return \App\Support\Money::toPersianDigits((string) $whole);
        }

        return \App\Support\Money::toPersianDigits(rtrim(rtrim(number_format($this->quantity_milli / 1000, 3), '0'), '.'));
    }

    /** line_total = ceil(unit_price * quantity), rounded once. */
    public static function totalFor(int $unitPrice, int $quantityMilli): int
    {
        return \App\Support\Money::divideRounded($unitPrice * $quantityMilli, 1000);
    }
}
