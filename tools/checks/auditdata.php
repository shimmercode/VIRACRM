<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;

$rows = AuditLog::query()->orderByDesc('id')->limit(12)->get();

echo "total audit rows: " . AuditLog::query()->count() . "\n\n";
printf("%-4s %-14s %-16s %-8s %s\n", 'id', 'event', 'subject', 'user', 'desc');
foreach ($rows as $r) {
    printf(
        "%-4s %-14s %-16s %-8s %s\n",
        $r->id,
        $r->event,
        $r->subject_type ? class_basename($r->subject_type) . '#' . $r->subject_id : '—',
        $r->user_id ?? '—',
        mb_substr((string) $r->description, 0, 46)
    );
}

// Prove no secret ever reached the table.
$leak = AuditLog::query()
    ->where('description', 'like', '%password%')
    ->orWhere('changes', 'like', '%"password":"[^[]%')
    ->count();
echo "\nrows with a raw password value: $leak\n";
