# Design System — coverage against REQ-11

**Requirement group:** REQ-11 (الزامات رابط کاربری و تجربه استفاده), source lines 225–236
**Catalog entries:** `docs/requirements/requirements.catalog.yaml` → REQ-11.01 … REQ-11.05
**Assessed:** 2026-11-22, against the running build, not against the documentation
**Brand colours:** `#450073` and `#D2AB67` unchanged, as instructed.

This document answers one question: **which parts of the Design System requirement are
actually implemented, and which are not yet.** Every "Covered" line names the file that
proves it. Nothing here is claimed on the strength of a planning document.

---

## 1. The twelve components REQ-11.02 names

REQ-11.02 requires: colour, typography, Button, Input, Select, Modal, Table, Badge, Alert,
Card, Navigation, Sidebar, Header, Form Validation — "with identical behaviour on every
page".

| # | Component | Status | Evidence | Note |
|---|-----------|--------|----------|------|
| 1 | Colour | **Covered** | `resources/css/tokens.css` | Full token set; `#450073` primary, `#D2AB67` gold, ink, canvas, surface, borders, status. 42/42 token-discipline assertions pass (`css-audit`). |
| 2 | Typography | **Covered** | `tokens.css` (`--vira-text-*`), `public/build/fonts/` | 10-step scale, Vazirmatn self-hosted (OFL), no external font host. |
| 3 | Button | **Covered** | `components.css` `.v-btn` (19 rules) | primary / secondary / ghost / danger, sm/md, icon-only, disabled, focus ring. |
| 4 | Input | **Covered** | `components.css` `.v-input`, `.v-field` | Text, password with toggle, `dir="ltr"` for phone/email, error + hint wiring. |
| 5 | Select | **Covered** | `.v-select` (6 rules) | Native select styled to the system; `--sm` variant for filter bars. |
| 6 | Modal | **Covered** | `.v-modal` (6 rules), `.v-drawer__panel` | Focus trapped, Escape closes, focus restored — proven by `T-DRAWER-FOCUS/TRAP/ESCAPE`. |
| 7 | Table | **Covered** | `.v-table` (27 rules) | Sticky header, sortable headers preserving filters, numeric alignment, RTL-correct. Now also responsive: card view < 768px. |
| 8 | Badge | **Covered** | `components/badge.blade.php`, `status-badge.blade.php`, `.v-badge` | Semantic status per domain (customer/contract/project), icon + text, not colour alone. |
| 9 | Alert | **Covered** | `components/alert.blade.php`, `.v-alert` (14 rules) | Fixed earlier defect: dropped unknown attributes until `$attributes` was merged. `role="alert"` live region verified (`T-ALERT-LIVE`). |
| 10 | Card | **Covered** | `.v-card` (31 rules) | Header/body/footer, `--narrow`, overflow clipping for scrollers. |
| 11 | Navigation | **Covered** | `components/nav.blade.php`, `.v-drawer`, `.v-sidebar` | Sidebar rail ≥1024px, drawer below; sign-out reachable at 390/768/1440 (`T-LOGOUT-*`). |
| 12 | Sidebar / Header | **Covered** | `.v-topbar` (14 rules), `.v-sidebar` (11 rules), `components/topbar.blade.php` | Sticky header, independent sidebar scroll, collapse behaviour. |
| — | Form Validation | **Covered** | `_form.blade.php`, `.v-field__error` | `aria-invalid` + `aria-describedby` on every field, error summary focused on re-render (`T-ERROR-SUMMARY`, `T-FIELD-INVALID`). |

**Result: 12 of 12 named components exist and are wired into real pages.**

---

## 2. What is *not* yet covered

These are honest gaps. None of them blocks the current screens; all of them would matter as
the product grows.

