# MySQL Production Backup Acceptance

**Gate 2 of the final infrastructure gate.**
**Date:** 2026-11-22 · **Status:** **BLOCKED** (real-server half)

The MySQL backup **implementation** is complete and its refusal paths are proven.
The MySQL backup **acceptance** requires a real server, and there is none. This
document separates the two so neither is mistaken for the other.

---

## 1. What is proven

| Suite | Result | What it establishes |
|---|---|---|
| `tools/checks/backup-driver-matrix.mjs` | **23/23** | strategy selection, refusals, format compatibility, destination guards |
| `tools/checks/backup-hardening.mjs` | **16/16** | encryption at rest, key gates, document-root refusal, wrong-key restore, one-bit flip |
| `tests/Feature/BackupDriverMatrixTest.php` | 38 assertions | in-process strategy and container behaviour |
| `tests/Feature/BackupRestoreTest.php` | 96 assertions | full SQLite round trip, 57 tables / 484 rows |

Specifically proven, on SQLite and in-process:

- A `mysql` connection selects `MysqlDumpSnapshotSource`, not the SQLite path.
- A missing `mysqldump` **refuses before the destination directory is created**,
  exits 1, and writes nothing.
- An unimplemented driver throws, naming the driver and the supported set.
- The container records `database_format` and `database_driver`; a
  format-mismatched restore is refused before anything is moved aside.
- A refused restore changes no data table (56 compared, `audit_logs` excluded)
  and appends exactly one `restore_failed` row (484 → 485).

## 2. What is NOT proven

**The `mysqldump` success path has never run.** `verifyDumpContainsData()` — which
checks the `MySQL dump` header, that every table is mentioned, and that populated
tables have `INSERT INTO` rows — has never seen real `mysqldump` output. Its
assumptions come from documented behaviour.

If real output differs structurally, the verification would **reject a good dump**.
That fails safe (nothing is published) but is still a defect an operator would hit
on the first run.

---

## 3. Why it cannot run here

| Requirement | Status |
|---|---|
| MySQL / MariaDB server | none; nothing listening on 3306 |
| `mysqldump`, `mysql` client | neither on PATH |
| `proc_open` — needed to invoke `mysqldump` | **declared but `Function not implemented`** |
| Install via apt | `E: Could not open lock file /var/lib/apt/lists/lock … Permission denied`; `mariadb-server` not in index |
| Docker / Podman | absent |

### 3.1 The `proc_open` trap

`function_exists('proc_open')` returns **true**. Any check that only tests for
existence concludes process execution is available. It is not:

```
Warning: proc_open(): proc_open() failed - Function not implemented
proc_open FAILED to spawn
```

php-wasm declares the function and cannot implement it. This is why
`MysqlDumpSnapshotSource` checks for `proc_open` **and** refuses with an explicit
message rather than assuming: on a real PHP build the call works; here it does
not, and the operator is told which.

### 3.2 A route investigated and rejected

`mysql-memory-server` (npm — reachable) provisions a real MySQL binary. It
downloads from:

- `cdn.mysql.com` → **HTTP 000**; or
- `github.com/Sebastian-Webster/mysql-server-musl-binaries` releases →
  `mysql-musl-8.4.11-x86_64.tar.gz`, 182,768,237 bytes, served from
  `release-assets.githubusercontent.com` (302 → TLS failure).

No path to a real server. SQLite was not substituted, because SQLite is not
evidence about MySQL — the two disagree on transactions, locking, charset
collation, `information_schema`, and dump format, which are exactly the areas a
backup must get right.

---

## 4. Environment for the acceptance run

Record these on the target host before starting:

```bash
mysql --version                      # expect 8.0+ or MariaDB 10.6+
mysqldump --version
php -v
php -m | grep -E 'pdo_mysql|openssl|zip'
php -r 'var_dump(function_exists("proc_open"));'
mysql -e "SELECT @@version, @@version_comment, @@sql_mode, @@character_set_server"
```

Connection configuration to record **without credentials**: host, port, database
name, and user privileges (`SHOW GRANTS FOR 'vira'@'127.0.0.1'`).

---

## 5. The acceptance procedure

**Never use the same database as source and target.** Create a disposable source.

