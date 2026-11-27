# MySQL Backup Test Matrix

**Date:** 2026-11-22
**Commit:** `884e68e`
**Suites:** `tests/Feature/BackupDriverMatrixTest.php` (38 assertions),
`tools/checks/backup-driver-matrix.mjs` (22/22),
`tools/checks/backup-hardening.mjs` (16/16)
**Verdict:** every command-level row executable in this environment passes;
**3 BLOCKED** on the absence of a MySQL server.

Every row states what was run, what was observed, and whether the observation
supports the claim. Rows that could not be executed are marked **BLOCKED** with
the exact missing prerequisite — they are not left out, and they are not
substituted with a simulated equivalent.

---

## 0. Legend

| Mark | Meaning |
|---|---|
| **PASS** | Executed in this environment; the stated observation was seen |
| **BLOCKED** | Could not execute; the missing prerequisite is named |
| **N/A** | Does not apply to this deployment |

---

## 1. Driver selection

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-01 | Factory on a `sqlite` connection | `SqliteSnapshotSource`, format `sqlite-file` | selected, format `sqlite-file` | **PASS** |
| M-02 | Factory on a `mysql` connection | `MysqlDumpSnapshotSource`, format `mysqldump` | selected, format `mysqldump` | **PASS** |
| M-03 | Factory on a `mariadb` connection | same as MySQL | same as MySQL | **PASS** |
| M-04 | Factory on an unimplemented driver (`sqlsrv`) | throws naming the driver and the supported set | throws; message contains `sqlsrv` and `sqlite, mysql, mariadb` | **PASS** |
| M-05 | `driver()` reflects the live connection, not config | returns the connection's driver | returns the connection's driver | **PASS** |

M-04 is the row that matters most. The original defect was an unimplemented
driver (MySQL) being silently treated as the implemented one (SQLite). The test
asserts a *throw*, not a fallback, and asserts the message names both halves so an
operator knows what happened and what is supported.

---

## 2. Readiness and fail-closed behaviour

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-06 | `mysqldump` absent, `MYSQLDUMP_PATH` unset | refuse, do not write | `backup REFUSED: mysqldump was not found on PATH…`, exit 1 | **PASS** |
| M-07 | `proc_open` disabled | refuse, naming process execution | covered by unit assertion on the guard | **PASS** |
| M-08 | Refusal happens **before** the destination is created | no directory | destination directory absent | **PASS** |
| M-09 | Refusal message states nothing was written and nothing pruned | both sentences present | both present | **PASS** |
| M-10 | Refusal writes a `backup_failed` audit row | one row | one row | **PASS** |
| M-11 | SQLite source path empty | refuse | refuses | **PASS** |
| M-12 | SQLite source `:memory:` | refuse | refuses | **PASS** |
| M-13 | SQLite source missing | refuse | refuses | **PASS** |
| M-14 | SQLite source unreadable (mode 000) | refuse | refuses | **PASS** |
| M-15 | `VIRA_BACKUP_KEY` unset | refuse | refuses | **PASS** |

M-08 is the assertion that separates "fails" from "fails closed". Creating the
directory and then refusing leaves an artefact that a monitoring script or a
human will read as progress.

---

## 3. SQLite round trip (the driver that exists here)

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-16 | Backup succeeds and reports format and driver | `backup written and verified`, `format : sqlite-file (sqlite)` | exactly that | **PASS** |
| M-17 | Table count matches the live database | count from `sqlite_master` | 57, read independently | **PASS** |
| M-18 | Row count matches the live database | count matching | 484 | **PASS** |
| M-19 | Container is self-verified before publication | restore gates run on `.partial` | no `.partial` remains; container restores | **PASS** |
| M-20 | Restore into a separate empty database | 57 tables, 484 rows | exactly that | **PASS** |
| M-21 | Header records `database_format` and `database_driver` | both present | `sqlite-file` / `sqlite` | **PASS** |
| M-22 | Snapshot counts match after an inserted probe row | count increments by 1 | increments by 1 | **PASS** |

M-22 is the anti-decoration test. A backup that copies a file always reports the
same counts; one that reads the live database reports a change when the data
changes.

---

## 4. Integrity and tampering

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-23 | Restore with the **wrong key** | refuse at gate 3 (GCM tag) | exit 1, target byte-identical | **PASS** |
| M-24 | One-bit flip mid-ciphertext | refuse | exit 1, target unchanged | **PASS** |
| M-25 | Truncated container | refuse | exit 1 | **PASS** |
| M-26 | Random bytes | refuse at gate 1 (magic) | exit 1, "not a VIRA backup" | **PASS** |
| M-27 | Container with a modified header | refuse | exit 1 | **PASS** |
| M-28 | Refused restore leaves no `.pre-restore-*` | none | none | **PASS** |

M-24 is worth its own line: a single flipped bit is the smallest possible
corruption and the one most likely to occur on real storage. GCM authentication
catches it before a byte of the target is touched.

---