| # | Gap | Why it matters | Severity |
|---|-----|----------------|----------|
| G1 | **No Tooltip component.** `.v-tooltip` has 0 rules; hover reveals use the native `title` attribute. | Native `title` is not keyboard-reachable and takes ~1s to appear. Icon-only buttons on desktop rely on it. Mitigated on touch: card view adds visible text labels. | Medium |
| ~~G2~~ | **CORRECTED — a Checkbox component does exist.** It is `.v-check` (6 rules, `components.css:246`), used on the login screen ("مرا به خاطر بسپار") and in the duplicate-customer confirmation. My earlier claim that `.v-checkbox` had 0 rules was true but irrelevant: I checked the wrong class name and drew a conclusion from it. | — | **Closed, was a false gap.** |
| ~~G3~~ | **CORRECTED AND CLOSED — implemented this phase.** `app.js` set `.is-loading` on the submit button but **no CSS rule defined it**, so a submitted form looked idle while the request was in flight. `REQ-11.05` names Loading as one of four required states, so this was a real gap. Now: `.v-btn.is-loading` ring, `aria-busy` on the form, and a `role="status"` announcement. `prefers-reduced-motion` swaps the rotation for a pulse. | — | **Closed.** |
| ~~G4~~ | **CLOSED — removed.** Proof of zero dependency was gathered first: 0 references in `resources/views/`, 0 in `resources/js/`, and the only PHP references were this matrix's own assertion. The block was then removed (1160 bytes), with a guard that every selector in it began `.v-tab*` and that nothing outside it referenced those selectors. `design-system.mjs` now asserts both the markup **and** the stylesheet rule are absent, so it cannot return unnoticed. | — | **Closed.** |

### Filter chips (added this phase)

The filter bar kept state in the querystring with no visible representation, so a user
arriving with filters set could not see what was filtered without scrolling back to three
selects. Worse, on a phone those selects can be off-screen entirely while the results below
are filtered. `components/filter-chips.blade.php` renders one removable pill per active
filter; each is an `<a>` whose `href` drops exactly that one parameter and preserves every
other filter plus the sort. Verified by `filter-chips.mjs` (22/22).
| G5 | **Skeleton loading state absent.** `.v-skeleton` has 3 rules but no view uses it. | Same reason as G3 — nothing loads client-side yet. | Low |
| G6 | **No documented component contract.** Each component works, but there is no single page stating "a `.v-btn--primary` is used for exactly one action per view", etc. | This is what keeps a design system from drifting. It is documentation, not code. | Medium |

**Loading / Empty / Success / Error** (REQ-11.05): Empty and Error are covered
(`x-empty-state`, alert + field errors). Success is covered for the flows that exist (flash
alerts). **Loading is the one state with no implementation** — see G3/G5, and it is
defensible only because the architecture is server-rendered end to end.

---

## 3. Requirement-by-requirement status

| Requirement | Status | Evidence / gap |
|-------------|--------|----------------|
| **REQ-11.01** Persian RTL, responsive, desktop-first, no raw template | **Covered** | `dir="rtl"` shell, 6-width layout suite (7 pages × 6 widths = 178 assertions), logical properties throughout, no third-party template. |
| **REQ-11.02** Shared Design System, identical behaviour everywhere | **Covered** | §1 — 12/12 components. Token discipline enforced by `css-audit` (42/42), so a page cannot silently invent a colour. |
| **REQ-11.03** Vazirmatn self-hosted, licensed | **Covered** | `public/build/fonts/`, OFL, no external host. |
| **REQ-11.04** Jalali input/display, standard storage | **Covered** | `calendar` suite passes: input masked, display Jalali-only, storage Gregorian, no day-shift at boundaries. |
| **REQ-11.05** Loading/Empty/Success/Error + understandable errors | **Partly covered** | Empty/Success/Error covered with assertions. **Loading absent** (G3/G5) — no async surface exists to attach it to. |

---

## 4. What changed in this phase

1. **Responsive table → card view** (`resources/js/table-cards.js`, `components.css`).
   Covered a real, measured defect: at 390px the table hid 41% of its width including the
   entire Actions column. Now 0px overflow, all fields and actions visible, no horizontal
   swipe. Desktop unchanged (`T-CARD-*-desktop`), no-JS keeps the scrollable table.
2. **Tooltip gap partially mitigated**: card actions carry visible text ("مشاهده"/"ویرایش")
   instead of relying on a hover tooltip that touch has no access to.

Nothing else in the Design System was altered. **No colour, type scale, spacing or radius
token was changed**, and `#450073` / `#D2AB67` are untouched — `css-audit` confirms.

---

## 5. Recommendation

The Design System is **complete for the 22 screens that exist** and **incomplete as a system**
in three respects that only cost when the product grows: Tooltip (G1), Checkbox (G2), and a
written component contract (G6). Loading/skeleton (G3/G5) should be built with the first
async surface, not before — building them now would be untested code on a server-rendered
app.

None of this requires changing the brand. All three gaps are additive.
