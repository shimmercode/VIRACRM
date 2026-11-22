<?php
$root = dirname(__DIR__, 2);
$method = 'GET';
$uri = '/login';
$host = 'localhost';
$postBody = '';
$contentType = '';
$accept = 'text/html';

$parts = parse_url($uri);
$path = $parts['path'] ?? '/';
$query = $parts['query'] ?? '';

$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['QUERY_STRING'] = $query;
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = explode(':', $host)[0];
$_SERVER['SERVER_PORT'] = explode(':', $host)[1] ?? '80';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = $root . '/public';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_ACCEPT'] = $accept;
$_SERVER['HTTP_USER_AGENT'] = 'vira-dev-server';
$_SERVER['CONTENT_TYPE'] = $contentType;
$_SERVER['CONTENT_LENGTH'] = '0';
$_SERVER['HTTPS'] = 'off';
parse_str($query, $_GET);

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create($uri, $method, [], [], [], [], null);
$request->headers->set('Host', $host);
$request->headers->set('Accept', $accept);

echo "pathinfo: " . var_export($request->getPathInfo(), true) . "\n";
echo "SCRIPT_NAME in _SERVER: " . ($_SERVER['SCRIPT_NAME'] ?? '?') . "\n";

$response = $kernel->handle($request);
echo "status: " . $response->getStatusCode() . "\n";
echo "location: " . ($response->headers->get('Location') ?? '—') . "\n";
$route = $request->route();
echo "bound: " . ($route ? $route->uri() : 'NONE') . "\n";
