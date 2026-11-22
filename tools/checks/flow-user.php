<?php

/**
 * DEVELOPMENT ONLY — prepare a user inside a flow's private database.
 *
 *   php tools/checks/flow-user.php <db-path> <username> <password> <must_change 0|1>
 *
 * Prints "OK <username>" on success. Never touches the shared database: the
 * path is passed explicitly and installed as DB_DATABASE before bootstrap.
 *
 * This is the isolation seam for credentials. Each flow sets its own password
 * on its own copy of the database, so no flow inherits a password from another
 * and no password needs to be stored in the repository, in .env, or in a file
 * that outlives the run.
 */

$dbPath = $argv[1] ?? null;
$username = $argv[2] ?? null;
$password = $argv[3] ?? null;
$mustChange = ($argv[4] ?? '0') === '1';

if ($dbPath === null || $username === null || $password === null) {
    fwrite(STDERR, "usage: flow-user.php <db-path> <username> <password> <must_change>\n");
    exit(2);
}

if (! is_file($dbPath)) {
    fwrite(STDERR, "no database at {$dbPath}\n");
    exit(2);
}

// Must happen before bootstrap: Laravel's Dotenv is immutable and will not
// overwrite a key that is already present in $_ENV/$_SERVER.
$_ENV['DB_DATABASE'] = $dbPath;
$_SERVER['DB_DATABASE'] = $dbPath;
putenv('DB_DATABASE=' . $dbPath);

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Refuse to run against anything but the flow sandbox, so a mistyped path can
// never rewrite a real installation's credentials.
$resolved = realpath($dbPath);
$sandbox = realpath($root . '/.arena/flows');

if ($resolved === false || $sandbox === false || ! str_starts_with($resolved, $sandbox . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "refusing to modify a database outside .arena/flows\n");
    exit(2);
}

$user = User::query()->firstOrNew(['username' => $username]);

$user->fill([
    'name' => $user->name ?: $username,
    'username' => $username,
    'password' => Hash::make($password),
    'is_active' => true,
    'must_change_password' => $mustChange,
]);

if ($username === 'admin') {
    $user->is_superadmin = true;
}

$user->save();

// A superadmin does not need a role; anyone else gets the `viewer` role so the
// permission checks have something real to reject.
if ($username !== 'admin') {
    $viewer = Role::query()->where('code', 'viewer')->first();

    if ($viewer) {
        // sync() replaces the pivot rows, so a reused username cannot keep a
        // role it picked up in an earlier scenario.
        $user->roles()->sync([$viewer->id]);
    }
}

echo "OK {$username}\n";
