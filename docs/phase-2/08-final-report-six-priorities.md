# Final report — the six priorities

**Date:** 2026-11-22 · **Branch:** `arena/3b43de84-viracrm` · **HEAD:** `90afcec`
**Base:** `309eabf` (main) · **Previous phase:** `baeb213`
**Approval status:** UI/UX and Production approval still **withheld by the user** — this
report does not claim sign-off.

Every figure below was produced by running the thing described. Where something is not
finished, it is listed as unfinished.

---

## 1. Commit and branch

| | |
|---|---|
| Branch | `arena/3b43de84-viracrm` (active) |
| Head commit | `90afcec` — `test: isolate every suite, close the seed-password hole, add card view` |
| Parent | `baeb213` — `feat(ui): design system, UI/UX audit and accessibility fixes` |
| Pushed | yes, `origin/arena/3b43de84-viracrm` |
| Working tree | clean (0 uncommitted changes) |

---

## 2. Git status

```
$ git status --short
(no output)
$ git status -sb
## arena/3b43de84-viracrm
```

Confirmed clean, nothing staged, nothing ahead. `public/build/` is git-ignored by the
project's existing convention, so compiled CSS/JS ships as build output rather than as
committed artifacts — the source files (`resources/css/*`, `resources/js/*`) are what the
commit contains.

---

## 3. Full regression suite

### Result

```
$ node tools/checks/run-all.mjs --repeat 2

suites:     16 executed, 0 skipped
assertions: 368/368
failed:     none
isolation:  shared database untouched
repeatable: yes
```

### Isolation proof — the gate that started this work

Row counts of the **shared** database, before and after a complete two-pass run:

| Table | Before | After | |
|-------|--------|-------|---|
| customers | 7 | 7 | ok |
| contracts | 5 | 5 | ok |
| projects | 3 | 3 | ok |
| work_items | 9 | 9 | ok |
| users | 2 | 2 | ok |
| **sessions** | 199 | 199 | ok |
| **audit_logs** | 203 | 203 | ok |

Before this phase, `sessions` and `audit_logs` grew on every run (`158 → 170`,
`176 → 184`). The HTTP-level assertions all passed while this was happening — only the
row-count comparison exposed it, which is why that gate is kept.

### Repeatability

Two consecutive passes produced **identical** suite totals. No suite depends on the order
it runs in.

### Suite breakdown

| Suite | Result |
|-------|--------|
| unit + feature | 7/7 (395 underlying assertions) |
| views (Blade compilation) | 29/29 |
| authorization (PHP) | OK |
| flow: login | 8/8 |
| flow: authenticated | 20/20 |
| flow: authorization | 11/11 |
| flow: CRUD | 18/18 |
| css-audit (token discipline) | 42/42 |
| contrast | OK |
| unstyled classes | OK |
| undefined tokens | OK |
| calendar (Jalali) | OK |
| a11y | 10/10 |
| login-shots | 24/24 |
| ui-shots (7 pages × 6 widths) | 178/178 |
| **table-cards (new)** | **21/21** |

### How isolation works

Each flow gets its own database copy under `.arena/flows/<name>/`, its own cookie jar, and a
password generated at run time. The seam is an `env` map on the renderer spec, applied in
`tools/http-render.php` **before** `bootstrap/app.php`, which beats `.env` because Laravel's
`LoadEnvironmentVariables` uses the *immutable* dotenv repository — it only writes keys that
are absent from `$_ENV`/`$_SERVER`. Browser suites start a private `tools/dev-server.mjs` on
an OS-assigned port with `DB_DATABASE` in its environment.

---

## 4. `SEED_ADMIN_PASSWORD` status

**Decided and implemented.** Full policy: `docs/phase-1/22-bootstrap-credential-policy.md`

