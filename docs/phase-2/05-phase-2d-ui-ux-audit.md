# Phase 2-D — UI/UX audit and design-system implementation

**Date:** 2026-11-22 · **Branch:** `arena/3b43de84-viracrm` ·
**Design system:** `design-system/vira-crm/` · **Evidence:** `docs/phase-2/evidence/{2c,ui-audit}/`

This is the formal audit report. Every "Pass" below is backed by a command that was run
against the live application, not by an inspection of the source.

---

## 1. Headline result

| Metric | Value |
|--------|-------|
| **Critical issues found / fixed** | 2 / 2 |
| **Major issues found / fixed** | 5 / 5 |
| **Minor issues found / fixed** | 4 / 3 (1 accepted, see §7) |
| Browser layout assertions | **178 / 178** across 7 pages × 6 widths |
| Accessibility assertions | **10 / 10** |
| Login-page assertions | **24 / 24** across 4 widths |
| Total browser assertions | **212 / 212** |
| Screenshots captured | **51** (42 layout + 4 login + 5 not-implemented) |

### Scorecard

| Section | Status | Evidence |
|---------|--------|----------|
| Visual Design | **Pass** | 51 screenshots; §5 |
| Brand Consistency | **Pass** | Tokens unchanged from the mandated palette; §3 |
| Design System | **Pass** | `design-system/vira-crm/` — MASTER, tokens, 3 guideline docs, 7 page docs |
| Typography | **Pass** | 12px floor at every width; Vazirmatn self-hosted, 4 weights |
| Color Contrast | **Pass** | 8/8 pairs ≥ 4.5:1 (lowest 5.24:1) |
| RTL | **Pass** | `dir=rtl`/`lang=fa` on all 42 page+width combinations; sidebar right-aligned; 2 RTL content bugs fixed |
| Responsive | **Pass** | 0px horizontal overflow at 390/430/768/1024/1280/1440 |
| Forms UX | **Pass** | Labels, inline + summary errors, `aria-invalid`, focus management, submit guard |
| Tables UX | **Pass** | Scroll containment, status chips with dot, right-aligned Latin values |
| Dashboard UX | **Pass** | Real data, Jalali dates, urgent-first grouping |
| Accessibility | **Pass** | 10/10 a11y checks incl. modal focus trap and accessible names |
| Interaction States | **Pass** | Focus ring measured, hover/active/disabled/loading defined, 0ms under reduced-motion |

---

## 2. Scope: what actually exists

The brief listed 22 screens. Six have no route at all. This is recorded, not glossed.

| # | Screen from brief | Route | Status |
|---|-------------------|-------|--------|
| 1 | Login | `GET/POST /login` | **Implemented** |
| 2 | Dashboard | `GET /` | **Implemented** |
| 3 | Customers List | `GET /customers` | **Implemented** |
| 4 | Customer Create/Edit | `GET/POST/PUT /customers/...` | **Implemented** |
| 5 | Customer Details | `GET /customers/{id}` | **Implemented** |
| 6 | Contracts List | `GET /contracts` | **Implemented** |
| 7 | Contract Create/Edit | `GET/POST/PUT /contracts/...` | **Implemented** |
| 8 | Contract Details | `GET /contracts/{id}` | **Implemented** |
| 9 | Contract Template Management | — | **Not Implemented** (table + model exist, no UI) |
| 10 | Projects List | — | **Not Implemented** |
| 11 | SEO Project Create/Edit | — | **Not Implemented** |
| 12 | Website Design Project Create/Edit | — | **Not Implemented** |
| 13 | Project Details | — | **Not Implemented** |
| 14 | Project Credentials | — | **Not Implemented** |
| 15 | Reminders | — | **Not Implemented** (`ReminderSchedule` support class only) |
| 16 | Notes | — | **Partial** — read-only lists on customer and contract detail |
| 17 | Employees | — | **Not Implemented** |
| 18 | Employee Details | — | **Not Implemented** |
| 19 | Payroll | — | **Not Implemented** |
| 20 | Payslip | — | **Not Implemented** |
| 21 | Reports | — | **Not Implemented** |
| 22 | Settings | — | **Not Implemented** |

