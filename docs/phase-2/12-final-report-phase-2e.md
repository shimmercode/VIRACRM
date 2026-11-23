# Final report — phase 2-E

**Date:** 2026-11-22 · **Branch:** `arena/3b43de84-viracrm` · **Approval:** still withheld by the user

Every number below came from running the thing described. Where something is unfinished,
it is listed as unfinished.

---

## 1. Commit and branch

| | |
|---|---|
| Branch | `arena/3b43de84-viracrm` (active) |
| Previous phase | `ac01e5e` (docs: final report for the six priorities) |
| This phase | see `git log -1` after the commit |
| Pushed | `origin/arena/3b43de84-viracrm` |
| Working tree | clean |

---

## 2. Git status

`git status --short` is empty after the commit. Nothing staged, nothing ahead of origin.

---

## 3. Regression suite — the structural fix

### What was wrong

`run-all.mjs` printed `REGRESSION SUITE PASSED` and **exited 0** while `--fast` skipped five
browser suites. A skip was invisible in the verdict.

### What it does now

| Requirement | Implementation | Proven |
|-------------|----------------|--------|
| Full run is the default | no flag = all 18 suites | `18 total · 18 executed` |
| No required test skipped without declaration | every suite is `required: true`; `--fast` is the only skip, it is global and printed | `SKIP … (--fast: explicitly skipped)` |
| Executed / Passed / Failed / Skipped always reported | one summary line, all four numbers, every run | `432 executed · 432 passed · 0 failed · 0 skipped` |
| Any unrun required suite ⇒ not PASSED | verdict becomes `INCOMPLETE` | tested |
| Non-zero exit when a required test is skipped | `exit 2` | tested: `EXIT: 2` |
| Two consecutive full runs identical | `--repeat 2` compares per-suite totals | `pass 2 matches pass 1` |

**Exit codes** are now three-way and each was verified by breaking something on purpose:

| Code | Meaning | How it was proven |
|------|---------|-------------------|
| `0` | PASSED | full run, 432/432 |
| `1` | FAILED | injected a failure into `tokens.mjs` → `EXIT with genuine failure: 1` |
| `2` | INCOMPLETE | `--fast` → `EXIT: 2` |

### Two consecutive full runs

```
suites     18 total · 18 executed · 18 passed · 0 failed · 0 skipped
assertions 432 executed · 432 passed · 0 failed · 0 skipped
isolation  shared database untouched
repeatable yes
REGRESSION SUITE PASSED
exit 0
```

Shared database row counts identical across the whole run (`sessions 217 → 217`,
`audit_logs 218 → 218`).

### Suite inventory (18)

unit · views · authorization(PHP) · flow-login 8/8 · flow-authed 20/20 · flow-authz 11/11 ·
flow-crud 18/18 · css-audit 42/42 · contrast · unstyled · tokens · calendar · a11y 10/10 ·
login-shots 24/24 · ui-shots 178/178 · table-cards 21/21 · **overflow 44/44** (new) ·
**design-system 20/20** (new)

---

## 4. Admin credential delivery — the real arrangement

Full runbook: `docs/phase-2/11-install-credential-runbook.md`

An install was **simulated step by step** on a throwaway database:

```
STEP 1  operator generates the secret
        mode: 0600 · length: 24 chars · on disk, not yet consumed
STEP 2  seeder runs
        account created: yes
        must_change_password: 1 · is_superadmin: 1 · hash: $2y$12$
STEP 3  file consumed? exists after seeding: no — deleted
STEP 4  interrupted install: stale file is inert; nothing reads it until the seeder runs
```

Answers to each question asked:

| Question | Answer |
|----------|--------|
| How is it generated? | `random_bytes(18)` → ~144 bits, or operator-supplied file/env |
| How does the admin get it? | They write it (path 1) or it is printed once (path 3) |
| Where does the file live? | `/run/secrets/admin_pw` recommended (RAM, not disk) |
| Permissions? | `0600` under `umask 077` — measured `mode: 0600` |
| When deleted? | Immediately after reading, before the account is created |
| Install interrupted? | File is inert; re-running consumes it once and deletes it |
| Weak/wrong password? | **Refused before any account is created** — 3 cases executed, `admin rows created: 0` each |
| Forced change? | **Yes**, every path; `T-AUTH-12` shows the 302 to `/password` |

