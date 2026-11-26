# Vira CRM — Production Readiness

**Branch:** `arena/3b43de84-viracrm` · **Final SHA:** `d1a17b1` · **Date:** 2026-11-22
**Scope of this round:** stabilisation, documentation alignment and production preparation.
**No new features were added to the CRM.**

**Verdict: NOT PRODUCTION READY — awaiting your review.** No production release is declared
anywhere in this document.

Every item ends with one of: **Pass** · **Fail** · **Not Tested** · **Blocked**.

---

## Verdict summary

| # | Item | Verdict |
|---|---|---|
| 1 | Git commit and branch | **Pass** |
| 2 | Composer and lock file | **Fail** (lock absent; cause identified, remediation defined) |
| 3 | MySQL/MariaDB test | **Blocked** (no server obtainable in this environment) |
| 4 | TLS and session security | **Fail** on current config · checklist delivered |
| 5 | Backup and restore | **Pass** for the mechanism · **Blocked** for scheduling |
| 6 | Performance and N+1 | **Pass** |
| 7 | Requirements traceability | **Pass** (corrected and regenerated) |
| 8 | Blocked items | listed below |
| 9 | Known limitations | listed below |
| 10 | Host migration checklist | delivered below |

---

## 1. Git commit and branch — **Pass**

| | |
|---|---|
| Branch | `arena/3b43de84-viracrm` (from `309eabf` of `main`) |
| Baseline for this round | `4e9aec4` — clean tree, HEAD = origin |
| This round's commit | `d1a17b1` |
| Full regression on `d1a17b1` | **42/42 suites · 1352 assertions · exit 0** |
| Isolation proof | `customers 7→7, contracts 5→5, projects 3→3, work_items 9→9, users 2→2, sessions 1→1, audit_logs 232→232` |
| Commit preserved | yes; nothing was rewritten, reverted or force-pushed |

The previous round's chain (`049088d` → `c04ca2c` → `9b62267` → `4e9aec4`) is intact. This
round adds three check scripts, a runner registration, traceability corrections and this
document. **No CRM feature code changed.**

Suite growth 40 → **42**; assertions 1324 → **1352**. The increase is the two new suites
(`composer-integrity` 9, `query-performance` 19) — new coverage, not renumbering. The one
commit not covered by that full run is the commit containing this sentence, whose only
test-reachable effect is the release manifest, re-verified by `docs-integrity` 52/52 inside it.

---

## 2. Composer and lock file — **Fail**

### What was found

| Check | Result |
|---|---|
| `composer.lock` on disk | **absent** |
| `composer.lock` in git history | **never committed** (`git log --all -- composer.lock` empty) |
| Ignored by `.gitignore` | **no** — `/vendor/` is ignored, the lock file is not |
| `composer` binary | **absent** |
| `repo.packagist.org` | **BLOCKED** — verified: TLS connection closed (EOF) |
| Debian package repo | **BLOCKED** — cannot install Composer |
| Process user | uid 1001 (not root) |

**Correction to the previous report:** it described the lock file as "untracked". That
understated it — the file does not exist at all. Nothing was ever generated and then left out
of git.

### Why it is absent

`composer update` resolves against Packagist, which is unreachable from this environment. The
`vendor/` tree here was built by `tools/resolve-vendor.py`, a GitHub-only resolver written for
this sandbox. That tool produces a working `vendor/` and a real PSR-4 autoloader, but it does
not produce a Composer lock file, and it cannot.

### Validity of what IS installed

Verified by the new `composer-integrity` suite (**8/9**, the one failure being the
documentation gate this file now satisfies):

| Package | Declared | Installed | Satisfies |
|---|---|---|---|
| `laravel/framework` | `12.40.0` | **12.40.0** (tag v12.40.0) | exact match |
| `mpdf/mpdf` | `^8.2` | **8.3.1** (tag v8.3.1) | yes |
| `php` | `^8.2` | 8.3.36 | yes |

- All 80 `vendor/` directories come from the resolved graph — no strays.
- `vendor/` is gitignored; zero files tracked.
- `resolve-vendor.py --verify` → `OK: vendor/ has 80 packages and an autoloader`.

