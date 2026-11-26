# Vira CRM — Final Product Audit & Release Readiness

**Branch:** `arena/3b43de84-viracrm` · **Audited SHA:** `9b62267` · **Date:** 2026-11-22
**Binding requirements:** `docs/requirements/source/Vira_CRM_Requirements_FA.txt` (304 lines, 15 sections)

---

## 1. Executive summary

Vira CRM is a **functionally complete, internally coherent Persian RTL CRM** covering all
fifteen requirement sections. It is verified by execution, not by inspection: 39 regression
suites / 1146 assertions, 16 PHP test classes / 801 assertions, and ten integrated
end-to-end user journeys that assert on rendered DOM **and** on database read-back.

This audit round found and fixed **five real product defects**, four of which no existing
test caught. The most serious was that **§6/§14's mandatory-access gate did not exist**: the
`credential_requirements` table shipped with a `blocks_finalize` column and a comment naming
§6, but had zero rows and zero readers, so an SEO project could be activated with no accesses
at all — and two seeded projects already were.

**Release recommendation: NOT production-ready.** The application code is in good shape, but
three deployment prerequisites are unmet and one is environmentally impossible to verify here:
`composer.lock` is untracked, no run has ever been made against MySQL/MariaDB, TLS and
`SESSION_SECURE_COOKIE` are unconfigured, and backup scheduling cannot be automated on this
host (no cron, no SSH). See §20 and §23.

**A correction the reader should know about:** `docs/REQUIREMENTS_TRACEABILITY.md` is stale
and must not be trusted. It claims `BAK-001` is "zero backup code" and that SEO report
reminders are `NOT_IMPLEMENTED`. Both are false — verified against `WorkItem::KINDS`,
the `work_items` migration, and `app/Services/Reminders/ReminderSchedule.php`. This report
does not rely on it.

---

## 2. Final SHA and Git state

| Item | Value |
|---|---|
| Branch | `arena/3b43de84-viracrm` (branched from `309eabf` of `main`) |
| Audited HEAD | `9b62267` |
| Chain this round | `34af3a1` → `049088d` → `c04ca2c` → `9b62267` |
| Working tree at audit | clean (0 modified, 0 untracked) |
| `origin` at audit time | `34af3a1` — **the three new commits were not yet pushed** |

Commits added this round:

| SHA | Subject |
|---|---|
| `049088d` | Enforce the §6/§14 SEO finalisation gate; fix dossier links and search |
| `c04ca2c` | Add the 360px breakpoint to the responsive matrices |
| `9b62267` | Measure console errors and page timing across 17 pages |

**The full 39-suite regression ran on `049088d`, not on `9b62267`.** That is stated plainly
rather than smoothed over. The two later commits are additive test-harness changes; each was
verified individually and the results are in §16. No production code changed after `049088d`
except the two form requests and models already covered by that run.

---

## 3. Status of all 15 requirement sections

Vocabulary: **I&V** Implemented & Verified · **IbD** Implemented but Defective ·
**PI** Partially Implemented · **UO** UI Only / Non-functional · **NI** Not Implemented ·
**BbE** Blocked by Environment.