```bash
# 0. Disposable source, isolated from production
mysql -e "CREATE DATABASE vira_accept_src CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
# seed representative CRM records: customers, contracts, projects, invoices,
# credential_values (encrypted), audit rows — including at least one row with
# Persian text and one with an emoji, to exercise utf8mb4

# 1. Back up
php artisan vira:backup --path=/var/backups/vira-accept --no-prune
#    EXPECT: "backup written and verified", format: mysqldump (mysql),
#    tables and rows matching the source

# 2. Confirm the dump holds ACTUAL RECORDS, not just a schema
#    extract database.sql from the container, then:
grep -c '^INSERT INTO' database.sql
grep -c "INSERT INTO \`customers\`" database.sql     # must be > 0
grep -q 'CREATE TABLE' database.sql && echo schema-present
head -3 database.sql                                  # expect a MySQL dump header

# 3. Restore into a SEPARATE EMPTY database
mysql -e "CREATE DATABASE vira_accept_tgt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql vira_accept_tgt < database.sql

# 4. Compare logical fingerprints, source vs target
for db in vira_accept_src vira_accept_tgt; do
  mysql -N -e "SELECT '$db',
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db'),
    (SELECT SUM(TABLE_ROWS) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db')"
done
#    per-table row counts must match exactly:
mysql -N -e "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
             WHERE TABLE_SCHEMA='vira_accept_src' ORDER BY TABLE_NAME" > /tmp/src.txt
mysql -N -e "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
             WHERE TABLE_SCHEMA='vira_accept_tgt' ORDER BY TABLE_NAME" > /tmp/tgt.txt
diff /tmp/src.txt /tmp/tgt.txt && echo FINGERPRINTS-MATCH

# 5. Relationships and critical records
mysql vira_accept_tgt -e "
  SELECT COUNT(*) AS orphan_contracts FROM contracts c
    LEFT JOIN customers u ON u.id=c.customer_id WHERE u.id IS NULL;
  SELECT COUNT(*) AS orphan_items FROM invoice_items i
    LEFT JOIN invoices v ON v.id=i.invoice_id WHERE v.id IS NULL;
  SELECT COUNT(*) AS users FROM users;"
#    every orphan count must be 0

# 6. Wrong key
VIRA_BACKUP_KEY=<a different 32-byte base64 key> php artisan vira:restore <container>
#    EXPECT: REFUSED at gate 3, exit 1, target unchanged

# 7. Corrupted file
#    flip one byte in the container, retry → EXPECT: exit 1, target unchanged

# 8. Incompatible driver
php artisan vira:restore <sqlite-container> --force
#    EXPECT: refused as incompatible, before anything is written

# 9. Failed restore leaves business data unchanged
#    fingerprint vira_accept_tgt before and after the failed attempts in 6-8;
#    only audit_logs may differ (the refusal records itself)

# 10. Audit events, separately from business-data integrity
mysql vira_accept_tgt -e "SELECT event, COUNT(*) FROM audit_logs GROUP BY event"
#    EXPECT: restore_failed rows from the refusals, restore_ok from the success
```

### 5.1 Pass criteria

| Criterion | Requirement |
|---|---|
| Backup reports success | `backup written and verified`, format `mysqldump (mysql)` |
| Dump contains records | `INSERT INTO` present for every populated table |
| Restore completes | exit 0 |
| Table counts | identical, per table, source vs target |
| Orphan rows | 0 for every relationship checked |
| Persian / emoji text | preserved byte-identically |
| Wrong key | exit 1, no data table changed |
| Corrupted container | exit 1, no data table changed |
| Driver mismatch | refused before any write |
| Audit trail | records the refusals and the success |

### 5.2 Cleanup

```bash
mysql -e "DROP DATABASE vira_accept_src; DROP DATABASE vira_accept_tgt;"
rm -rf /var/backups/vira-accept
```

---

## 6. Residual risks, independent of the gate

1. **MyISAM tables.** `--single-transaction` gives a consistent snapshot only for
   InnoDB. A table created without an explicit engine inherits the server default
   and may be MyISAM, which is dumped inconsistently. Verify:
   `SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='vira' AND ENGINE<>'InnoDB';`
   — expect no rows.
2. **Peak disk.** `mysqldump` writes `--result-file`, and the container is built
   from it. Budget roughly dump size + compressed container in the destination.
3. **`MYSQL_PWD` visibility.** The password is passed via the environment (never
   argv, which `ps` would show). `MYSQL_PWD` is still readable by other processes
   of the same user on some kernels. The alternative — a defaults file — writes
   the password to disk.
4. **`mysqldump` version skew.** A client much older than the server can refuse
   or silently omit newer column types. Keep the client at or above the server
   version.

---

## 7. Verdict

**BLOCKED — external infrastructure blocker.** Implementation complete, refusal
paths proven 23/23 + 16/16 + 134 PHP assertions, success path unexecuted.

**Owner:** whoever provisions the production database host.
**Blocks production:** yes.
**Would close:** §5 on a host matching §4.