The full route table (22 routes) is in §9. Each unimplemented module returns a **404
inside the application shell** with a Persian message and a link back — verified by
requesting `/projects`, `/employees`, `/payroll`, `/reports`, `/settings` and recording
`HTTP 404` with `title="صفحه پیدا نشد"`. Screenshots are in
`evidence/ui-audit/not-implemented-*.png`.

The sidebar lists these modules as **disabled rows with the note «به‌زودی»** rather than
as links that 404. That is a deliberate product decision: an honest label beats a dead
link, and it communicates the product's shape.

---

## 3. The design system, and what was overridden

### 3.1 The skill was really used

`ui-ux-pro-max` was installed into the repo with the official command:

```
npx skills add https://github.com/nextlevelbuilder/ui-ux-pro-max-skill --skill ui-ux-pro-max --agent '*' -y
→ .agents/skills/ui-ux-pro-max/  (7 skills found, 1 installed)
```

Its search tool was then run against this product, not used as inspiration:

| Query | Domain | What it returned |
|-------|--------|------------------|
| `B2B CRM enterprise SaaS…` | `--design-system` | Pattern, style, palette, typography, effects, anti-patterns |
| `CRM customer management dashboard` | `product --rtl` | **`rtl_level: caveats`** — see 3.2 |
| `dashboard data dense enterprise minimal` | `style --rtl=full` | Minimalism & Swiss, Spectrum — both `rtl_level: full` |
| `data table dense row scanning` | `ux` | Scroll wrapper, modular type scale |
| `form validation error summary inline` | `ux` | **Focusable error summary (High)** |
| `focus keyboard modal dialog` | `ux` | **Modal focus trap, return focus (High)** |
| `persian arabic multilingual` | `typography` | Noto Naskh / Noto Sans Arabic |
| `blade component layout validation` | `--stack laravel` | `@extends` layouts, flash in layout — both already satisfied |

The raw generated output is preserved at
`design-system/vira-crm/_generated/MASTER.skill-generated.md` and
`_generated/tokens.skill-generated.css`, so the difference between *what the tool
recommended* and *what the project decided* stays auditable.

### 3.2 The most important finding

The skill flags its own **"CRM & Client Management"** product pattern as
**`rtl_level: caveats`** — meaning the pattern encodes reading direction. Its own
instructions say that for a strict RTL build the caller should not use it and should
propose a mirrorable alternative. Vira therefore takes its **structure** from the
`rtl_level: full` Minimalism/Swiss row, and its information architecture from the
product's own requirements. Adopting the CRM template would have baked an LTR-first
layout into a Persian product.

### 3.3 Overrides (documented, not silent)

| Generated | Decision | Reason |
|-----------|----------|--------|
| Primary `#0F172A` navy | **Rejected** | Brand is `#450073`. The brief forbids changing the brand colour without approval. |
| Accent `#0369A1` blue | **Rejected** | Second competing hue; gold is the only sanctioned accent. |
| Plus Jakarta Sans | **Rejected** | Zero Persian coverage. |
| Noto Sans Arabic | **Rejected** | Valid for Arabic, inferior for Persian; Vazirmatn is mandated and better. |
| Density 8/10 | **Adopted in spirit** | Vira's 4–48px scale, 12/16 working defaults. |
| Minimalism & Swiss | **Adopted** | `rtl_level: full`, matches an enterprise data product. |
| All anti-patterns | **Adopted** | No gradients, no emoji icons, visible focus, 4.5:1. |

I did **not** change the brand palette. That decision is yours (§10).

---

## 4. Issues found and fixed

Each entry is a defect that survived an earlier "all green" run, which is why the audit
looked at rendered pixels rather than trusting the check count.

### CRITICAL

#### C-1 · Sign-out was unreachable below 1024px

The sidebar rail is `display: none` under 1024px and the mobile drawer repeated the
navigation but **not the sidebar footer**. On a phone or tablet there was no way to log
out at all.

Measured before the fix:

```
phone  390 : visible sign-out forms = 0
tablet 768 : visible sign-out forms = 0
desktop 1440: visible sign-out forms = 1
```

**Fix:** the drawer now renders the same footer form. **Verified:** `T-LOGOUT-phone-390`,
`T-LOGOUT-tablet-768`, `T-LOGOUT-desktop-1440` all pass.

#### C-2 · Modal drawer did not contain focus