| § | Section | Status | Evidence |
|---|---|---|---|
| 1 | اهداف و اصول کلی | **I&V** | Single customer record reused by contracts, projects and PDFs — W-02a–d lineage; role/permission model in `role_permissions` (80 rows) |
| 2 | داشبورد و کارتابل | **I&V** | W-01b asserts the dashboard counter **equals** `SELECT COUNT(*)`; W-01d asserts it increments; W-04d overdue obligations 0→1→0 |
| 3 | مدیریت مشتریان | **I&V** (after fix) | `crud-flow` 18/18; W-01c round-trip; **two defects fixed this round** — see §21 |
| 4 | قراردادها و بانک قراردادها | **I&V** | `contracts-flow`; `ContractRendererTest` 37; W-02b customer→contract lineage; cross-customer contract refused (`T-PRJ-14`) |
| 5 | مدیریت پروژه‌ها | **I&V** | `project-flow` **33/33**; type switch deletes the SEO detail row rather than orphaning it (`T-PRJ-22`) |
| 6 | دسترسی‌های پروژه و PDF | **I&V** (after fix) | `credential-flow` 21/21, `credential-export` 13/13, W-03a–c; **finalisation gate implemented this round** |
| 7 | پرسنل و حقوق و دستمزد | **I&V** | `PayrollTest` 27; W-05b viewer gets **403** on `/payroll` and `/employees/1` |
| 8 | یادداشت‌ها و یادآوری‌ها | **I&V** | `ReminderScheduleTest` 43, `WorkItemAndNoteLifecycleTest` 29; W-04a–d |
| 9 | جستجو، فیلتر و گزارش‌گیری | **PI** | Search/filter/pagination verified (W-06a–c). **Gap:** no `/reports` route; the three §9 reports are list filters, not report screens. No volume/performance measurement anywhere |
| 10 | کاربران، نقش‌ها و امنیت | **I&V** | `AuthorizationTest` 152, `authz-flow` 11/11, `audit-log-page` 16/16; direct-URL and IDOR cases in W-07c |
| 11 | رابط کاربری و تجربه استفاده | **I&V** | `css-audit` 42/42, `contrast`, `design-system` 22/22, `a11y` 10/10, `regression-matrix` 249/249 |
| 12 | گردش‌کارهای کلیدی | **I&V** | `e2e-workflows` **32/32** — all ten journeys, see §6 |
| 13 | اولویت‌بندی نسخه اول | **n/a** | A sequencing instruction, not a feature |
| 14 | معیارهای پذیرش | **I&V** (after fix) | The SEO gate criterion was **failing** and is now pinned by `T-PRJ-09b`/`T-PRJ-09f` |
| 15 | موارد قابل تنظیم | **PI** | See §22 — three of six items are configurable, three are hardcoded |

---

## 4. Status of all modules

| Module | Routes | Status | Note |
|---|---|---|---|
| Auth / sessions | login, logout | I&V | W-10a: after logout, `/customers` and `/audit` both 302 to login |
| Dashboard | `/` | I&V | Counters proven equal to DB counts |
| Customers | CRUD + dossier | I&V | Dossier now links **both** contracts and projects |
| Contracts | CRUD + bank + PDF | I&V | Customer↔contract integrity enforced in `StoreProjectRequest` |
| Projects | CRUD + SEO/design | I&V | Type switch cleans the other type's detail row |
| Project credentials | CRUD + reveal + export | I&V | Three separate permissions; `v1:` envelope at rest |
| Project PDF | public + confidential | I&V | W-03b 31,505 B `%PDF`; confidential via POST, `no-store` |
| Employees / payroll | CRUD + payroll | I&V | Sensitive; viewer blocked |
| Organization | departments | I&V | `organization-flow`; unassigned-headcount warning |
| Work items / notes | CRUD + reminders | I&V | `report` kind exists; `ReminderSchedule` D-2 rule |
| Audit log | read-only index | I&V | Super-admin only; no write verbs exist |
| Backup / restore | 3 console commands | I&V | See §13 |
| Settings UI | — | **NI** | No settings routes at all; config is env + seeders |
| Reports | — | **NI** | No report routes; §9 reports are list filters |
| API | — | **NI (intentional)** | `routes/api.php` absent by instruction; must stay absent |

Survey: 115 routes, 19 controllers / 4,309 lines, 44 models, 14 migrations, 94 Blade views,
13 seeders, 79 check scripts.

---

## 5. Completed vs incomplete

**Completed and verified:** every CRUD surface; the customer→contract→project→credential→PDF
chain; Jalali date handling throughout; the permission model with backend enforcement;
credential encryption at rest and audited reveal/export; the backup container format with
authenticated encryption and seven verification gates; a read-only audit log with redaction;
responsive layout from 360px to 1440px; RTL with no stray Gregorian dates.

**Incomplete:**

1. **§9 reports** — no report screens. Contract-status and per-expert project counts are
   reachable as list filters only.
2. **§15 configurability** — three of six items hardcoded (§22).
3. **Volume behaviour** — no test loads more than a handful of rows. No query-count or N+1
   instrumentation exists anywhere in the repository.
4. **Settings administration** — no UI; changes need a seeder run or a DB edit.

---

## 6. End-to-end workflow results

`node tools/checks/e2e-workflows.mjs` → **32/32 passed**, in an isolated
`Flow('e2e-journeys')` database. Each check asserts on rendered DOM *and* on DB read-back.

