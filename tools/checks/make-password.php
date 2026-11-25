<?php
/**
 * Hash a password with the application's own hasher, for test fixtures.
 *
 * Test suites need a real bcrypt hash to insert into a throwaway database. The
 * suite passes the plaintext on argv and receives only the hash on stdout; the
 * plaintext is never written to disk or to a log.
 *
 * Usage: node docs/tools/php-cli.mjs tools/checks/make-password.php '<plaintext>'
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plain = $argv[1] ?? '';

if (strlen($plain) < 8) {
    fwrite(STDERR, "usage: make-password.php <plaintext, min 8 chars>\n");
    exit(2);
}

echo \Illuminate\Support\Facades\Hash::make($plain), "\n";
