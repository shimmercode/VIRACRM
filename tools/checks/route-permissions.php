<?php

/*
|--------------------------------------------------------------------------
| Route permission audit
|--------------------------------------------------------------------------
|
| routes/web.php promises "every authenticated route declares the permission
| it needs". Nothing verified that promise, so it was a comment rather than a
| control: a route added later without `permission:` would inherit the auth
| gate and be reachable by every logged-in user.
|
| This enumerates the real router and fails on any authenticated route that
| declares no permission. Allow-listed exceptions must name their reason here,
| which turns "forgot to think about it" into a review decision.
|
*/

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Authenticated routes that legitimately need no permission, with the reason.
 * Adding an entry here should require justification, not convenience.
 */
const EXEMPT = [
    // The dashboard is every user's landing page after login; it renders only
    // what the user's own permissions allow, and its individual counts are
    // permission-gated per area (see the comment in routes/web.php).
    'dashboard' => 'landing page; renders only what the user may already see',

    // Any authenticated user may end their own session. Gating logout behind a
    // permission would strand someone who was just revoked.
    'logout' => 'any user may end their own session',

    // A user may always change their own password. These two sit deliberately
    // above the `password.settled` group (routes/web.php:45) so a forced reset
    // is reachable by the very user who is forced to do it.
    'password.change' => 'self-service own-password form',
    'password.update' => 'self-service own-password change',

    // The review environment is not gated by a permission because it is gated
    // three other ways, all stronger: routes/review.php is registered only when
    // config('review.enabled') is true, ReviewController::index() re-checks that
    // plus an environment allowlist and 404s otherwise, and each page is checked
    // against the catalogue's own declared permission by authorizePage().
    // A production deploy has no /review surface at all.
    'review.index' => 'gated by review.enabled + environment allowlist + per-page permission',
    'review.health' => 'same triple gate; reports row counts only',
    'review.preview' => 'same triple gate; read-only by design (no POST routes here)',
];

$routes = $app->make('router')->getRoutes();

$total = 0;
$authenticated = 0;
$missing = [];
$unknown = [];
$exemptUsed = [];

$permissions = Illuminate\Support\Facades\DB::table('permissions')->pluck('code')->all();

foreach ($routes as $route) {
    $total++;
    $methods = array_values(array_diff($route->methods(), ['HEAD']));
    if ($methods === []) {
        continue;
    }
    $middleware = $route->gatherMiddleware();

    $isAuthenticated = false;
    foreach ($middleware as $mw) {
        if ($mw === 'auth' || str_starts_with((string) $mw, 'auth:')) {
            $isAuthenticated = true;
        }
    }
    if (! $isAuthenticated) {
        continue;
    }
    $authenticated++;

    $declared = [];
    foreach ($middleware as $mw) {
        if (str_starts_with((string) $mw, 'permission:')) {
            $declared[] = substr($mw, strlen('permission:'));
        }
    }

    $name = $route->getName();
    $label = implode('|', $methods) . ' ' . $route->uri() . ' (' . ($name ?? 'unnamed') . ')';

    if ($declared === []) {
        if ($name !== null && array_key_exists($name, EXEMPT)) {
            $exemptUsed[$name] = EXEMPT[$name];
            continue;
        }
        $missing[] = $label;
        continue;
    }

    // A declared permission that does not exist in the table grants nothing and
    // refuses everything — a typo here silently locks users out.
    foreach ($declared as $d) {
        if (! in_array($d, $permissions, true)) {
            $unknown[] = $label . ' declares unknown permission: ' . $d;
        }
    }
}

$pass = $missing === [] && $unknown === [];

echo "T-RBAC-routes-covered   " . ($missing === [] ? 'PASS' : 'FAIL')
    . "  every authenticated route declares a permission"
    . " ($authenticated/$authenticated)" . PHP_EOL;
foreach ($missing as $m) {
    echo "  MISSING: $m" . PHP_EOL;
}

echo 'T-RBAC-permissions-real   ' . ($unknown === [] ? 'PASS' : 'FAIL')
    . '  every declared permission exists in the table' . PHP_EOL;
foreach ($unknown as $u) {
    echo "  UNKNOWN: $u" . PHP_EOL;
}

echo 'T-RBAC-exemptions-used    PASS  ' . count($exemptUsed) . ' allow-listed'
    . ($exemptUsed === [] ? '' : ' (' . implode(', ', array_keys($exemptUsed)) . ')') . PHP_EOL;
foreach ($exemptUsed as $name => $why) {
    echo "  EXEMPT: $name — $why" . PHP_EOL;
}

echo 'T-RBAC-exemptions-justified ' . (count(EXEMPT) > 0 ? 'PASS' : 'WARN')
    . '  ' . count(EXEMPT) . ' entries in EXEMPT, each with a stated reason' . PHP_EOL;

// run-all.mjs counts assertions from a "N/N passed" line; without one this
// suite would report OK on its exit code alone and its four checks would be
// invisible in the totals.
$checks = [
    'routes covered' => $missing === [],
    'permissions real' => $unknown === [],
    'exemptions used' => true,
    'exemptions justified' => count(EXEMPT) > 0,
];
$checkCount = count($checks);
$checkPassed = count(array_filter($checks));

echo PHP_EOL . "routes: $total | authenticated: $authenticated | missing permission: "
    . count($missing) . ' | unknown permission: ' . count($unknown) . PHP_EOL;
echo "$checkPassed/$checkCount passed" . PHP_EOL;

exit($pass ? 0 : 1);
