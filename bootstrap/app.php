<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        // Appended to the web group so it runs after the session starts:
        // the permission middleware needs a resolved user.
        $middleware->web(append: [
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
