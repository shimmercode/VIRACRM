<?php

namespace App\Enums;

use App\Services\I18n\Labels;

/**
 * REQ-03.01 / REQ-03.02 — natural person (حقیقی) vs legal entity (حقوقی).
 *
 * The distinction is not cosmetic: it changes which identity fields are
 * required, which fields are searchable, and how the contract template is
 * addressed. A natural person has a national ID; a legal entity has a
 * registration number plus an economic code.
 */
enum CustomerType: string
{
    case Natural = 'natural';
    case Legal = 'legal';

    public function label(): string
    {
        return Labels::get('customer.type.' . $this->value);
    }

    /**
     * Identity fields that must be filled for this customer type.
     *
     * @return list<string>
     */
    public function requiredIdentityFields(): array
    {
        return match ($this) {
            self::Natural => ['first_name', 'last_name', 'national_id'],
            self::Legal => ['company_name', 'registration_number'],
        };
    }

    /** Field names that are meaningless for this type and must be ignored. */
    public function forbiddenFields(): array
    {
        return match ($this) {
            self::Natural => ['company_name', 'registration_number', 'economic_code'],
            self::Legal => ['first_name', 'last_name', 'father_name'],
        };
    }

    public function hasNationalId(): bool
    {
        return $this === self::Natural;
    }
}