## 5. Format compatibility

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-29 | `sqlite-file` container restored onto a `mysqldump` host | refuse as incompatible | refused before anything moved; message names both formats | **PASS** |
| M-30 | `mysqldump` container restored onto a `sqlite` host | refuse as incompatible | same | **PASS** |
| M-31 | Matching format restores | success | success | **PASS** |
| M-32 | MySQL restore into a live server | explicitly not implemented | refused, exit 1, audit row, "Nothing was written" | **PASS** |
| M-33 | v1 container (no format recorded) | defaults to `sqlite-file`, still restores | restores | **PASS** |

M-33 is a compatibility requirement, not a nicety: containers written before this
change have no `database_format`. Defaulting them to `sqlite-file` — the only
format that existed then — keeps every existing backup restorable.

---

## 6. Destination guards

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-34 | `--path=public/backups` | refuse (document root) | exit 1, "document root" | **PASS** |
| M-35 | `--path=.arena/../public/backups` | refuse after `..` resolution | exit 1 | **PASS** |
| M-36 | `public/backups` does not exist afterwards | absent | absent | **PASS** |
| M-37 | Destination unwritable | refuse | refuses, no partial file | **PASS** |
| M-38 | Symlink pointing into the document root | refuse | `refusing to write a backup under the document root (/home/user/VIRACRM/public)`, exit 1, nothing created in `public/` | **PASS** |

M-35 and M-38 are the cases a naive prefix check misses. `str_starts_with($path,
'public/')` is false for `.arena/../public/backups`, and it is false for a symlink
at any name at all — yet both resolve inside the document root.
`destinationIsWebServed()` walks to the nearest existing directory and calls
`realpath()` before comparing, so both are caught.

The symlink case was **not** originally covered, only asserted in a docblock,
which is the same reasoning-without-evidence that let the original defect ship.
It is now executed: a symlink to `public/` is refused with exit 1 and nothing
appears inside `public/` (BDM-12b, BDM-12c).

---

## 7. MySQL against a real server

| # | Case | Expected | Result |
|---|---|---|---|
| **M-39** | Backup a real MySQL database; prove the dump contains records | `mysqldump` header, every table named, `INSERT INTO` rows for populated tables, row count matching the live database | **BLOCKED** |
| **M-40** | Restore that dump into a separate empty schema | tables and counts match the source | **BLOCKED** |
| **M-41** | Delete a row, restore, confirm the row returns | exact record restored | **BLOCKED** |

**Why blocked.** This environment has no MySQL or MariaDB server, no `mysqldump`
binary, no package manager able to install one, and no reachable package
repository. `proc_open` is disabled in the PHP runtime as well. The prerequisite
is a host with MySQL/MariaDB and the client tools.

**Why they are not simulated.** A stub `mysqldump` that emits a plausible file
would exercise the subprocess plumbing and nothing else. It could not establish
that a real dump is complete, consistent, or restorable — the three properties
these rows exist to test. A test that passes because it was built to pass is worse
than an honest gap, because it removes the reason to ever run the real one.

**The consequence, stated plainly.** `MysqlDumpSnapshotSource::verifyDumpContainsData()`
has never seen real `mysqldump` output. Its assumptions about the header format,
table naming, and `INSERT INTO` shape come from documented behaviour. If
`mysqldump` emits something structurally different on the target host — a
different header comment, extended inserts in an unexpected form — the
verification would reject a good dump, which fails safe (no backup is published)
but is still a defect the operator would hit on day one.

**The commands are in `docs/CUTOVER_RUNBOOK.md` §7.3 and §10.**
**Responsible:** whoever provisions the production database host.

---

## 8. Isolation

| # | Case | Expected | Observed | Result |
|---|---|---|---|---|
| M-42 | Shared `database/database.sqlite` unchanged by the whole matrix | byte-identical | byte-identical (sha256 `69d8b91f7ef3ba23…`) | **PASS** |
| M-43 | Every command runs against a private copy | no command touches the shared file | proven by M-42 | **PASS** |
| M-44 | `backup-hardening` BK-14 excludes `audit_logs` from the equality check | 56 data tables identical | 56 tables, 485 rows identical; only `audit_logs` differs, by design | **PASS** |

M-42 is asserted last and compared against a hash taken before anything ran. It is
the check that makes the rest of the matrix safe to execute repeatedly.

---

## 9. Summary

| Section | Rows | Pass | Blocked |
|---|---:|---:|---:|
| Driver selection | 5 | 5 | 0 |
| Readiness / fail-closed | 10 | 10 | 0 |
| SQLite round trip | 7 | 7 | 0 |
| Integrity / tampering | 6 | 6 | 0 |
| Format compatibility | 5 | 5 | 0 |
| Destination guards | 5 | 5 | 0 (symlink case executed as BDM-12b/c) |
| MySQL real server | 3 | 0 | **3** |
| Isolation | 3 | 3 | 0 |
| **Total** | **44** | **41** | **3** |

41 executed and passing. 3 blocked, all on the same missing prerequisite: a MySQL
server with client tools.

The blocked rows are the only ones that would prove the remediation works on the
deployment it was written for. Everything else proves the failure modes are
handled and the SQLite path is intact — which is what makes the change safe to
ship, not what makes it complete.
