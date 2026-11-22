<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REQ-10.05 — login input validation.
 *
 * Persian error text on purpose: these strings are read by the client's staff,
 * not by developers, and a Laravel default "The username field is required."
 * in an otherwise Persian interface reads as a broken product.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'نام کاربری را وارد کنید.',
            'username.max' => 'نام کاربری بیش از حد طولانی است.',
            'password.required' => 'رمز عبور را وارد کنید.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Trim only; never mutate the password (leading spaces can be intentional).
        if ($this->has('username')) {
            $this->merge(['username' => trim((string) $this->input('username'))]);
        }
    }
}
