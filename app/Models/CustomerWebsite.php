<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-03.06 — a customer may own several websites; search must match on domain.
 * The domain is normalised on save so "https://www.Example.COM/" and
 * "example.com" compare equal.
 */
class CustomerWebsite extends Model
{
    protected $fillable = ['customer_id', 'url', 'domain', 'label', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $site): void {
            if (filled($site->url) && blank($site->domain)) {
                $site->domain = self::extractDomain($site->url);
            }
            if (filled($site->domain)) {
                $site->domain = self::extractDomain($site->domain);
            }
        });
    }

    /** Reduce any URL-ish string to a bare lowercase host. */
    public static function extractDomain(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value) ?? $value;
        // Delimiter must not appear inside the pattern: '/', '?' and '#' are all
        // possible URL path separators, so use '~' as the delimiter here.
        $value = preg_replace('~[/?#].*$~', '', $value) ?? $value;
        $value = preg_replace('#^www\.#i', '', $value) ?? $value;

        return mb_strtolower(trim($value, " \t\n\r\0\x0B."));
    }
}
