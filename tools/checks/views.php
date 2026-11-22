<?php
/**
 * Compile every Blade view. A compile failure here is exactly the error a user
 * would hit at render time, so this is a cheap way to cover all branches —
 * including views no test route reaches.
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$compiler = $app->make('blade.compiler');
$dir = realpath(__DIR__ . '/../../resources/views');

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$ok = 0;
$fail = 0;

foreach ($it as $file) {
    if (! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $relative = str_replace($dir . '/', '', $file->getPathname());
    $relative = str_replace('/', '.', $relative);
    $relative = str_replace('.blade.php', '', $relative);

    $source = file_get_contents($file->getPathname());

    try {
        $compiled = $compiler->compileString($source);

        // Write and syntax-check the compiled PHP by loading it through the
        // tokenizer, which does not execute it.
        if (function_exists('token_get_all')) {
            token_get_all($compiled, TOKEN_PARSE);
        }

        echo 'OK   ' . $relative . "\n";
        $ok++;
    } catch (Throwable $e) {
        echo 'FAIL ' . $relative . ': ' . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\ncompiled: $ok, failed: $fail\n";
exit($fail === 0 ? 0 : 1);
