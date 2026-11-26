<?php

namespace App\Models;

use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * REQ-05.03 — structured keyword management (§5-3).
 *
 * WHY ROWS, NOT A TEXT BLOB
 * -------------------------
 * §5-3 requires keywords to be *managed*: counted against the package limit,
 * tracked per-keyword through a working status, and deduplicated. A comma
 * separated blob supports none of that — you cannot index it, count it
 * reliably, or stop «طراحی سایت» and «طراحی  سایت» being stored twice.
 *
 * `normalized` holds the searchable form (trimmed, collapsed whitespace,
 * lower-cased). It is what uniqueness and search compare against, so the
 * stored `keyword` keeps the exact text the user wrote.
 */
class ProjectKeyword extends Model
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
        ];
    }

    protected $fillable = [
        'project_id', 'keyword', 'normalized', 'status', 'notes',
    ];

    public const STATUSES = [
        'pending' => 'در انتظار',
        'working' => 'در حال کار',
        'done' => 'انجام‌شده',
        'removed' => 'حذف‌شده',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The comparable form of a keyword.
     *
     * Persian text needs more than trim/lowercase: the Arabic letters ي and ك
     * are typed interchangeably with the Persian ی and ک on standard Iranian
     * keyboards, and ZWNJ appears inside compound words. Two keywords that look
     * identical on screen must normalize to the same string or the duplicate
     * check is useless.
     */
    public static function normalize(string $keyword): string
    {
        $value = trim($keyword);

        // Unify Arabic letter forms with their Persian equivalents.
        $value = str_replace(['ي', 'ك'], ['ی', 'ک'], $value);

        // U+200C ZWNJ -> space, then collapse runs of whitespace.
        $value = str_replace("\u{200C}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        // Persian digits -> ASCII so «سال ۱۴۰۴» matches «سال 1404».
        $value = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );

        return Str::lower($value);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