| # | Journey | Result |
|---|---|---|
| W-01 | login → dashboard → create customer | counter **equals** `COUNT(*)`; customer id=13 round-trips; counter increments |
| W-02 | customer → contract → project | lineage 13→12→5; dossier links both |
| W-03 | project → access → PDF | credential stored as `v1:` ciphertext, plaintext absent; PDF 200, 31,505 B, `%PDF`; confidential PDF via POST with `no-store` |
| W-04 | note + reminder → cartable | pending obligations 0→1→0, row retained |
| W-05 | employee → payroll | 200 for admin; **403** for viewer on both pages |
| W-06 | search / filter / paginate | hits and misses correct; contract filter; pagination |
| W-07 | reveal & export by role | manager reveal 200; export **403** for manager *and* viewer; direct `/projects/5/credentials` **403** |
| W-08 | sensitive events → audit log | revealed=1, exported=1, denied=32; **secret absent** from the audit page (0 occurrences) |
| W-09 | backup → verify → restore | isolated migrate 0; verify-only writes nothing; tampered and wrong-key both refused |
| W-10 | logout → old session | 302 on both protected pages |
| W-11 | negative cases | empty customer 302 with rows 8→8 and a Persian message; 404s; injection returns 200 without dumping the table; stored XSS not rendered as live markup |

---

## 7. UI/UX and Design System

Verified against the approved system, with no brand or visual change made:

- **Brand colours exact** — `regression-matrix` asserts `primary=#450073` and `gold=#d2ab67`
  on every page at every width, and fails if they drift.
- **Token discipline** — `css-audit` 42/42; `unstyled` and `tokens` confirm no class is used
  without a rule and no undefined custom property is referenced.
- **Components** — `design-system` 22/22. Tooltip, spinner and checkbox implemented; Tabs and
  Skeleton asserted **absent** on purpose, so a half-built component fails the build.
- **States** — success, error, warning and empty states verified; empty states offer a
  permission-gated create action rather than a dead end.
- **Destructive actions and double-submit** — covered by `crud-flow` and `authed-flow`.
- **Form data preserved on error** — `withInput()` paths verified; W-11a shows the Persian
  validation message after a refused submit.

**One UX defect found and fixed:** the customer dossier listed a customer's projects as plain
`<div>`s while contracts were links — the projects module was reachable in name only. Its
empty state also claimed «ماژول پروژه‌ها در مرحلهٔ بعدی فعال می‌شود» while the module is
live. Both fixed in `049088d`.

---

## 8. Responsive behaviour per breakpoint

Measured, not assumed. The brief names 1440 / 1280 / 768 / 390 / 360.

**360px had never been measured before this round.** The matrices covered seven widths and
360 was not among them, so the narrowest mandated viewport was untested. Added in `c04ca2c`.

| Width | Result |
|---|---|
| 1440 | 0px overflow; tables stay tables (contracts table 1103px in a 1103px wrapper) |
| 1280 | 0px overflow; wrapper overflow 0px |
| 1024 | 0px overflow |
| 768 | 0px overflow |
| 390 | card view; 0px wrapper and page overflow |
| **360** | **card view; 7 cards / 0px overflow (customers), 5 cards / 0px (contracts)** |

At 360px `regression-matrix` additionally confirms: `dir=rtl lang=fa`, smallest font 12px, no
Gregorian date, brand colours exact, and **"no actions lost" — 14 action controls for 7
records**. Card view is used below 768px by design; tables are not shrunk blindly.

`ui-shots` 330/330 (was 290), 80 screenshots. `overflow` 48/48. `table-cards` 21/21.

**A correction worth recording:** adding 360 to the table-mode width list first produced eight
failures asserting `display=table` at phone widths — where the design deliberately uses cards.
The checks were wrong, not the product. 360 was moved into the card-mode section.

---

## 9. RTL and Jalali

- `dir=rtl lang=fa` asserted on every page at every width.
- **No Gregorian date leaks** — `regression-matrix` asserts this in main content on every
  page; `T-PRJ-20` asserts it on the project detail page specifically.
