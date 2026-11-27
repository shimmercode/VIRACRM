# Vira CRM — Final Acceptance Report

> **Branch:** `arena/3b43de84-viracrm`
> **SHA under test:** `963ea5e` — every number in this report was produced by
> running against that commit.
> **Verdict: NOT production ready.** Four items are BLOCKED by the environment
> and one product decision is outstanding. Section 12 states exactly what has
> to happen before acceptance.
>
> Nothing below is marked Pass that was not actually executed. Where the
> environment could not prove something, the section says BLOCKED and quotes
> the command output.

---

## 1. Product Completion

| Area | Delivered | Proven by |
|---|---|---|
| Accounting & contracts | contracts, invoices, statements, financial calculation, payments | `contracts`, `accounting`, `contract-report` |
| Human resources | attendance, leave, payroll, payslips, tax rules | `hr-payroll`, `payroll`, `attendance` |
| Organisation | hierarchy, roles, permissions, assignment | `org-structure`, `hr-permissions` |
| SEO | packages, keywords, projects, details, website address | `seo-workspace`, `seo-website`, `seo-packages` |
| Reports | status, expert workload, contracts, due/done/overdue | `reports`, `work-item-report`, `expert-workload` |
| PDF documents | finalised contract, access-handover minutes, payslip | `pdf-flow`, `template-flow` |
| Backup | `vira:backup` / `vira:restore`, AES-256-GCM, key gates | `backup-hardening` 13/13 |
| Settings | six §15 configuration screens, all proven to change behaviour | `admin-config` 24/24 |
| Integration | chains A–I end to end | `phase4-integration` 26/26 |
| Security | authn, authz, audit log, encryption, IP guard, headers | `security-headers` 16/16, `policies` 14/14, `rate-limit` |

**Test estate:** 67 automated suites (16 of them real Chromium), 18 PHP test
classes with 826 assertions, 105 check scripts.

> **Correction to the previous report.** It cited `backup-flow` and
> `backup-restore-flow` as the evidence for backup. **Those suites do not
> exist** — no file in `tools/checks/` matches, and the roster never contained
> them. Backup was in fact covered only inside `e2e-workflows.mjs` (W-09) and
> `phase4-integration.mjs` (scenario I). That citation was wrong; it is
> corrected here and the coverage is now a suite of its own.

---

## 2. Requirements Traceability

96 tracked requirements. Source of truth: `docs/requirements/requirements.catalog.yaml`
(the requirement text) joined with `tools/traceability-status.json` (status and
evidence). Full per-requirement detail, including each requirement's **actual
text from the binding document**, its gap and its remedy, is in
**`docs/REQUIREMENT_CLOSURE.md`** (generated).

| Status | Count |
|---|---:|
| `VERIFIED` | 63 |
| `PARTIAL` | 13 |
| `NOT_VERIFIED` | 1 |
| `NOT_IMPLEMENTED` | 15 |
| `BLOCKED` | 4 |
| **Total** | **96** |

**Weighted completion: 259/393 = 65.9%** of contractual weight is VERIFIED.

**Scope.** 84 requirements are in scope of the binding 15-section client
document. **12 are not**: the `REQ-16.x` set comes from a separate
file-workspace / online-office specification, and the binding document
(`docs/requirements/source/Vira_CRM_Requirements_FA.txt`) **has no section 16** —
its last section is §15 at line 287, followed by «جمع‌بندی» at line 297, verified
by direct grep. Those 12 are tracked so nothing is silently dropped, but they
are excluded from the completion figure and **must not be counted as delivery
defects**. No new requirement was added to scope without a source.

**Two statuses were wrong and were corrected this round:**

- **REQ‑11.03 → VERIFIED.** It was carried as NOT_VERIFIED with the evidence
  "licence not confirmed". The licence was already in the repository:
  `public/build/fonts/OFL.txt` is the SIL Open Font License 1.1 for Vazirmatn,
  and `resources/css/tokens.css:13-32` declares three self-hosted `@font-face`
  rules pointing at local `.woff2` files (all three present). §11‑3 asks for
  exactly "Vazirmatn, OFL, self-hosted, no external dependency".
- **REQ‑10.05** stays PARTIAL, but for a different reason than recorded. Every
  code-side control is now implemented **and asserted**; the remaining gap is
  HTTPS termination, which is deployment-side.

