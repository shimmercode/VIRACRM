<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * REQ-10.05 / §10 — authentication.
 *
 * SECURITY DECISIONS WORTH STATING:
 *
 * 1. The failure message is identical for "no such user", "wrong password" and
 *    "deactivated account". Distinguishing them tells an attacker which
 *    usernames exist, and tells a competitor which of their former staff still
 *    have accounts here.
 *
 * 2. Rate limiting is keyed on username + IP, not IP alone, so one shared
 *    office IP cannot lock out everyone, and one shared username cannot be
 *    brute-forced from many IPs without each IP being counted.
 *
 * 3. `session()->regenerate()` on login prevents session fixation: an attacker
 *    who planted a session id before login does not get the authenticated one.
 *
 * 4. A deactivated user is rejected at authentication time, not only by a
 *    middleware check later, so no session is ever created for them.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $username = (string) $validated['username'];
        $throttleKey = $this->throttleKey($username, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            AuditRecorder::loginFailed($username, 'rate_limited');

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => sprintf(
                        'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً %d ثانیه دیگر دوباره تلاش کنید.',
                        $seconds
                    ),
                ]);
        }

        $user = User::query()->where('username', $username)->first();

        // Generic guard: unknown user, wrong password and blocked account all
        // produce the same response.
        $credentialsOk = $user !== null
            && Auth::attempt(
                ['username' => $username, 'password' => (string) $validated['password']],
                (bool) ($validated['remember'] ?? false)
            );

        if (! $credentialsOk) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $reason = match (true) {
                $user === null => 'unknown_user',
                $user->isBlocked() => 'blocked_account',
                default => 'bad_password',
            };
            AuditRecorder::loginFailed($username, $reason);

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => 'نام کاربری یا رمز عبور نادرست است.',
                ]);
        }

        // The user record exists and credentials were accepted; now honour the
        // block, after logging out so no half-authenticated session survives.
        if (Auth::user()->isBlocked()) {
            Auth::logout();
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            AuditRecorder::loginFailed($username, 'blocked_account');

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'نام کاربری یا رمز عبور نادرست است.']);
        }

        // Session fixation defence + a clean session for the new identity.
        $request->session()->regenerate();
        RateLimiter::clear($throttleKey);

        $user = Auth::user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_attempts' => 0,
        ])->save();

        AuditRecorder::login($user->id, $user->username);

        // §10: a password set by an administrator must be changed on first use.
        if ($user->must_change_password) {
            return redirect()
                ->route('password.change')
                ->with('status', 'برای امنیت حساب، رمز عبور خود را تغییر دهید.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user !== null) {
            AuditRecorder::record(AuditRecorder::LOGOUT, null, 'خروج از حساب');
        }

        Auth::logout();

        // Invalidate the session and rotate the CSRF token so the old one
        // cannot be replayed.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'با موفقیت خارج شدید.');
    }

    private function throttleKey(string $username, ?string $ip): string
    {
        return 'login:' . mb_strtolower($username) . '|' . ($ip ?? 'unknown');
    }
}
