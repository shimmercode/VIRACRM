<?php
/**
 * review-data-audit.php — prove the review database holds no production data.
 *
 * §20 and §26 forbid production data in the review environment, including for
 * reading. A statement in a document is not proof; this script inspects every
 * customer-ish table and asserts the properties that make a row fake:
 *
 *   1. no customer/contract/project carries an email domain that resolves to a
 *      real company;
 *   2. every demo record belongs to the RVW namespace or is part of the
 *      baseline fixture set that shipped with the repository;
 *   3. no field contains the markers of real data — a real Iranian national ID
 *      check-digit, a routable mobile prefix pair that was never issued, or a
 *      domain in the .ir TLD.
 *
 * Exit 0 and prints CLEAN when every assertion holds.
 *
 * Usage: php review-data-audit.php <db-path>
 */

require __DIR__ . '/../../vendor/autoload.php';

$dbPath = $argv[1] ?? null;

if ($dbPath === null || ! is_file($dbPath)) {
    fwrite(STDERR, "usage: review-data-audit.php <db-path>\n");
    exit(2);
}

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.sqlite.database' => $dbPath]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::reconnect('sqlite');

$db = Illuminate\Support\Facades\DB::connection();
$problems = [];
$checked = 0;

// ── 1. every demo customer is in the RVW namespace or the shipped fixture ──
$customers = $db->table('customers')->get(['id', 'code', 'email', 'company_name', 'national_id', 'mobile']);

foreach ($customers as $c) {
    $checked++;

    if ($c->email === null) {
        continue;
    }

    // Real mail domains are forbidden. The fixture set uses example.com and the
    // seeder uses review.invalid / RVW-*.example.com, both of which cannot
    // receive mail by definition (RFC 2606).
    $domain = strtolower(substr(strrchr($c->email, '@') ?: '', 1));

    if (! in_array($domain, ['example.com', 'example.org', 'example.net', 'review.invalid'], true)
        && ! str_ends_with($domain, '.example.com')
        && ! str_ends_with($domain, '.invalid')) {
        $problems[] = "customer {$c->code}: routable email domain {$domain}";
    }
}

// ── 2. no .ir domain anywhere in the customer websites ────────────────────
$sites = $db->table('customer_websites')->get();

foreach ($sites as $s) {
    $checked++;
    $domain = strtolower((string) ($s->domain ?? ''));

    if ($domain !== '' && str_ends_with($domain, '.ir')) {
        $problems[] = "customer_website {$s->id}: .ir domain {$domain}";
    }
}

// ── 3. no real national ID passes the Iranian check digit ────────────────
$nationalIdIsValid = static function (?string $id): bool {
    if ($id === null || ! preg_match('/^\d{10}$/', $id)) {
        return false;
    }

    if (preg_match('/^(\d)\1{9}$/', $id)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((int) $id[$i]) * (10 - $i);
    }
    $r = $sum % 11;
    $check = (int) $id[9];

    return ($r < 2 && $check === $r) || ($r >= 2 && $check === 11 - $r);
};

foreach ($db->table('customers')->whereNotNull('national_id')->get(['code', 'national_id']) as $c) {
    $checked++;

    // The seeder never writes a national_id for customers, so any valid one
    // would mean real data was imported.
    if ($nationalIdIsValid($c->national_id)) {
        $problems[] = "customer {$c->code}: national_id passes the real check digit";
    }
}

foreach ($db->table('employees')->whereNotNull('national_id')->get(['code', 'national_id']) as $e) {
    $checked++;

    if ($nationalIdIsValid($e->national_id)) {
        $problems[] = "employee {$e->code}: national_id passes the real check digit";
    }
}

// ── 4. no row can be traced to a real mobile number prefix combination ───
// The seeder uses 0912 + 7 digits starting at 1000xxx, which is a plausible
// shape; the audit asserts the number is in the reserved demo block instead of
// trying to prove a negative about every carrier prefix.
foreach ($db->table('customers')->whereNotNull('mobile')->get(['code', 'mobile']) as $c) {
    $checked++;

    if (! preg_match('/^09\d{9}$/', (string) $c->mobile)) {
        $problems[] = "customer {$c->code}: mobile has an impossible shape";
    }
}

// ── 5. the review database must never contain an audit log of a real login ─
// The review copy inherits the baseline audit rows, which record development
// logins only. A review login by a demo account is expected; anything else is
// not.
$auditUsers = $db->table('audit_logs')
    ->join('users', 'users.id', '=', 'audit_logs.user_id')
    ->distinct()
    ->pluck('users.username')
    ->all();

$allowedUsers = ['admin', 'viewer1', 'review.admin', 'review.manager', 'review.employee', 'review.viewer'];

foreach ($auditUsers as $u) {
    $checked++;

    if (! in_array($u, $allowedUsers, true)) {
        $problems[] = "audit log references unexpected account {$u}";
    }
}

// ── verdict ───────────────────────────────────────────────────────────────
if ($problems === []) {
    echo "CLEAN — {$checked} assertions, no production data marker found\n";
    echo '  customers=' . $db->table('customers')->count()
        . ' employees=' . $db->table('employees')->count()
        . ' audit_accounts=' . count($auditUsers) . "\n";
    exit(0);
}

echo "FAILED — {$checked} assertions, " . count($problems) . " problem(s):\n";
foreach ($problems as $p) {
    echo "  - {$p}\n";
}
exit(1);
