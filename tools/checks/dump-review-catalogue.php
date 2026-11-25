<?php
/**
 * Dump config/review.php as JSON so the document generator never has to parse
 * PHP with a regex.
 *
 * The first attempt at tools/generate-page-inventory.mjs scanned the file line
 * by line. It worked on the multi-line rows and silently missed every pair that
 * shared a line with another pair — so `status` came back undefined and the
 * script refused to emit a document at all. That is the good failure. The bad
 * one would have been emitting a document with a plausible but wrong count.
 *
 * The catalogue is PHP; let PHP read it.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo json_encode(
    config('review.pages'),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
), "\n";
