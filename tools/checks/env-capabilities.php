<?php

/*
|--------------------------------------------------------------------------
| Environment capability probe
|--------------------------------------------------------------------------
|
| The backup design must not assume anything. Every capability this file
| reports was checked rather than inferred: the target is shared hosting where
| exec may be disabled, cron is BLOCKED and SSH is BLOCKED
| (docs/HOST_COMPATIBILITY.md). A design that assumes `tar` or a shell would be
| a design that cannot run where it has to run.
|
| Run it on the real host before relying on any answer here: this sandbox is
| php-wasm, which is NOT the deployment target.
|
*/

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'PHP                 : ' . PHP_VERSION . ' (' . PHP_OS . ')' . PHP_EOL;
echo 'sapi                : ' . PHP_SAPI . PHP_EOL;
echo 'openssl             : ' . (extension_loaded('openssl') ? 'yes' : 'NO') . PHP_EOL;
echo '  aes-256-gcm       : ' . (in_array('aes-256-gcm', openssl_get_cipher_methods(), true) ? 'yes' : 'NO') . PHP_EOL;
echo '  OpenSSL version   : ' . OPENSSL_VERSION_TEXT . PHP_EOL;
echo 'ZipArchive          : ' . (class_exists('ZipArchive') ? 'yes' : 'NO') . PHP_EOL;
echo 'PDO drivers         : ' . implode(',', PDO::getAvailableDrivers()) . PHP_EOL;
echo 'hash_hmac/hash_equals: ' . ((function_exists('hash_hmac') && function_exists('hash_equals')) ? 'yes' : 'NO') . PHP_EOL;
echo 'random_bytes        : ' . (function_exists('random_bytes') ? 'yes' : 'NO') . PHP_EOL;

// exec may exist as a function and still be blocked by disable_functions.
$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
echo 'exec() callable     : ' . (function_exists('exec') && ! in_array('exec', $disabled, true) ? 'yes' : 'NO (disabled)') . PHP_EOL;
echo 'memory_limit        : ' . ini_get('memory_limit') . PHP_EOL;
echo 'max_execution_time  : ' . ini_get('max_execution_time') . PHP_EOL;
echo 'open_basedir        : ' . (ini_get('open_basedir') !== '' ? ini_get('open_basedir') : '(unset)') . PHP_EOL;

echo PHP_EOL . '— paths —' . PHP_EOL;
echo 'storage_path()      : ' . storage_path() . PHP_EOL;
echo '  writable          : ' . (is_writable(storage_path()) ? 'yes' : 'NO') . PHP_EOL;
echo 'storage/app/private : ' . (is_writable(storage_path('app/private')) ? 'writable' : 'NOT writable') . PHP_EOL;
echo 'database path       : ' . config('database.connections.sqlite.database') . PHP_EOL;
echo '  exists            : ' . (file_exists(config('database.connections.sqlite.database')) ? 'yes' : 'NO') . PHP_EOL;
echo 'default disk        : ' . config('filesystems.default') . PHP_EOL;

echo PHP_EOL . '— scheduler / cron —' . PHP_EOL;
$schedule = $app->make(Illuminate\Console\Scheduling\Schedule::class);
echo 'scheduled events    : ' . count($schedule->events()) . PHP_EOL;
echo 'cron reachable      : NOT TESTED from here (docs/HOST_COMPATIBILITY.md marks it BLOCKED)' . PHP_EOL;
