#!/usr/bin/env php
<?php

/**
 * Does `vira:backup` back up the database the application is actually using?
 *
 * WHY THIS EXISTS
 * ───────────────
 * The backup engine is built on `SQLite3::backup()` — a native, side-effect-free
 * file snapshot — and `BackupDatabase` reads its source from
 * `database.connections.sqlite.database`, not from the default connection.
 * That is correct for the SQLite deployment the project runs on today. It is
 * NOT correct for a MySQL/MariaDB deployment, and the failure is silent in the
 * worst direction: the command would snapshot a file that is not the live
 * database rather than refuse.
 *
 * This probe answers one question per driver: which source would the backup
 * command read, and does the live connection agree? It needs no database
 * server because it only resolves configuration.
 *
 * It is NOT a MySQL test. It is configuration resolution, and the report says so.
 *
 * USAGE
 *   php tools/checks/backup-driver-probe.php
 *   DB_CONNECTION=mysql DB_DATABASE=vira_prod php tools/checks/backup-driver-probe.php
 *
 * EXIT
 *   0  the configured backup source is the default connection's source
 *   1  they disagree (the deployment would back up the wrong thing)
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$default = (string) config('database.default');
$sqlite = (string) config('database.connections.sqlite.database');
$active = (string) config("database.connections.{$default}.database");

printf("default connection        : %s\n", $default);
printf("backup source (connection)  : sqlite.database = %s\n", $sqlite);
printf("live source (%s)  : %s.database = %s\n", $default, $default, $active);

/*
 * For SQLite, the two agree by construction — the live database IS that file.
 * For anything else they cannot agree: one is a filesystem path and the other
 * is a schema name on a server.
 */
$agrees = $default === 'sqlite' && $sqlite === $active;

if ($default !== 'sqlite') {
    printf("\nis the backup source a file on this host? %s\n",
        is_file($sqlite) ? 'yes' : 'NO — it is not a path at all');

    echo "\nRESULT: MISMATCH.\n";
    echo "The application reads from {$default}, but `vira:backup` would snapshot\n";
    echo "`{$sqlite}`. On this deployment the command does not back up the live\n";
    echo "database. Run `vira:backup` only where DB_CONNECTION=sqlite, or move the\n";
    echo "backup path to a mysqldump/MySQL-native strategy before switching engines.\n";

    exit(1);
}

echo "\nRESULT: MATCH.\n";
echo "The backup source is the file the application reads.\n";

exit($agrees ? 0 : 1);
