<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contract;
use App\Support\Money;

$c = Contract::query()->first();
if (! $c) { echo "no contracts\n"; exit; }
echo "raw amount: " . var_export($c->amount, true) . "\n";
echo "fromRial: " . var_export($c->amountAsMoney()->amount, true) . "\n";
echo "formatPersian: [" . $c->amountAsMoney()->formatPersian() . "]\n";
echo "formatAscii: [" . $c->amountAsMoney()->formatAscii() . "]\n";
echo "\nMoney::parse('120,000,000') = ";
$p = Money::parse('120,000,000');
echo $p ? $p->amount : 'NULL';
echo "\nMoney::parse('۱۲۰٬۰۰۰٬۰۰۰') = ";
$q = Money::parse('۱۲۰٬۰۰۰٬۰۰۰');
echo $q ? $q->amount : 'NULL';
echo "\n";
