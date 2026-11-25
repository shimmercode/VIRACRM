<?php

/**
 * VIRA CRM — render one HTTP request through the real Laravel kernel.
 *
 * Emits a framed response on stdout so the Node dev server can split headers
 * from body without guessing:
 *
 *     VIRA_STATUS <code>
 *     VIRA_HEADER  <name>: <value>
 *     VIRA_BODY
 *     <raw body bytes>
 *
 * USAGE (all input arrives as argv, see the note below):
 *
 *     php tools/http-render.php <json-request-file>
 *
 * The JSON file contains:
 *     { "method", "uri", "host", "cookie", "post_body", "content_type", "accept" }
 *
 * WHY ARGV AND NOT ENVIRONMENT VARIABLES:
 *   The PHP-WASM runtime used in this sandbox does NOT inherit the parent
 *   process environment — `getenv('REQUEST_URI')` returns `false` even when the
 *   variable is set on the command line. An earlier version of this harness
 *   read its input from getenv(), silently fell back to `'/'` for every field,
 *   and therefore rendered the dashboard for every URL requested. Every request
 *   "passed" while testing nothing.
 *
 *   Passing a file path in argv is the only channel that demonstrably works
 *   here: argv is delivered by the runtime, and the file is read from the
 *   mounted host filesystem.
 *
 * SECOND CONSEQUENCE OF THE SAME LIMITATION: `php://input` is unavailable under
 * the CLI SAPI, so a POST body cannot be streamed. It is read from the JSON
 * file and installed into the Request instance directly. Application code still
 * sees an ordinary Illuminate\Http\Request.
 */

$root = dirname(__DIR__);

$specPath = $argv[1] ?? null;

if ($specPath === null || ! is_file($specPath)) {
    fwrite(STDERR, "usage: php tools/http-render.php <json-request-file>\n");
    exit(2);
}

$spec = json_decode((string) file_get_contents($specPath), true);

if (! is_array($spec)) {
    fwrite(STDERR, "invalid request spec JSON\n");
    exit(2);
}

$method = strtoupper((string) ($spec['method'] ?? 'GET'));
$uri = (string) ($spec['uri'] ?? '/');
$host = (string) ($spec['host'] ?? 'localhost:8080');
$cookie = (string) ($spec['cookie'] ?? '');
$postBody = (string) ($spec['post_body'] ?? '');
$contentType = (string) ($spec['content_type'] ?? '');
$accept = (string) ($spec['accept'] ?? 'text/html,application/xhtml+xml');

/*
 * PER-FLOW ENVIRONMENT OVERRIDES
 *
 * Specs may carry an "env" map. It is applied BEFORE bootstrap, because that is
 * the only point where it has any effect: Laravel's Dotenv is immutable and
 * only writes a key that is absent from $_ENV/$_SERVER, so a value present
 * before bootstrap always wins over .env.
 *
 * This is what lets each regression flow own a private database: point
 * DB_DATABASE at a per-flow copy and the flow can create, update and delete
 * records, and log sessions in and out, without touching the shared database
 * or any other flow.
 */
$envOverrides = is_array($spec['env'] ?? null) ? $spec['env'] : [];

foreach ($envOverrides as $key => $value) {
    if (! is_string($key) || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
        fwrite(STDERR, "refusing malformed env key\n");
        exit(2);
    }

    $value = (string) $value;

    // Set all three: config() reads $_ENV/$_SERVER through Env::getRepository(),
    // while anything calling getenv() directly needs the C-level variable.
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

$parts = parse_url($uri);
$query = $parts['query'] ?? '';

// The superglobals matter: Laravel's URL generator, session cookie domain and
// CSRF handling read them rather than the Request instance alone.
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
$_SERVER['HTTP_USER_AGENT'] = (string) ($spec['user_agent'] ?? 'vira-dev-server');
$_SERVER['CONTENT_TYPE'] = $contentType;
$_SERVER['CONTENT_LENGTH'] = (string) strlen($postBody);
$_SERVER['HTTPS'] = 'off';

/*
 * Proxy headers, when the caller supplies them.
 *
 * The preview harness terminates TLS and talks plain HTTP to the dev server. If
 * this renderer ignores that, Laravel emits `http://` asset URLs that an HTTPS
 * page blocks as mixed content. Setting the superglobals (not only the Request
 * headers) is what makes the URL generator and the session cookie pick it up.
 */
$forwardedProto = (string) ($spec['forwarded_proto'] ?? '');
$forwardedHost = (string) ($spec['forwarded_host'] ?? '');

if ($forwardedProto !== '') {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = $forwardedProto;
    if ($forwardedProto === 'https') {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
    }
}

if ($forwardedHost !== '') {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = $forwardedHost;
}

parse_str($query, $_GET);

if ($postBody !== '' && str_contains($contentType, 'application/x-www-form-urlencoded')) {
    parse_str($postBody, $_POST);
}

if ($cookie !== '') {
    foreach (explode(';', $cookie) as $pair) {
        if (! str_contains($pair, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $pair, 2);
        $_COOKIE[trim($name)] = urldecode(trim($value));
    }
}

require $root . '/vendor/autoload.php';

try {
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $request = Illuminate\Http\Request::create(
        $uri,
        $method,
        $method === 'POST' ? $_POST : [],
        $_COOKIE,
        [],
        [],
        $postBody !== '' ? $postBody : null
    );

    $request->headers->set('Host', $host);
    $request->headers->set('Accept', $accept);
    $request->headers->set('User-Agent', $_SERVER['HTTP_USER_AGENT']);

    /*
     * Request::create() builds its own server bag from its arguments and
     * ignores the superglobals this file set above. So the proxy headers have
     * to be attached to the Request as well — otherwise they exist in
     * $_SERVER, are never read, and TrustPreviewProxy sees a direct http
     * connection. That was the second half of the "page opens unstyled" bug.
     */
    if ($forwardedProto !== '') {
        $request->headers->set('X-Forwarded-Proto', $forwardedProto);
    }
    if ($forwardedHost !== '') {
        $request->headers->set('X-Forwarded-Host', $forwardedHost);
    }
    if ($forwardedProto === 'https') {
        $request->server->set('HTTPS', 'on');
        $request->server->set('SERVER_PORT', '443');
    }

    if ($contentType !== '') {
        $request->headers->set('Content-Type', $contentType);
    }

    $response = $kernel->handle($request);

    echo 'VIRA_STATUS ' . $response->getStatusCode() . "\n";

    foreach ($response->headers->all() as $name => $values) {
        foreach ((array) $values as $value) {
            // Newlines inside a header value would corrupt the frame.
            echo 'VIRA_HEADER ' . $name . ': ' . str_replace(["\r", "\n"], ' ', $value) . "\n";
        }
    }

    echo "VIRA_BODY\n";
    echo $response->getContent();

    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "VIRA_STATUS 500\n";
    echo "VIRA_HEADER content-type: text/plain; charset=utf-8\n";
    echo "VIRA_BODY\n";
    echo 'Unhandled: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