| Question | Answer |
|----------|--------|
| How is it set? | Three paths in order: `SEED_ADMIN_PASSWORD_FILE` (path to a secret file), `SEED_ADMIN_PASSWORD` (env), or nothing (random, printed once). |
| How is it delivered securely? | The file path is preferred: never on a command line, never in a process listing. The seeder **deletes the file** after reading it. |
| How is first-login change enforced? | `must_change_password = true` on every path; the `password.settled` middleware redirects every route to `/password` until changed. |
| Is it in the repository? | No. Also removed from `prepare.php`, `authz.php`, `pages.mjs`, `check403.mjs` and two report documents. |
| Minimum strength? | 12 characters for operator-supplied secrets; below that the seeder throws **before** creating the account. |

### A real defect found and fixed

The seeder previously used `getenv()` only. Verified directly:

```
SEED_ADMIN_PASSWORD='Test-Value-123' … → getenv: false
```

An operator who set the variable got a **random** password instead — silently. The seeder
now reads `$_ENV`, then `$_SERVER`, then `getenv()`, and reports which source it used.

### Verified paths

| Path | Result |
|------|--------|
| generated | account created, bcrypt `$2y$12$`, `must_change_password=1`, `is_superadmin=1` |
| `SEED_ADMIN_PASSWORD_FILE` | account created; file **deleted** afterwards |
| weak secret (5 chars) | refused: *"must be at least 12 characters; refusing to create a weak superadmin"*, **no account created** |

---

## 5. Final Login decision

**Keep the current design. Nothing changes.**

Measured at 390 / 430 / 768 / 1440: single column below 768, two column above, no page
scroll, 12px minimum text, one primary action. 24/24 assertions.

Deliberate absences, each for a reason: no "remember me" (session lifetime is a server
decision), no self-service reset (no such requirement; the superadmin provisions accounts),
no SSO buttons (no identity provider). A control the system cannot honour is a defect.

Evidence: `docs/phase-2/evidence/ui-audit/login-{phone-390,phone-430,tablet-768,desktop-1440}.png`

---

## 6. Final Mobile Tables decision

**Card view below 768px; table at 768px and above.** Implemented and tested.

### The measurement that decided it

At 390px, `/customers`, 6 columns:

| | Horizontal scroll (before) | Card view (after) |
|---|---|---|
| Hidden width | **257px (41%)** | **0px** |
| Columns visible | 3 of 6 | all fields |
| **Actions visible without a gesture** | **0** | **2** |
| Page overflow | 0 | 0 |

Under RTL the Actions column is the leftmost — the furthest thing from where the eye starts.
A user had to swipe the full table width before they could open a record, on a list whose
entire purpose is opening records.

Above 768px the table returns, because comparison across rows is the dominant task there and
the table wins it.

### Why card view over horizontal scroll

The screens are record-centric, not matrix-centric. Someone on a phone wants *one* customer,
not 7 × 6 values side by side. Scrolling optimises the rarer task and charges the common one
a two-way swipe.

### What was built

| Item | Detail |
|------|--------|
| Markup | Unchanged — still a real `<table>` with `<th scope="col">` |
| Enhancement | `resources/js/table-cards.js` builds cards from the live rows |
| Fallback | No-JS and any unexpected DOM → the scrollable table remains |
| Screen readers | `aria-hidden` swapped so one representation is announced, never both |
| Scope | `/customers` and `/contracts` — the only list tables in the product |

### Before / after

| Before | After |
|--------|-------|
| `evidence/mobile-tables/before-customers-390.png` | `evidence/mobile-tables/after-customers-390.png` |
| `evidence/mobile-tables/before-contracts-390.png` | `evidence/mobile-tables/after-contracts-390.png` |
| — | `after-customers-430-full.png`, `after-contracts-430-full.png` |
| — | `after-customers-768-table.png` (table returns at 768) |

Gate: `tools/checks/table-cards.mjs`, 21/21, **validated by removing the fix** (it failed
with `0 labelled field(s)`, catching the bug I had introduced).

---

## 7. Design System status

Full matrix: `docs/phase-2/06-design-system-gap-matrix.md`

