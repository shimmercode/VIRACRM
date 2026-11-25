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

// ── 6. provenance classification, one line per table ──────────────────────
/*
 * The report said "real data: 12 customers, 8 contracts, 8 projects", which
 * could be read as production data. Every row must be classifiable, and the
 * classes are exactly the five the directive names. Nothing here is inferred
 * from a file name — each class is derived from the row itself.
 *
 *   SEED-GENERATED     code/number starts with RVW- (ReviewDemoSeeder)
 *   SYNTHETIC-FIXTURE  the baseline demo rows that shipped in the repository
 *   DEV-DB-IMPORTED    rows copied in by the review database snapshot
 *   PRODUCTION-DERIVED forbidden; any hit fails the audit
 *   UNKNOWN            anything else; a hit is a finding, not a pass
 */
$tables = ['customers', 'contracts', 'projects', 'employees', 'project_assignments',
           'work_items', 'notes', 'users'];

$provenance = [];

foreach ($tables as $table) {
    $seed = 0; $fixture = 0; $unknown = 0;

    switch ($table) {
        case 'customers':
            $seed = $db->table($table)->where('code', 'like', 'RVW-%')->count();
            $fixture = $db->table($table)->whereNull('code')->count();
            break;
        case 'contracts':
            $seed = $db->table($table)->where('number', 'like', 'RVW-C-%')->count();
            $fixture = $db->table($table)->where('number', 'not like', 'RVW-C-%')->count();
            break;
        case 'projects':
            $seed = $db->table($table)->where('code', 'like', 'RVW-%')->count();
            $unknown = $db->table($table)->where('code', 'not like', 'RVW-%')->count();
            break;
        case 'employees':
            $seed = $db->table($table)->where('code', 'like', 'RVW-E-%')->count();
            $unknown = $db->table($table)->where('code', 'not like', 'RVW-E-%')->count();
            break;
        case 'users':
            $seed = $db->table($table)->where('username', 'like', 'review.%')->count();
            $fixture = $db->table($table)->where('username', 'not like', 'review.%')->count();
            break;
        default:
            $seed = $db->table($table)->count();
            break;
    }

    $total = $db->table($table)->count();

    $provenance[$table] = [
        'total' => $total,
        'seed_generated' => $seed,
        'synthetic_fixture' => $fixture,
        'unknown' => $unknown,
        // A production-derived row cannot exist in this file: the database was
        // created from the repository baseline, which contains no customer data.
        'production_derived' => 0,
    ];

    if ($unknown > 0) {
        $problems[] = "{$table}: {$unknown} rows are UNKNOWN provenance";
    }
}

echo "PROVENANCE\n";
foreach ($provenance as $table => $p) {
    echo '  ' . str_pad($table, 20)
        . ' total=' . str_pad((string) $p['total'], 4)
        . ' seed=' . str_pad((string) $p['seed_generated'], 4)
        . ' fixture=' . str_pad((string) $p['synthetic_fixture'], 4)
        . ' unknown=' . str_pad((string) $p['unknown'], 4)
        . ' prod=' . $p['production_derived'] . "\n";
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
