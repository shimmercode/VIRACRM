<?php

namespace App\Models;

use App\Models\Concerns\HasDomainConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ-05.02 — SEO-specific fields for a project.
 *
 * These live in their own table so `projects` stays the shared header for both
 * project types. A design project simply has no row here.
 *
 * Why a separate row at all: §5 requires the SEO form to ask for an SEO package
 * and a keyword count, and the design form to ask for a domain and site type.
 * Making every column nullable on one wide table would let an SEO project carry
 * a domain and a design project carry a keyword count, with nothing to prevent
 * it. A row that simply does not exist cannot hold a nonsense value.
 */
class SeoProjectDetail extends Model
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
            'recurrence' => 'RECURRENCES',
        ];
    }

    protected $fillable = [
        'project_id', 'seo_package_id', 'keyword_count', 'website_url', 'financial_notes',
        'recurrence', 'reminder_offset_days',
    ];

    protected function casts(): array
    {
        return [
            'keyword_count' => 'integer',
            'reminder_offset_days' => 'integer',
        ];
    }

    /** §5-1: how often the invoice/report work items repeat. */
    public const RECURRENCES = [
        'monthly' => 'ماهانه',
        'quarterly' => 'فصلی',
        'semiannual' => 'شش‌ماهه',
        'yearly' => 'سالانه',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SeoPackage::class, 'seo_package_id');
    }

    /**
     * §5-1 — «آدرس وب‌سایت».
     *
     * Returns null when the value is acceptable, or a Persian message when it
     * is not. Empty is acceptable: the column is nullable and an SEO project
     * may be recorded before anyone knows the address.
     *
     * WHY NOT LARAVEL'S `url` RULE
     * ----------------------------
     * `url` demands a scheme, so it rejects `example.ir` — which is how an
     * Iranian operator most often writes it, and which is what §5-1 asks for.
     * Rejecting the common form would push users to paste anything at all into
     * a different field, which is worse than a permissive rule. So the rule
     * accepts a bare host, a scheme, a port, a path, a query and a fragment,
     * and Persian IDN labels typed in Arabic script rather than punycoded.
     */
    public static function websiteError(?string $raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        // A space cannot appear in a URL, and a pasted label usually has one.
        if (preg_match('/\s/u', $value)) {
            return 'آدرس وب‌سایت نمی‌تواند فاصله داشته باشد.';
        }

        $scheme = '#^[a-z][a-z0-9+.\-]*://#i';
        $withoutScheme = preg_replace($scheme, '', $value) ?? $value;

        // Two schemes means this is not a URL, it is two things pasted together.
        if (preg_match($scheme, $withoutScheme)) {
            return 'آدرس وب‌سایت معتبر نیست. نمونه: https://example.ir';
        }

        // '/' '?' and '#' all terminate the authority, so the delimiter here
        // must be none of them.
        $host = preg_replace('~[/?#].*$~', '', $withoutScheme) ?? $withoutScheme;
        $host = preg_replace('#^.*@#', '', $host) ?? $host;   // userinfo
        $host = preg_replace('#:\d+$#', '', $host) ?? $host; // port
        $host = mb_strtolower(trim($host, '.'));

        if ($host === '') {
            return 'آدرس وب‌سایت معتبر نیست. نمونه: https://example.ir';
        }

        // ASCII labels, plus the Arabic-script block for Persian IDNs.
        if (! preg_match('/^[a-z0-9\x{0600}-\x{06FF}]([a-z0-9\x{0600}-\x{06FF}.\-]*)$/u', $host)) {
            return 'آدرس وب‌سایت معتبر نیست. نمونه: https://example.ir';
        }

        // A dotless host is only legitimate for local development.
        if (! str_contains($host, '.') && $host !== 'localhost') {
            return 'آدرس وب‌سایت باید شامل دامنه باشد. نمونه: example.ir';
        }

        return null;
    }
}