**12 of 12 components that REQ-11.02 names exist and are wired into real pages**: colour,
typography, button, input, select, modal, table, badge, alert, card, navigation,
sidebar/header — plus form validation with `aria-invalid` + `aria-describedby`.

| Requirement | Status |
|-------------|--------|
| REQ-11.01 Persian RTL responsive | Covered |
| REQ-11.02 Shared Design System | Covered (12/12) |
| REQ-11.03 Vazirmatn self-hosted | Covered |
| REQ-11.04 Jalali input/display/storage | Covered |
| REQ-11.05 Loading/Empty/Success/Error | **Partly** — Loading absent |

**Gaps (honest):**

| # | Gap | Severity |
|---|-----|----------|
| G1 | No Tooltip component (native `title` only) | Medium |
| G2 | No Checkbox component | Low (nothing needs one yet) |
| G3 | No Spinner / `aria-busy` | Low (server-rendered) |
| G4 | `.v-tabs` exists but unused | Low |
| G5 | `.v-skeleton` exists but unused | Low |
| G6 | No written component contract | Medium |

**Brand colours unchanged:** `#450073` primary and `#D2AB67` gold. `css-audit` 42/42 and
`tokens` (0 undefined) both confirm no token was invented or altered.

---

## 8. Remaining items

| # | Item | Blocked by |
|---|------|------------|
| 1 | Login page redesign | **Nothing** — recommendation is to keep it; awaiting the user's decision |
| 2 | Gold circle | As above |
| 3 | Design System G1 (Tooltip), G6 (contract) | Needs approval to add components |
| 4 | Loading state (G3/G5) | Should be built with the first async surface, not before |
| 5 | Password complexity rule / breach check | Needs a product decision; length is enforced now |
| 6 | Reference-product pattern review (§1 of the original brief) | **Not done** — attio/linear/monday/saasframe/saasui/mobbin/dribbble/behance were never fetched |
| 7 | Final UI/UX + Production approval | The user |

---

## 9. Known limitations

Stated plainly; none of these is hidden behind a passing test.

1. **Verification is PHP-WASM, not the customer's host.** The sandbox has no native PHP. Unit
   tests, flows and renderer all run under php-wasm 8.3. Behaviour on the target host with
   real `php-fpm`/Apache is not proven here.
2. **The browser suites skip when Playwright is unavailable.** `run-all.mjs` reports
   `skipped`, not `passed` — but a run that skips 4 suites still prints PASSED, so the
   skipped count has to be read, not the colour.
3. **A 33px horizontal overflow exists at exactly 1024px** on `/customers`. It is
   **pre-existing**, not introduced here — verified by disabling the new CSS and re-measuring.
   It is inside the wrapper, does not affect the page, and is not covered by any assertion.
4. **Card view loses column alignment.** Comparing one field down ten records is harder on a
   phone than in a table. Accepted, because that is the rarer task.
5. **`getenv()` never works under WASM**, so any future code that reads config must use
   `$_ENV`/`$_SERVER` or it will fail silently — the exact bug fixed in `UserSeeder`.
6. **Test passwords are generated, so a failed run cannot be re-entered by hand.** Re-run the
   flow, or use `prepare.php` + `VIRA_SHOW_SECRET=1` for interactive work.
7. **`.arena/flows/` accumulates** if a suite crashes before `close()`. It is git-ignored and
   safe to delete; it is not cleaned automatically on hard failure.
8. **No test asserts the 1024px case**, which is why limitation 3 has survived this long.

---

## 10. What the user should decide

1. **Login page** — accept "no change", or ask for a specific change.
2. **Gold circle** — accept "decorative only, gold-ink for text".
3. **Mobile tables** — accept card view as implemented, or ask for the scroll variant.
4. **Design System** — whether to build G1 (Tooltip) and G6 (component contract) now.
5. **Approval** — UI/UX and Production remain withheld; this report is evidence, not a
   request for sign-off on anything not listed above.
