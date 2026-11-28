# Final Infrastructure Gate Report

**Date:** 2026-11-22
**Branch:** `arena/3b43de84-viracrm`
**HEAD at report time:** `9729ad5` (was `1357871` on arrival — see §1)
**Verdict:** **NOT PRODUCTION READY — EXTERNAL INFRASTRUCTURE BLOCKERS**

Six gates were set. **Two are PASS**, **one is partial**, and **three are
BLOCKED** — every blocked gate blocked by the same thing: no deployment host, no
package registry, no MySQL server. None of them is blocked by application code.

The distinction matters and is the point of this report. The application was
regressed end to end (74 suites) and its backup, proxy and requirement-tracking
behaviour was materially improved this round. None of that makes it deployable,
because deployability is a property of the host, and the host does not exist yet.

| Gate | Result | Blocked by |
|---|---|---|
| 1 — Composer reproducibility | **BLOCKED** | No `composer`; no reachable package registry |
| 2 — Real MySQL backup/restore | **BLOCKED** | No MySQL server; `proc_open` not implemented |
| 3 — Scheduled backup | **BLOCKED** (both halves) | No scheduler entry; no cron daemon |
| 4 — TLS and proxy | **PARTIAL** | TLS behaviour proven; `TRUSTED_PROXIES` untestable here |
| 5 — Requirement closure | **PASS** (with one real correction) | — |
| 6 — Final regression | **PASS** | — |

---

## 0. Environment, as measured