**Recovery** was the gap with no answer before. `UserSeeder` refuses to touch an existing
admin, so a lost credential had no supported path. Now:

```bash
php artisan vira:reset-admin-password --generate
php artisan vira:reset-admin-password --password-file=/run/secrets/admin_pw
```

Verified: `before must_change: 0` → reset → `after must_change: 1`, exit 0, file deleted,
**secret does not appear in the output** for the file path (`secret appears in output: no`).

No password appears in this report, in Git, in a screenshot, or in any recorded output.

---

## 5. The 1024px overflow — fixed

**Cause, measured:** `.v-table` had `min-width: 720px`. At 1024px the sidebar is 264px and
`.v-content` has 28px padding a side, leaving **687px**. The table's natural width is exactly
687px — the *floor*, not the content, caused the overflow: `720 − 687 = 33`.

**Fix:** `min-width: min(720px, 100%)` — the floor yields to the space that exists.

| Width | Before | After |
|-------|--------|-------|
| 768 | 0 | 0 (table) |
| **1024** | **33px** | **0** |
| 1280 | 0 | 0 |
| 1440 | 0 | 0 |
| 390 | 0 (cards) | 0 (cards — unchanged) |

Gate: `tools/checks/overflow.mjs`, **44/44**, validated by restoring the bug — it failed with
`table wrapper overflow 33px`, and only at 1024px.

Screenshots: `evidence/overflow-1024/before-*.png` / `after-*.png` at 768, 1024 and 1440.

**Card view below 768px was not broken** — asserted in the same suite
(`T-OVF-*-390-cards`: 7 and 5 cards, table `display: none`, 0px overflow).

---

## 6. Design reference research — actually done this time

Full report: `docs/phase-2/09-design-reference-research.md`

**Three real products inspected with visible evidence:**

| Product | What was seen |
|---------|---------------|
| **Attio** | Sidebar IA, record list (`Company · ICP Score · Owner`), dashboard with KPI cards + funnel + trend + bar + donut |
| **Linear** | Grouped list with counted headers (`Backlog 8`, `Todo 71`, `In Progress 3`, `Done 53`) |
| **Close CRM** | Dense lead table, filter chips with `×`, **actions revealed only on hover at the row's far end** |

Plus two catalogue pages (saasui.design: 115 CRM screens, 160 table screens) for pattern frequency.

**Screenshots kept:** `evidence/design-system/attio-crm-table-view-dashboard-screensho-1.webp`,
`linear-app-issues-list-table-view-dark-s-1.webp`, `linear-app-issues-list-table-view-dark-s-2.png`.

**Key finding, and it is mostly negative:**

- Close CRM's hover-revealed row actions are the industry default *and* the exact cause of the
  mobile defect this project fixed. Fine with a mouse; unusable on touch, and worse in RTL
  because the actions column is at the far end of the reading direction. **Rejected for Vira.**
- Attio's chart-heavy dashboard would bury the overdue-reminder list that REQ-11.05 makes the
  dashboard's job. **Rejected.**
- Linear's grouped counted headers are a genuine improvement — but a **behaviour change** to a
  list that currently passes 178 assertions. **Documented as a candidate, not implemented.**
- Close's filter chips (removable pills) are worth adopting — same reason: behaviour change,
  awaits approval.

Not usable as evidence and therefore not cited: mobbin, dribbble, behance (app shells /
infinite feeds return no inspectable structure).

---

## 7. Design System — six items decided

Full contract: `docs/phase-2/10-component-contract.md`

| Item | In requirements? | Decision | Implemented | Tested | RTL |
|------|------------------|----------|-------------|--------|-----|
| **Tooltip** | Not named, but `title=` was the only affordance on 12 icon buttons (mouse-only) | **Implemented** — CSS-only `data-tooltip`, works with JS off | yes | 6 assertions | logical props + mirrored `translateX` |
| **Spinner** | **Yes — REQ-11.05 "Loading"** | **Implemented** — `.v-btn.is-loading` was set by JS but had *no CSS* | yes | 6 assertions | inherits button, direction-neutral |
| **Checkbox** | Used on 2 screens | **Already existed** as `.v-check` — my earlier "no checkbox" claim was a **false gap** | was already there | 4 assertions | native, RTL-safe |
| **Tabs** | **No** — no requirement, no screen | **Not needed.** Dead `.v-tabs` rule; recommendation is to remove it | no | asserts it stays unused | n/a |
| **Skeleton** | Not named | **Not needed.** Server-rendered app: no client-side wait to fill | no | asserts it stays unused | n/a |
| **Component contract** | — | **Written** | n/a | — | includes RTL rules |

