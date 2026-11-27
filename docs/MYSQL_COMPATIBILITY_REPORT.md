# MYSQL / MARIADB COMPATIBILITY REPORT

**Project:** VIRA CRM — سیستم مدیریت ارتباط با مشتریان ویرا
**Commit under test:** `f73e61743663203a761dff1995627b80f7a4a0a8`
**Date:** 2026-11-27
**Overall status:** **BLOCKED** — no MySQL or MariaDB server could be provisioned in this environment.

---

## 1. The short version

The client asked for a MySQL regression as a release gate. **That regression did
not run, and this report does not pretend otherwise.** No MySQL or MariaDB
server exists in this environment and none could be installed. Every claim below
marked *static* comes from reading the code and configuration; nothing marked
static is a substitute for executing against a real server.

What this report *does* contain is a defect that the client needs to know about
before switching engines, because it would make the backup system silently
useless — and that defect was found by execution, not by inspection.

| Question | Answer | How established |
|---|---|---|
| Is there a MySQL/MariaDB server here? | No | binary search, `docker`/`podman` absent |
| Can one be installed? | No | `deb.debian.org` TLS handshake fails |
| Did migrations ever run on MySQL? | No | no server to run them on |
| Is the schema MySQL-compatible? | **Not proven** | static review only — see §4 |
| Does `vira:backup` work on MySQL? | **No — confirmed defect** | executed, see §3 |

---

## 2. Why provisioning failed, exactly

Three routes were attempted and all three are closed. These are the commands and
their real output, not summaries.

### 2.1 Is a server or container runtime already installed?

```
$ command -v mysql mysqld mariadb mariadbd docker podman
(none found)

$ command -v mysql_install_db mariadb-install-db
(none found)
```

`systemctl` exists but there is no init system behind it:

```
$ systemctl is-system-running
Failed to connect to bus: No such file or directory

$ systemctl list-timers
Failed to connect to bus: No such file or directory
```

### 2.2 Can one be installed from the OS repositories?

`apt` and `apt-get` are present. The package index cannot be updated:

```
$ apt-get update
E: Could not open lock file /var/lib/apt/lists/lock - open (13: Permission denied)
```

The permission problem is not the real blocker — the repository is unreachable
even before permissions matter:

```
$ python3 -c "urllib.request.urlopen('https://deb.debian.org/debian/dists/stable/Release')"
URLError: <urlopen error TLS/SSL connection has been closed (EOF)>
```

**This is an external infrastructure restriction.** Raising the process
privileges would not help, because the host that serves Debian packages closes
the TLS connection mid-handshake.

### 2.3 Can the harness at least connect to an existing server?

`tools/checks/mysql-compatibility.php` is written and safety-guarded (it refuses
any database whose name does not match `/_vira_mysql_probe_/`). Run with a
throwaway configuration it reports the honest failure:

```
$ DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=_vira_mysql_probe_ \
  DB_USERNAME=root DB_PASSWORD=... php tools/checks/mysql-compatibility.php
FAIL connect  SQLSTATE[HY000] [2006] MySQL server has gone away
exit 1
```

Run bare, it exits `2` (usage), which is correct: it is a harness meant to be
invoked against a real server, not a test that passes by default.

**Responsible party for this blocker:** the client's infrastructure team, or
whoever provides the staging host. The command to run there is in §6.

---

## 3. A confirmed defect: the backup engine cannot back up MySQL

This is the most important finding in this report, and it does not depend on a
server being available, because it is a question of which *source* the command
reads.

`app/Services/Backup/BackupBuilder.php:202` opens the source with the native
`SQLite3` class:

```php
$source = new SQLite3($this->databasePath, SQLITE3_OPEN_READONLY);
$dest   = new SQLite3($target);
if ($source->backup($dest) !== true) { ... }
```

and `app/Console/Commands/BackupDatabase.php:96` supplies that path from the
**SQLite connection specifically**, not from the default connection:

```php
$databasePath = (string) config('database.connections.sqlite.database');
```

On the SQLite deployment this project actually runs on, these agree, and the
backup is correct. On a MySQL deployment they cannot agree, and the command does
not refuse — it proceeds against a path that is not the live database.

### 3.1 Executed proof

`tools/checks/backup-driver-probe.php` resolves the configuration without
needing a server. In the real deployment:

```
$ php tools/checks/backup-driver-probe.php
default connection        : sqlite
backup source (connection)  : sqlite.database = /home/user/VIRACRM/database/database.sqlite
live source (sqlite)  : sqlite.database = /home/user/VIRACRM/database/database.sqlite

RESULT: MATCH.
exit 0
```

Under a MySQL driver:

```
$ DB_CONNECTION=mysql DB_DATABASE=vira_prod php tools/checks/backup-driver-probe.php
default connection        : mysql
backup source (connection)  : sqlite.database = vira_prod
live source (mysql)  : mysql.database = vira_prod

is the backup source a file on this host? NO — it is not a path at all

RESULT: MISMATCH.
exit 1
```

Read the third line carefully. With `DB_CONNECTION=mysql`, the value the backup
command uses as a *file path* is the bare string `vira_prod` — a schema name,
not a path. So on the client's MySQL host `vira:backup` would either fail with
"database not found: vira_prod" or, if a file of that name happened to exist,
encrypt and store something that is not the database. Neither outcome is a
backup.

### 3.2 Status and remedy

| Item | Status |
|---|---|
| Backup works on the SQLite deployment | **PASS** — 16/16 in `backup-hardening` |
| Backup works on a MySQL deployment | **FAIL** — proven above |
| Restore works on a MySQL deployment | **FAIL** — same coupling, `RestoreBackup.php:113` |

