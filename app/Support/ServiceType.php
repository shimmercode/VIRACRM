<?php

namespace App\Support;

/**
 * The service lines the agency actually sells.
 *
 * WHY THIS EXISTS INSTEAD OF A DB TABLE
 * -------------------------------------
 * §19.1 gives revenue and expense accounts per service (SEO, site design,
 * support, other). The service a *journal line* carries must therefore be one of
 * a small fixed set, or per-service profitability stops adding up to total
 * revenue. A free-text column would let "SEO", "seo" and "سئو" become three
 * different services in a report.
 *
 * The list is a constant, but the mapping to the revenue account is data
 * (OD-41 codes), so re-pointing a service to a different account is a config
 * change, not a code change.
 */
final class ServiceType
{
    public const SEO = 'seo';
    public const DESIGN = 'design';
    public const SUPPORT = 'support';
    public const OTHER = 'other';

    /** service key => [label, revenue account code] */
    public const MAP = [
        self::SEO => ['سئو', '4-1-01'],
        self::DESIGN => ['طراحی سایت', '4-1-02'],
        self::SUPPORT => ['پشتیبانی', '4-1-03'],
        self::OTHER => ['سایر خدمات', '4-1-04'],
    ];

    public static function label(string $key): string
    {
        return self::MAP[$key][0] ?? $key;
    }

    public static function revenueAccount(string $key): string
    {
        return self::MAP[$key][1] ?? '4-1-04';
    }

    /** @return array<string,string> key => label, for selects */
    public static function options(): array
    {
        return array_map(fn ($v) => $v[0], self::MAP);
    }

    public static function isValid(string $key): bool
    {
        return isset(self::MAP[$key]);
    }

    /** Map a project's `type` column onto a service key. */
    public static function fromProjectType(string $type): string
    {
        return match ($type) {
            'seo' => self::SEO,
            'design' => self::DESIGN,
            'support' => self::SUPPORT,
            default => self::OTHER,
        };
    }
}
