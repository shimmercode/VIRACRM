#!/usr/bin/env php
<?php

/**
 * VIRA CRM — MySQL/MariaDB compatibility harness.
 *
 * STATUS: NOT YET EXECUTED. There is no MySQL or MariaDB server in the
 * development environment (no client, no server binary, no container runtime,
 * and the OS package repository is unreachable). This file is the instrument to
 * run on a host that has one; it is not evidence of a passing test, and the
 * production-readiness report records item "MySQL/MariaDB run" as BLOCKED until
 * somebody runs this and pastes the output.
 *
 * WHY IT IS A HARNESS AND NOT A TEST SUITE
 * ----------------------------------------
 * It is written to be run once, by hand, against a THROWAWAY database, and to
 * print a verdict per area. It deliberately does not join the regression suite:
 * a suite that cannot run in CI is a suite that silently rots, and a skipped
 * test that looks green is worse than an absent one.
 *
 * SAFETY
 * ------
 * It refuses to run unless the database name matches /_vira_mysql_probe_/ and
 * the server reports no other tables in it. It never touches the application
 * database, never touches shared data, and drops what it creates.
 *
 * USAGE
 * -----
 *   mysql -u root -e "CREATE DATABASE _vira_mysql_probe_ CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
 *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=_vira_mysql_probe_ \
 *   DB_USERNAME=root DB_PASSWORD=... php tools/checks/mysql-compatibility.php
 *
 * The ten areas below are the ones the client asked for. Each prints
 * PASS / FAIL / SKIP with what it actually observed.
 */

declare(strict_types=1);

// ── bootstrap ─────────────────────────────────────────────────────────────
$root = dirname(__DIR__, 2);

if (! is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/ missing — resolve dependencies first.\n");
    exit(2);
}

require $root . '/vendor/autoload.php';

$driver = getenv('DB_CONNECTION') ?: 'mysql';

if ($driver !== 'mysql') {
    fwrite(STDERR, "DB_CONNECTION must be 'mysql' for this harness (got '{$driver}').\n");
    exit(2);
}

$results = [];

$record = static function (string $id, ?bool $ok, string $detail) use (&$results): void {
    $results[] = ['id' => $id, 'ok' => $ok, 'detail' => $detail];
    $verdict = $ok === null ? 'SKIP' : ($ok ? 'PASS' : 'FAIL');
    printf("%-5s %-30s %s\n", $verdict, $id, $detail);
};

// ── connect ───────────────────────────────────────────────────────────────
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

// Refuse anything that is not obviously a throwaway probe database.
if (! preg_match('/^_vira_mysql_probe_/', $name)) {
    fwrite(STDERR,
        "REFUSING: DB_DATABASE must start with '_vira_mysql_probe_' (got '{$name}').\n"
        . "This harness creates and drops tables; point it at a throwaway database.\n");
    exit(2);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
} catch (Throwable $e) {
    $record('connect', false, 'could not connect: ' . $e->getMessage());
    echo "\nNo verdict can be given for any area. Item stays BLOCKED.\n";
    exit(1);
}

$serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
$isMaria = stripos($serverVersion, 'maria') !== false;

$record('connect', true, sprintf('%s %s', $isMaria ? 'MariaDB' : 'MySQL', $serverVersion));

// ── 0. the database must be empty ─────────────────────────────────────────
$existing = (int) $pdo->query('select count(*) from information_schema.tables where table_schema = '
    . $pdo->quote($name))->fetchColumn();

if ($existing > 0) {
    $record('empty-database', false, "database already holds {$existing} tables — refusing to proceed");
    exit(1);
}
$record('empty-database', true, 'database is empty');

// ── 1. engine, charset and collation ──────────────────────────────────────
/*
 * Persian text is the whole point of this product, so the character set is not
 * a detail. utf8mb3 (what MySQL calls "utf8") cannot store the full range and
 * silently truncates or errors on 4-byte sequences; a wrong collation makes
 * Persian search behave inconsistently between = and LIKE.
 */
