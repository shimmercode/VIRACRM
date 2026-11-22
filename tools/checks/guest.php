<?php
require __DIR__ . '/../../vendor/autoload.php';
$root = dirname(__DIR__, 2);
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "auth default guard: " . config('auth.defaults.guard') . "\n";
echo "session driver: " . config('session.driver') . "\n";
echo "session cookie: " . config('session.cookie') . "\n";

// Simulate the guest request without the kernel.
$request = Illuminate\Http\Request::create('/login', 'GET');
$app->instance('request', $request);

$auth = $app->make('auth');
echo "Auth::check(): " . var_export($auth->guard()->check(), true) . "\n";
echo "Auth::user(): " . var_export($auth->guard()->user(), true) . "\n";

// Is there a stray row in this WASM session's DB?
$dir = database_path();
echo "db dir: $dir\n";
foreach (glob($dir . '/*') as $f) { echo "  " . basename($f) . ' ' . filesize($f) . " bytes\n"; }