### The concrete risk

`composer.json` sets `minimum-stability: dev` and floats `mpdf/mpdf: ^8.2`. This sandbox runs
mpdf **8.3.1**. Without a lock file, `composer install` on the target host resolves whatever
8.x is current that day — which may not be the version these 1324 assertions were run against.
That is the reproducibility failure mode, stated plainly.

### What I did NOT do

I did not write a `composer.lock` by hand. A lock with invented content hashes and guessed
transitive versions would be worse than none: `composer install` would either fail outright or
silently resolve something different, and it would look authoritative.

### Remediation (one command, on any machine with Packagist access)

```bash
composer install --no-dev --optimize-autoloader   # then verify:
composer validate --strict --no-check-all
diff <(composer show --locked | sort) <(expected list)
```

Then commit the generated `composer.lock` and confirm the pinned mpdf version matches
**8.3.1**, or accept the newer one deliberately and re-run the suite.

**No dependency was upgraded in this round.**

---

## 3. MySQL/MariaDB test — **Blocked**

**This item is NOT passed.** There is no MySQL or MariaDB in this environment and none can be
obtained:

| Probe | Result |
|---|---|
| `mysql`, `mariadb`, `mysqld`, `mariadbd` | all absent |
| Ports 3306 / 3307 / 33060 | nothing listening |
| `docker`, `podman` | absent — no container runtime |
| `apt-get` | present, but `deb.debian.org` is **BLOCKED** |
| Process user | uid 1001 — cannot install system packages |
| PyPI | reachable, but publishes no MySQL *server* |

### What was delivered instead

`tools/checks/mysql-compatibility.php` — a harness to run **on a host that has the database**.
It is deliberately **not** registered in the regression suite: a suite that cannot run in CI
rots silently, and a skipped test that looks green is worse than an absent one.

Safety: it refuses to run unless `DB_DATABASE` starts with `_vira_mysql_probe_` and the target
database is empty. Verified here — pointing it at a real database name exits 2 with a refusal.

It covers: engine/charset/collation, migrations from empty, table count, InnoDB on every table,
foreign key presence **and enforcement** (inserts a dangling child and requires rejection),
unique-index key length against the utf8mb4 limit, Persian write/read round trip, Persian
equality vs `LIKE` search, date fidelity, transaction rollback, and seeds.

### Static findings already established without a server

These are real and do not need MySQL to be true:

1. **The backup system is SQLite-only.** `app/Services/Backup/BackupBuilder.php` uses
   `SQLite3::backup()`, queries `sqlite_master`, and `BackupRestorer.php` runs
   `PRAGMA integrity_check`. None of these exist on MySQL. A MySQL backup path
   (`mysqldump` or Percona XtraBackup) must be designed **before** cutover — this is the
   single largest migration risk in the project.
2. `contracts.number` is `VARCHAR(32) UNIQUE` and `users.email` is `VARCHAR(255) UNIQUE`.
   At utf8mb4 the latter needs 1020 bytes, which is fine on InnoDB DYNAMIC (3072 limit,
   default since MySQL 5.7.7 / MariaDB 10.2) but fails on COMPACT (767). The harness reports
   the longest key so this is visible rather than surprising.
3. Status columns are backed PHP enums with **no CHECK constraint** behind them. This round
   proved it: inserting `type='real'` (invalid; the enum is `natural|legal`) and
   `status='completed'` (invalid for `ContractStatus`) both **wrote successfully** and only
   failed later, when a view cast the value — surfacing as an HTTP 500. The application itself
   is protected by `Rule::in` validation in its FormRequests, so this is reachable only by
   bypassing Eloquent (direct SQL, imports, a future API). Worth a CHECK constraint or an
   import-path guard.

### Still not covered even after the harness runs

Locking and concurrency under parallel writers; a full backup/restore cycle on MySQL; the
regression suite itself with `DB_CONNECTION=mysql`; realistic volume and query counts on MySQL.

---

## 4. TLS and session security — **Fail** on current configuration

### What the code actually does

