<?php
require __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$app = require $root . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/login', 'GET');
$request->headers->set('Host', 'localhost');

// Ask the router what it would match BEFORE the kernel runs.
$router = $app->make('router');
$matched = $router->getRoutes()->match($request);
echo "would match: " . $matched->uri() . ' (' . $matched->getName() . ")\n";
echo "mw: " . implode(',', $matched->gatherMiddleware()) . "\n\n";

// Now run the kernel and see which route actually got bound.
try {
    $response = $kernel->handle($request);
    echo "status: " . $response->getStatusCode() . "\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    $route = $request->route();
    echo "bound route: " . ($route ? $route->uri() : 'NONE') . "\n";
}