---

## 3. MySQL Verification — **BLOCKED**

**Not verified. SQLite is not accepted as evidence for MySQL.**

No MySQL or MariaDB exists in this environment:

```
mysqld: absent    mariadbd: absent    mysql: absent
mariadb: absent   mysqld_safe: absent
no mysql runtime dirs
```

The compatibility harness was run for real, and reports its own inability:

```
$ DB_CONNECTION=mysql DB_DATABASE=_vira_mysql_probe_1 DB_HOST=127.0.0.1 \
    DB_USERNAME=probe DB_PASSWORD=probe php tools/checks/mysql-compatibility.php
FAIL  connect   could not connect: SQLSTATE[HY000] [2006] MySQL server has gone away

No verdict can be given for any area. Item stays BLOCKED.
```

Without `DB_DATABASE` starting `_vira_mysql_probe_` the harness refuses
(`exit 2`), because it creates and drops tables.

**Therefore unverified against MySQL:** migrations from empty, seeding, the PHP
test suite, all 67 suites, CRUD, RBAC, contract and financial calculation,
payroll, PDF, backup/restore, the Jalali calendar, and transactions with
foreign keys. Every one of those is proven **on SQLite only**.

**To close:** run the harness, then `php artisan migrate --force && db:seed` on
an empty MySQL database, then the full regression with `DB_CONNECTION=mysql`,
and record every SQL error found.

---

## 4. Composer Verification — **BLOCKED**

`composer` is not on `PATH`; `/tmp/composer.phar` exists (615,266 bytes) but the
package repository is unreachable from this environment:

```
$ node -e "require('https').get('https://repo.packagist.org/packages.json', …)"
ERROR ECONNRESET
```

**So `composer.lock` was not generated and no clean install was tested.**

Declared requirements, verified by reading `composer.json`:

```
php                ^8.2
laravel/framework  12.40.0
mpdf/mpdf          ^8.2
```

Extensions required (all present on the development runtime, PHP 8.3.36):
`pdo`, `pdo_mysql`, `pdo_sqlite`, `openssl`, `mbstring`, `gd`, `zip`, `json`,
`tokenizer`, `xml`, `ctype`, `fileinfo`, `curl`, `bcmath`. `intl` is **not**
required and is absent — do not add a dependency on it.

**To close, on a host with network access:**

```bash
composer install
composer validate
composer install --no-dev --optimize-autoloader   # in a clean directory
php tests/run.php && node tools/checks/run-all.mjs
```

then commit the resulting `composer.lock`. No dependency was changed to make a
test pass.

---

## 5. Security Verification

### New: `security-headers` (16/16)

REQ‑10.05 was carried as PARTIAL with the evidence "no security headers test".
The implementation was never the gap — `SecurityHeaders` was already registered
at `bootstrap/app.php:62`. What was missing was any assertion that the headers
reach a client, so a refactor could have dropped the middleware with nothing
noticing. Now asserted over real HTTP:

| Control | Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `same-origin` |
| `X-Permitted-Cross-Domain-Policies` | `none` |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=(), payment=()` |
| `Content-Security-Policy` | all 7 directives, incl. `frame-ancestors 'none'`; `script-src` has **no** `unsafe-inline` |
| Session cookie | `HttpOnly`, `SameSite=lax`, `Path=/`, `Secure` when configured |
| `X-Powered-By` | absent |

### Bug found by that suite, and fixed

`SecurityHeaders` set `Cache-Control` only `if (! $response->headers->has('Cache-Control'))`.
Symfony gives every response a default `Cache-Control: no-cache, private`, so
**the guard was always false and the intended value never shipped**. Pages were
`no-cache` — which means "revalidate before reuse", not "do not store" — while
carrying customer and credential metadata. Now set unconditionally:

```
Cache-Control=must-revalidate, no-cache, no-store, private
```

### Session cookie now fails safe

`config/session.php` had `'secure' => env('SESSION_SECURE_COOKIE')`. That
returns `null` when the variable is absent, and `null` is falsy — so a
deployment that simply forgot the line silently shipped its session over plain
HTTP. It now defaults to Secure when `APP_ENV=production` and the variable is
absent. Verified by booting the real config both ways:

```
APP_ENV=local      , variable absent → bool(false)   (behaviour unchanged)
APP_ENV=production , variable absent → bool(true)    (fail-safe)
```

`.env` was restored byte-identical afterwards (`cmp` clean, same SHA-256).

### Guards, verified by execution

```
tools/checks/artisan-probe.php database/database.sqlite          → exit 3 "refusing to run a command against the shared database"
tools/checks/artisan-probe.php .arena/../database/database.sqlite → exit 3 (traversal caught)
tools/checks/artisan-probe.php ../escape.sqlite                  → exit 3 "only .arena/ or /tmp/ paths are allowed"
tools/checks/scratch-count.php database/database.sqlite          → exit 3
```

### Repository secret scan

```
.env tracked by git?                      no ("did not match any file(s) known to git")
tracked files containing base64: keys      none
tracked APP_KEY value                      none (.env.example ships APP_KEY= empty)
tracked VIRA_BACKUP_KEY / CREDENTIAL_KEY   none
bcrypt hashes in tracked files             4 hits — all the literal prefix "$2y$12$" in
                                           documentation, plus a smoke test that hashes a
                                           throwaway string at runtime
