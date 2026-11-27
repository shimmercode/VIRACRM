# BACKUP AND RESTORE — ACCEPTANCE RECORD

**Project:** VIRA CRM — سیستم مدیریت ارتباط با مشتریان ویرا
**Commit under test:** `f73e61743663203a761dff1995627b80f7a4a0a8`
**Date:** 2026-11-27
**Suite:** `tools/checks/backup-hardening.mjs` — **16/16 passed**, exit 0
**Overall status:** **PASS on SQLite** · **BLOCKED** for scheduled execution · **FAIL** for MySQL (see §8)

---

## 1. What was actually executed

Not a demonstration, not a proposal: a full cycle run against disposable
databases, with the shared development database fingerprinted before and after.

The client's requirement was a complete end-to-end cycle. Below is that
requirement, one line per clause, and what the run produced.

| # | Requirement (client's words) | Status | Evidence |
|---|---|---|---|
| 1 | Generate a backup from a disposable test database | **PASS** | `BK-06` — exit 0, produced `vira-backup-…-876f5e.vbk1` |
| 2 | Verify encryption | **PASS** | `BK-07`, `BK-08` — 7.990 bits/byte |
| 3 | Verify file integrity | **PASS** | `BK-09`, `BK-11` |
| 4 | Destination outside the public document root | **PASS** | `BK-03`, `BK-04`, `BK-05` |
| 5 | Restore into a separate empty database | **PASS** | `BK-12` — exit 0 |
| 6 | Compare logical data fingerprints | **PASS** | `BK-14` — 56 tables identical |
| 7 | Verify restored records and relationships valid | **PASS** | `BK-15` — zero orphans |
| 8 | Test a failed restore; original unaffected | **PASS** | `BK-16`, `BK-10` |
| 9 | Scheduled execution via a real cron mechanism | **BLOCKED** | §7 — no scheduler exists here |

---

## 2. The backup itself

```
$ php artisan vira:backup --path=<private>
PASS BK-06  exit 0 → vira-backup-2026-11-27-175643-876f5e.vbk1
```

The container is not a zip and not a renamed SQL dump. Its structure, verified
byte-wise:

- magic `VIRABK1\n`
- a JSON header: `cipher=aes-256-gcm`, `kdf=hkdf-sha256`, salt, IV, GCM tag,
  `manifest_sha256`, `payload_bytes`, `created_at`, `contents`
- then the ciphertext

### 2.1 Encryption is real, not nominal

`BK-08` measures the payload's entropy and greps it for things that should not
survive encryption:

```
50805 bytes of payload at 7.990 bits/byte (random = 8),
with no SQLite magic and no plaintext Persian
```

8.0 is the theoretical maximum for random bytes. 7.990 over 50 KB means no
compressible structure remains. The complementary checks are what make this
conclusive rather than suggestive: the SQLite file header magic is absent, and
so is any Persian text — the database is full of Persian customer names, so
plaintext anywhere would have been found.

### 2.2 Integrity, and what happens when it fails

`BK-11` flips a single bit at byte 25,778 of a valid container and restores.
AES-GCM's authentication tag rejects it before a byte is written:

```
restore with 1 flipped bit → exit 1, audited
```

`BK-09` confirms a detached `.sha256` ships beside the container, so corruption
in transit can be detected without decrypting.

---

## 3. Destination validation — the tests that matter

A backup is a complete dump of the customer database. If it is reachable over
HTTP the entire access-control system is decoration.

| Attempt | Result |
|---|---|
| `--path=public/backups` | **refused**, exit 1 |
| `--path=public/a/b/c` | **refused**, exit 1 |
| `--path=public` | **refused**, exit 1 |
| `--path=.arena/../public/backups` | **refused** — `..` resolved first |
| a **symlink** pointing into `public/` | **refused** — judged by where it lands |
| `--path` outside the document root | accepted, exit 0 |
| default `storage/app/backups` | outside the root, confirmed |

After all attempts: `public/` still holds only `.htaccess, build, favicon.ico,
index.php, robots.txt`. No container reached it.

The symlink case is the one worth dwelling on. A prefix check on the *written*
path would accept `linkdir/escape/backups` because the string does not begin
with `public/`. `realpath()` follows the link and judges the destination by
where it actually lands, which is the only check that cannot be talked around.

> **Historical note, kept deliberately.** An earlier version of this guard used
> `substr($path, strlen($prefix)) === ''` as a prefix test. That expression is
> true only when the string *equals* the prefix, so `public/backups` was not
> caught. The defect was found by running the refusal cases beside an
> acceptance case — the negative tests alone would have passed by accident.

---

## 4. Restore into an empty database

```
PASS BK-12  restore into a fresh database exits 0 and reconciles:
            customers 7=7, contracts 5=5, projects 3=3,
            users 2=2, work_items 9=9
```

---

## 5. Logical fingerprint comparison

Row counts per table are a weak test — they cannot see a rewritten column, which
is exactly how a content leak was missed earlier in this project. `BK-14`
therefore compares a content digest, table by table:

```
PASS BK-14  all 56 data tables are logically identical between source
            and restored copy (485 rows total); only audit_logs differs,
            by design (backup_ok / restore_ok)
```

