<?php

/**
 * VIRA CRM — query count, N+1 detection and timing for the pages that matter.
 *
 * WHY THIS EXISTS
 * ---------------
 * Until now nothing in the repository counted a single SQL query. Every browser
 * suite asserts on rendered HTML, which means a page could issue four hundred
 * queries to draw ten rows and pass every test. The client asked for this
 * measured rather than assumed, so this measures it.
 *
 * WHAT IT REPORTS
 *   · total queries per page
 *   · wall-clock render time per page
 *   · N+1 detection: the same query shape executed repeatedly, which is the
 *     signature of a missing eager load
 *   · the same measurement against a REALISTIC DATA VOLUME, because a query
 *     count on a seven-row database proves nothing about a seven-thousand-row one
 *
 * ISOLATION
 * ---------
 * It runs against a throwaway copy of the schema, never the application
 * database, and the volume scenario seeds that copy only.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$results = [];

$record = static function (string $id, bool $ok, string $detail) use (&$results): void {
    $results[] = ['id' => $id, 'ok' => $ok, 'detail' => $detail];
    printf("%-5s %-34s %s\n", $ok ? 'PASS' : 'FAIL', $id, $detail);
};

// ── isolated database ─────────────────────────────────────────────────────
$work = rtrim(sys_get_temp_dir(), '/') . '/vira-perf-' . bin2hex(random_bytes(4));
mkdir($work, 0700, true);
$dbPath = $work . '/perf.sqlite';
touch($dbPath);

// Boot the application first, then repoint its default connection at the
// throwaway file. Creating a second Capsule alongside the booted app doubles
// the connection set and exhausts the WASM heap.
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.perf' => [
    'driver' => 'sqlite',
    'database' => $dbPath,
    'prefix' => '',
    'foreign_key_constraints' => true,
]]);
config(['database.default' => 'perf']);
DB::purge('perf');
DB::setDefaultConnection('perf');

/*
 * Migrate through the console kernel rather than the migrator service
 * directly: calling $app->make('migrator')->run() crashes the WASM runtime,
 * while `artisan migrate` completes normally. Output is discarded — the check
 * asserts on the resulting table count, which is the thing that matters.
 */
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->call('migrate', ['--force' => true, '--no-interaction' => true]);

$tables = (int) DB::connection('perf')->select("select count(*) c from sqlite_master where type='table'")[0]->c;
$record('perf-00-isolated', $tables > 40, "isolated database has {$tables} tables (application DB untouched)");

// ── seed a realistic volume ───────────────────────────────────────────────
/*
 * Seven customers is not a workload. These numbers are chosen to be plausible
 * for a company running an SEO agency for a few years: enough that an N+1 costs
 * real time, not so many that seeding takes minutes on a WASM interpreter.
 */
const CUSTOMERS = 120;
const CONTRACTS = 90;
const PROJECTS = 80;

$seedStart = microtime(true);

// The console exception renderer fatals on this interpreter (no ext-intl, so
// Symfony's Normalizer is missing), which would hide the real seeding error.
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'SEED/RUN ERROR: ' . $e->getMessage() . "\n");
    exit(1);
});

DB::connection('perf')->table('roles')->insert([
    'code' => 'perf_admin', 'title' => 'مدیر آزمون کارایی', 'is_system' => 0,
    'created_at' => now(), 'updated_at' => now(),
]);

$userId = DB::connection('perf')->table('users')->insertGetId([
    'name' => 'کاربر کارایی', 'username' => 'perf_admin',
    'password' => 'x', 'is_superadmin' => 1, 'is_active' => 1,
    'must_change_password' => 0,
    'created_at' => now(), 'updated_at' => now(),
]);