```

---

## 6. Backup / Restore Verification

New suite `tools/checks/backup-hardening.mjs` — **13/13**. It measures rather
than trusting the code's own description:

| Check | Result |
|---|---|
| No `VIRA_BACKUP_KEY` | exit 1, nothing written |
| Malformed key | exit 1, nothing written |
| `--path=public/backups` | **refused** |
| `--path=public/a/b/c` | **refused** |
| `--path=public` | **refused** |
| `--path=/tmp/...` | accepted, exit 0 |
| Container format | `VIRABK1` + `cipher=aes-256-gcm`, `kdf=hkdf-sha256`, salt, IV, GCM tag, manifest hash |
| Encrypted at rest | **7.991 bits/byte** (random = 8), no SQLite magic, no plaintext Persian |
| Detached checksum | `<container>.sha256` present |
| Wrong key on restore | exit 1 + `restore_failed` audit row |
| One flipped bit | exit 1 + audited |
| Real restore | exit 0, 487 rows + 2 files, all tables reconcile |
| Shared database | fingerprint unchanged |

**Hole found and closed.** `vira:backup` accepted any `--path`, including one
inside `public/`. An encrypted dump of the whole database in the document root
would be fetchable by anyone who guessed the filename, with a leaked key the
only thing between them and the data. `BackupDatabase` now resolves the
destination's nearest existing ancestor and refuses anything under the document
root.

The first version of that guard had a bug, caught by its own positive twin: it
used `substr($resolved, strlen($publicRoot))` and compared to `''`, and `substr`
returns `''` for *any* offset past the end of the string — so every path shorter
than the document root, `/tmp` included, looked like it was inside it and
legitimate backups were refused. Replaced with a real prefix test.

**Blocked:** `VIRA_BACKUP_KEY` is absent from `.env` here (backups in this
sandbox use a generated key), and **cron scheduling cannot be verified** — no
`cron`, `crond` or `crontab` binary exists. The scheduled cycle must be proven
on the real host.

---

## 7. UI/UX Verification

### Gap found

`ui-shots.mjs` covers eleven pages: login, dashboard, and the customer /
contract / project lists, details and forms. Those are the pages that existed
when the earlier audit ran. **Payroll, the three reports, the organisation
chart and the six §15 settings screens had no screenshot and no measured layout
at all.** A route that renders is not a page that has been audited.

### New: `ui-modules` (83/83)

Twelve module pages through real Chromium at the same eight widths
(1440/1280/1024/768/576/430/390/**360**). Per page, every assertion a computed
number from the live DOM:

| Assertion | Result |
|---|---|
| HTTP status | 200 on all 12 |
| RTL | `dir=rtl lang=fa` on all 12 |
| Typography | body `font-family: Vazirmatn, …` on all 12 |
| Responsive fit | **no horizontal overflow at any of the 8 widths**, down to 360px |
| Forms | every field on every page has a reachable label |
| Tables | every table has a header row |
| Shell | sidebar + topbar + 24 nav links present on module pages |
| 360px | sidebar hidden rather than covering the viewport |

24 screenshots captured (each page at 1440 and 360) in `docs/evidence/ui-modules/`.

### Existing audits, all green on this SHA

`ui-shots` (11 pages × 8 widths) · `a11y` 10/10 · `design-system` 22/22 ·
`css-audit` 42/42 (143.5 KB stylesheet) · `tokens` · `unstyled`.

---

## 8. Regression Results

Run once, in full, on **`963ea5e`**:

```
suites     67 total · 67 executed · 67 passed · 0 failed · 0 skipped
assertions 1827 executed · 1827 passed · 0 failed · 0 skipped
isolation  shared database untouched (every pass)

