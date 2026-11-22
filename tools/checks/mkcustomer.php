<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;

$c = Customer::query()->firstOrCreate(
    ['company_name' => 'شرکت نمونهٔ آزمون'],
    [
        'type' => 'legal',
        'status' => 'active',
        'mobile' => '09121234567',
        'city' => 'تهران',
    ]
);

echo "customer id=" . $c->id . " name=" . $c->display_name . "\n";
echo "search_key=" . var_export($c->search_key, true) . "\n";
echo "normalized_mobile=" . var_export($c->normalized_mobile, true) . "\n";