**Why `audit_logs` is excluded, and why that is not a dodge.** The backup
command appends a `backup_ok` row to the source *after* building the container,
and the restore appends `restore_ok` to the target. The audit table is
*supposed* to differ — that difference is the record that both operations
happened. Requiring a whole-database digest match would make a correct restore
fail, and would tempt a future engineer to weaken the check. Every other table
must match exactly, and does.

---

## 6. Relationships after restore

A digest match proves the rows came back; it does not prove they still refer to
each other.

```
PASS BK-15  pragma foreign_key_check returns nothing,
            0 contracts and 0 work items point at a missing customer
```

`PRAGMA foreign_key_check` scans the whole restored database for every declared
foreign key, so this covers the entire relationship graph rather than the two
tables named.

---

## 7. Failed restore — the original must survive

The dangerous failure is not "restore fails". It is "restore fails *after*
partially overwriting". Two executions:

```
PASS BK-16  wrong key → exit 1, 0 data tables changed,
            and a restore_failed audit row IS appended
PASS BK-10  wrong key → exit 1, refusal recorded in the audit log
```

Zero data tables changed proves the restore is atomic with respect to the
target: a bad container cannot half-overwrite a good database. The audit row is
appended by design — a refusal that leaves no trace is indistinguishable from a
backup that never ran.

---

## 8. Scheduled execution — BLOCKED

The client asked for verification through a real cron mechanism. No mechanism
exists on this host:

```
$ command -v crontab cron crond anacron at
(none found)

$ systemctl list-timers
Failed to connect to bus: No such file or directory

$ ls -ld /etc/cron.d
drwxr-xr-x 2 root root 60   # present, root-owned, no daemon behind it
```

`/etc/cron.d` exists as an empty directory on the base image with no `crond`
running and no init system to start one — `systemctl` fails to reach a bus. This
is not a permission problem.

**Status: BLOCKED.** Writing a crontab file that nothing will execute, and
calling it "verified", is exactly the failure mode the directive forbids.

**What is verified instead:** the command is schedule-*safe*. It takes
`--no-prune`, it prunes only after a successful container is written and
audited, and a failure leaves "No previous backup was deleted" (`BackupDatabase`
returns before pruning). Two consecutive runs produce two distinct containers
(a timestamp plus a random suffix). Those properties are what a scheduler
depends on, and they are tested; the scheduler itself is not.

**Responsible party:** the client's operations team, on the production host:

```cron
# /etc/cron.d/vira-backup — daily 02:00, run as the app user
0 2 * * * vira  cd /var/www/vira && php artisan vira:backup --no-interaction >> storage/logs/backup.log 2>&1
```

Verify by allowing two cycles and confirming two containers exist with a growing
audit trail — not by reading the crontab.

---

## 9. A defect that blocks MySQL backups — FAIL

While reviewing engine support, `tools/checks/backup-driver-probe.php` was run to
ask a single question: does `vira:backup` back up the database the application
is actually using?

```
$ DB_CONNECTION=mysql DB_DATABASE=vira_prod php tools/checks/backup-driver-probe.php
backup source (connection) : sqlite.database = vira_prod
live source (mysql)        : mysql.database  = vira_prod
is the backup source a file on this host? NO — it is not a path at all
RESULT: MISMATCH.   exit 1
```

The backup engine is `SQLite3`-native and reads
`database.connections.sqlite.database` rather than the default connection. On
MySQL that value is a schema name, not a path, and the live database is never
consulted.

| Deployment | Backup status |
|---|---|
| SQLite (what this project runs on today) | **PASS** — 16/16 |
| MySQL / MariaDB | **FAIL** — would not back up the live database |

Full analysis in `docs/MYSQL_COMPATIBILITY_REPORT.md` §3. **The MySQL cutover is
blocked until this is fixed**, and it must be fixed with a server present to
test against.

---

## 10. Isolation — no shared data was touched

Every command in this suite ran through `artisan-probe` against a private copy.
The shared database is fingerprinted before and after:

```
PASS BK-13  database/database.sqlite unchanged: 69d8b91f7ef3ba23…
```

The full-database fingerprint taken independently before and after this work
session is `54fe60a4a88d1e61f6950b19ff8d79774dc7d6d509b8becfdc6d3600b5e95c4a`
(57 tables, 484 rows), identical to the value recorded before the session began.

---

## 11. Status summary

| Area | Status | Exit |
|---|---|---|
| Backup creation, encryption, integrity | **PASS** | 0 |
| Destination validation incl. traversal + symlink | **PASS** | 0 |
| Restore to empty database, records + relationships | **PASS** | 0 |
| Logical fingerprint equality | **PASS** | 0 |
| Failed restore leaves original intact | **PASS** | 0 |
| Isolation from shared data | **PASS** | 0 |
| Scheduled execution (cron) | **BLOCKED** | — |
| Backup on MySQL | **FAIL** | 1 |

**Verdict:** backup and restore are **accepted for the SQLite deployment**. They
are **not accepted for MySQL**, and scheduling is **unverified** — both require
the production host.