REGRESSION SUITE PASSED
exit 0
```

### Test isolation — the Critical item

**Pass condition: the development database's hash must be identical before and
after a full test run.**

```
before:  tables=57 rows=484 sha256=54fe60a4a88d1e61f6950b19ff8d79774dc7d6d509b8becfdc6d3600b5e95c4a
after:   tables=57 rows=484 sha256=54fe60a4a88d1e61f6950b19ff8d79774dc7d6d509b8becfdc6d3600b5e95c4a
```

**Identical.** No test changed a real user's password; no suite leaves the
environment in an unknown state on failure.

**How the leak escaped for so long.** The runner already had an isolation check,
but it compared `select count(*)` over seven hardcoded tables. Rewriting one
column of one existing row leaves every row count identical, so the check
printed "shared database untouched" on runs that were not untouched. It was
found by bisecting the suites one at a time against a **content** fingerprint:

```
clean    unit / views / policies / route-permissions / route-smoke
clean    composer-integrity / query-performance / docs-integrity
clean    flow-login / flow-authed / flow-authz / flow-crud / customer-create
MUTATES  authorization   d2143d11 → 468ebd9b
```

Exactly one row, one column:

```
--- users: -1 +1
   WAS (2, 'کاربر مشاهده‌گر', 'viewer1', …, '$2y$12$1Kdz…', …)
   NOW (2, 'کاربر مشاهده‌گر', 'viewer1', …, '$2y$12$nrD1…', …)
