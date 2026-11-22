# VIRA CRM — Phase 1 Architecture Decision Record (ADR)

| Field | Value |
|---|---|
| **ADR ID** | ADR-0001 |
| **Status** | Proposed — pending employer approval (Phase 1 gate) |
| **Date** | 2026-11-22 |
| **Deciders** | Employer (Vira Agency) + Engineering team |
| **Supersedes** | — |

## 1. Context

VIRA CRM must be a standalone CRM (not a WordPress plugin) for Vira digital agency. The brief
mandates Laravel + MySQL/MariaDB unless a real host compatibility check shows a blocker, and it
requires the framework decision to be **verified against the actual host** before being locked.

Two environment facts discovered during Phase 0 matter for this ADR:

1. **The target host is not accessible from the development sandbox.** `crm.viraagency.co`
   resolves to `185.164.72.77` and TCP/443 accepts connections, but TLS handshake is closed by
   the server from this network (all TLS versions). There is no SSH/FTP/cPanel access.
   → Host compatibility checks (PHP version, extensions, DB, Composer, Artisan, Cron, memory,
   Document Root, SSL, Queue, Storage, Backup) are **blocked** and cannot be claimed as done.
2. **The development sandbox has no PHP runtime.** PHP 8.x cannot be installed in this session
   (no Debian repo access, `sudo` is present but apt cannot reach mirrors). However, the npm
   package `@php-wasm/node` provides a **real PHP 8.3.36 interpreter** (WebAssembly build of
   php-src) with `mbstring`, `openssl`, `json`, `pdo_sqlite`, `pdo_mysql`, `gd`, `zip`, `curl`,
   `sqlite3`, `dom`, `SimpleXML`, `bcmath`, `fileinfo`, `iconv`, `imagick`, `zlib`, `opcache`.
   This was **executed and verified** in this session (see `phase-1/00-dev-environment-validation.md`).

The host compatibility question therefore cannot be answered today, and we must choose a
framework baseline that (a) satisfies the brief's Laravel default, (b) is deployable on both
shared hosting and a future VPS without rewrite, and (c) is testable in this sandbox.

## 2. Decision

**Adopt Laravel 12 LTS-line (currently `laravel/framework` ^12.40, requires PHP ^8.2) as the
application framework, targeting PHP 8.2/8.3 as runtime baseline, MySQL 8.0 or MariaDB 10.6+
as production database, with SQLite used only for automated tests.**

Rationale:
- **Laravel 12 vs 13:** Laravel 13 (released 2026-03-17) fits the brief's “PHP 8.2 or compatible”
  arrow only loosely: framework v13 itself requires PHP `^8.3`, while Laravel 12 requires PHP
  `^8.2`. Choosing 12 keeps the PHP 8.2 possibility open until the employer's real host version
  is known. Laravel 12 is the previous major and still receives fixes; an upgrade path 12→13 is
  designed via the standard upgrade guide and is not a rewrite.
- **API surface used:** only long-stable framework features (Eloquent, Form Requests, Blade,
  Policies/Gates, Middleware, Notifications, Queues, Scheduler, Storage, Notifications).
  No Laravel-13-only API is used, so the 12→13 upgrade is low-risk.
- **Third-party packages are pinned to versions that support Laravel 12 *and* PHP 8.2**
  (verified against upstream composer.json on 2026-11-22):

  | Package | Pinned version | Constraint evidence |
  |---|---|---|
  | `laravel/framework` | `^12.40` | requires `php ^8.2` |
  | `laravel/sanctum` | `^4.5` | php ^8.2, laravel ^12\|^13 |
  | `laravel/fortify` | `^1.43` | php ^8.2, laravel ^12\|^13 |
  | `spatie/laravel-permission` | `^6.21` | php ^8.0, laravel ^8..^12 (v7 requires PHP ^8.3) |
  | `morilog/jalali` | `^3.5` | php ^7.1\|^8.0 |
  | `mpdf/mpdf` | `^8.3` | php ~8.0..8.5, ext-gd, ext-mbstring |
  | `phpoffice/phpspreadsheet` | `^4.5` | php ^8.1, requires ext-dom/xml/zip/etc. |

  (Note: `phpoffice/phpspreadsheet` 5.x requires PHP ^8.2 as well, but 4.5 keeps broader host
  compatibility and is sufficient for XLSX generation; final pin is re-verified after host
  compatibility is complete.)

**If the employer's host proves incompatible with Laravel's minimum requirements** (e.g. PHP
8.0/8.1, no Composer, no Cron, no writable storage outside `public_html`), we will return to the
employer with a **documented compatibility report and the options in §6**, and we will **not**
silently change the architecture.