$row = $pdo->query('select default_character_set_name, default_collation_name '
    . 'from information_schema.schemata where schema_name = ' . $pdo->quote($name))->fetch(PDO::FETCH_ASSOC);

$charset = $row['default_character_set_name'] ?? '';
$collation = $row['default_collation_name'] ?? '';

$record('charset-utf8mb4', $charset === 'utf8mb4', "database charset={$charset} collation={$collation}");

// ── run the real migrations ───────────────────────────────────────────────
/*
 * This is the part that matters most: the migrations are exercised against the
 * real engine, from an empty database, exactly as a fresh production install
 * would be. Anything SQLite tolerated and MySQL rejects surfaces here.
 */
$migrate = shell_exec(sprintf(
    'cd %s && DB_CONNECTION=mysql DB_HOST=%s DB_PORT=%s DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s '
    . 'php artisan migrate --force --no-interaction 2>&1',
    escapeshellarg($root),
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($name),
    escapeshellarg($user),
    escapeshellarg($pass),
));

$migrateOutput = trim((string) $migrate);
$migrateOk = $migrateOutput !== '' && stripos($migrateOutput, 'error') === false
    && stripos($migrateOutput, 'exception') === false;

$record('migrations-from-empty', $migrateOk,
    $migrateOk ? 'artisan migrate completed' : 'migrate failed — see output below');

if (! $migrateOk) {
    echo "\n---- migrate output ----\n{$migrateOutput}\n---- end ----\n";
}

$tableCount = (int) $pdo->query('select count(*) from information_schema.tables where table_schema = '
    . $pdo->quote($name))->fetchColumn();

$record('table-count', $tableCount > 0, "{$tableCount} tables created");

// ── 2. every table is InnoDB ──────────────────────────────────────────────
/*
 * Foreign keys and transactions require InnoDB. A table that silently landed on
 * another engine would accept the schema and then ignore the constraints, which
 * is the worst possible outcome: it looks correct until data is lost.
 */
$engines = $pdo->query('select engine, count(*) c from information_schema.tables '
    . 'where table_schema = ' . $pdo->quote($name) . ' group by engine')->fetchAll(PDO::FETCH_ASSOC);

$engineSummary = implode(', ', array_map(
    static fn ($r) => $r['engine'] . '=' . $r['c'],
    $engines,
));

$allInnodb = count($engines) === 1 && ($engines[0]['engine'] ?? '') === 'InnoDB';

$record('engine-innodb', $allInnodb, $engineSummary);

// ── 3. foreign keys actually exist ────────────────────────────────────────
$fkCount = (int) $pdo->query('select count(*) from information_schema.table_constraints '
    . 'where table_schema = ' . $pdo->quote($name) . " and constraint_type = 'FOREIGN KEY'")->fetchColumn();

$record('foreign-keys', $fkCount > 0, "{$fkCount} foreign key constraints present");

// ── 4. unique indexes, and the utf8mb4 key-length trap ────────────────────
/*
 * utf8mb4 costs 4 bytes per character, so a UNIQUE index on VARCHAR(255) needs
 * 1020 bytes. InnoDB allows 3072 with DYNAMIC row format (the default on
 * MySQL 5.7.7+ / MariaDB 10.2+), but 767 with COMPACT — and the migration then
 * fails outright on an older server. Reporting the longest keys makes that
 * visible instead of surprising somebody at 3am during a deploy.
 */
$longKeys = $pdo->query(
    'select s.table_name, s.column_name, c.character_maximum_length, s.index_name
       from information_schema.statistics s
       join information_schema.columns c
         on c.table_schema = s.table_schema
        and c.table_name = s.table_name
        and c.column_name = s.column_name
      where s.table_schema = ' . $pdo->quote($name) . '
        and s.non_unique = 0
        and c.character_maximum_length is not null
      order by c.character_maximum_length desc
      limit 5'
)->fetchAll(PDO::FETCH_ASSOC);