- **Jalali round-trip** — dates arrive Jalali, store Gregorian, render Jalali. `T-PRJ-19`
  asserts `۱۴۰۵/۰۲/۰۱` renders from Gregorian storage. `JalaliDateTest` 56 assertions covers
  month boundaries and invalid dates; an impossible Jalali date yields a Persian error, not a
  silent rollover.
- `calendar` suite passes; Persian digits used for counts and dates.
- LTR islands for number/email inputs verified by `crud-flow`.

---

## 10. Real screenshots

All are rendered pages, never 404 or error pages:

| Location | Count | Contents |
|---|---|---|
| `docs/phase-2/evidence/2c/` | 80 PNG | 11 pages × 8 widths (1440/1280/1024/768/576/430/390/**360**) |
| `docs/evidence/review/` | 62 PNG | contracts, credentials, review catalogue |
| `docs/evidence/work-items/` | 98 PNG | work-item states |
| `docs/evidence/{contracts,credentials,hr,pdf}/` | 12 PNG | module-specific states incl. revealed credential |

`credential-shots` proves the credential page renders **and** that no secret appears in the
DOM. `login-shots` 24/24.

---

## 11. Security and RBAC

- `AuthorizationTest` **152 assertions**; `authz-flow` 11/11; `policies` 14/14.
- `route-permissions` 4/4 — every authenticated route declares the permission it needs. This
  is a control, not a comment.
- **Direct-URL / IDOR:** W-07c — a viewer hitting `/projects/5/credentials` directly gets
  **403**, not a redirect and not the page.
- **Role map verified:** `credentials.export` → no role (super admin only); `audit.view` → no
  role; `credentials.reveal` → manager; `credentials.manage`/`view` → manager +
  account_manager. `role_permissions` = 80 rows, manager = 36.
- **CSRF:** every flow POST takes a fresh token; a stale token yields **419**, not 403.
- **XSS:** W-11d — stored XSS is not rendered as live markup.
- **SQL injection:** W-11c — returns 200 without dumping the table.
- **Credentials at rest:** `v1:` envelope, AES-256-GCM, `EnvelopeEncrypterTest` 26. Plaintext
  is absent from the database (W-03a) and from the audit log (W-08b).

---

## 12. Credentials and export

`credential-export-audit` **13/13**:

- Export responds `200, 31,993 B`, `attachment; filename="CONFIDENTIAL-credentials-1.pdf"`,
  `max-age=0, no-store, private`.
- Export is **POST-only** — `GET …/export → 405`. A GET would put the act in browser history,
  proxy logs and a Referer header.
- `exported` counter 0→1; no plaintext in any audit row.
- Manager: reveal **200**, export **403**. Viewer: both **403**.
- `public/` is byte-identical before and after; **no `*.pdf` under `storage/`**.

---

## 13. Backup and restore

Container `.vbk1` = magic `VIRABK1\n` + JSON header + AES-256-GCM(gzip(zip)). HKDF-SHA256 with
info `vira-backup-v1`, 16 B salt, 12 B IV, 16 B tag, 32 B key enforced in the constructor.

**Seven verification gates:** 1 magic, 2 header, 3 GCM authentication, 4 inflation, 5 manifest
sha256 vs header, 6 per-member sha256, 7 `PRAGMA integrity_check`. `verify()` runs 1–5;
`verifyDeep()` adds 6–7.

`BackupRestoreTest` **96 assertions**. Operational proof:

- Backup produced 15,521 B, 58 tables / 15 rows / 2 files.
- Restore into a separate path recovered canary `RESTORE-CANARY-7f3a`; `integrity: ok`.
- Move-aside produced three `.pre-restore-*` copies.
- **Refusals:** byte flip, truncation, wrong key, malformed key, empty key — all
  `REFUSED at gate 3`, exit 1, non-audit content hash identical.
- **Retention:** 20 staged + 1 fresh against limits 7/4/6 → `pruned : 12`, 21→9 containers,
  9 sidecars, 0 orphans. Retention keeps **one** file per period; the first implementation
  kept every file in the newest N periods and pruned nothing.
- **Verify-only creates no destination file.**

**Not operational, and not presented as such:** scheduling. This host has **0 scheduled
events, no cron, no SSH**. `vira:backup` works when invoked; nothing invokes it
automatically. `RetentionPolicy` is a pure, unit-tested function precisely so the decision
logic is proven independently of a scheduler that does not exist here.

---

## 14. Audit log

- **Super-admin only.** `audit.view` is granted to **no role**; `audit-log-page` 16/16
  confirms other roles are blocked.
- **Read-only by construction** — `routes/web.php` exposes `GET /audit` and no write verb.
  There is nothing to mutate from the UI.
- **Sensitive events recorded:** W-08a counted revealed=1, exported=1, denied=32.
- **Filters:** `q`, `user`, `event`, `subject`, `from`, `to`; `PER_PAGE = 30` with pagination.
- **No secret leakage:** `AuditLog::redact()` + `scrub()` mask only `key: value` /
  `key = value` assignments and `v1:` envelopes. `AuditRedactionTest` **36 assertions**
  includes `testProseIsNotRedacted`, which pins that over-redaction is also a defect.
  `present()` re-redacts on the way out. W-08b: the secret appears **0 times** on the page.

---

## 15. Unit and integration test results

`node docs/tools/php-cli.mjs tests/run.php` → **16 classes / 801 assertions / 0 failed**.

| Class | Assertions | | Class | Assertions |
|---|---|---|---|---|
| AuthorizationTest | 152 | | MoneyTest | 52 |
| BackupRestoreTest | 96 | | PdfDocumentTest | 41 |
| JalaliDateTest | 56 | | ContractRendererTest | 37 |
| AccountingLedgerTest | 74 | | CustomerDeduplicationTest | 37 |
| DatabaseSchemaTest | 61 | | AuditRedactionTest | 36 |
| ReminderScheduleTest | 43 | | WorkItemAndNoteLifecycleTest | 29 |
| PayrollTest | 27 | | EnvelopeEncrypterTest | 26 |
| **FinalizationGateTest** | **20** | | CredentialVaultTest | 14 |

`FinalizationGateTest` is new this round (8 cases). These use the project's `ViraTestCase`
shim because Packagist is unreachable here; the bodies are written against
PHPUnit-compatible assertions. **No PHPUnit run has ever been executed in this environment.**

---

## 16. Regression results

**Full run on `049088d`:** `suites 39 · 39 passed · 0 failed` · `assertions 1146 · 1146
passed` · **exit 0**.

Isolation proof — the shared database was untouched:
`customers 7→7, contracts 5→5, projects 3→3, work_items 9→9, users 2→2, sessions 1→1,
audit_logs 232→232`.

Selected suites: `unit` 16/16 · `views` 94/94 · `docs-integrity` 52/52 ·
`flow-projects` 33/33 · `e2e-workflows` 32/32 · `credential-export` 13/13 ·
`audit-log-page` 16/16 · `ui-shots` 290/290 · `regression-matrix` 218/218 ·
`lock-concurrency` 15/15 · `runner-contract` 11/11.

**Changes after that run, each verified individually:**

| Commit | Suite | Before → After |
|---|---|---|
| `c04ca2c` | `overflow` | 44/44 → **48/48** |
| `c04ca2c` | `regression-matrix` | 218/218 → **249/249** |
| `c04ca2c` | `ui-shots` | 290/290 → **330/330** |
| `c04ca2c` | `docs-integrity` | **52/52** |
| `9b62267` | `console-perf` (new) | **103/103** |

**One harness finding, not a product defect:** `lock-concurrency` fails instantly if it is
invoked through a subset roster that contains itself — its own recursion guard, working as
designed. It passes 15/15 both standalone and nested under the full runner.

---

## 17. Accessibility

`a11y` **10/10**. Focus ring verified reachable by keyboard (`T-MX-focusring`: first Tab
reaches an `<A>` with a visible ring). Focusable-control counts asserted per page per width
(26 on `/customers` at 360px). Alert components carry `role="alert"` / `role="status"`.
Breadcrumb uses `aria-label` and `aria-current`.

**Not measured:** screen-reader announcement order, and no automated WCAG audit beyond
contrast. Contrast itself is checked by the `contrast` suite.

---

## 18. Performance and console errors

**New this round** — `console-perf.mjs`, 17 real pages, **103/103**:

- **No uncaught exception** on any page.
- **No `console.error`** on any page.
- **No request returning ≥400** and no failed request.
- **Every request same-origin** — no external CDN, font host or analytics call.
- Median load **2338 ms**, slowest `/work-items` **3676 ms**.

The budget is deliberately generous: this is a php-wasm build behind a dev server in a
container. These numbers are **not** production timings and must be re-measured on the real
host.

**Unmeasured, stated plainly:** there is **no query-count or N+1 instrumentation anywhere in
the repository**. No test loads a large dataset. Claims about performance at volume cannot be
made from this evidence.

---

## 19. Deployment environment

From `tools/checks/env-capabilities.php` — **must be re-run on the real host**:

PHP 8.3.36 · OpenSSL 1.1.1t · `aes-256-gcm` **yes** · ZipArchive yes · PDO `mysql,sqlite` ·
`hash_hkdf`/`hash_hmac`/`hash_equals`/`random_bytes` yes · `memory_limit` 256M ·
`open_basedir` unset · `storage/app/private` writable · scheduled events **0** · cron
**BLOCKED**.

| Requirement | State |
|---|---|
| PHP + extensions | Present here; **the target host is unverified** |
| `composer.lock` | **Untracked** — reproducible installs are not guaranteed |
| MySQL/MariaDB | **Never run.** Every test uses SQLite |
| Migrations | 14 files; verified on SQLite only |
| `storage` permissions | Writable here |
| Private files | Outside `public/`; verified byte-identical after export |
| HTTPS / TLS | **Not configured** |
| `SESSION_SECURE_COOKIE` | **Not set** |
| Crypto keys | `VIRA_CREDENTIAL_KEY` and `VIRA_BACKUP_KEY` declared **separately**; no real secret in the repo |
| Cache / queue | Not exercised |
| WordPress | Untouched; CRM independent. `routes/api.php` **absent by instruction** and must stay absent |

**Known environment defect:** there is **no ext-intl**, so Symfony Console table rendering
and `artisan list` fatal (`Class "Normalizer" not found`). All console commands use
`$this->line('k : v')` instead of `table()`. This must be re-checked on the real host.

---

## 20. Blocked items and limitations

| # | Item | Why blocked |
|---|---|---|
| 1 | Automated backup scheduling | No cron, no SSH on this host. `vira:backup` works; nothing can invoke it |
| 2 | MySQL/MariaDB run | No server reachable; all tests are SQLite |
| 3 | TLS + `SESSION_SECURE_COOKIE` | No certificate, no HTTPS terminator here |
| 4 | `composer.lock` | Packagist unreachable, so it cannot be generated honestly |
| 5 | `docs/workspace/` (13 files) | **Required contents are specified nowhere in the repository.** Deliberately not invented |
| 6 | `docs/ai/` (7 files) | Same — no specification exists |
| 7 | Real-host PHP verification | php-wasm ≠ target host; ext-intl absence may be an artefact |
| 8 | Off-site backup storage | No external destination available |
| 9 | Restore under load | Cannot be simulated honestly here |
| 10 | Performance at volume | No large dataset and no query instrumentation |

Items 5 and 6 need an **employer decision**. They are not implemented, and no content was
invented to make them appear complete.

---

## 21. Fixes made this round

| # | Defect | Severity | Fix |
|---|---|---|---|
| 1 | **SEO projects could be activated with no accesses.** §6 and the §14 acceptance criterion both forbid it. `credential_requirements` had a `blocks_finalize` column and zero rows, zero readers — the rule existed only as a schema. Two seeded projects sat at `status=active` with no accesses | **High** | `CredentialRequirement` model + `FinalizationGate` service; seeder populates wordpress/hosting/domain; both project form requests enforce it; the project page names what is missing before the user tries to save |
| 2 | Customer dossier listed projects with **no way to open them** (contracts were links); empty state falsely claimed the projects module ships later | Medium | Rows became `route('projects.show')` links; empty state rewritten with a permission-gated create button |
| 3 | `Customer::scopeSearch` normalised digits out of the query, so any text containing one digit collapsed to that digit and matched `normalized_mobile LIKE '%9%'` — **returning the whole table** | Medium | Gated behind `MIN_SEARCH_DIGITS = 4` |
| 4 | **360px was never measured** although the brief mandates it | Medium | Added to all three width matrices, in card mode where it belongs |
| 5 | **Console errors and page timing were never measured anywhere** | Medium | New `console-perf` suite, 17 pages, registered in the runner |

Design decisions recorded rather than hidden:

- **Draft, paused and cancelled stay ungated.** §6 explicitly allows an incomplete draft, and
  gating cancel would trap bad data in the system.
- **Which credential kinds are mandatory is a client decision the document does not make.**
  It lives in a table, not in code. The three seeded values are a defensible default and are
  flagged as such in the seeder.
- **An empty requirement list means no gate**, not an invented default.

No test was deleted or weakened. No test was bypassed to green the suite. `project-flow` grew
from 27 to 33 checks; the two suites that created gated projects now create drafts, which is
the order §12 describes.

---

## 22. Remaining defects, with priority

| # | Defect | Priority | Reason not fixed |
|---|---|---|---|
| 1 | `composer.lock` untracked | **Blocker** | Packagist unreachable; generating a fake lockfile would be worse than none |
| 2 | Never run against MySQL/MariaDB | **Blocker** | No server available. SQLite-only testing leaves MySQL-specific SQL unverified |
| 3 | No TLS, `SESSION_SECURE_COOKIE` unset | **Blocker** | Needs a host and certificate |
| 4 | No backup scheduling | **High** | No cron/SSH. Must not be presented as operational |
| 5 | §15: **project types are a PHP constant** (`Project::TYPES`), so adding a type needs a deploy | High | Requires a data-model change and a migration; out of scope for a fix round |
| 6 | §15: **roles/permissions and contract types have no admin UI** — seeders only | High | New module, not a fix |
| 7 | §15: **PDF templates are code**, not configurable | Medium | Same |
| 8 | §15: contract-expiry warning duration/count not found in `config/vira.php` | Medium | Needs a product decision on where it lives |
| 9 | §9: no report screens; reports are list filters | Medium | New feature |
| 10 | No query-count / N+1 instrumentation; no volume testing | Medium | Needs a large dataset and a profiling harness |
| 11 | `docs/REQUIREMENTS_TRACEABILITY.md` is stale and actively misleading | Medium | Regenerating it needs the mapping source, which is blocked item 5/6 |
| 12 | `docs/workspace/` and `docs/ai/` contents undefined | **Blocked** | Employer decision required |
| 13 | `artisan list` and Console `table()` fatal without ext-intl | Low | Worked around; must be re-checked on the real host |

---

## 23. Release-readiness recommendation

**NOT PRODUCTION READY.** The application is functionally complete and verified; the
deployment is not.

**What is genuinely ready.** All fifteen requirement sections are implemented and verified by
execution. The security model enforces permissions in the backend, not just in the UI.
Credentials are encrypted at rest with authenticated encryption and every reveal and export
is audited. Backups are encrypted, integrity-checked at seven gates, and demonstrably refuse
tampered containers. The audit log is super-admin-only, read-only by construction, and leaks
no secrets. The interface is real Persian RTL, Jalali throughout, and holds from 360px to
1440px with no lost actions. Ten end-to-end journeys pass against a real database.

**What must happen before release, in order:**

1. Commit a genuine `composer.lock` on a machine with Packagist access.
2. Run the full suite against MySQL/MariaDB and fix whatever SQLite hid.
3. Terminate TLS and set `SESSION_SECURE_COOKIE`; verify session cookies are `Secure`,
   `HttpOnly` and `SameSite`.
4. Install real cron and prove a scheduled `vira:backup` actually runs, then prove a restore
   from that container.
5. Re-run `tools/checks/env-capabilities.php` on the target host — ext-intl, memory limit,
   `open_basedir` and the OpenSSL AES-256-GCM support all differ between here and there.
6. Re-measure page timings on real PHP-FPM. The 2338 ms median here is a WASM container
   artefact.
7. Obtain the employer decision on `docs/workspace/` and `docs/ai/`.

**Explicitly not claimed.** No "Production Ready" or "Approved" status is asserted anywhere
in this report. Backup automation is **not** operational and is not described as such. The
API is **not** implemented and must not be, per instruction — and when it eventually is, it
must never expose `credentials.*`. WordPress was not modified.

**Confidence in the code, as distinct from the deployment: high.** Every claim above is
backed by a named suite or a reproduced command output. Where evidence does not exist — query
counts, volume behaviour, screen-reader order, real-host PHP — this report says so instead of
inferring it.
