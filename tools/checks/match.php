<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['/login', '/', '/customers'] as $uri) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $route = $app->make('router')->getRoutes()->match($request);
    echo str_pad($uri, 12) . ' -> ' . $route->uri() . ' (' . ($route->getName() ?? '—') . ")\n";
    echo '   pathinfo: ' . var_export($request->getPathInfo(), true) . "\n";
    echo '   middleware: ' . implode(',', $route->gatherMiddleware()) . "\n";
}
