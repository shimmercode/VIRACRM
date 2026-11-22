<?php

namespace App\Enums;

use App\Services\I18n\Labels;

/**
 * REQ-04.02 — the six contract statuses named in the client document §4:
 * پیش‌نویس، فعال، در آستانه انقضا، پایان‌یافته، تمدیدشده، لغوشده.
 *
 * DESIGN NOTE: "در آستانه انقضا" (expiring soon) is a *derived* state, not an
 * editable one. Nobody sets it by hand — it is computed from the end date and
 * the configurable warning thresholds (client document §2 and §15). Storing it
 * as a manual status would let the two disagree, which is exactly the class of
 * bug that makes an expiry dashboard untrustworthy.
 */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return Labels::get('contract.status.' . $this->value);
    }

    /** Statuses that can be chosen by a user in a form. */
    public static function selectable(): array
    {
        return [self::Draft, self::Active, self::Expired, self::Renewed, self::Cancelled];
    }

    /** Statuses that a user may not set directly because they are derived. */
    public static function derived(): array
    {
        return [self::Expiring];
    }

    public function isDerived(): bool
    {
        return in_array($this, self::derived(), true);
    }

    /** A contract in these states still binds the customer financially. */
    public function isLive(): bool
    {
        return match ($this) {
            self::Active, self::Expiring => true,
            default => false,
        };
    }

    /** Allowed transitions, so the UI can't offer a nonsensical move. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Expiring, self::Expired, self::Cancelled],
            self::Expiring => [self::Active, self::Expired],
            self::Expired => [self::Renewed],
            self::Renewed => [self::Active],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