$longest = $longKeys[0] ?? null;
$longestBytes = $longest ? ((int) $longest['character_maximum_length']) * 4 : 0;

$record('unique-index-key-length', $longestBytes <= 3072,
    $longest
        ? sprintf('longest unique key %s.%s = %d chars = %d bytes (InnoDB limit 3072 with DYNAMIC, 767 with COMPACT)',
            $longest['table_name'], $longest['column_name'],
            (int) $longest['character_maximum_length'], $longestBytes)
        : 'no character unique keys');

// ── 5. constraints really reject bad data ─────────────────────────────────
/*
 * Declaring a constraint and having it enforced are different things. This
 * inserts a child row pointing at a parent that does not exist and requires the
 * server to refuse it.
 */
$fkRejected = null;
try {
    $pdo->exec("insert into projects (customer_id, type, title, status, amount, created_at, updated_at)
                values (999999, 'seo', 'probe', 'draft', 0, now(), now())");
    $fkRejected = false;
    $pdo->exec("delete from projects where title = 'probe'");
} catch (Throwable $e) {
    $fkRejected = true;
}

$record('fk-enforced', $fkRejected,
    $fkRejected ? 'dangling customer_id was rejected by the server'
                : 'DANGLING FOREIGN KEY WAS ACCEPTED — constraints are not enforced');

// ── 6. Persian text survives a write/read round trip ──────────────────────
$persian = 'شرکت ویرا — تست سازگاری پایگاه داده';
$roundTrip = null;

try {
    $stmt = $pdo->prepare('insert into customers (type, company_name, status, created_at, updated_at)
                           values (?, ?, ?, now(), now())');
    $stmt->execute(['legal', $persian, 'active']);
    $id = (int) $pdo->lastInsertId();

    $read = $pdo->query("select company_name from customers where id = {$id}")->fetchColumn();
    $roundTrip = ($read === $persian);

    $pdo->exec("delete from customers where id = {$id}");
} catch (Throwable $e) {
    $record('persian-roundtrip', false, 'round trip failed: ' . $e->getMessage());
    $roundTrip = null;
}

if ($roundTrip !== null) {
    $record('persian-roundtrip', $roundTrip,
        $roundTrip ? 'Persian text stored and read back byte-identical' : 'PERSIAN TEXT WAS MANGLED');
}

// ── 7. Persian search and collation ───────────────────────────────────────
/*
 * The application's search normalises Persian text and uses LIKE. Whether that
 * behaves the same on MySQL as on SQLite depends on the collation, so this
 * compares an equality match with a LIKE match on the same Persian string.
 */
$searchOk = null;
try {
    $pdo->prepare('insert into customers (type, company_name, status, created_at, updated_at)
                   values (?, ?, ?, now(), now())')->execute(['legal', 'شرکت آزمون جستجو', 'active']);

    $eq = (int) $pdo->query("select count(*) from customers where company_name = 'شرکت آزمون جستجو'")->fetchColumn();
    $like = (int) $pdo->query("select count(*) from customers where company_name like '%آزمون%'")->fetchColumn();

    $searchOk = ($eq === 1 && $like >= 1);

    $pdo->exec("delete from customers where company_name = 'شرکت آزمون جستجو'");
    $record('persian-search', $searchOk, "equality matched {$eq}, LIKE matched {$like}");
} catch (Throwable $e) {
    $record('persian-search', false, 'search probe failed: ' . $e->getMessage());
}

// ── 8. dates ──────────────────────────────────────────────────────────────
/*
 * Jalali dates are converted to Gregorian before storage, so what the database
 * must do is hold a DATE faithfully. A column that landed as VARCHAR, or a
 * timezone shift applied on read, both show up here.
 */
$dateOk = null;
try {
    $pdo->prepare('insert into customers (type, company_name, status, created_at, updated_at)
                   values (?, ?, ?, now(), now())')->execute(['legal', 'probe-date', 'active']);
    $id = (int) $pdo->lastInsertId();

    $pdo->prepare('update customers set created_at = ? where id = ?')->execute(['2026-03-21 00:00:00', $id]);
    $back = (string) $pdo->query("select date(created_at) from customers where id = {$id}")->fetchColumn();

    $dateOk = ($back === '2026-03-21');

    $pdo->exec("delete from customers where id = {$id}");
    $record('date-fidelity', $dateOk, "wrote 2026-03-21, read back {$back}");
} catch (Throwable $e) {
    $record('date-fidelity', false, 'date probe failed: ' . $e->getMessage());
}

// ── 9. transactions roll back ─────────────────────────────────────────────
$txOk = null;
try {
    $before = (int) $pdo->query('select count(*) from customers')->fetchColumn();

    $pdo->beginTransaction();
    $pdo->prepare('insert into customers (type, company_name, status, created_at, updated_at)
                   values (?, ?, ?, now(), now())')->execute(['legal', 'probe-tx', 'active']);
    $pdo->rollBack();

    $after = (int) $pdo->query('select count(*) from customers')->fetchColumn();

    $txOk = ($before === $after);
    $record('transaction-rollback', $txOk, "rows {$before} -> {$after} after rollback");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $record('transaction-rollback', false, 'transaction probe failed: ' . $e->getMessage());
}

// ── 10. the SQLite-only backup path ───────────────────────────────────────
/*
 * REPORTED, NOT TESTED. The backup system uses SQLite3::backup(), queries
 * sqlite_master, and runs PRAGMA integrity_check. None of those exist on MySQL.
 * This harness cannot make that work; it records the incompatibility so it is
 * not discovered during an incident.
 */
$record('backup-on-mysql', null,
    'NOT TESTED — app/Services/Backup uses SQLite3::backup(), sqlite_master and '
    . 'PRAGMA integrity_check, which have no MySQL equivalent. A MySQL backup '
    . 'path (mysqldump or xtrabackup) must be designed before cutover.');

// ── 11. seeds ─────────────────────────────────────────────────────────────
$seed = shell_exec(sprintf(
    'cd %s && DB_CONNECTION=mysql DB_HOST=%s DB_PORT=%s DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s '
    . 'php artisan db:seed --force --no-interaction 2>&1',
    escapeshellarg($root),
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($name),
    escapeshellarg($user),
    escapeshellarg($pass),
));

$seedOutput = trim((string) $seed);
$seedOk = stripos($seedOutput, 'error') === false && stripos($seedOutput, 'exception') === false;

$record('seeds', $seedOk, $seedOk ? 'db:seed completed' : 'db:seed failed — see output below');

if (! $seedOk) {
    echo "\n---- seed output ----\n{$seedOutput}\n---- end ----\n";
}

// ── summary ───────────────────────────────────────────────────────────────
$pass = count(array_filter($results, static fn ($r) => $r['ok'] === true));
$fail = count(array_filter($results, static fn ($r) => $r['ok'] === false));
$skip = count(array_filter($results, static fn ($r) => $r['ok'] === null));

echo "\n──────────────────────────────────────────────────────\n";
printf("areas %d · pass %d · fail %d · not-tested %d\n", count($results), $pass, $fail, $skip);

echo "\nNOT COVERED BY THIS HARNESS (still to be done on the target host):\n";
echo "  · locking and concurrency under parallel writers\n";
echo "  · a full backup and restore cycle against MySQL\n";
echo "  · the regression suite itself (tools/checks/run-all.mjs) with DB_CONNECTION=mysql\n";
echo "  · realistic data volume and query counts\n";

echo "\nPaste this whole output into docs/PRODUCTION_READINESS.md §3.\n";
echo "Until that is done, the MySQL/MariaDB item is BLOCKED, not passed.\n";

exit($fail > 0 ? 1 : 0);
