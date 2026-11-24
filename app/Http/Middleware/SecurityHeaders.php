<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ-10.05 — baseline security headers.
 *
 * Deliberately conservative, because this application is served from a shared
 * host behind configuration we do not control. Everything here is safe to send
 * from PHP alone and cannot break a host's own header rules.
 *
 * NOTE ON CSP: `script-src 'self'` is used without 'unsafe-inline' for scripts.
 * The interface ships a small external JS file rather than inline handlers
 * precisely so this policy can stay strict.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // The review environment must never be indexed or archived. The layout
        // already carries a robots meta tag; this is the header-level twin, so
        // the rule survives even for pages rendered without that layout.
        if (config('review.enabled')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        }

        // Only the device APIs actually needed; none are.
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');

        // Pages contain customer and credential metadata: never cache them.
        if (! $response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; " // inline styles used by Blade component attributes
            . "script-src 'self'; "
            . "font-src 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "object-src 'none'"
        );

        return $response;
    }
}
