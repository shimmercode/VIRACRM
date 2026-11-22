<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ-10.07 — force a password change when an administrator created or reset
 * the account.
 *
 * Without this, `must_change_password` is decorative: the flag is set, nobody
 * reads it, and the shared initial password stays in use indefinitely.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        // Allow the logout and password-change routes themselves to work.
        if ($request->routeIs('password.*', 'logout', 'logout.session')) {
            return $next($request);
        }

        return redirect()
            ->route('password.change')
            ->with('warning', 'برای ادامه، رمز عبور خود را تغییر دهید.');
    }
}
