# Final UX proposals — Login page, gold circle, mobile tables

**Status:** proposal, awaiting approval · **Brand colours:** `#450073` / `#D2AB67` unchanged
**Evidence:** `docs/phase-2/evidence/` · **Verified:** 2026-11-22

Three decisions the user asked to be settled. Each one states the problem, the measured
evidence, the recommendation, and what it would cost. Nothing here is implemented beyond the
mobile-table fix, which was measured, built and regression-tested because it was an active
defect rather than a matter of taste.

---

## 1. The Login page

### What it is now

A split layout: a brand panel carrying the VIRA CRM wordmark, the gold circle motif and a
short line of copy, beside a compact form (username, password, sign-in). On phones the panel
collapses and the form takes the full width.

Measured at four widths (`login-shots`, 24/24):

| Width | Layout | Smallest text | Body scroll |
|-------|--------|---------------|-------------|
| 390 | single column | 12px | none |
| 430 | single column | 12px | none |
| 768 | single column | 12px | none |
| 1440 | two column (brand + form) | 12px | none |

Evidence: `docs/phase-2/evidence/ui-audit/login-{phone-390,phone-430,tablet-768,desktop-1440}.png`

### The proposal

**Keep the split layout on desktop, keep the single column below 768px. Do not add anything.**

The reasoning is not aesthetic preference — it is about what the screen has to do:

1. **One action per screen.** The page has exactly one primary control. Adding "remember me",
   SSO buttons or a marketing block would dilute that, and the product has one authentication
   method. A sign-in screen that offers choices the system cannot honour is a defect, not a
   feature.
2. **No password recovery link.** The requirement has no self-service reset, and the superadmin
   provisions accounts. A "forgot password" link would be a promise that 404s. It is correctly
   absent.
3. **The brand panel earns its space.** It is the only screen a user sees before they trust the
   product. It carries the wordmark and the palette and nothing else — no stock imagery, no
   gradient mesh. On a phone it is the first thing dropped, because at 390px the form needs
   every pixel.
4. **The forced-change path is handled.** A bootstrap credential lands on `/password`, not the
   dashboard, and that screen reuses the same field components — so the transition from
   "first login" to "normal login" does not change visual grammar.

### What I recommend against

| Suggestion | Why not |
|------------|---------|
| Illustrated hero image | Adds weight (a login page should be the lightest page in the product) and a dependency that then has to be maintained in two locales. |
| "Remember me" | The session lifetime is a server decision (`config/session.php`); a checkbox that does not change it is theatre. |
| Social/SSO buttons | Not in the requirement, no identity provider configured. |
| Dark-mode variant | Not requested; doubles the surface that every contrast assertion must cover. |

**Cost of the proposal: zero. Nothing changes.** That is the point — the screen is measured
correct at every width and adds no unsupported affordances.

---

## 2. The gold circle

### What it is

A `#D2AB67` circular motif used as the brand accent on the login panel. Gold is the second
brand colour; it is never used for text on white (contrast would fail) and never for primary
actions (purple owns those).

### The proposal

**Keep it, in exactly its current role: decorative accent only, always `aria-hidden`.**

The rule that should govern every future use:

| Allowed | Not allowed |
|---------|-------------|
| Decorative shapes on marketing/empty states | Body text in gold on white — `#D2AB67` on `#FFFFFF` is ≈2.1:1, well below 4.5:1 |
| A ring or border accent on a dark purple ground | Gold as the only signal of state |
| Pairing with purple, where purple carries meaning | Gold text at small sizes anywhere |

When gold **must** carry text — for example a gold-tinted badge — the text uses `--vira-gold-ink`
(`#7A5A1D`), which is the accessible pair and is already in the token set. That substitution
is why the badge text passes contrast while still reading as gold.

**Cost of the proposal: zero.** No token, colour or position changes.

---

## 3. Mobile tables: horizontal scroll vs card view

This one was **measured, decided and implemented**, because the current behaviour was an
active defect rather than a design preference.

### The measurement (before)

At 390px, on `/customers` with 6 columns:

