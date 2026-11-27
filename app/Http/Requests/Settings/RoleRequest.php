<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §15 / REQ-15.01 — «نقش‌ها و مجوزهای کاربران».
 *
 * Roles were data from the start (`Role` documents that the client defers the
 * role list to the owner) and `Role::syncPermissions()` already existed, but the
 * only way to reach either was the seeder. This is the screen.
 *
 * THE ONE RULE THAT MUST NOT BE BROKEN
 * ------------------------------------
 * A user may not grant a permission they do not hold themselves. Without it,
 * anyone who is ever given `users.manage` could open their own role, tick
 * `payroll.approve`, and be an approver on the next page load — the escalation
 * this screen exists to make impossible. A superadmin implicitly holds every
 * permission, so the check never blocks legitimate administration.
 */
final class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('role')?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9._-]*$/',
                Rule::unique('roles', 'code')->ignore($currentId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Keyed by row id, so a dot in a permission code is not read as
            // nesting: permissions[] holds codes, one entry per ticked box.
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'عنوان نقش', 'code' => 'کد نقش', 'permissions' => 'مجوزها'];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'نقشی با این کد پیش‌تر ثبت شده است.',
            'code.regex' => 'کد نقش تنها می‌تواند از حروف کوچک لاتین، رقم و - _ . تشکیل شود.',
        ];
    }

    /** @return list<string> */
    public function permissionCodes(): array
    {
        $codes = $this->input('permissions', []);

        return is_array($codes)
            ? array_values(array_unique(array_map('strval', $codes)))
            : [];
    }

    /**
     * The permissions this actor is not allowed to hand out.
     *
     * Empty for a superadmin, who holds everything implicitly.
     *
     * @return list<string>
     */
    public function escalationAttempt(): array
    {
        $actor = $this->user();

        if ($actor === null || $actor->is_superadmin) {
            return [];
        }

        $own = $actor->permissions()->pluck('code')->all();

        return array_values(array_diff($this->permissionCodes(), $own));
    }

    public function validatedForModel(): array
    {
        return [
            'title' => trim((string) $this->input('title')),
            'code' => strtolower(trim((string) $this->input('code'))),
            'description' => ($d = $this->input('description')) === '' || $d === null ? null : (string) $d,
        ];
    }
}