```

`tools/checks/authz.php` did `firstOrNew(['username'=>'viewer1'])` →
`Hash::make($pw)` → `save()`, and a fresh bcrypt salt on every run.

**Fix.** `authz.php` now copies the database into a private directory and points
`DB_DATABASE` at the copy before bootstrap — the mechanism `policies.php` and
the browser flows already used — and refuses to run if the override did not
take. The runner's isolation check was replaced with
`tools/checks/db-fingerprint.php`, a SHA-256 over every row of every table
opened `mode=ro`, with per-table digests so a failure names the table.

**Negative control** (proving the check can fail): restoring the old
`authz.php` makes it print

```
BAD WHOLE DATABASE   49344ac733f8c97c… -> b124275caae0a13f…
BAD users            ba3233746b79f645… -> 44ca5fdb3ff702d8…
exit=1
```

and the fixed version returns `ok 57 tables · every row identical`.

`php tests/run.php` was also cleared: the fingerprint is identical before and
after a full 826-assertion run.

---

## 9. Deployment Checklist

**`docs/DEPLOYMENT_CHECKLIST.md`** — a standalone handover document, separate
from `DEPLOYMENT_GUIDE.md`. Seventeen items, each with the command that proves
it, and a sign-off table: PHP version · extensions · Composer · MySQL ·
environment variables · `APP_KEY` · `APP_ENV`/`APP_DEBUG` · HTTPS · trusted
proxies · session cookie · storage permissions · document root · queue/cron ·
backup · restore · logging and error handling · initial admin setup.

No real key or password appears in it or anywhere in the repository; every
secret-bearing value is written as `<...>` and `.env.example` ships them empty.

Two items in it are already flagged as unproven rather than ticked: Composer
(§3) and MySQL (§4).

---

## 10. Remaining Blockers

| # | Blocker | Why it is blocked here | What closes it |
|---|---|---|---|
| 1 | **MySQL / MariaDB compatibility** | no server binary, no runtime dir, harness reports `FAIL connect` | run the harness + migrate + seed + full regression on real MySQL |
| 2 | **`composer.lock` / reproducible install** | `repo.packagist.org` → `ECONNRESET` | `composer install` on a host with network, commit the lockfile |
| 3 | **HTTPS** | no TLS terminator in this environment | certificate + redirect + HSTS on the real host |
| 4 | **Cron / scheduled backup** | no `cron`, `crond` or `crontab` binary | install the schedule entry, run two cycles, record the crontab |
| 5 | **`VIRA_BACKUP_KEY` in the live `.env`** | absent here; sandbox uses a generated key | `php artisan vira:backup-key`, store the value off-box |

---

## 11. Known Limitations

These are gaps in the product, not in the evidence, and each has a remedy in
`docs/REQUIREMENT_CLOSURE.md`.

| Requirement | Gap |
|---|---|
| §9‑6 (REQ‑09.06) | No Excel export. No writer installed. Marked «تکمیلی», P2 |
| §10‑6 (REQ‑10.06) | No upload subsystem at all — no MIME/size gate, no private storage, no IDOR surface tested |
| §6‑7 (REQ‑06.07) | Depends on §10‑6; there is no file to warn about |
| §12‑9 (REQ‑12.09) | Three of four invoice fields work. The **file** needs §10‑6; `document_attachment_id` is fillable but nothing writes it |
| §15‑3 (REQ‑15.03) | No web installer; setup is CLI only |
| §15‑4 (REQ‑15.04) | The shipped `database/database.sqlite` has an **empty `migrations` table**, so it cannot report its own version. Ship an empty database plus migrations instead |
| §15‑6 (REQ‑15.06) | Developer docs are extensive; there is **no end-user manual** |
| §2‑1 (REQ‑02.01) | Dashboard renders four indicator groups; §2‑1 names ten |
| §3‑5 (REQ‑03.05) | `national_id` is format- and checksum-validated but has **no uniqueness rule**, so two customers can share one |
| §12‑8 (REQ‑12.08) | `timezone => UTC`. The *business* day boundary for an Iranian client was never confirmed with them |
| §12‑7, §13‑2 | External gateway / real scheduler required |

**The development database is not a deliverable.** `database/database.sqlite`
is git-ignored, and although the suite no longer mutates it, it is still a
development artefact whose `viewer1` password was last written by a test.
Deploy with `migrate --seed` on an empty database.

---

## 12. Final Production Readiness Decision

### **NOT PRODUCTION READY. Final acceptance is not granted.**

**What is genuinely proven:**

- 67 of 67 suites pass on `963ea5e`, 1827 of 1827 assertions, exit 0.
- The full regression leaves the development database **bit-for-bit unchanged** —
  the Critical isolation item is closed, with a negative control proving the
  check can fail.
- 63 of 96 tracked requirements are VERIFIED; 65.9% of contractual weight.
- Backup encryption is measured (7.991 bits/byte), not asserted, and a wrong
  key or a single flipped bit is refused and audited.
- Every module page renders RTL, in Vazirmatn, with no horizontal overflow down
  to 360px, with every form field labelled.
- No secret is tracked in the repository.
- Two real bugs were found by tests written this round and fixed: the
  `Cache-Control` guard that never fired, and `vira:backup` accepting a path
  inside the document root.

**What is not proven, and why it blocks acceptance:**

MySQL compatibility has **never been executed** — every database claim in this
project rests on SQLite. There is no `composer.lock`, so the install is not
reproducible. HTTPS and cron cannot be exercised here at all.

**Conditions for acceptance, in order:**

1. `composer install` on a networked host → commit `composer.lock`; re-run the
   full regression there.
2. Real MySQL: run `tools/checks/mysql-compatibility.php`, then
   `migrate --force` + `db:seed` on an empty database, then the full 67-suite
   regression with `DB_CONNECTION=mysql`. Record and fix every SQL error.
3. On the production host: HTTPS with HSTS, `TRUSTED_PROXIES` set to the real
   proxy (never `*`), `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
   `VIRA_BACKUP_KEY` and `VIRA_CREDENTIAL_KEY` generated.
4. Install the cron entry and prove two consecutive backup cycles, plus one
   real restore.
5. **Product decision required:** are the twelve §16 requirements in this
   contract? The binding document does not contain a section 16, so this has to
   be asked rather than assumed. Until it is answered, they are excluded from
   scope and from the completion figure.

Until 1–4 are executed and 5 is answered, this report should be read as a
complete and honest statement of what is done and what is not — not as
acceptance.
