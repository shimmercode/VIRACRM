<?php
/**
 * REQ-10.03 — record-level policies.
 *
 * authz.php proves the permission *helpers* answer correctly. That is not the
 * same thing as proving a policy is registered and actually consulted: a Gate
 * with no policy registered silently falls through, and a policy registered for
 * the wrong class name never runs. Both failures look like success from the
 * permission helper's point of view.
 *
 * This script therefore calls Gate::allows() / Gate::denies() — the same path
 * the application uses — and asserts the answers.
 */
/*
 * ISOLATION — this script writes fixtures (users, roles, a contract, a
 * project), so it must never run against the shared database.
 *
 * The first version of this file did exactly that and was caught by the
 * regression runner's isolation check, which reported "SHARED DATABASE WAS
 * MODIFIED" with users 2 -> 6 and projects 3 -> 4. That is the check doing its
 * job on the person who wrote the other checks.
 *
 * The fix is the same mechanism the browser flows use: copy the database into
 * a private directory and point DB_DATABASE at the copy BEFORE bootstrap, so
 * Laravel's immutable Dotenv never sees the shared path.
 */
$flowRoot = getenv('VIRA_FLOW_ROOT') ?: ('/home/user/VIRACRM/.arena/flows');
$privateDir = rtrim($flowRoot, '/') . '/policies';

if (! is_dir($privateDir) && ! mkdir($privateDir, 0o775, true) && ! is_dir($privateDir)) {
    fwrite(STDERR, "cannot create {$privateDir}\n");
    exit(2);
}

$sharedDb = getenv('VIRA_BASE_DB') ?: '/home/user/VIRACRM/database/database.sqlite';
$privateDb = $privateDir . '/database.sqlite';

if (! copy($sharedDb, $privateDb)) {
    fwrite(STDERR, "cannot copy database to {$privateDb}\n");
    exit(2);
}

// A stale WAL/SHM beside the shared database would make the copy inconsistent.
foreach (['-wal', '-shm'] as $suffix) {
    @unlink($privateDb . $suffix);
}

$_ENV['DB_DATABASE'] = $privateDb;
$_SERVER['DB_DATABASE'] = $privateDb;
putenv('DB_DATABASE=' . $privateDb);

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Belt and braces: if the override somehow did not take, writing fixtures to
// the shared database is the one thing this script must not do.
if (realpath((string) config('database.connections.sqlite.database')) === realpath($sharedDb)) {
    fwrite(STDERR, "refusing to run: still pointed at the shared database\n");
    exit(2);
}

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

$results = [];
$check = function (string $id, bool $ok, string $detail) use (&$results): void {
    $results[] = $ok;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . str_pad($id, 34) . $detail . "\n";
};

// A password nobody knows and nobody needs: this script never authenticates.
$pw = rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');

