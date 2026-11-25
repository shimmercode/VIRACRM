<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes generated URLs match the scheme and host the browser actually used.
 *
 * WHY THIS EXISTS
 * ---------------
 * The review environment runs behind a TLS-terminating preview proxy that
 * forwards plaintext HTTP to the dev server. Laravel therefore believed it was
 * serving `http://`, built every stylesheet and script URL with that scheme,
 * and an HTTPS page blocked them as mixed content. The result was raw,
 * unstyled HTML — which reads to the user as "the environment opens nothing".
 *
 * Two conditions have to be handled, and the first cost the most time:
 *
 *   1. The proxy does NOT always send X-Forwarded-Proto. Trusting only that
 *      header leaves the bug in place on exactly the requests that matter.
 *      VIRA_FORCE_HTTPS exists for deployments that know they are behind TLS.
 *
 *   2. When a forwarded host is present, the URL root must use it — otherwise
 *      absolute URLs point at an internal name the browser cannot resolve.
 *
 * Every relaxation here is opt-in through configuration. A direct connection
 * with no forwarded headers and no VIRA_FORCE_HTTPS behaves exactly as before.
 */
class TrustPreviewProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        $proto = $request->headers->get('X-Forwarded-Proto');
        $host = $request->headers->get('X-Forwarded-Host');

        // An explicitly configured scheme wins: this deployment knows it is
        // reached over TLS, whatever the proxy did or did not forward.
        $forced = env('VIRA_FORCE_HTTPS');
        $https = $forced === 'true' || $forced === '1' || $proto === 'https';

        if ($https || $host) {
            $scheme = $https ? 'https' : $request->getScheme();
            $authority = $host ?: $request->getHttpHost();

            // forceRootUrl() sets scheme and root in one call, so a bare
            // X-Forwarded-Host and a forced https take the same path.
            URL::forceRootUrl("{$scheme}://{$authority}");

            // Keep the Request itself consistent: code that inspects the
            // request (not just the URL generator) must agree with what was
            // just emitted, or a form action and its CSRF origin disagree.
            if ($https) {
                $request->server->set('HTTPS', 'on');
            }
        }

        $response = $next($request);

        /*
         * The app's own bot wall is correct for production and wrong here.
         *
         * `X-Frame-Options: DENY` and `frame-ancestors 'none'` stop the CRM
         * being clickjacked. The preview harness embeds it in an iframe on
         * purpose, so framing must be allowed — by named origins only, never
         * by '*'.
         *
         * Gated on VIRA_PREVIEW_EMBED, set only by the review environment's
         * start command. A production deploy that forgets it keeps DENY.
         */
        $embed = env('VIRA_PREVIEW_EMBED');

        if ($embed) {
            $response->headers->remove('X-Frame-Options');

            $csp = $response->headers->get('Content-Security-Policy', '');
            $csp = preg_replace('/frame-ancestors[^;]*(;\s*)?/', '', $csp);
            $csp = rtrim(trim($csp), ';');

            // `self` plus the configured origin(s), space-separated.
            $response->headers->set(
                'Content-Security-Policy',
                $csp . "; frame-ancestors 'self' {$embed}"
            );
        }

        return $response;
    }
}
