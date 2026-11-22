<?php
/** Repair demo work-item periods that were stored as Gregorian. Dev only. */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WorkItem;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;

$fixed = 0;
foreach (WorkItem::query()->get() as $item) {
    [$year] = array_pad(explode('-', (string) $item->period), 2, null);
    if ((int) $year > 1600) {
        $item->period = substr(JalaliDate::fromCarbon(CarbonImmutable::parse($item->due_date))->formatAscii(), 0, 7);
        $item->save();
        $fixed++;
    }
}
echo "periods repaired: $fixed\n";
foreach (WorkItem::query()->limit(4)->get() as $i) {
    echo "  period={$i->period} title={$i->title}\n";
}