$makeUser = function (string $username, bool $superadmin, array $permissions) use ($pw): User {
    $u = User::query()->firstOrNew(['username' => $username]);
    $u->fill([
        'name' => $username,
        'username' => $username,
        'password' => Hash::make($pw),
        'is_superadmin' => $superadmin,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $u->save();

    if (! $superadmin) {
        // Build a dedicated role so the assertions do not depend on whichever
        // permissions the seeded viewer/manager roles happen to carry today.
        $role = Role::query()->firstOrCreate(
            ['code' => 'policy-test-' . $username],
            ['title' => 'آزمون سیاست ' . $username, 'is_active' => true]
        );

        $ids = \App\Models\Permission::query()
            ->whereIn('code', $permissions)
            ->pluck('id')
            ->all();

        \Illuminate\Support\Facades\DB::table('role_permissions')->where('role_id', $role->id)->delete();
        foreach ($ids as $pid) {
            \Illuminate\Support\Facades\DB::table('role_permissions')->insert([
                'role_id' => $role->id, 'permission_id' => $pid,
            ]);
        }

        $u->roles()->sync([$role->id]);
    }

    return $u->fresh();
};

// ── Fixtures ─────────────────────────────────────────────────────────────
$customer = Customer::query()->first();
if ($customer === null) {
    $customer = new Customer();
    $customer->fill([
        'type' => 'legal', 'status' => 'active',
        'company_name' => 'مشتری آزمون سیاست',
        'mobile' => '0912' . random_int(1000000, 9999999),
    ]);
    $customer->save();
}

$contract = Contract::query()->firstOrNew(['title' => 'قرارداد آزمون سیاست']);
if (! $contract->exists) {
    $max = (int) Contract::withTrashed()->max('id');
    $contract->fill([
        'number' => 'VIRA-C-POL' . ($max + 1),
        'customer_id' => $customer->id,
        'title' => 'قرارداد آزمون سیاست',
        'amount' => 1_000_000,
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
    $contract->save();
}

$project = Project::query()->firstOrNew(['title' => 'پروژه آزمون سیاست']);
if (! $project->exists) {
    $project->fill([
        'customer_id' => $customer->id,
        'type' => 'seo',
        'title' => 'پروژه آزمون سیاست',
        'status' => 'active',
        'amount' => 500_000,
    ]);
    $project->save();
}

// ── Users with deliberately narrow grants ────────────────────────────────
$plainViewer = $makeUser('policy_viewer', false, ['contracts.view', 'projects.view']);
$moneyViewer = $makeUser('policy_money', false, ['contracts.view', 'contracts.view_financials']);
$owner = $makeUser('policy_owner', false, []);           // no blanket project view
$nobody = $makeUser('policy_nobody', false, []);

// The project owner case: assign the project to a user with no permission.
$project->owner_user_id = $owner->id;
$project->save();

$superadmin = User::query()->where('is_superadmin', true)->firstOrFail();

// ── Contract policies ────────────────────────────────────────────────────
$check('T-POL-01', Gate::forUser($plainViewer)->allows('view', $contract),
    'viewer with contracts.view may view the contract');

$check('T-POL-02', ! Gate::forUser($plainViewer)->allows('viewFinancials', $contract),
    'viewer WITHOUT contracts.view_financials cannot see the amount');

$check('T-POL-03', Gate::forUser($moneyViewer)->allows('viewFinancials', $contract),
    'viewer WITH contracts.view_financials may see the amount');

$check('T-POL-04', ! Gate::forUser($plainViewer)->allows('update', $contract),
    'viewer cannot update the contract');

$check('T-POL-05', ! Gate::forUser($nobody)->allows('view', $contract),
    'user with no permission cannot view the contract');

$check('T-POL-06', Gate::forUser($superadmin)->allows('viewFinancials', $contract),
    'superadmin may see amounts');

// ── Project policies ────────────────────────────────────────────────────
$check('T-POL-07', Gate::forUser($plainViewer)->allows('view', $project),
    'viewer with projects.view may view the project');

$check('T-POL-08', ! Gate::forUser($plainViewer)->allows('viewFinancials', $project),
    'viewer without projects.view_financials cannot see the amount');

// A record assigned to someone who cannot open it is unusable (§5-5).
$check('T-POL-09', Gate::forUser($owner)->allows('view', $project),
    'project owner may view the project without the blanket permission');

$check('T-POL-10', Gate::forUser($owner)->allows('viewFinancials', $project) === false,
    'project owner may NOT see the amount without the financial permission');

$check('T-POL-11', ! Gate::forUser($nobody)->allows('view', $project),
    'a non-owner without permission cannot view the project');

$check('T-POL-12', ! Gate::forUser($plainViewer)->allows('delete', $project),
    'viewing does not imply deleting');

// ── Registration sanity ─────────────────────────────────────────────────
$contractPolicy = Gate::getPolicyFor(Contract::class);
$projectPolicy = Gate::getPolicyFor(Project::class);

$check('T-POL-13', $contractPolicy instanceof \App\Policies\ContractPolicy,
    'ContractPolicy is registered for the Contract model');
$check('T-POL-14', $projectPolicy instanceof \App\Policies\ProjectPolicy,
    'ProjectPolicy is registered for the Project model');

$passed = count(array_filter($results));
$total = count($results);

echo "\n--- policies: {$passed}/{$total} passed ---\n";
exit($passed === $total ? 0 : 1);