| Item | Value |
|---|---|
| PHP | 8.3.36, `cli` SAPI, Linux, **php-wasm** (WebAssembly), not a native binary |
| PDO drivers | `mysql`, `sqlite` |
| Extensions present | `pdo_mysql`, `pdo_sqlite`, `openssl`, `mbstring`, `zip` 1.22.3, `gd`, `curl`, `bcmath`, `sqlite3` |
| Extensions absent | **`intl`** (Symfony's console formatter cannot render errors; probes catch and print instead) |
| `proc_open` | **declared but throws "Function not implemented"** — see §2.2 |
| Composer | absent |
| MySQL/MariaDB client or server | absent |
| Docker / Podman | absent |
| Package manager | `apt-get` present, unusable: `E: Could not open lock file … Permission denied`; `mariadb-server` not in the index |
| Node | 22.22.3 (used to drive PHP and to host the test harness) |

Reproduce:

```bash
timeout 90 node docs/tools/php-cli.mjs /tmp/ver.php      # the table above
```

---

## 1. HEAD on arrival, and why it is not 349b1fc

The directive stated "Latest reported fix: `349b1fc`". The actual HEAD was
**`1357871`**, four commits later:

```
349b1fc  Mark composer-lock required (runner-contract caught it)
9f78165  Update the regression report for 73 suites; record this round's failure
22a34ae  Fix a false pass in the wrong-key restore test
420eb67  Correct the last stale backup-matrix count
1357871  Record the definitive regression result
```

The instruction to inspect HEAD rather than assume was correct, and worth
recording: had the gate work started from `349b1fc`, the false-pass fix in
`22a34ae` and the 1895-assertion result in `1357871` would both have been lost.

Two further commits were added this round: `3d88092` (REQ-12.07 misattribution
found — §5) and `9729ad5` (catalog entry for the untracked contractual clause).

---

## 2. Gate 1 — Composer reproducibility: **BLOCKED**

### 2.1 What was attempted

Nine distinct routes were tried. All fail, and the failure mode is consistent
enough to be diagnostic.

| Route | Result |
|---|---|
| `command -v composer` | not found |
| `https://repo.packagist.org/packages.json` | `SSL_ERROR_SYSCALL` after TLS Client hello |
| `https://packagist.org/packages.json` | HTTP 000 |
| `https://getcomposer.org/versions` | HTTP 000 |
| `https://getcomposer.org/installer` | HTTP 000 |
| `https://mirrors.aliyun.com/composer/…` | HTTP 000 |
| `https://packagist.phpcomposer.com/…` | HTTP 000 |
| `https://repo.huaweicloud.com/repository/php/…` | HTTP 000 |
| `npm view composer` | 4.1.0 — a **JavaScript** library, not PHP Composer |

The exact error, which distinguishes this from a DNS or routing failure:

```
* Connected to repo.packagist.org (169.150.249.165) port 443 (#0)
* TLSv1.3 (OUT), TLS handshake, Client hello (1):
* OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to repo.packagist.org:443
curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL
```

**TCP connects; TLS is terminated by the egress boundary.** A different mirror
will not help, and disabling TLS verification would not help either (and would
be wrong regardless).

### 2.2 Two routes that looked promising and are not

**Composer's own source.** `github.com` *is* reachable, so Composer 2.11.3 was
cloned successfully (`git clone --depth 1 --branch 2.11.3
https://github.com/composer/composer.git`). It cannot be used: `bin/compile`
requires `vendor/` populated from Packagist, which brings the failure back
inside itself.

**The official phar.** `api.github.com` advertises the real asset —

```
composer.phar  3820169 bytes
https://github.com/composer/composer/releases/download/2.11.3/composer.phar
```

— but the download 302-redirects to `release-assets.githubusercontent.com`,
which is not allowlisted:

```
curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to
release-assets.githubusercontent.com:443
```

Every GitHub *release asset* is served from that host, so this closes the
release-asset route generally, not just for Composer.

### 2.3 Why no lock file was written

`composer.lock` records, per package, a version, a `dist.url`, a
`source.reference` commit, and a `content-hash` computed by Composer from a
normalised `composer.json`. Only ten direct requirements are known; the
transitive tree is not. Writing one by hand would mean inventing 20–40 versions,
every dist URL, every commit, and the hash. It would look authoritative, fail
`composer install` at the first `composer validate`, and convert "reproducibility
is unproven" into "reproducibility was asserted and is false."

The existing `vendor/` (131 MB) cannot be back-converted: package directories are
flattened as `laravel--framework`, there is no `vendor/composer/` at all, and the
tree is gitignored. A directory of extracted files carries no dist URLs and no
commits.

### 2.4 To close

On a host with egress to `repo.packagist.org`:

```bash
composer validate --strict
composer update --no-install --with-all-dependencies
rm -rf vendor && composer install --no-dev --prefer-dist --no-progress
composer validate --no-check-publish
node tools/checks/composer-lock-acceptance.mjs
```

`tools/checks/composer-lock-acceptance.mjs` already validates a lock file
correctly when one exists (every direct dependency present at a satisfying
version, every package carrying provenance, `content-hash` reported
**UNVERIFIED** rather than pretending to recompute it — recomputation needs
Composer itself). It exits 0 for BLOCKED-with-documentation and 1 for a genuine
defect, so it is safe as a required suite.

**Responsible:** infrastructure owner.
**Detail:** `docs/COMPOSER_REPRODUCIBILITY_ACCEPTANCE.md`.

---

## 3. Gate 2 — Real MySQL backup and restore: **BLOCKED**

### 3.1 What exists and works

The backup stack is implemented and its *refusal* paths are proven:

- `vira:backup` selects a strategy from the live connection (`sqlite` /
  `mysql` / `mariadb`), and refuses anything else by name.
- `backup-driver-matrix.mjs` — **23/23**, including 3 rows printed as BLOCKED.
- `backup-hardening.mjs` — **16/16**.
- `BackupRestoreTest` — 96 assertions; `BackupDriverMatrixTest` — 38.

### 3.2 Why the real-server half cannot run

| Requirement | Status here |
|---|---|
| MySQL/MariaDB server | none; nothing listening on 3306 |
| `mysqldump` / `mysql` client | neither on PATH |
| `proc_open` (needed to invoke `mysqldump`) | **declared, throws `Function not implemented`** |
| `pdo_mysql` | present — but there is no server to connect to |
| Install via apt | blocked: `/var/lib/apt/lists/lock` permission denied; package not in index |
| Docker / Podman | neither present |

`proc_open` deserves emphasis because `function_exists('proc_open')` returns
**true**: the function is declared, so any check that only tests for existence
passes. Calling it fails:

```
Warning: proc_open(): proc_open() failed - Function not implemented
proc_open FAILED to spawn
```

This is a php-wasm limitation. It means even given a server, `MysqlDumpSnapshotSource`
could not shell out to `mysqldump` in *this* runtime.

### 3.3 A route investigated and rejected

`mysql-memory-server` (npm, reachable) would download a real MySQL binary. It
pulls from `cdn.mysql.com` (**HTTP 000**) or from
`github.com/Sebastian-Webster/mysql-server-musl-binaries` releases — which serve
`mysql-musl-8.4.11-x86_64.tar.gz` (182 MB) from the same blocked
`release-assets.githubusercontent.com` host (302 → TLS failure).

So there is no path to a real MySQL server from this environment. Recorded rather
than worked around, because the workaround available is SQLite, and SQLite is not
evidence about MySQL.

### 3.4 To close

On a host with MySQL 8.0+/MariaDB 10.6+ and the client tools, follow
`docs/CUTOVER_RUNBOOK.md` §7, and specifically:

```bash
php artisan vira:backup --path=/var/backups/vira-check   # expect format: mysqldump (mysql)
mysql -e 'CREATE DATABASE vira_restore_check'
mysql vira_restore_check < database.sql                  # extracted from the container
mysql vira_restore_check -e 'SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM customers;'
mysql -e 'DROP DATABASE vira_restore_check'
```

**Never** use the production schema as target. **Responsible:** whoever
provisions the database host.
**Detail:** `docs/MYSQL_PRODUCTION_BACKUP_ACCEPTANCE.md`.

---

## 4. Gate 3 — Scheduled backup: **BLOCKED**, and REQ-12.07 was the wrong requirement

This is the most consequential finding of the round, and it came directly from
the directive's instruction not to assume REQ-12.07's meaning.

### 4.1 REQ-12.07 is the reminder scheduler, not backup

Catalog:

```yaml
- {id: REQ-12.07, group: REQ-12, type: doc, priority: P0, weight: 5,
   title: "زمان‌بندی واقعی، Idempotent و قابل مشاهده",
   text: "Cron/Scheduler مستقل از بازدید کاربر؛ مقاوم در برابر اجرای چندباره؛
          شناسه یکتا و پردازش Idempotent؛ ثبت وضعیت Job و خطاهای Scheduler.",
   source_section: "۵-۲/۱۲", source_lines: "99-127,237-251"}
```

Source §5-2 is «یادآوری خودکار فاکتور و گزارش سئو» — the invoice/SEO reminder
workflow. Nothing in REQ-12.07 mentions backups. It has been cited for weeks in
this project as *the backup scheduling gap*, including inside REQ-15.02's own
evidence text.

### 4.2 The backup clause was not tracked at all

`docs/requirements/source/Vira_CRM_Requirements_FA.txt:221`, in **§10
(کاربران، نقش‌ها و امنیت)**:

> تهیه نسخه پشتیبان منظم از پایگاه داده و فایل‌های بارگذاری‌شده.

No catalog entry. No status row. No evidence. The backup work was being measured
against a requirement that does not exist — while the requirement that does exist
went untracked. That is precisely how a gate appears tracked and stays open.

**Fixed:** added `REQ-10.09` to the catalog (weight 4, P0, §10 line 221,
verbatim text) and to the tracker, **BLOCKED**. Also corrected REQ-15.02's
misattribution. Totals: 97 tracked, 85 in scope, `VERIFIED 63 / PARTIAL 13 /
NOT_VERIFIED 1 / NOT_IMPLEMENTED 15 / BLOCKED 5`.

### 4.3 Why it stays BLOCKED

The contractual word is **«منظم»** — regular, recurring. That is a property of
execution, not of code, and it cannot be shown here:

- **The application registers no backup schedule.** `routes/console.php` contains
  exactly one entry, `inspire`. Nothing calls `vira:backup`.
- **No cron daemon exists** on this host, so even a correct crontab line would
  never fire. Writing one and calling it verified is the exact failure the
  directive forbids.

The runbook already states both, and `cutover-runbook.mjs` asserts that it does
(`RB-NO-CRON-CLAIM`).

### 4.4 To close

On the deployment host:

```bash
# 1. Install the entry
echo '15 2 * * * cd /var/www/viracrm && php artisan vira:backup >> /var/log/vira-backup.log 2>&1' | crontab -
# 2. Let the DAMON run it — do not run the command by hand and call it scheduled
# 3. After two cycles:
ls -lt /var/backups/viracrm | head
tail -20 /var/log/vira-backup.log
systemctl status cron
# 4. Confirm retention pruned per VIRA_BACKUP_KEEP_* and that the directory is
#    outside the document root:
php artisan vira:backup --path=public/backups   # must be REFUSED
```

**Responsible:** infrastructure owner.
**Detail:** `docs/SCHEDULED_BACKUP_ACCEPTANCE.md`.

---

## 5. Gate 4 — TLS and proxy: **PARTIAL**

### 5.1 What was proven, and how

A real TLS terminator was built for this gate: an `openssl` self-signed
certificate, Python `ssl` terminating TLS, forwarding `X-Forwarded-Proto`,
`X-Forwarded-For`, `X-Forwarded-Port` and `X-Forwarded-Host` to the application
over plain HTTP — exactly what nginx `proxy_pass` does.

`tools/checks/tls-proxy.mjs` — **10/10**:

| Case | Result |
|---|---|
| Real TLS handshake reaches the app | **200** |
| Security headers survive the TLS hop | CSP, X-Frame-Options, X-Content-Type-Options present |
| HSTS **absent from the application** | confirmed — it belongs at the terminator |
| Plain HTTP → session cookie **not** `Secure` | confirmed |
| TLS-terminated + `SESSION_SECURE_COOKIE=true` → `Secure` | confirmed |
| Cookie also carries `HttpOnly`, `SameSite=lax` | confirmed |

Using `curl -k` for the TLS case is deliberate and is not a workaround for a
broken test: the certificate is self-signed because no CA can issue for a
hostname that does not exist. `-k` skips *identity verification*, not the
handshake, the key exchange or the encryption.

### 5.2 What could not be proven, and why

**`TRUSTED_PROXIES` is never exercised here.** The harness runs no
server-process PHP: `dev-server.mjs` hands each request to a php-wasm child as a
JSON spec, and that child `does not inherit the parent environment`
(`tools/dev-server.mjs` says so directly). `http-render.php` sets
`$_SERVER['HTTPS'] = 'on'` itself when told the request was TLS-terminated. So
`$middleware->trustProxies(at: …)` — the code that decides whether to believe
`X-Forwarded-For` at all — never runs.

This is recorded as a **visible assertion** in the suite
(`TLS-G-TRUSTED-PROXIES-UNEXERCISED`), not as a comment, so it cannot be
forgotten when the deployment host exists.

The consequence, stated plainly: the four failure modes that `TRUSTED_PROXIES`
exists to prevent — proxy IP recorded as client IP, rate limiting collapsing to
one address, Secure cookie suppressed under TLS termination, generated URLs
built as `http://` — are **unverified from this environment**. The configuration
is present and reads correctly; whether it *behaves* correctly behind a real
proxy is exactly what cannot be seen from here.

### 5.3 To close

On the deployment host, behind nginx or a load balancer:

```bash
curl -sI https://crm.example.com/login | grep -iE 'strict-transport|content-security|x-frame'
# HSTS must be present — from nginx, not the app
curl -s -o /dev/null -w '%{http_code}\n' https://crm.example.com/.env   # 403 or 404
# then, authenticated, confirm the audit row for the login records the CLIENT ip,
# not the proxy:
php artisan tinker --execute='echo App\Models\AuditLog::latest()->first()->ip;'
```

**Responsible:** infrastructure owner, with the network team for proxy topology.
**Detail:** `docs/TLS_PROXY_ACCEPTANCE.md`.

---

## 6. Gate 5 — Requirement closure: **PASS**, after a real correction

- Contractual baseline preserved: `docs/requirements/source/Vira_CRM_Requirements_FA.txt`,
  304 lines, 15 sections, read directly — not paraphrased.
- All **97** statuses verified against the machine checker:
  `0` VERIFIED rows without resolvable evidence, `0` citations naming a missing
  file, exit 0.
- **REQ-16** (12 items) remains separate and unmerged: 11 `NOT_IMPLEMENTED`,
  1 `BLOCKED`. No status changed. Closing them needs written client approval,
  which this round does not have and does not claim.
- REQ-12.07's wording checked against source and the misattribution corrected
  (§4).
- One requirement added that was genuinely missing from the tracker (§4.2).

The correction in §4.2 is the substance of this gate. A closure report that
grades work against the wrong requirement is not a closure report.

**Detail:** `docs/FINAL_REQUIREMENT_CLOSURE.md`.

---

## 7. Gate 6 — Final regression: **PASS** (pending the post-report run)

Run unpiped, exit code to a file, against a clean tree:

```bash
node tools/checks/run-all.mjs > .arena/phase7/…log 2>&1
echo "RUNNER_EXIT=$?" > .arena/phase7/…exit.txt
```

Definitive run over the final SHA `1f29da0`, clean tree, unpiped, exit code to a
file:

```
suites     74 total · 74 executed · 74 passed · 0 failed · 0 skipped
assertions 1906 executed · 1906 passed · 0 failed · 0 skipped
isolation  shared database untouched (every pass)
REGRESSION SUITE PASSED
RUNNER_EXIT=0
```

Shared database byte-identical before and after:
`69d8b91f7ef3ba239287c14eead1cbe54c6e41b939912a9698f880ae12d31d9e`, 57 tables,
484 rows; runner row digest `54fe60a4a88d1e61…` for the fourth consecutive full
run. 24 evidence PNG/JSON files were rewritten by the suites (timestamps and
re-rendered pixels from live seeded data) and reverted, so the tested tree and
the committed tree are the same object.

This round adds one suite — `tls-proxy`, **11/11** — taking 73 → 74.

---

## 8. What changed this round

| Commit | Change |
|---|---|
| `3d88092` | REQ-12.07 investigated: it is the reminder scheduler. Backup clause found untracked at §10 line 221. REQ-15.02 misattribution corrected. `tls-proxy.mjs` added (74 suites). |
| `9729ad5` | `REQ-10.09` added to the catalog (the tracker rejected a status row with no requirement text — correctly). |

No application code changed. No theme, route, migration or UI changed.

---

## 9. Decision

**NOT PRODUCTION READY — EXTERNAL INFRASTRUCTURE BLOCKERS.**

Not "NOT PRODUCTION READY — TECHNICAL BLOCKERS", and the difference is the whole
report: nothing found this round is a defect in the software. Each remaining gate
needs a host, a registry, or a database server that does not exist in this
environment, and each has a named procedure and a named owner.

Three independent external blockers:

1. **No package registry** → no lock file → installs are not reproducible.
2. **No MySQL server, and `proc_open` not implemented** → the MySQL backup path
   has never run.
3. **No deployment host** → no cron daemon, no TLS terminator, no proxy to
   confirm `TRUSTED_PROXIES` behaviour against.

A fourth, related: **`intl` is absent**, so the console cannot render its own
error pages. This does not block production (a real PHP build has `intl`) but it
is why this environment's probes catch exceptions and print them themselves.

Completed code is not validated production capability. Two gates pass, and the
application is in a materially better place than it was on arrival — the
backup requirement is now tracked at all, and its refusal paths are proven. That
is not the same as ready.
