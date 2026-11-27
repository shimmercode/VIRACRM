# MySQL Backup Remediation

**Date:** 2026-11-22
**Commit:** `884e68e`
**Scope:** `vira:backup`, `vira:restore`, and the strategy layer behind them
**Verdict:** defect **fixed and proven**; MySQL success path **BLOCKED** on a
host with no MySQL server.

---

## 1. The defect

`vira:backup` read the SQLite database path out of configuration and treated it
as a filesystem path, without ever asking the application which database it was
actually connected to. At `963ea5e`, `app/Console/Commands/BackupDatabase.php:96`:

```php
$databasePath = (string) config('database.connections.sqlite.database');
```

On the development deployment this is the file `database/database.sqlite`, so the
command backed that file up and every test passed. On a MySQL deployment,
`database.connections.sqlite.database` is the env value `DB_DATABASE` — the
**schema name**, e.g. `vira_prod`. A schema name is a string, and the builder
happily treated the string as a path.

The failure was not that it crashed. It was worse:

- It **did not** back up the MySQL database, because it never read it.
- It **did not** refuse, because nothing was checking what driver it was on.
- It produced a file with the right name, the right magic bytes, a valid
  encryption envelope and a manifest — and reported success.

An operator would have had a directory of backup containers, none of which
contained the production database, and no signal that anything was wrong. The
first time this surfaces is a restore, which is the one moment a backup must not
be a decoration.

**Why the whole test suite missed it:** every test ran on SQLite, where reading
`database.connections.sqlite.database` gives the correct answer. The defect is
invisible precisely on the driver the tests use. This is the shape of bug that a
green suite is worst at catching: the configuration being read is a *plausible*
source that happens to be correct in the only environment exercised.

---

## 2. The fix

Backup and restore no longer read configuration to guess what to capture. They
ask the live connection, and the answer selects a strategy.

### 2.1 Strategy selection

`app/Services/Backup/Snapshot/SnapshotSourceFactory.php`:

```php
$driver = $connection->getDriverName();   // from the RESOLVED connection

return match ($driver) {
    'sqlite'           => new SqliteSnapshotSource(...),
    'mysql', 'mariadb' => new MysqlDumpSnapshotSource(...),
    default            => throw new SnapshotUnavailable(...),
};
```

A driver that is not in the list **throws**, naming the driver and the supported
set. It does not fall through to SQLite, and it does not try to cope.

`fromContainer()` resolves the manager through the `DB` facade rather than
`app('db')`, because the project's test harness binds `db` on a private container
that the global `app()` cannot see. Both paths end at the same `DatabaseManager`.

### 2.2 The four classes

| Class | Lines | Responsibility |
|---|---:|---|
| `DatabaseSnapshotSource.php` | 62 | Interface: `driver()`, `format()`, `member()`, `describe()`, `assertReady()`, `snapshot()` |
| `SqliteSnapshotSource.php` | 125 | Online backup via `SQLite3::backup()`, not a file copy |
| `MysqlDumpSnapshotSource.php` | 331 | `mysqldump` via `proc_open`, streamed to a file |
| `SnapshotUnavailable.php` | 17 | Distinct exception type so callers can report the cause |
| `SnapshotSourceFactory.php` | 110 | Driver → strategy, or a refusal |

### 2.3 Fail closed, before anything is written

`assertReady()` is called **before** the destination directory is created and
before any success is reported. Every one of these is a refusal, not a degraded
mode:

| Condition | Message | Exit |
|---|---|---|
| `mysqldump` not on PATH and `MYSQLDUMP_PATH` unset | `mysqldump was not found on PATH (checked "mysqldump" and MYSQLDUMP_PATH). Install the MySQL/MariaDB client tools on the application host, or set MYSQLDUMP_PATH to the binary.` | 1 |
| `proc_open` disabled in the PHP build | `A MySQL backup requires a PHP build that permits process execution.` | 1 |
| Unimplemented driver | names the driver and the supported list | 1 |
| SQLite path empty or `:memory:` | refuses | 1 |
| SQLite file missing or unreadable | refuses | 1 |
| `VIRA_BACKUP_KEY` unset | refuses | 1 |