The drawer is `role="dialog" aria-modal="true"`, but on open, focus landed on
`.v-drawer__scrim` — an element **behind** the panel — and Tab escaped into the page
underneath. The cause was a selector scoped to the whole drawer, whose first match is the
scrim button.

**Fix:** a `focusable()` helper scoped to `.v-drawer__panel`, plus a re-entry guard.
**Verified:** `T-DRAWER-FOCUS` (inside panel, not on scrim), `T-DRAWER-TRAP` (0 of 24
Tab presses escaped), `T-DRAWER-ESCAPE` (closes, focus returns to the trigger).

### MAJOR

#### M-1 · `dir="ltr"` values detached from their labels

Phone numbers, national IDs, emails and domains are marked `dir="ltr"` for character
order. But `dir` also flips what `text-align: start` means: on a full-width `<dd>` it
resolved to the **left**, while the label sat right and on a separate line, so each field
read as two unrelated rows.

**Fix:** explicit `text-align: right` for LTR values inside the RTL document.
**Verified:** `T-DL-ALIGN`. The check was validated by removing the CSS and confirming it
fails (`misaligned: ۰۹۱۲۱۲۳۴۵۶۷, ۰۲۱۸۸۷۷۶۶۵۵, info@arman.example…`).

#### M-2 · List-page primary action fell out of the header

`customers/index` and `contracts/index` emitted the create button as a bare child of
`.v-pagehead`. The head is a `space-between` flex row, so a third child became a third
flex item and wrapped onto its own row, leaving the title isolated at the top-left of a
right-aligned page.

**Fix:** both wrapped in `.v-pagehead__actions`. **Verified:** `T-PAGEHEAD-tablet-768`,
`T-PAGEHEAD-desktop-1440` (same row, title precedes action in RTL).

#### M-3 · Error summary could not receive focus, and `aria-invalid` was missing

Two separate faults:
- `<x-alert>` never merged `$attributes`, so the caller's `tabindex="-1"` and `id` were
  silently discarded. The High-severity "focusable error summary" rule was unmeetable.
- Invalid inputs had `.is-invalid` (visual only) with no `aria-invalid` and no reference
  to their error text.

**Fix:** `$attributes->class([...])` on the alert root; `aria-invalid="true"` +
`aria-describedby="<hint> <field>-error"` via a helper; `initErrorSummary()` moves focus.
**Verified:** `T-ERROR-SUMMARY` (present, `tabindex=-1`, focused), `T-FIELD-INVALID`
(fields invalid, all referencing their error text).

#### M-4 · Gregorian dates on the customer detail and contract list

Third recurrence of this class: `تاریخ ثبت 2026-11-22` and two contracted dates rendered
via `->format('Y-m-d')`.

**Fix:** a single `<x-fa-date>` component now owns the calendar decision; both views
route through it, and no Blade file calls `->format('Y-m-d')` any more (`grep` returns
zero). **Verified:** `calendar.mjs` — 5/5 pages, 0 Gregorian dates. The guard was
validated by re-injecting the bug and confirming failure.

#### M-5 · Test harness screenshotted 404s as passes

`/customers/1` and `/contracts/1` were hard-coded. After a re-seed those records were
gone, the pages rendered 404 — which trivially passes "no horizontal overflow" — and the
shell assertions failed with `sidebar=null` instead of naming the problem.

**Fix:** detail ids are discovered from the list pages; `T-STATUS` asserts every page is
`< 400`. Adding it immediately exposed 4 hidden failures.

### MINOR

#### m-1 · 8px above a 22px heading

The topbar→title gap was 8px. A bold Persian title's ascenders nearly fill its line box,
so it read as glued to the bar. **Fixed:** 20px; `T-HEAD-GAP` enforces ≥16px.

#### m-2 · Evidence contained test-run artefacts

Screenshots showed `شرکت آزمون Amwsa1duf` rows left by the CRUD flow. **Fixed:** purged
before capture; the seeder is idempotent so demo data is stable.

#### m-3 · `.arena/` was not git-ignored

Scratch diagnostic scripts were eligible for commit. **Fixed:** added to `.gitignore`.

#### m-4 · Chart/legend guidance — accepted, see §7

---

## 5. Responsive evidence

Six widths, all 7 authenticated pages. Measured values, not impressions.

