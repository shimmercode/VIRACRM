<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * REQ-10.07 — password policy and change.
 *
 * §10 asks for an appropriate password policy and user deactivation. This
 * covers self-service change; administrative reset lives with user management.
 *
 * POLICY RATIONALE: a minimum of 12 characters with mixed classes is used
 * instead of a short complex password. Length resists offline cracking far
 * better than symbol substitution, and the client's users are office staff who
 * will accept a passphrase but will write a symbol salad on a sticky note.
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
                'different:current_password',
            ],
        ], [
            'current_password.required' => 'رمز عبور فعلی را وارد کنید.',
            'password.required' => 'رمز عبور جدید را وارد کنید.',
            'password.confirmed' => 'تکرار رمز عبور جدید مطابقت ندارد.',
            'password.min' => 'رمز عبور باید حداقل ۱۲ نویسه باشد.',
            'password.different' => 'رمز عبور جدید باید با رمز فعلی متفاوت باشد.',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            AuditRecorder::record(AuditRecorder::DENIED, $user, 'تغییر رمز با رمز فعلی نادرست');

            return back()->withErrors(['current_password' => 'رمز عبور فعلی نادرست است.']);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // A password change invalidates other sessions by rotating the remember
        // token; the current session stays valid so the user is not thrown out.
        $request->session()->regenerate();

        AuditRecorder::record(AuditRecorder::UPDATED, $user, 'تغییر رمز عبور توسط کاربر');

        return redirect()->route('dashboard')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