Every refusal is followed by `Nothing was written. No previous backup was
deleted.` and an `backup_failed` audit row.

The ordering matters. Creating the directory first and validating afterwards
leaves an empty directory behind on every refusal, and an operator looking at a
directory tree cannot tell "refused" from "wrote nothing yet". The directory is
created only once a source has confirmed it can produce a snapshot.

### 2.4 Success is not "a file exists"

`BackupBuilder` writes to `.partial`, then **re-opens its own output through the
restore gates** before promoting it:

```php
$verify = (new BackupRestorer($cipher))->verifyDeep($partial);
```

If any gate fails, or the recorded counts disagree with the snapshot, the
container is not renamed and no `.sha256` is written. Only a container that would
restore is published.

For MySQL specifically, `MysqlDumpSnapshotSource::verifyDumpContainsData()` checks
that the dump has a `MySQL dump` header, mentions every table, and contains
`INSERT INTO` rows for the populated ones (`users`, `migrations`) — because a
correctly named file that contains schema and no data is exactly the failure this
defect produced.

### 2.5 Restore is driver-aware

The container records `database_format` (`sqlite-file` | `mysqldump`) and
`database_driver` in both its header and its manifest. Restore compares that with
the current host **before moving anything aside**:

```
incompatible container: it holds a "sqlite-file" database but this host runs
"mysqldump". Refusing before anything is written.
```

Installing a MySQL dump over a SQLite file leaves two unusable files and a
database that boots into an error.

**Restoring *into* a MySQL server is not implemented.** `vira:restore` refuses it
explicitly, with exit 1 and an audit row, rather than copying a dump over a
schema name — which is the same category of mistake as the original defect.
Restoring is done with the `mysql` client against a target database the operator
chooses; see `docs/CUTOVER_RUNBOOK.md` §7.3.

---

## 3. Evidence

### 3.1 The defect, before and after

Originally, with `DB_CONNECTION=mysql DB_DATABASE=vira_prod`:

```
backup written            ← a file, from the schema name, containing nothing
```

After `884e68e`, the same command:

```
backup REFUSED: mysqldump was not found on PATH (checked "mysqldump" and
MYSQLDUMP_PATH). Install the MySQL/MariaDB client tools on the application host,
or set MYSQLDUMP_PATH to the binary. Refusing rather than writing a backup that
does not contain the database.
Nothing was written. No previous backup was deleted.
```

Exit code **1**, output directory **not created**. Captured by
`tools/checks/backup-driver-matrix.mjs` cases BDM-05..BDM-08.

### 3.2 Positive controls (SQLite, the driver that exists here)

```
$ php artisan vira:backup --path=…/out --no-prune
backup written and verified
  file   : vira-backup-2026-11-27-201930-723371.vbk1
  format : sqlite-file (sqlite)
  bytes  : 51,339
  tables : 57
  rows   : 484
  files  : 2
  sha256 : ad707dc74a2a2d069236c8d71a1142c7213c57c70b7924c55568c6c767887863
```

Restored into a separate empty database with the same key:

```
target driver : sqlite
target format : sqlite-file
restore complete
  tables         : 57
  rows           : 484
  files restored : 2
  format         : sqlite-file
```

57 tables and 484 rows round-tripped. The counts are read from the live database
via `sqlite_master` and compared against the snapshot, so they are not a label.

### 3.3 Test results

| Suite | Result |
|---|---|
| `tests/run.php` (PHP classes) | **19/19, 864 assertions, 0 failed** |
| `BackupDriverMatrixTest` | 38 assertions |
| `BackupRestoreTest` | 96 assertions |
| `tools/checks/backup-driver-matrix.mjs` | **22/22** (3 rows printed BLOCKED) |
| `tools/checks/backup-hardening.mjs` | **16/16**, unchanged by the refactor |

`backup-hardening` passing unchanged is the important line: it means the
existing guarantees — wrong key refused, target unchanged, audit row written,
foreign keys intact — survived the rewrite rather than being re-established
around it.

---

## 4. What is still BLOCKED, and why