| Width | Horizontal overflow | Sidebar | Drawer | Page header |
|-------|--------------------|---------|--------|-------------|
| 1440 | 0px | visible, right-aligned | — | title + action, one row |
| 1280 | 0px | visible, right-aligned | — | one row |
| 1024 | 0px | visible, right-aligned | — | one row |
| 768 | 0px | hidden | present | one row (fixed in M-2) |
| 430 | 0px | hidden | present | stacked (correct) |
| 390 | 0px | hidden | present | stacked (correct) |

The customer table keeps a `min-width` so columns stay legible; below 768 it scrolls
inside `.v-tablewrap` while the **page** overflow stays at 0px. Every ancestor from
`.v-tablewrap` up to `.v-content` carries `min-width: 0`, because a flex item's default
`min-width: auto` otherwise expands the page instead of the wrapper.

### Screenshots (all in `docs/phase-2/evidence/`)

| Page | 1440 | 768 | 390 |
|------|------|-----|-----|
| Dashboard | `2c/dashboard-desktop-1440.png` | `2c/dashboard-tablet-768.png` | `2c/dashboard-phone-390.png` |
| Customers list | `2c/customers-list-desktop-1440.png` | `2c/customers-list-tablet-768.png` | `2c/customers-list-phone-390.png` |
| Customer detail | `2c/customer-detail-desktop-1440.png` | `…-tablet-768.png` | `…-phone-390.png` |
| Customer form | `2c/customer-form-desktop-1440.png` | `…-tablet-768.png` | `…-phone-390.png` |
| Contracts list | `2c/contracts-list-desktop-1440.png` | `…-tablet-768.png` | `…-phone-390.png` |
| Contract detail | `2c/contract-detail-desktop-1440.png` | `…-tablet-768.png` | `…-phone-390.png` |
| Contract form | `2c/contract-form-desktop-1440.png` | `…-tablet-768.png` | `…-phone-390.png` |
| **Login** | `ui-audit/login-desktop-1440.png` | `ui-audit/login-tablet-768.png` | `ui-audit/login-phone-390.png` |
| Not implemented | — | — | `ui-audit/not-implemented-{projects,employees,payroll,reports,settings}.png` |

Login is captured by a **separate** script (`tools/checks/ui-login-shots.mjs`) because the
main harness authenticates once and reuses the context, so `GET /login` redirects. The
previous report correctly noted that login had no screenshot; that gap is now closed with
24 real assertions.

---

## 6. Accessibility

| Check | Result |
|-------|--------|
| Accessible name on every visible control | PASS — 5 pages scanned |
| Live region on flash and error blocks | PASS — `role="alert"` / `role="status"` |
| Error summary focusable and focused | PASS |
| Invalid fields reference their error text | PASS |
| Modal focus enters, traps, restores | PASS |
| Sign-out reachable at every width | PASS |
| Contrast | 8/8 ≥ 4.5:1 |
| Focus ring | 2px solid, 2px offset, never removed |
| Type floor | 12px rendered minimum |
| Reduced motion | all durations 0ms |

**Known limitation:** two controls in dense table rows are 28px tall, below the 44px the
skill recommends for touch. They meet WCAG 2.2 AA (24px). Raising them to 44px would push
the customer table into more horizontal scrolling at 390px; the trade-off favours the
table. Recorded, not hidden.

---

## 7. Accepted limitations

1. **Two 28px table controls** — see §6. Deliberate trade-off, AA-compliant.
2. **No client-side blur validation.** The skill prefers it; Blade forms have no client
   state for it, and a server round-trip is honest about what actually validates.
3. **No charts.** With 7 customers and 5 contracts a chart is decoration. The Reports
   design doc specifies one accent series with a legend for when volume justifies it.
4. **No skeleton screens.** Pages are server-rendered; there is no fetch to wait on.
5. **Project/contract party editing** is display-only on the contract detail — the fields
   exist on the model but not on the form.
6. **Chromium and `playwright-core` are not vendored** — the sandbox cannot reach any
   package registry. Both checks exit with code 2 and a clear message when absent.

---

## 8. Regression results

Run after all fixes, in this order.

