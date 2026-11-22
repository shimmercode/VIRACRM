<?php
/**
 * Development check: every Eloquent model used by the 2-C controllers resolves
 * to a real table and can be instantiated. Catches the class of bug where a
 * controller references a model that was never written.
 */
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$models = [
    'Attachment', 'AuditLog', 'Contract', 'ContractFile', 'ContractTemplate',
    'ContractType', 'ContractVersion', 'Customer', 'CustomerContact',
    'CustomerWebsite', 'Note', 'Permission', 'Project', 'Role', 'Setting',
    'SettingsHistory', 'User', 'WorkItem',
];

$fail = 0;
foreach ($models as $m) {
    $cls = 'App\\Models\\' . $m;
    if (! class_exists($cls)) {
        echo "FAIL missing class $cls\n";
        $fail++;
        continue;
    }
    try {
        $inst = new $cls();
        echo 'OK   ' . str_pad($m, 18) . $inst->getTable() . "\n";
    } catch (Throwable $e) {
        echo 'FAIL ' . $m . ': ' . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\n" . ($fail === 0 ? 'ALL MODELS OK' : "$fail FAILURES") . "\n";
exit($fail === 0 ? 0 : 1);