**No MySQL server and no `mysqldump` exist in this environment.**
`mysqldump` is not on PATH, there is no package manager, and no registry is
reachable to install one. `proc_open` is disabled in the PHP runtime as well.

Three matrix rows therefore cannot execute:

| Row | What it needs |
|---|---|
| BDM-20 | A real MySQL/MariaDB server with `mysqldump` installed |
| BDM-21 | The same server, to restore into a separate empty schema |
| BDM-22 | A real dump, to confirm it contains `INSERT INTO` rows for each table |

These are **not simulated**. A stub `mysqldump` emitting a plausible file would
prove that the plumbing invokes a subprocess, and nothing about whether a real
dump is complete, restorable, or consistent. It would be a test that passes
because it was written to.

The MySQL strategy is implemented and its structural checks are in place, but
`verifyDumpContainsData()` has **never run against real `mysqldump` output**. Its
expectations about the header, the table list and the `INSERT INTO` form are
based on documented `mysqldump` behaviour and are unverified against a live
binary. That distinction is recorded rather than glossed.

**To close it**, on a host with MySQL and the client tools:

```bash
# 1. Prove a backup contains records.
php artisan vira:backup --path=/var/backups/vira-check
#    expect: format: mysqldump (mysql), a row count matching the live database

# 2. Prove it restores.
mysql -e 'CREATE DATABASE vira_restore_check'
#    extract database.sql from the container, then:
mysql vira_restore_check < database.sql
mysql vira_restore_check -e 'SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM customers;'

# 3. Prove the refusal paths on a host WITHOUT the tools.
#    (already proven here: BDM-05..BDM-08)

# 4. Drop the check database.
mysql -e 'DROP DATABASE vira_restore_check'
```

**Responsible:** whoever provisions the production database host.
**Never** run step 2 against the production schema.

---

## 5. Files changed

| File | Change |
|---|---|
| `app/Services/Backup/Snapshot/DatabaseSnapshotSource.php` | **new** — interface |
| `app/Services/Backup/Snapshot/SnapshotUnavailable.php` | **new** — exception type |
| `app/Services/Backup/Snapshot/SqliteSnapshotSource.php` | **new** — online backup API |
| `app/Services/Backup/Snapshot/MysqlDumpSnapshotSource.php` | **new** — mysqldump strategy |
| `app/Services/Backup/Snapshot/SnapshotSourceFactory.php` | **new** — driver dispatch |
| `app/Services/Backup/BackupBuilder.php` | strategy ctor, pre-write readiness, self-verification, `.partial` promotion |
| `app/Services/Backup/BackupRestorer.php` | format compatibility gate, format-dispatched integrity check, member from manifest |
| `app/Console/Commands/BackupDatabase.php` | strategy resolution, refusal path, format in output and audit |
| `app/Console/Commands/RestoreBackup.php` | driver/format derivation, explicit MySQL refusal |
| `tests/Feature/BackupDriverMatrixTest.php` | **new** — 38 assertions |
| `tests/Feature/BackupRestoreTest.php` | builder ctor updated |
| `tools/checks/backup-driver-matrix.mjs` | **new** — 22/22 command-level cases |

---

## 6. Residual risks

1. **The MySQL path has never run.** Stated above; it is the largest residual
   and it is a verification gap, not a known bug.
2. **`mysqldump` output varies by version and server.** `--single-transaction` and
   `--skip-lock-tables` behave differently against MyISAM tables, which are not
   transactional; a MyISAM table can be dumped inconsistently. The application's
   schema uses InnoDB throughout, but a table added later without an explicit
   engine could inherit a server default of MyISAM.
3. **Large databases stream into a temp file.** `mysqldump` writes to
   `--result-file`, and the container is built from that file. Peak disk use is
   roughly the dump size plus the compressed container. A multi-gigabyte database
   needs that much free space in the backup destination.
4. **A password is passed via `MYSQL_PWD`, not argv.** This keeps it out of
   `ps`, but `MYSQL_PWD` is visible to other processes of the same user on some
   kernels. The alternative — a defaults file — would need the password written
   to disk.