| Suite | Result |
|-------|--------|
| Unit + feature tests | **7/7 classes, 395 assertions** |
| Blade compilation | **29/29** |
| Browser layout (`ui-shots.mjs`) | **178/178** |
| Accessibility (`a11y.mjs`) | **10/10** |
| Login (`ui-login-shots.mjs`) | **24/24** |
| Jalali calendar (`calendar.mjs`) | **5/5 pages, 0 Gregorian** |
| Token audit (`css-audit.mjs`) | **42/42** |
| Contrast (`contrast.mjs`) | **8/8 AA** |
| Unstyled classes (`unstyled.mjs`) | **0** |
| Undefined tokens (`tokens.mjs`) | **0** |
| Login flow | **5/5** |
| Authenticated flow | **17/17** |
| Authorization flow | **10/10** |
| Authorization (PHP) | **ALL PASS** |
| CRUD flow | **17/17** |
| 403 page | inside shell, Persian, escape hatch |

No backend, database, permission or workflow logic was changed for any UI fix. The only
non-presentational edits were the drawer footer (a navigation omission), the alert
attribute merge (an accessibility attribute being discarded), and one Blade helper that
adds `aria-invalid`/`aria-describedby`.

> **Note on running the flow suites:** `authed-flow` mutates the admin password and
> `crud-flow` alters the session, so the flows must be run individually with
> `prepare.php` between them. Running them in a single shell loop produces a spurious
> `1/13` for `crud-flow` — that is a test-ordering artefact, not a product failure, and it
> is worth fixing in the harness.

---

## 9. Route table (proof of scope)

```
GET      up
GET|POST login
POST     logout
GET|PUT  password
GET      /
GET      customers
GET      customers/create
POST     customers
GET      customers/{customer}
GET      customers/{customer}/edit
PUT      customers/{customer}
DELETE   customers/{customer}
GET      contracts
GET      contracts/create
POST     contracts
GET      contracts/{contract}
GET      contracts/{contract}/edit
PUT      contracts/{contract}
DELETE   contracts/{contract}
GET      storage/{path}
```

22 routes. No `projects`, `employees`, `payroll`, `reports`, `settings` or
`contract-templates` route exists.

---

## 10. Decisions needed from you

1. **Brand palette — no change made.** The skill recommended navy `#0F172A`. I kept
   `#450073` because the brief says not to change it without approval. If you want the
   palette reconsidered, say so explicitly and I will produce a contrast-tested
   comparison; otherwise it stays.
2. **Gold accent usage.** Currently `.v-auth__aside` carries a large soft gold circle.
   It is a decorative mark, not information. Do you want gold reserved strictly for
   expiry/premium signals (my recommendation), or is a brand flourish on the login wall
   acceptable?
3. **Build order for the six missing modules.** Projects, Employees, Payroll, Reports,
   Settings and Contract Templates have no routes. Which comes next? Payroll and
   Credentials carry the most sensitive permissions, so I would do them together with
   their access tests rather than last.
4. **Table density on phones.** Currently the table scrolls horizontally. The alternative
   is a card layout below 767px (one record per card with 3 key fields, tapping through
   to detail). Better on a phone, more work, and it diverges from the desktop pattern.
   Your call.
5. **`SEED_ADMIN_PASSWORD`** is still an open decision (carried from Phase 2): currently a
   random 24-character password printed once when the env var is absent.

---

## 11. How to reproduce

```bash
# data + server
PHP_WASM_PATH=/tmp/phpwasm/node_modules node docs/tools/php-cli.mjs tools/seed.php --demo
PHP_WASM_PATH=/tmp/phpwasm/node_modules node docs/tools/php-cli.mjs tools/checks/prepare.php
PHP_WASM_PATH=/tmp/phpwasm/node_modules node tools/dev-server.mjs --port 8080

# assets
node tools/build-assets.mjs && node tools/build-js.mjs

# style gates
node tools/checks/css-audit.mjs && node tools/checks/contrast.mjs \
  && node tools/checks/unstyled.mjs && node tools/checks/tokens.mjs

# browser gates — each starts its own dev server against its own database copy,
# so no password environment variable is needed (see lib/browser-flow.mjs)
node tools/checks/ui-shots.mjs
node tools/checks/a11y.mjs
node tools/checks/ui-login-shots.mjs
node tools/checks/calendar.mjs
node tools/checks/table-cards.mjs
```

Environment overrides: `VIRA_BASE_URL`, `VIRA_CHROMIUM`, `VIRA_CHROMIUM_LIBS`,
`VIRA_PLAYWRIGHT`, `VIRA_SHOTS_OUT`.