| Metric | Value |
|--------|-------|
| Wrapper width | 364px |
| Table width | 621px |
| **Hidden width** | **257px — 41%** |
| Columns fully visible | 3 of 6 (مشتری, نوع, راه تماس) |
| **Columns off-screen** | **وضعیت, قرارداد, عملیات** |
| Available rows without scrolling | 4 |
| Row height | 75px |

Evidence: `docs/phase-2/evidence/mobile-tables/before-customers-390.png` — the screenshot
shows the table cut off mid-column, with the **entire Actions column outside the viewport**.

That last row is the decisive fact. Under RTL the Actions column is the **leftmost**, so it is
the *farthest* thing from where the eye starts. A user must swipe the full table width — twice,
to find the control and to come back — before they can open or edit a record. On a list whose
whole purpose is opening records.

### Option A — horizontal scroll (the previous behaviour)

| | |
|---|---|
| **Pro** | Zero new code. Every column is available. Column alignment between rows is exact, so cross-row comparison is easy. |
| **Con** | Actions are unreachable without a two-way swipe. The user cannot see what the controls *are* until after gesturing. Nothing signals the table continues. Compared across rows means repeated swiping. |
| **Measured** | 41% hidden; 0 of 2 actions visible without a gesture. |

### Option B — card view (implemented)

| | |
|---|---|
| **Pro** | Every field and every action visible at once, no gesture. Each record is a self-contained object with a title, its fields, and its actions — matching how the user thinks about "a customer". Badges stay rendered as badges. Touch targets become full-width. |
| **Con** | Column alignment is lost, so comparing one field down ten rows is harder. More vertical space per record (7 records ≈ 2 screens). |
| **Measured** | **0px hidden; all 4 fields and both actions visible.** |

### Decision

**Card view below 768px; table at 768px and above.**

Justification: the screens are record-centric, not matrix-centric. A user on a phone is
looking for *one* customer to open, not comparing 7 × 6 values. Card view optimises the
dominant task; horizontal scroll optimises the rarer one and charges the dominant task a
two-way swipe for it.

Above 768px the table has room, comparison is the dominant task, and the table wins. The
breakpoint is where the six-column table stops fitting, not an arbitrary device width.

### What was actually built

| Item | Detail |
|------|--------|
| Markup | Unchanged. Still a real `<table>` with `<thead>`/`<th scope="col">`. |
| Enhancement | `resources/js/table-cards.js` builds `<ul class="v-cards">` from the live rows: cell 0 → card title (from the row's primary link), remaining `<th>` labels → field labels, StatusBadge → corner badge, Actions cell → a labelled action bar. |
| Fallback | If the script cannot read headers or rows, it does nothing and the scrollable table remains. With JavaScript off, the table remains. |
| A11y | `aria-hidden` is swapped between table and cards so a screen reader hears one, never both. |
| Desktop | Byte-identical rendering; `T-CARD-*-desktop` asserts the table is still a table. |
| Scope | `/customers` and `/contracts` — the only two list tables in the product. |

### Evidence

| Before | After |
|--------|-------|
| `mobile-tables/before-customers-390.png` | `mobile-tables/after-customers-390.png` |
| `mobile-tables/before-contracts-390.png` | `mobile-tables/after-contracts-390.png` |

### Regression gate

`tools/checks/table-cards.mjs` — **21/21**. It asserts cards exist, cards contain *labelled
fields* (not just titles), overflow is ≤ 0px, actions are reachable, the desktop table is
untouched, and the no-JS path still loads. The gate was validated by removing the fix: it
failed with `0 labelled field(s)`, exactly as intended — the first bug it caught was mine.

---

## 4. Summary of what needs approval

| # | Decision | Recommendation | Changes brand? |
|---|----------|----------------|----------------|
| 1 | Login page | Keep as-is (split desktop / single column mobile) | No |
| 2 | Gold circle | Keep as decorative accent only; gold-ink for any gold text | No |
| 3 | Mobile tables | Card view < 768px, table ≥ 768px — **implemented** | No |

Only #3 changes the product. #1 and #2 confirm current behaviour, which is why they cost
nothing — and why the answer to "should we redesign the login page" is "no, and here is the
measurement that says it already works at four widths".
