<?php

namespace App\Http\Requests\Customer;

use Illuminate\Validation\Validator;

/**
 * REQ-03.04 — customer edit validation.
 *
 * Same field rules as create. Two deliberate differences:
 *
 *  - No duplicate prompt. An existing customer always "duplicates" itself, and
 *    re-prompting on every edit teaches users to dismiss the warning without
 *    reading it — which then makes the warning useless for new records.
 *  - National ID and mobile checks are still applied, because a typo is just
 *    as damaging on edit as on create.
 */
class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers.update') ?? false;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateNationalId($validator);
                $this->validateMobile($validator);
            },
        ];
    }
}
