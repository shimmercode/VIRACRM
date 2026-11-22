<?php

/**
 * VIRA CRM — diagnose a request without Laravel's exception renderer.
 *
 * DEVELOPMENT TOOL ONLY. Laravel's default renderer instantiates Composer's
 * ClassLoader to build its stack traces. This sandbox uses a custom autoloader
 * that has no such class, so every real application error would be masked by a
 * second-order "ClassLoader not found" page.
 *
 * This script installs a pass-through exception handler that re-throws, so the
 * original throwable reaches the catch block and is printed in full.
 *
 * Usage: same environment variables as tools/http-render.php.
 */

$root = getenv('VIRA_ROOT') ?: dirname(__DIR__);

$method = getenv('METHOD') ?: 'GET';
$uri = getenv('REQUEST_URI') ?: '/';
$host = getenv('HTTP_HOST') ?: 'localhost:8080';
$postBody = getenv('POST_BODY') ?: '';
$contentType = getenv('CONTENT_TYPE') ?: '';

foreach (explode(';', getenv('COOKIE') ?: '') as $pair) {
    if (str_contains($pair, '=')) {
        [$name, $value] = explode('=', $pair, 2);
        $_COOKIE[trim($name)] = urldecode(trim($value));
    }
}

require $root . '/vendor/autoload.php';

// A handler that does nothing but re-throw.
$passThrough = new class implements Illuminate\Contracts\Debug\ExceptionHandler {
    public function report(Throwable $e): void {}
    public function render($request, Throwable $e) { throw $e; }
    public function renderForConsole($output, Throwable $e): void { throw $e; }
    public function shouldRenderJsonWhen($request, Throwable $e): bool { return false; }
    public function shouldReport(Throwable $e): bool { return false; }
};

try {
    $app = require $root . '/bootstrap/app.php';

    $app->singleton(
        Illuminate\Contracts\Debug\ExceptionHandler::class,
        fn () => $passThrough
    );

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $request = Illuminate\Http\Request::create(
        $uri, $method, [], $_COOKIE, [], [],
        $postBody !== '' ? $postBody : null
    );
    $request->headers->set('Host', $host);
    if ($contentType !== '') {
        $request->headers->set('Content-Type', $contentType);
    }

    $response = $kernel->handle($request);

    echo "STATUS " . $response->getStatusCode() . "\n";
    foreach ($response->headers->all() as $name => $values) {
        foreach ((array) $values as $value) {
            echo "HEADER $name: $value\n";
        }
    }
    echo "BODY\n";
    echo $response->getContent();
} catch (Throwable $e) {
    echo "STATUS 500\nBODY\n";
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo 'at ' . $e->getFile() . ':' . $e->getLine() . "\n\n";
    foreach (array_slice($e->getTrace(), 0, 12) as $i => $frame) {
        $loc = ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?');
        $fn = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        echo '  #' . $i . ' ' . $loc . ' ' . $fn . "\n";
    }
    if ($e->getPrevious()) {
        echo "\nPREVIOUS: " . get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage() . "\n";
        echo 'at ' . $e->getPrevious()->getFile() . ':' . $e->getPrevious()->getLine() . "\n";
    }
}
