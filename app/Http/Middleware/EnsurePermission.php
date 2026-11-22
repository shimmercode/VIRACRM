<?php

namespace App\Http\Middleware;

use App\Services\Security\AuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ-10.03 — server-side permission enforcement.
 *
 * §10 and the phase brief both state that hiding a button is not access
 * control. This middleware is the control: every route that touches a
 * restricted area declares the permission it needs, and a request without it
 * is refused even if the user types the URL directly.
 *
 * Usage:  ->middleware('permission:customers.view')
 *
 * A refusal is audited (REQ-10.04) because a pattern of refused requests from
 * one account is exactly the signal an administrator needs.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        // Multiple permissions in the declaration mean "any of these".
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        AuditRecorder::denied(implode('|', $permissions));

        abort(403, 'شما به این بخش دسترسی ندارید.');
    }
}