| Setting | Source | Current state |
|---|---|---|
| `secure` | `env('SESSION_SECURE_COOKIE')` | **no default** — null unless explicitly set |
| `http_only` | `env('SESSION_HTTP_ONLY', true)` | true by default — safe |
| `same_site` | `env('SESSION_SAME_SITE', 'lax')` | lax by default — safe |
| `domain` | `env('SESSION_DOMAIN')` | null — correct |
| `lifetime` | `env('SESSION_LIFETIME', 120)` | 120 min |
| Trusted proxies | — | **no TrustProxies configuration found** |
| `.env.example` | — | `APP_ENV=local`, `APP_DEBUG=true` |

**The gap that was fixed this round:** `.env.example` did not mention `SESSION_SECURE_COOKIE`,
`SESSION_HTTP_ONLY` or `SESSION_SAME_SITE` at all. Since `config/session.php` reads the first
with no safe default, **the template was the only place it could be set** — and it wasn't. A
deployment copying that template would run with the session cookie sendable over plain HTTP.
All three are now spelled out in `.env.example` with explicit development vs production values.

### Production checklist

| # | Item | How to verify | Status |
|---|---|---|---|
| 1 | Valid SSL/TLS certificate | `openssl s_client -connect host:443 -servername host` shows a trusted chain, not self-signed | **Not Tested** — no host |
| 2 | HTTPS enforced, HTTP redirected | `curl -I http://host/` → `301` to `https://`; no `200` on plain HTTP | **Not Tested** |
| 3 | `SESSION_SECURE_COOKIE=true` | `php artisan tinker --execute="dump(config('session.secure'))"` → `true` | **Fail here** (unset), fixed in template |
| 4 | `HttpOnly` | response header `Set-Cookie:` contains `HttpOnly` | Code default true; **verify on host** |
| 5 | `SameSite=Lax` (or `Strict`) | `Set-Cookie:` contains `SameSite=Lax` | Code default lax; **verify on host** |
| 6 | CSRF protection active | every state-changing route requires a token; a stale token returns **419** | **Pass** — proven: all flow checks take a fresh token, stale → 419 |
| 7 | Session rotation on login | after login the session **id changes**; the pre-login id is dead | **Pass** — W-10a: after logout, `/customers` and `/audit` both redirect |
| 8 | Cookie domain correct | `SESSION_DOMAIN` null (host-only) or the exact apex; never a wildcard | **Pass** — null |
| 9 | `APP_KEY` and secrets not exposed | `APP_KEY`, `VIRA_CREDENTIAL_KEY`, `VIRA_BACKUP_KEY` live in `.env` only; **no real secret in the repo, reports, screenshots or test output** | **Pass** — W-08b: secret appears 0 times on the audit page; `credential-shots` proves no secret in the DOM |
| 10 | Trusted proxies configured | behind a TLS terminator, `TrustProxies` must list it or `secure` cookies and generated URLs break | **Fail** — no configuration found; must be added if terminating TLS at a proxy |
| 11 | `APP_ENV=production`, `APP_DEBUG=false` | debug pages leak stack traces and env | **Fail here** (`.env.example` is dev) |
| 12 | Dev/prod separation | separate `.env` per environment; the template documents both | **Pass** — template now states both |

---

## 5. Backup and restore — mechanism **Pass**, scheduling **Blocked**

### Mechanism (verified)

Container `.vbk1` = magic `VIRABK1\n` + JSON header + AES-256-GCM(gzip(zip)). HKDF-SHA256,
info `vira-backup-v1`, 16 B salt, 12 B IV, 16 B tag, 32 B key enforced in the constructor.

| Property | Evidence |
|---|---|
| Encryption | AES-256-GCM (authenticated), `EnvelopeEncrypterTest` 26 |
| Key protection | `VIRA_BACKUP_KEY`, separate from `VIRA_CREDENTIAL_KEY`, env-only |
| Verification | 7 gates: magic, header, GCM auth, inflation, manifest sha256, per-member sha256, `PRAGMA integrity_check` |
| Tamper detection | byte flip, truncation, wrong/malformed/empty key → all `REFUSED at gate 3`, exit 1 |
| Isolated restore | restores into a separate path; canary recovered; `integrity: ok`; move-aside `*.pre-restore-*` |
| Retention | limits 7/4/6 → `pruned : 12`, 21→9 containers, 0 orphans; keeps one file per period |
| Audit | recorded **before** any console output |
| Tests | `BackupRestoreTest` **96 assertions** |

