<?php

/**
 * Emit every registered route as JSON, read from the live router.
 *
 * A hand-maintained list of URLs to smoke-test goes stale the day a route is
 * added, and the routes that get forgotten are exactly the new ones. This reads
 * the router instead, so a route that exists is a route that gets tested.
 *
 * Usage: node docs/tools/php-cli.mjs tools/checks/route-inventory.php > out.json
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$out = [];

foreach ($app->make('router')->getRoutes() as $route) {
    $methods = array_values(array_diff($route->methods(), ['HEAD']));

    $out[] = [
        'methods' => $methods,
        'uri' => $route->uri(),
        'name' => $route->getName(),
        'parameters' => $route->parameterNames(),
        'middleware' => $route->gatherMiddleware(),
    ];
}

// The php-wasm runner appends a trailer line, so the JSON is delimited.
echo "VIRA_ROUTES_JSON_BEGIN\n";
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "\nVIRA_ROUTES_JSON_END\n";
