<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = $app->make('router')->getRoutes();
echo "Total routes: " . count($routes) . "\n\n";
foreach ($routes as $route) {
    printf(
        "%-8s %-28s %-26s %s\n",
        implode('|', array_diff($route->methods(), ['HEAD'])),
        $route->uri(),
        $route->getName() ?? '—',
        implode(',', $route->gatherMiddleware())
    );
}