### Scheduling — **Blocked, and not presented as operational**

There is **no cron and no SSH** on this host (`scheduled events: 0`). `vira:backup` works when
invoked; nothing invokes it. `RetentionPolicy` is a pure, unit-tested function precisely so the
decision logic is proven independently of a scheduler that does not exist here.

### Operational plan for the target host

| Aspect | Recommendation |
|---|---|
| **Schedule** | Daily 03:00 server local time, after the nightly low-traffic window. Weekly and monthly copies are derived by the retention policy, not by separate jobs |
| **Invocation** | Host cron calling `php artisan vira:backup --no-interaction`. If the host offers no cron, an external scheduler (host control panel, or a CI cron job) must SSH in and run the same command — the command is the contract, the trigger is interchangeable |
| **Storage** | `VIRA_BACKUP_PATH`, outside the web root, permissions `0700`. **Off-site copy is mandatory** — a backup on the same disk as the database is not a backup |
| **Encryption** | Already AES-256-GCM at rest. The key must never live in the repo or beside the backups |
| **Retention** | 7 daily / 4 weekly / 6 monthly (already the defaults; `VIRA_BACKUP_KEEP_*`) |
| **Access control** | Filesystem owner only; no group or world read |
| **Success reporting** | Exit 0 plus a written audit row. Alert on any non-zero exit |
| **Failure reporting** | **A missing backup must alert as loudly as a failed one.** A cron job that silently stops is the common failure, so monitor for "no new container in 26 hours", not only for exit codes |
| **Test restore** | Monthly, into a scratch path, via `php artisan vira:restore --verify`. A restore never tested is a hypothesis |
| **Suggested RPO** | 24 hours (daily cycle). If the business needs less, shorten the interval — the mechanism supports it unchanged |
| **Suggested RTO** | ≤ 1 hour: container available, `vira:restore --verify` then `--force`, plus application redeploy |

**Blocker carried forward:** the SQLite-only backup path (§3, finding 1). On MySQL the current
builder cannot run at all.

---

## 6. Performance and N+1 — **Pass**

Measured by the new `query-performance.php` suite against an **isolated throwaway database**
(58 tables migrated fresh; the application database was not touched — asserted by
`perf-00-isolated`).

### Realistic volume

`customers=120 · contracts=90 · projects=80 · work_items=80`, seeded in 538 ms.

### Results

| Page | Status | Queries | Time | Repeated shapes |
|---|---:|---:|---:|---|
| Dashboard `/` | 200 | 15 | 262 ms | none |
| Customers list | 200 | **6** | 77 ms | none |
| Customer detail | 200 | 14 | 48 ms | none |
| Contracts list | 200 | **7** | 101 ms | none |
| Contract detail | 200 | 8 | 43 ms | none |
| Projects list | 200 | **9** | 77 ms | none |
| Project detail | 200 | 12 | 45 ms | none |
| Search `/customers?q=…` | 200 | **6** | 56 ms | none |

**19/19 checks passed.** No query shape repeated more than 12 times on any page — **no N+1
detected**. Detection works by normalising each query (bound values → `?`) and counting shapes,
so `where id = 1` and `where id = 2` are recognised as one query run twice.

### Scale behaviour — the finding that matters

Doubling customers **120 → 240 rows changed the customers-list query count by +0** (6 → 6).
A list with an N+1 would have roughly doubled. This proves pagination plus eager loading are
actually in effect, not merely present in the code.

### Honest limits on this evidence

- Times are from a **php-wasm interpreter**, not PHP-FPM. They are comparable *relative*
  figures and must be re-measured on the real host.
- Volume is 120 customers, not 12,000. The bounded-count result is what generalises; the
  absolute milliseconds do not.