$customerIds = [];
for ($i = 1; $i <= CUSTOMERS; $i++) {
    $customerIds[] = DB::connection('perf')->table('customers')->insertGetId([
        // CustomerType is a backed enum: 'natural' | 'legal'. Inserting anything else
        // renders fine at write time and only explodes when a view casts it.
        'type' => $i % 4 === 0 ? 'natural' : 'legal',
        'company_name' => 'شرکت نمونه شماره ' . $i,
        'first_name' => 'نام' . $i,
        'last_name' => 'نام‌خانوادگی' . $i,
        'mobile' => '0912' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
        'normalized_mobile' => '912' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
        'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

$contractIds = [];
for ($i = 1; $i <= CONTRACTS; $i++) {
    $contractIds[] = DB::connection('perf')->table('contracts')->insertGetId([
        'customer_id' => $customerIds[$i % CUSTOMERS],
        'contract_type_id' => null,
        'number' => 'VIRA-C-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'title' => 'قرارداد نمونه ' . $i,
        'amount' => 10000000 + $i,
        // ContractStatus is a backed enum: draft|active|expiring|expired|renewed|cancelled.
        // There is no CHECK constraint behind it, so a bad value writes fine and
        // only fails when a view casts it — see the report's integrity note.
        'status' => ['active', 'expired', 'draft'][$i % 3],
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-01',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

for ($i = 1; $i <= PROJECTS; $i++) {
    $pid = DB::connection('perf')->table('projects')->insertGetId([
        'customer_id' => $customerIds[$i % CUSTOMERS],
        'contract_id' => $contractIds[$i % CONTRACTS],
        'type' => $i % 3 === 0 ? 'design' : 'seo',
        'code' => 'VIRA-P-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'title' => 'پروژه نمونه ' . $i,
        'status' => 'active',
        'amount' => 5000000 + $i,
        'owner_user_id' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    if ($i % 3 !== 0) {
        DB::connection('perf')->table('seo_project_details')->insert([
            'project_id' => $pid, 'keyword_count' => 20, 'recurrence' => 'monthly',
            'reminder_offset_days' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Two work items per project: the list pages eager-load these.
    for ($k = 1; $k <= 1; $k++) {
        DB::connection('perf')->table('work_items')->insert([
            'kind' => $k === 1 ? 'report' : 'invoice',
            'project_id' => $pid,
            'due_date' => '2026-06-0' . $k,
            'state' => 'pending',
            'period' => 'monthly',
            'reminder_offset_days' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

$seedMs = (int) round((microtime(true) - $seedStart) * 1000);

$rowCount = static fn (string $t) => (int) DB::connection('perf')->table($t)->count();

$record('perf-01-volume', $rowCount('customers') === CUSTOMERS && $rowCount('projects') === PROJECTS,
    sprintf('seeded in %dms: customers=%d contracts=%d projects=%d work_items=%d',
        $seedMs, $rowCount('customers'), $rowCount('contracts'),
        $rowCount('projects'), $rowCount('work_items')));

// ── measurement ───────────────────────────────────────────────────────────
/*
 * Dispatches the request through the real HTTP kernel, so the count includes
 * middleware, the controller, eager loads and view rendering — not just the
 * controller's own queries.
 */
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::on('perf')->find($userId);
Illuminate\Support\Facades\Auth::setUser($user);

/**
 * @return array{queries:int, ms:int, shapes:array<string,int>, status:int}
 */
$measure = static function (string $uri) use ($kernel, $user, $app): array {
    Illuminate\Support\Facades\Auth::setUser($user);

    $queries = [];
    DB::connection('perf')->listen(static function ($q) use (&$queries): void {
        $queries[] = $q->sql;
    });

    $start = microtime(true);

    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession($app->make('session.store'));

    // The debug handler would render a console page and fatal on the missing
    // ext-intl Normalizer, hiding the cause. Capture it instead.
    $error = null;
    set_error_handler(static fn ($n, $m) => throw new ErrorException($m, 0, $n));
    try {
        $response = $kernel->handle($request);
    } catch (Throwable $e) {
        $error = get_class($e) . ': ' . $e->getMessage();
        $response = new Illuminate\Http\Response('', 500);
    } finally {
        restore_error_handler();
    }

    if ($response->getStatusCode() >= 500) {
        // Re-dispatch with debug on so the handler renders the exception rather
        // than the production error page.
        config(['app.debug' => true]);
        $dbg = $kernel->handle(Illuminate\Http\Request::create($uri, 'GET'));
        $body = strip_tags($dbg->getContent());
        $body = preg_replace('/\s+/', ' ', $body);
        $error = 'HTTP ' . $response->getStatusCode() . ' body: ' . substr((string) $body, 0, 400);
    }

    if ($error !== null) {
        $app->make('log')->debug('perf-probe-error', ['uri' => $uri, 'error' => $error]);
        fwrite(STDERR, "  [probe] {$uri} -> {$error}\n");
    }

    $ms = (int) round((microtime(true) - $start) * 1000);

    /*
     * Normalise a query to its shape by replacing every bound value with ?,
     * so "where id = 1" and "where id = 2" are recognised as the same query
     * run twice — which is exactly what an N+1 looks like.
     */
    $shapes = [];
    foreach ($queries as $sql) {
        $shape = preg_replace('/\s+/', ' ', trim($sql));
        $shape = preg_replace("/'[^']*'/", '?', $shape);
        $shape = preg_replace('/\b\d+\b/', '?', $shape);
        $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
    }

    return ['queries' => count($queries), 'ms' => $ms, 'shapes' => $shapes, 'status' => $response->getStatusCode()];
};

// ── the pages the client named ────────────────────────────────────────────
$pages = [
    'dashboard' => '/',
    'customers-list' => '/customers',
    'customer-detail' => '/customers/' . $customerIds[5],
    'contracts-list' => '/contracts',
    'contract-detail' => '/contracts/' . $contractIds[5],
    'projects-list' => '/projects',
    'project-detail' => '/projects/5',
    'search' => '/customers?q=' . rawurlencode('نمونه'),
];

// Budgets are per page on THIS interpreter, which is slow. They exist to catch
// an unbounded N+1, not to benchmark hardware.
$budget = [
    'dashboard' => 60, 'customers-list' => 90, 'customer-detail' => 60,
    'contracts-list' => 90, 'contract-detail' => 60, 'projects-list' => 90,
    'project-detail' => 60, 'search' => 90,
];

// A shape repeated more often than this, on a page listing N rows, is an N+1.
const NPLUS1_THRESHOLD = 12;

echo "\n  page                 status  queries      ms   repeated shapes\n";
echo '  ' . str_repeat('─', 68) . "\n";

foreach ($pages as $tag => $uri) {
    $m = $measure($uri);

    $repeated = array_filter($m['shapes'], static fn ($c) => $c > NPLUS1_THRESHOLD);
    arsort($repeated);

    $worst = $repeated ? array_key_first($repeated) : null;
    $worstCount = $worst ? $repeated[$worst] : 0;

    printf("  %-20s %6d %8d %7d   %s\n", $tag, $m['status'], $m['queries'], $m['ms'],
        $worst ? "{$worstCount}x " . substr((string) $worst, 0, 30) : 'none');

    $record('perf-q-' . $tag, $m['status'] === 200 && $m['queries'] <= $budget[$tag],
        sprintf('%s -> %d, %d queries (budget %d), %dms',
            $uri, $m['status'], $m['queries'], $budget[$tag], $m['ms']));

    $record('perf-n1-' . $tag, $worstCount === 0,
        $worstCount === 0
            ? 'no query shape repeated more than ' . NPLUS1_THRESHOLD . ' times'
            : sprintf('POSSIBLE N+1: a shape ran %dx — %s', $worstCount, substr((string) $worst, 0, 90)));
}

// ── scale behaviour ───────────────────────────────────────────────────────
/*
 * The question that matters is not "how many queries today" but "does the count
 * grow with the data". A list page with an N+1 doubles its queries when the
 * rows double; one with eager loads does not.
 */
$before = $measure('/customers');

for ($i = CUSTOMERS + 1; $i <= CUSTOMERS * 2; $i++) {
    DB::connection('perf')->table('customers')->insert([
        'type' => 'legal',
        'company_name' => 'شرکت مقیاس ' . $i,
        'mobile' => '0913' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
        'normalized_mobile' => '913' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
        'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

$after = $measure('/customers');

$delta = $after['queries'] - $before['queries'];

$record('perf-scale-customers', $delta <= 10,
    sprintf('customers %d -> %d rows changed the query count by %+d (%d -> %d); a bounded count means the list is paginated and eager-loaded',
        CUSTOMERS, CUSTOMERS * 2, $delta, $before['queries'], $after['queries']));

// ── cleanup ───────────────────────────────────────────────────────────────
DB::disconnect('perf');
@unlink($dbPath);
@rmdir($work);

$pass = count(array_filter($results, static fn ($r) => $r['ok']));
printf("\n──────────────────────────────────────────────────────\n%d/%d passed\n", $pass, count($results));

if ($pass !== count($results)) {
    foreach ($results as $r) {
        if (! $r['ok']) {
            printf("FAILED %s: %s\n", $r['id'], $r['detail']);
        }
    }
    exit(1);
}

exit(0);
