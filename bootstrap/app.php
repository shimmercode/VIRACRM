<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The CRM Review Environment is its own route file and is only mounted
        // when VIRA_REVIEW_ENABLED is on. A production deploy therefore has no
        // /review surface to forget to protect.
        then: function () {
            if (config('review.enabled')) {
                \Illuminate\Support\Facades\Route::middleware('web')
                    ->group(__DIR__.'/../routes/review.php');
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * Reverse-proxy trust.
         *
         * The framework ships TrustProxies configured to trust NOTHING, which is
         * the right default and wrong for every real deployment: this product is
         * meant to sit behind nginx or a load balancer, and without naming the
         * proxy the application sees the proxy's address as the client's. Three
         * things break, and all three are security evidence rather than cosmetics:
         * audit rows and users.last_login_ip record the proxy instead of the
         * person, IP-based login throttling counts every user as one address,
         * and the forwarded scheme is ignored so a TLS-terminated deployment
         * believes it is serving http and will not set a Secure cookie.
         *
         * Left unset the behaviour is exactly what it was before — trust nothing
         * — so a developer running the app directly is unaffected and nobody is
         * silently taught to trust `*`.
         */
        $middleware->trustProxies(
            // The parameter is `at`, not `proxies` — Middleware::trustProxies()
            // takes (array|string|null $at, ?int $headers). Naming it wrong is a
            // fatal at boot, not a warning, which is why the render check below
            // exists.
            at: ($proxies = env('TRUSTED_PROXIES')) === null || $proxies === ''
                ? null
                : array_values(array_filter(array_map('trim', explode(',', (string) $proxies)))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Appended to the web group so it runs after the session starts:
        // the permission middleware needs a resolved user.
        // TrustPreviewProxy must run BEFORE SecurityHeaders: it rewrites the
        // URL root, and SecurityHeaders builds the CSP that has to know whether
        // this deployment may be framed.
        $middleware->web(append: [
            \App\Http\Middleware\TrustPreviewProxy::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'password.settled' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);

        // Authenticated users hitting /login are bounced to the dashboard by
        // the controller instead of the default guest redirect, so no extra
        // configuration is needed here.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render 403 and 404 with the application's own layout rather than
        // Laravel's default Symfony page, which appears in English and does
        // not match the product (§8, §12).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            $status = $e->getStatusCode();

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $status);
            }

            // A guest who lands on a protected URL is sent to login, not to
            // a 403 that makes the application look broken.
            if ($status === 403 && auth()->guest()) {
                return redirect()->guest(route('login'));
            }

            $view = match ($status) {
                403 => 'errors.403',
                429 => 'errors.429',
                503 => 'errors.503',
                default => 'errors.404',
            };

            if (! view()->exists($view)) {
                return null;
            }

            return response()->view($view, ['exception' => $e], $status);
        });
    })->create();
