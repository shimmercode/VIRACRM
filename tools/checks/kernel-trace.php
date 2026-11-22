<?php
require __DIR__ . '/../../vendor/autoload.php';
$root = dirname(__DIR__, 2);
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/login', 'GET');
$request->headers->set('Host', 'localhost');

// Hook the router AFTER the kernel resolves it, via termination.
$response = $kernel->handle($request);

echo "status: " . $response->getStatusCode() . "\n";
echo "location: " . ($response->headers->get('Location') ?? '—') . "\n";
$route = $request->route();
echo "bound route: " . ($route ? $route->uri() . ' mw=' . implode(',', $route->gatherMiddleware()) : 'NONE') . "\n";