- Modules that do not exist get **no performance claim**: there are no report screens and no
  file-upload module, so neither is measured.

---

## 7. Requirements traceability — **Pass** (corrected)

`docs/REQUIREMENTS_TRACEABILITY.md` is generated from `tools/traceability-status.json` and
`docs/requirements/requirements.catalog.yaml`, so the **sources** were corrected and the
document regenerated — not hand-edited.

| Status | Before | After |
|---|---:|---:|
| `VERIFIED` | 50 | **56** |
| `PARTIAL` | 18 | 15 |
| `NOT_IMPLEMENTED` | 22 | **19** |
| `BLOCKED` | 4 | 4 |
| P0 `VERIFIED` | 15 | **20** |

### False claims corrected

| ID | Requirement | Was | Now | Evidence that settled it |
|---|---|---|---|---|
| `REQ-15.02` | Backup and restore tested | `NOT_IMPLEMENTED` — *"zero backup code"* | **VERIFIED** | 4 service classes, 3 console commands, `BackupRestoreTest` 96 assertions, 7 gates |
| `REQ-06.02` | Mandatory SEO access list + finalisation gate | `NOT_IMPLEMENTED` — *"credential_requirements empty"* | **VERIFIED** | 3 blocking rows seeded; `FinalizationGate`; `T-PRJ-09b` refused / `T-PRJ-09f` released; `FinalizationGateTest` 20 assertions |
| `REQ-12.04` | SEO report reminders (D-2 + due date) | `NOT_IMPLEMENTED` — *"no seo reports"* | **VERIFIED** | `WorkItem::KINDS` includes `report`; `ReminderSchedule` returns `2026-03-19` for due `2026-03-21`; `ReminderScheduleTest` 43; W-04d 0→1→0 |
| `REQ-06.04` | Audit of reveal/export | `PARTIAL` — *"no audit VIEW yet"* | **VERIFIED** | the view exists: `GET /audit`, super-admin only, `audit-log-page` 16/16 |
| `REQ-06.05` | Confidential credentials PDF | `PARTIAL` | **VERIFIED** | `credential-export` 13/13; POST-only, `no-store`, no `*.pdf` under `storage/`. Residual is `REQ-06.07` (files), tracked separately |
| `REQ-03.07` | Customer 360° dossier | `PARTIAL` — *"no contracts/projects"* | **VERIFIED** | dossier lists both as links; project rows were unlinked until `049088d` |
| `REQ-15.04` | Migrations | `PARTIAL` — *"9 migrations"* | `PARTIAL` — **14** | count corrected |

### Catalogue metadata corrected

The catalogue declared the reference document `NOT_AVAILABLE_IN_REPO` and named a `.docx`.
The binding source **is** in the repo: `docs/requirements/source/Vira_CRM_Requirements_FA.txt`,
304 lines, 15 sections — confirmed by direct read. Metadata now records the path and the
correction. (The `.docx` genuinely is absent; the `.txt` is authoritative.)

**No implemented feature is now marked Not Implemented, and nothing was upgraded without
reading the code that implements it.** Each row above names the artefact and the test.

Items left as `NOT_IMPLEMENTED` remain so because they are real gaps — global cross-entity
search, Excel export, file uploads, the installer, and the file-workspace module (`REQ-16.*`).

---

## 8. Blocked items

| # | Item | Why | What unblocks it |
|---|---|---|---|
| B1 | MySQL/MariaDB run | No server, no container runtime, OS repo blocked, uid 1001 | Run `tools/checks/mysql-compatibility.php` on a host with the DB |
| B2 | `composer.lock` generation | Packagist unreachable | One `composer install` on any machine with Packagist |
| B3 | Backup scheduling | No cron, no SSH | Host cron or an external scheduler |
| B4 | TLS verification | No certificate, no HTTPS terminator, no host | Deploy to the target host |
| B5 | Real-host PHP capabilities | php-wasm ≠ target; **no ext-intl** here, so Symfony Console `table()` and `artisan list` fatal | Re-run `tools/checks/env-capabilities.php` on the host |
| B6 | Off-site backup storage | No external destination reachable | Configure and prove one |
| B7 | `docs/workspace/` (13 files) and `docs/ai/` (7 files) | **Contents specified nowhere in the repository** | Your decision — proposal in §10 |