The remedy is a `mysqldump`-based strategy selected by driver, and a hard
refusal in both commands when the driver is not `sqlite`. **This was not
implemented**, because doing so properly requires a server to test against and
this environment has none; shipping an untested code path that overwrites a live
database would be worse than the honest refusal. It is recorded here as the
condition that must be met before any MySQL cutover.

---

## 4. Static compatibility review (not evidence)

The following was read from source. **None of it substitutes for running the
migrations**, and none of it is reported as a pass.

### 4.1 Intended engine and version

`config/database.php` ships MySQL/MariaDB with:

```php
'charset'   => env('DB_CHARSET', 'utf8mb4'),
'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
'strict'    => true,
```

The schema code is explicitly version-aware. The domain-constraint migration
(`2026_02_01_000100_add_domain_check_constraints.php:166-175`) enables CHECK
constraints only where the engine supports them:

```php
if (stripos($version, 'MariaDB') !== false) {
    return version_compare($this->numericVersion($version), '10.2.1', '>=');
}
return version_compare($this->numericVersion($version), '8.0.16', '>=');
```

So the supported floor is **MySQL 8.0.16+** or **MariaDB 10.2.1+**. The down
path of that migration returns early on SQLite and uses
`ALTER TABLE ... DROP CONSTRAINT` elsewhere — correct per dialect, but unexecuted.

### 4.2 Things that could bite, and what the code does about them

| Concern | What the code does | Risk |
|---|---|---|
| utf8mb4 index length (767-byte key limit) | Indexed string columns are declared `191` — `domain`, `keyword`, `normalized` — which is the standard workaround | Low — deliberate |
| CHECK constraints | Version-gated as above | Low |
| Money precision | **All financial columns are `unsignedBigInteger`**, integer Rial. No `decimal`, no float | Low — correct for Rial; cannot hold subunit |
| Persian collation (`unicode_ci` is accent-insensitive) | Search correctness does **not** rely on collation: `Customer::normalizePersianText()` maps ي→ی, ك→ک, strips ZWNJ and Arabic diacritics in PHP before writing `search_key` | Low |
| JSON columns | 6 `->json()` columns, mapped to MySQL `JSON` | Medium — unexecuted; MySQL JSON has no default and no index |
| Foreign keys | 96 `foreignId`/`foreign` declarations | Medium — SQLite does not enforce FKs by default, so 67 passing suites do **not** prove the FK graph is consistent |
| `dropColumn` / table rebuilds | 3 occurrences | Low |

**The foreign-key row deserves emphasis.** SQLite can be configured to ignore
foreign keys, and this project's suites pass on SQLite. MySQL will reject an
orphan insert that SQLite accepted. A migration that succeeds on SQLite is
therefore *not* evidence that it will succeed on MySQL, and §4 must not be read
as such.

### 4.3 Columns and counts observed

```
migrations                16
foreignId / foreign       96
->json()                   6
->decimal()                0
->unique() / ->index()   113
dropColumn / renameColumn  3
```

---

## 5. What would have to be true to call MySQL "verified"

In order, with the artefacts each step produces:

1. A host with MySQL 8.0.16+ or MariaDB 10.2.1+ and an empty database named to
   match `/_vira_mysql_probe_/`.
2. `php artisan migrate --seed` against it, exit 0, with the migration table
   listing all 16 migrations.
3. `php tools/checks/mysql-compatibility.php` — all ten areas PASS.
4. The 69-suite roster run with `DB_CONNECTION=mysql`, all passing.
5. A backup → restore cycle using a **MySQL-native** strategy, because §3 shows
   the current one cannot work there.
6. Row-count and fingerprint comparison between the two engines, to catch
   silent type coercion (Jalali dates, JSON round-tripping, Rial overflow).

Steps 1–6 are all outstanding.

---

## 6. Commands for the client host

```bash
# 1. create a disposable probe database
mysql -u root -e "CREATE DATABASE _vira_mysql_probe_ \
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2. run the harness against it
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=_vira_mysql_probe_ \
  DB_USERNAME=root DB_PASSWORD=<password> \
  php tools/checks/mysql-compatibility.php

# 3. confirm the backup defect before any cutover
DB_CONNECTION=mysql DB_DATABASE=vira_prod php tools/checks/backup-driver-probe.php
#    expected today: RESULT: MISMATCH, exit 1
```

The harness prints `PASS` / `FAIL` / `SKIP` per area with the values it observed.
Paste its output into this report; do not summarise it as "passed".

---

## 7. Status summary

| # | Gate item | Status | Evidence |
|---|---|---|---|
| 1 | Intended engine + version identified | **PASS** | `config/database.php:54-59`; version gate at `…domain_check_constraints.php:166-175` |
| 2 | Isolated instance provisioned | **BLOCKED** | §2 — no runtime, repo TLS-blocked |
| 3 | Empty database, migrate + seed | **BLOCKED** | depends on #2 |
| 4 | Full regression against MySQL | **BLOCKED** | depends on #2 |
| 5 | SQL compatibility / indexes / constraints / transactions / JSON / dates / collations / FKs | **BLOCKED** | static review only, §4 |
| 6 | Authorization, financial, payroll, reporting on MySQL | **BLOCKED** | depends on #4 |
| 7 | Backup operations on MySQL | **FAIL** | §3 — proven defect, not a provisioning problem |

**Nothing in this report is MySQL evidence.** Item 7 is a genuine defect found by
execution; items 1–6 are blocked or unproven, and the release decision treats
them as such.
