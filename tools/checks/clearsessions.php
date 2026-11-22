<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "sessions before: " . Illuminate\Support\Facades\DB::table('sessions')->count() . "\n";
Illuminate\Support\Facades\DB::table('sessions')->delete();
echo "sessions after: " . Illuminate\Support\Facades\DB::table('sessions')->count() . "\n";
