<?php
$root = dirname(__DIR__, 2);
$uri = '/login'; $method = 'GET'; $host = 'localhost'; $postBody = ''; $contentType = ''; $accept = 'text/html';
$parts = parse_url($uri);
$_SERVER['REQUEST_METHOD']=$method; $_SERVER['REQUEST_URI']=$uri; $_SERVER['QUERY_STRING']='';
$_SERVER['HTTP_HOST']=$host; $_SERVER['SERVER_NAME']='localhost'; $_SERVER['SERVER_PORT']='80';
$_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['SCRIPT_FILENAME']=$root.'/public/index.php';
$_SERVER['DOCUMENT_ROOT']=$root.'/public'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_ACCEPT']=$accept; $_SERVER['HTTP_USER_AGENT']='vira-dev-server';
$_SERVER['CONTENT_TYPE']=''; $_SERVER['CONTENT_LENGTH']='0'; $_SERVER['HTTPS']='off';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create($uri, $method, [], [], [], [], null);
$request->headers->set('Host', $host);
$request->headers->set('Accept', $accept);

// Register a listener that fires when the router matched.
$app->make('events')->listen(Illuminate\Routing\Events\RouteMatched::class, function ($e) use ($app) {
    echo "MATCHED ROUTE: " . $e->route->uri() . " mw=" . implode(',', $e->route->gatherMiddleware()) . "\n";
});

$response = $kernel->handle($request);
echo "status: " . $response->getStatusCode() . " location: " . ($response->headers->get('Location') ?? '—') . "\n";
