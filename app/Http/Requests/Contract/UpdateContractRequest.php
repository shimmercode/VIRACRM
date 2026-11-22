<?php

namespace App\Http\Requests\Contract;

use Illuminate\Validation\Validator;

/**
 * REQ-04.09 — contract edit validation.
 *
 * Identical field rules to create. `contracts.update` is a separate permission
 * because §10 distinguishes reading a contract from changing it, and a
 * mis-typed amount on a signed contract is not a recoverable mistake.
 */
class UpdateContractRequest extends StoreContractRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('contracts.update') ?? false;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateAmount($validator);
                $this->validateDates($validator);
            },
        ];
    }
}