### Two corrections to my previous report

1. **Checkbox was never missing.** I grepped `.v-checkbox` and concluded from zero matches
   for a class that does not exist. The component is `.v-check` and has 6 rules.
2. **"Remember me" is implemented.** I wrote that it was not, "because the session lifetime is
   a server decision". `LoginController.php:75` passes it to `Auth::attempt()`. Both errors are
   corrected in `06-design-system-gap-matrix.md`.

### A real defect found while doing this

The login form was the **only** form without `data-guard`, so the most-used submit in the
product showed no busy state at all. It now has one, with `aria-busy`, a `role="status"`
announcement, and `prefers-reduced-motion` support.

---

## 8. Before / after screenshots

| Change | Before | After |
|--------|--------|-------|
| 1024px overflow | `evidence/overflow-1024/before-customers-1024.png` | `evidence/overflow-1024/after-customers-1024.png` |
| 1024px overflow (contracts) | `before-contracts-1024.png` | `after-contracts-1024.png` |
| 768 / 1440 regression | `before-*-{768,1440}.png` | `after-*-{768,1440}.png` |
| Login loading state | (was invisible — no CSS) | `evidence/design-system/login-loading-form-detail.png` |
| Login checkbox | `login-after-checkbox-1440.png` | (unchanged component, now documented) |
| Tooltip | `title=` only, mouse-only | `evidence/design-system/tooltip-hover-row.png` |
| Mobile tables (previous phase) | `evidence/mobile-tables/before-*-390.png` | `after-*-390.png` |

---

## 9. Design System status

12/12 components named by REQ-11.02 exist. Of the six previously-listed gaps: **two were
false** (Checkbox existed; Spinner's class existed but was unstyled — that part was real),
**two are now implemented and tested** (Tooltip, Spinner), **two are documented as not
required** (Tabs, Skeleton), and **the contract is written**.

Brand colours `#450073` and `#D2AB67` are unchanged. `css-audit` 42/42, `tokens` 0 undefined.

---

## 10. Known limitations

1. **Verification is PHP-WASM, not the customer's host.** No native PHP here. Behaviour under
   real `php-fpm`/Apache is not proven in this environment.
2. **`--fast` still exists.** It now exits 2 and prints INCOMPLETE, but a CI job that ignored
   exit codes could still misread it. CI must check the exit code, not the word.
3. **The `--repeat` comparison is per-suite totals, not per-assertion.** Two runs could in
   principle produce the same totals with different individual assertions passing.
4. **Recovery requires shell access.** `vira:reset-admin-password` is deliberately not an HTTP
   route; a host with no shell has no supported recovery.
5. **No password complexity rule beyond length**, and no breach-corpus check. Length alone is
   enforced (12 chars).
6. **The reset command has no rate limit.** It needs shell access, which is already database-level
   trust.
7. **Card view loses column alignment** — comparing one field down ten rows is harder on a phone.
   Accepted trade-off, documented.
8. **`getenv()` never works under WASM.** Any future config read must use `$_ENV`/`$_SERVER` or
   it fails silently — the exact bug fixed in `UserSeeder`.
9. **mobbin / dribbble / behance were not inspectable.** Not cited as evidence.
10. **`.arena/flows/` accumulates** if a suite crashes before `close()`. Git-ignored, safe to
    delete, not cleaned automatically.
11. **`is-loading` leaves the button label hidden** for the whole request. If a request takes
    very long the user sees only a ring. Acceptable, but it is a real trade-off.

---

## 11. Nothing awaiting a backend change

No backend, database, contract, permission or project-structure change was made for this phase
except **one new artisan command** (`vira:reset-admin-password`), which was required to answer
the user's own question about recovering the initial credential.

Awaiting the user's decision:

1. **Filter chips** and **grouped list headers** — behaviour changes, not implemented.
2. **Remove the dead `.v-tabs` rule** — recommended.
3. **Approval** of this phase.