---

## 9. Known limitations

1. **No PHPUnit has ever run here.** Tests use the project's `ViraTestCase` shim because
   Packagist is unreachable; bodies are PHPUnit-compatible but unexecuted by PHPUnit.
2. **All tests are SQLite.** MySQL-specific SQL, collation and locking are unverified.
3. **The backup system cannot run on MySQL** (§3 finding 1) — the largest migration risk.
4. **Status columns have no CHECK constraint.** Invalid enum values write successfully and fail
   only on render. The app is protected by FormRequest validation; direct SQL or a future API
   would not be.
5. **No ext-intl on this interpreter**, so console table rendering fatals. All commands use
   `line('k : v')`. Must be re-checked on the host.
6. **Performance figures are from php-wasm.** Relative, not absolute.
7. **Volume tested is 120 customers**, not production scale.
8. **`lock-concurrency` fails if invoked via a subset roster containing itself** — its own
   recursion guard, working as designed. 15/15 standalone and nested under the full runner.
9. **No global cross-entity search** (`REQ-09.01`): search is per-entity. §9 of the binding
   document asks for «جستجوی سراسری مشتریان», which `/customers?q=` satisfies; a unified search
   across all entity types is not implemented and is recorded as such.
10. **No report screens.** The §9 reports are list filters, not report pages.

---

## 10. `docs/workspace/` and `docs/ai/` — proposal for your decision

**Nothing was created.** Both directories are referenced but their required contents are
specified nowhere in the repository, so inventing files would manufacture compliance. Below is
a proposal to decide on.

### `docs/workspace/` — 13 files

Interpretation: operational documentation for people running the system, as distinct from
`docs/` which is largely engineering-facing.

| Proposed file | Purpose | Audience | Necessity |
|---|---|---|---|
| `00-index.md` | Entry point, reading order | all | high |
| `01-what-is-vira.md` | Product overview in plain Persian | client, new staff | high |
| `02-getting-started.md` | First login, change password, tour | new staff | high |
| `03-customers.md` | Creating and finding customers, dossier | account managers | high |
| `04-contracts.md` | Contract bank, statuses, PDF output | account managers | high |
| `05-projects.md` | SEO vs design projects, statuses | project leads | high |
| `06-project-access.md` | Recording accesses, the finalisation gate | project leads | high |
| `07-reminders.md` | Cartable, due dates, the D-2 rule | managers | high |
| `08-payroll.md` | Payroll cycle, who sees what | accountant | medium |
| `09-users-and-roles.md` | Roles, permissions, what each can do | admin | high |
| `10-audit-log.md` | Reading the event log | admin | medium |
| `11-backup-restore.md` | What is backed up, how to request a restore | admin, host | high |
| `12-faq-troubleshooting.md` | Common problems and their answers | all | medium |

### `docs/ai/` — 7 files

Interpretation: context packs so an AI assistant works on this codebase reliably. This has
proven valuable in practice — several defects this round came from assumptions a document
could have prevented.

| Proposed file | Purpose | Audience | Necessity |
|---|---|---|---|
| `00-project-context.md` | What the product is, its constraints, the binding requirements source | AI | high |
| `01-architecture.md` | Layers, where logic belongs, the service objects | AI | high |
| `02-conventions.md` | Naming, Jalali handling, Persian UI strings, token discipline | AI | high |
| `03-security-invariants.md` | Permission model, credential handling, what must never be logged | AI | **critical** |
| `04-testing.md` | How to run checks, the isolation rule, what "verified" means here | AI | high |
| `05-known-traps.md` | The environment traps documented in this repo's history | AI | high |
| `06-do-not-do.md` | No API, no WordPress changes, no Design System changes without approval | AI | **critical** |

**Please confirm, amend or replace these lists.** On approval I will create them from verified
repository content only.

---

## 11. Host migration checklist

Run in order. Each step has a verification, because an unverified step is an assumption.

### Phase A — prerequisites