## 3. Consequences

### Positive
- Matches the brief's default architecture; long-term maintainability, security patches, and
  hiring pool are strong.
- Runs on shared hosting (PHP 8.2+, MySQL) as well as a VPS; no WordPress coupling.
- Deployment without rewrite between staging and production: everything environment-driven
  via `.env` (Section 4 of the brief).
- Fully testable in this sandbox through the PHP-WASM runner (unit/service tests and the
  scheduler/date/PDF logic can be executed for real; see §5).

### Negative / risks
- **Host compatibility unknown (blocked).** PHP version < 8.2 or absence of Cron would force
  architecture re-decision (see §6). Mitigation: Phase 0 compatibility script + employer
  checklist; design keeps all business logic in plain PHP services that are easy to run under
  a CLI cron/scheduler on any host.
- **PDF rendering:** `mpdf` uses its own text engine (good Persian/bidi support with
  `autoScriptToLang`+`autoLangToFont`) but requires `ext-gd` and `ext-mbstring` on the host
  (both are ubiquitous on cPanel/Plesk hosts but must be verified).
- **Scheduler on shared hosting** requires a real cron entry (`schedule:run` every minute, or
  `schedule:work` on VPS). On hosts that only allow hourly cron, the reminder engine's SLA
  (“D-2 from the start of the calendar day”) must be renegotiated with the employer (open
  question Q-05).
- **PHP 12→13 upgrade** planned for a later major; tracked as technical debt, not a blocker.

## 4. Alternatives considered

| Option | Why rejected / deferred |
|---|---|
| **Laravel 13** | Requires PHP ^8.3; host unknown. Revisit after host check (if PHP ≥ 8.3 is guaranteed, 13 is acceptable and the code is compatible). |
| **Symfony 7** | Fully valid, but adds framework-specific complexity for a small team; the brief's default is Laravel; no functional benefit here. |
| **Plain PHP (no framework)** | Lowest host requirements but discards proven security/auth/session/migration machinery; higher long-term cost and risk. |
| **WordPress plugin** | Explicitly forbidden by the brief (Section 2). |
| **Node/JS stack (e.g. Adonis/Nest)** | Host is PHP-centric (cPanel-type), and the brief says PHP/Laravel; also increases ops burden. |

## 5. Verification plan for this decision

1. **Today (done):** PHP-WASM runtime executed PHP 8.3.36; extensions listed and verified;
   Persian/bidi and Jalali/date logic tests can run in the sandbox (evidence in
   `phase-1/00-dev-environment-validation.md`).
2. **After employer provides host access (or runs our compatibility script):** execute
   `docs/tools/host-compatibility-check.php` on the target subdomain and record the report.
3. **Gate:** if any hard requirement (PHP ≥ 8.2, MySQL ≥ 5.7/8.0 or MariaDB ≥ 10.3, Cron,
   writable storage, Document Root control) fails, escalate to the employer before Phase 3.

## 6. Fallback options if host is incompatible

| Blocker | Fallback | Impact |
|---|---|---|
| PHP < 8.2 only | Pin Laravel 11 (requires PHP ^8.2 as well) → plain PHP 8.1-compatible service layer with the same modular design and DB schema; or move to a VPS/subdomain on another host. | Framework downgrade or hosting change; **requires employer approval**; no data-model change. |
| No Composer / no CLI | Vendor directory shipped in deploy ZIP; run migrations via a protected web installer; scheduler via provider cron hitting a signed URL endpoint. | Slightly higher exposure surface; must be hardened (signed URL, IP allowlist). |
| No Cron | External cron service (e.g. cron-job.org) calling a signed, rate-limited scheduler endpoint. | Documented risk; scheduling SLA depends on external service. |
| No writable storage outside public | Storage in `public_html/storage` protected by `.htaccess` deny rules + randomized file names + controller-only delivery. | Weaker defense-in-depth; documented. |
| No SSL | Do not deploy credentials module until SSL is available. | **Hard blocker for credentials module.** |

## 7. Open questions tied to this ADR

- Q-01: What is the exact PHP version and extension list on `crm.viraagency.co`?
- Q-02: Is Composer available via SSH/CLI, or must we deploy a vendored build?
- Q-03: Is cron available, at what minimum interval?
- Q-04: Can we set Document Root to `public/`, or must we live with `public_html` layout?
- Q-05: What is the required scheduling SLA for D-2 reminders (day-boundary time)?

---

_This ADR is part of the Phase 1 deliverable package and is not final until the employer approves
it at the Phase 1 gate._
