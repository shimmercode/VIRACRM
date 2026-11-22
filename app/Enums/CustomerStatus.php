<?php

namespace App\Enums;

/**
 * REQ-03.02 — customer lifecycle status.
 *
 * The five states are the ones implied by §3: a new contact starts as a lead,
 * becomes a prospect when a conversation starts, an active customer when a
 * contract exists, and leaves via inactive (dormant) or archived (no longer
 * worked, kept for history).
 */
enum CustomerStatus: string
{
    case Lead = 'lead';
    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'سرنخ',
            self::Prospect => 'در مذاکره',
            self::Active => 'مشتری فعال',
            self::Inactive => 'غیرفعال',
            self::Archived => 'بایگانی',
        };
    }

    /** Badge tone, so status colours stay consistent across every page (§8). */
    public function tone(): string
    {
        return match ($this) {
            self::Lead => 'neutral',
            self::Prospect => 'info',
            self::Active => 'success',
            self::Inactive => 'warn',
            self::Archived => 'muted',
        };
    }

    /** @return array<string,string> value => label, for <select> options. */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