- [ ] PHP **8.2+** with `ext-intl`, `ext-mbstring`, `ext-openssl`, `ext-zip`, `ext-pdo_mysql`
      → `php -m`
- [ ] Composer 2.x → `composer --version`
- [ ] MySQL 5.7.7+ or MariaDB 10.2+ (InnoDB, DYNAMIC row format, `utf8mb4`)
- [ ] A writable `storage/` outside the web root

### Phase B — dependencies

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] **Commit the generated `composer.lock`** (item B2)
- [ ] `composer validate --strict` → no warnings
- [ ] Confirm the locked `mpdf/mpdf` version; this suite was run against **8.3.1**

### Phase C — database

- [ ] Create the database: `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
- [ ] Run `tools/checks/mysql-compatibility.php` against a `_vira_mysql_probe_*` database
      and **paste its full output into §3**
- [ ] `php artisan migrate --force` from empty
- [ ] `php artisan db:seed --force`
- [ ] Verify every table is InnoDB and foreign keys are enforced

### Phase D — configuration

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `php artisan key:generate` — a fresh `APP_KEY`
- [ ] `VIRA_CREDENTIAL_KEY` generated, stored outside the repo
- [ ] `VIRA_BACKUP_KEY` generated — **a different key from the credential key**
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`
- [ ] `SESSION_DRIVER=database`, `SESSION_DOMAIN=null`
- [ ] Configure `TrustProxies` if TLS terminates at a reverse proxy
- [ ] `php artisan config:cache route:cache view:cache`

### Phase E — TLS

- [ ] Valid certificate installed; chain verified with `openssl s_client`
- [ ] HTTP → HTTPS redirect; no `200` on plain HTTP
- [ ] HSTS header once the redirect is confirmed stable
- [ ] `Set-Cookie` observed with `Secure; HttpOnly; SameSite=Lax`

### Phase F — verification on the host

- [ ] `php tools/checks/env-capabilities.php` → compare against this report's §4
      (ext-intl present, AES-256-GCM available, memory limit)
- [ ] Full regression: `node tools/checks/run-all.mjs` → expect **42/42**
- [ ] PHP tests: `tests/run.php` → expect **16 classes / 801 assertions**
- [ ] `node tools/checks/query-performance.php` → re-measure on real PHP-FPM
- [ ] Log in, create a customer, create a contract, create an SEO project → confirm the
      finalisation gate refuses activation before accesses exist
- [ ] Confirm the audit page is reachable by a super admin and returns 403 for others

### Phase G — backup

- [ ] `VIRA_BACKUP_PATH` set outside the web root, `0700`
- [ ] Cron entry installed for `php artisan vira:backup --no-interaction`
- [ ] **Prove the cron job actually fires** — wait for a real scheduled run
- [ ] Run one test restore into a scratch path with `vira:restore --verify`
- [ ] Configure off-site copy and alerting on "no new container in 26 hours"

### Phase H — before announcing

- [ ] Smoke-test every main page in a real browser at 1440 / 1280 / 768 / 390 / 360
- [ ] Confirm no console errors (`node tools/checks/console-perf.mjs`)
- [ ] Confirm no real secret appears in any log, report or screenshot
- [ ] Obtain your written approval

---

## What this round changed

| File | Change |
|---|---|
| `tools/checks/composer-integrity.mjs` | **new** — 9 checks on Composer state |
| `tools/checks/query-performance.php` | **new** — query counts, N+1 detection, scale test |
| `tools/checks/mysql-compatibility.php` | **new** — host-side MySQL harness (not in the suite) |
| `tools/checks/run-all.mjs` | registered the two new suites → **42 suites** |
| `tools/traceability-status.json` | 7 requirements corrected against the code |
| `docs/requirements/requirements.catalog.yaml` | source-document metadata corrected |
| `docs/REQUIREMENTS_TRACEABILITY.md` | regenerated |
| `.env.example` | session cookie security keys added, dev/prod values documented |

No CRM feature code changed. No test was deleted or weakened. No real secret was committed.
The shared database was left untouched — `query-performance` asserts this itself.
