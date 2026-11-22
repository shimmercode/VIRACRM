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
| G2 | **No Checkbox component.** `.v-checkbox` has 0 rules; nothing in the current 22 screens needs one. | The first screen that needs multi-select (bulk actions, permission matrix) will invent its own. | Low (until needed) |
| G3 | **No Spinner / loading indicator.** `.v-spinner` has 0 rules and no `aria-busy` usage anywhere. | Every page is server-rendered, so there is no in-page wait to indicate. Becomes necessary if any list becomes async. | Low (current architecture) |
| G4 | **`.v-tabs` exists (1 rule) but no screen uses it.** | Dead-ish CSS. Either a screen should use it or it should be removed rather than left as an untested component. | Low |
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
