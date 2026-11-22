# Vira CRM — Design System Master

> **Source of truth.** Every screen in this project is built against this document.
> If a page has an override in `pages/<page>.md`, that override wins. Otherwise this
> file wins. A screen that deviates without a recorded reason is a defect.

**Project:** Vira CRM · **Audience:** Iranian B2B agencies and service firms ·
**Language:** Persian, RTL · **Mode:** Light (primary) ·
**Stack:** Laravel 12 + Blade + hand-authored CSS (no Tailwind, no Vite) ·
**Last updated:** 2026-11-22

---

## 1. How this document was produced

Three inputs, kept deliberately separate so the brand is never overwritten by a
generator's default:

| Input | What it contributed | Where it landed |
|-------|--------------------|-----------------|
| **UI UX Pro Max** (`nextlevelbuilder/ui-ux-pro-max-skill`, installed at `.agents/skills/ui-ux-pro-max`) | Style selection, UX rules, Laravel stack guidance, RTL risk assessment, anti-pattern list | This file + `ux-guidelines.md` |
| **The Vira brand palette** (mandated by the brief) | The actual colours and typeface | §2 — **unmodified** |
| **Reference products** (Attio, Linear, Monday) | Structural patterns only — never visual copying | §4, §6 |

The skill's raw output is preserved verbatim in `_generated/MASTER.skill-generated.md`
so the difference between *recommended* and *decided* stays auditable.

### 1.1 Where the generated system was overridden, and why

The skill's `--design-system` run proposed a **navy + blue** palette (`#0F172A`,
`#0369A1`) with **Plus Jakarta Sans**, and its Persian typography pairing suggested
**Noto Naskh / Noto Sans Arabic**. None of those were adopted, for reasons recorded here:

| Generated proposal | Decision | Reason |
|--------------------|----------|--------|
| Primary `#0F172A` (navy) | **Rejected** | Vira's identity is `#450073` purple. Changing the brand colour requires the owner's approval, which has not been given. |
| Accent `#0369A1` (blue) | **Rejected** | Introduces a second competing hue; the brief restricts gold to accents only. |
| Background `#F8FAFC`, border `#E2E8F0` | **Rejected** | Near-identical to the mandated `#F6F5F8` / `#E6E2EA`; keeping the brand set avoids a second slate ramp. |
| Plus Jakarta Sans | **Rejected** | Latin-only; does not cover Persian glyphs at all. |
| Noto Sans Arabic | **Rejected** | Technically valid for Persian, but Vazirmatn is mandated and is the better Persian UI face (better digit shapes, self-hosted). |
| Density 8/10 (8–32px scale) | **Adopted in spirit** | Vira's space scale is 4–48px with 12/16 as the working defaults — dense but not cramped. |
| Style: Minimalism & Swiss (`rtl_level: full`) | **Adopted** | Verified RTL-safe by the skill itself. Matches the product: enterprise, data-dense, restrained. |
| Anti-patterns (no decorative gradients, no emoji icons, visible focus, 4.5:1) | **Adopted wholesale** | Consistent with the brief and with WCAG AA. |

### 1.2 A finding worth recording

The skill flags its own **"CRM & Client Management" product row as `rtl_level: caveats`** —
meaning that pattern encodes reading direction. For a strict Persian RTL build the skill
instructs the caller to *not* use it and propose a mirrorable alternative. Vira therefore
takes its structure from the `rtl_level: full` Minimalism/Swiss row and its information
architecture from the product's own requirements, not from the CRM template. This is the
single most consequential decision in this document.

---

## 2. Colour

Brand tokens only. Every value below is defined in `resources/css/tokens.css` and used by
name — no raw hex appears in a component.

### 2.1 Core

| Token | Value | Role |
|-------|-------|------|
| `--vira-primary` | `#450073` | Brand, primary action, active state |
| `--vira-primary-hover` | `#5A0095` | Hover |
| `--vira-primary-active` | `#360058` | Pressed |
| `--vira-primary-soft` | `#F3E9FA` | Selected row, soft badge |
| `--vira-primary-border` | `#DCC3EE` | Border on soft surfaces |
| `--vira-ink` | `#211B2B` | Primary text |
| `--vira-bg` | `#F6F5F8` | Page canvas |
| `--vira-surface` | `#FFFFFF` | Cards, forms, panels |
| `--vira-gold` | `#D2AB67` | Accent — limited use only |
| `--vira-gold-ink` | `#7A5A1D` | Gold-family text (5.80:1 on gold-soft) |

### 2.2 Text

| Token | Value | Contrast on `#FFFFFF` |
|-------|-------|----------------------|
| `--vira-text` | `#211B2B` | 15.39:1 |
| `--vira-text-secondary` | `#5C5566` | 7.13:1 |
| `--vira-text-muted` | `#6F6A77` | 5.24:1 |

All three clear WCAG AA (4.5:1). `--vira-text-muted` was previously `#77727E` at 4.6:1 and
was darkened after the contrast gate flagged it.

### 2.3 Status

Status colour carries meaning and must never be borrowed for decoration.

| Token | Value | Meaning |
|-------|-------|---------|
| `--vira-success` | `#1C7C54` | Active, confirmed, paid |
| `--vira-warning` | `#9A6100` | Expiring, needs attention |
| `--vira-danger` | `#A5263B` | Overdue, destructive, failed |
| `--vira-info` | `#1A5C8A` | Neutral notice |

Each has a `-soft` background and `-border` companion for badges and alerts.

### 2.4 Rules

1. Purple is the identity, not the wallpaper — it appears in the sidebar active
   state, primary buttons, links and focus rings. Never as a page background.
2. Gold only for accents and emphasis that is *rare*: expiry warnings, premium
   markers. Never for body text, never for a large fill.
3. Card backgrounds are white. The canvas is `--vira-bg`. Never invert.
4. Borders are `--vira-border`; `--vira-border-strong` only where two adjacent
   surfaces need separating without a shadow.
5. **Status colour is never brand colour.** A "success" badge is green, not purple.
6. No decorative gradients. No glows. Shadows are the four-step scale in §5.

---

## 3. Typography

**Face:** Vazirmatn, self-hosted (`public/build/fonts/`), 4 weights, woff2,
`font-display: swap`. No CDN — the UI must render identically offline.

**Why not the generated pairing:** Plus Jakarta Sans has no Persian coverage, and
Noto Sans Arabic is a compromise for Persian specifically. Vazirmatn is designed for
Persian, has correct Persian digit forms, and is already the mandated face.

| Token | Size | Use |
|-------|------|-----|
| `--vira-text-xs` | 12px | Badges, table meta, timestamps |
| `--vira-text-sm` | 13px | Table cells, form labels |
| `--vira-text-base` | 14px | Body — the default |
| `--vira-text-md` | 16px | Emphasised body, inputs |
| `--vira-text-lg` | 18px | Card titles |
| `--vira-text-xl` | 22px | Page titles |

**Rules**

- 12px is the floor. Nothing renders smaller (enforced by `T-TYPE-MIN`).
- Page titles are 22px/700 with `line-height: 1.3`. A bold Persian title's
  ascenders nearly fill the line box, so it needs ≥16px of clearance above it —
  this is why `.v-content` keeps extra top padding on phones (`T-HEAD-GAP`).
- Numbers use `font-variant-numeric: tabular-nums` in tables so columns align.
- Letter-spacing is `-0.01em` on display sizes only. Never on Persian body text —
  it breaks the joining behaviour of the script.

---

## 4. Layout and spacing

### 4.1 Space scale

4px base: `4, 8, 12, 16, 20, 24, 28, 32, 36, 40, 44, 48`.
Tokens 7, 9 and 11 are real scale steps — they were once missing from the file, and
because *one* undefined `var()` invalidates the whole declaration, page padding silently
became `0`. `tools/checks/tokens.mjs` now fails on any undefined reference.

### 4.2 Shell

| Element | Size |
|---------|------|
| Sidebar (expanded) | 264px, right side in RTL |
| Sidebar (collapsed) | 68px |
| Content max width | 1400px |
| Breakpoints | 1023px (rail → drawer), 767px, 479px |

The shell is a flex row. In RTL the first child renders on the right *without*
`row-reverse` — using `row-reverse` would double-mirror.

`.v-main` is the scroll container at `height: 100vh` with the content scrolling inside it,
so the topbar stays put. Below 1023px everything resets to normal document flow, because
on a phone the page must scroll as one unit.

### 4.3 Radius and elevation

| Token | Value |
|-------|-------|
| `--vira-radius-sm` | 6px |
| `--vira-radius` | 10px |
| `--vira-radius-lg` | 14px |
| `--vira-radius-full` | 999px |
| `--vira-shadow-xs` | `0 1px 2px rgba(33,27,43,.04)` |
| `--vira-shadow-sm` | `0 1px 3px … , 0 1px 2px …` |
| `--vira-shadow-md` | `0 4px 12px … , 0 1px 3px …` |
| `--vira-shadow-lg` | `0 12px 32px … , 0 4px 8px …` |

Cards use `--vira-shadow-xs` plus a 1px border. Heavy shadows are rejected: they make a
dense table read as a stack of floating boxes.

### 4.4 Motion

`--vira-duration-fast: 120ms`, `--vira-duration: 180ms`, `--vira-duration-slow: 260ms`,
easing `cubic-bezier(0.2, 0, 0.13, 1)`. Under `prefers-reduced-motion: reduce` all three
collapse to `0ms`. Motion only ever confirms a state change — nothing decorative.

---

## 5. Structure borrowed from the reference products

Patterns only; no pixel was copied.

| From | Pattern adopted | Applied at |
|------|----------------|-----------|
| **Attio** | Record detail as a *set of labelled panels* rather than one long form; the identity header stays pinned with the record's code and status | Customer detail, contract detail |
| **Attio** | Filters as a persistent bar above the table, each control keeping the other filters in the querystring | Customer list, contract list |
| **Linear** | One primary action per screen, top-left in RTL, everything else demoted to an icon or the row menu | Every page header |
| **Linear** | Dense rows with generous *vertical* rhythm and tight horizontal gaps; separators instead of borders per cell | All tables |
| **Monday** | Status as a coloured chip with a dot, never colour alone | Status badges |
| **Monday** | Work items grouped by urgency band (overdue / today / upcoming) rather than a flat list | Dashboard |

### 5.1 Rejected patterns

- Attio's multi-column record peek (too much for a 22-route app at this stage).
- Linear's command palette (requires client-side routing we do not have).
- Monday's board/timeline views (the data model supports it; the UI does not yet).

---

## 6. Components

Specifications live in `component-guidelines.md`. Non-negotiables:

| Component | Rule |
|-----------|------|
| Button | One `--primary` per view. 38px min height. Focus ring always visible. |
| Table | Scrolls inside `.v-tablewrap`; the page never scrolls sideways. |
| Field | Visible label, never placeholder-only. Error text referenced by `aria-describedby`. |
| Alert | `role="alert"` for errors, `role="status"` for everything else. |
| Badge | Colour **and** a dot or label — never colour alone. |
| Modal / drawer | Focus moves in, is trapped, Escape closes, focus returns to the trigger. |

---

## 7. Anti-patterns (do not ship)

- Decorative purple/pink gradients, glassmorphism, glow effects.
- Emoji as icons. The icon component is a single SVG sprite.
- Placeholder-only labels; errors reported only at the top of the page with no
  link to the field.
- Hover-only affordances on touch targets.
- A second class vocabulary for a component that already exists (`.v-stats` vs
  `.v-stats-grid` — this happened, and half the stat cards rendered unstyled).
- `->format('Y-m-d')` in a Blade view. Use `<x-fa-date>`.

---

## 8. Verification

| Gate | Command | Threshold |
|------|---------|-----------|
| Tokens | `node tools/checks/tokens.mjs` | 0 undefined |
| Token discipline | `node tools/checks/css-audit.mjs` | 42/42, no stray hex |
| Contrast | `node tools/checks/contrast.mjs` | 8/8 ≥ 4.5:1 |
| Class coverage | `node tools/checks/unstyled.mjs` | 0 unstyled |
| Layout | `node tools/checks/ui-shots.mjs` | 178/178 across 6 widths |
| Accessibility | `node tools/checks/a11y.mjs` | 10/10 |
| Calendar | `node tools/checks/calendar.mjs` | 0 Gregorian dates |

---

## 9. Page index

`pages/dashboard.md` · `pages/customers.md` · `pages/contracts.md` ·
`pages/projects.md` · `pages/employees.md` · `pages/payroll.md` · `pages/reports.md`

Pages marked **Not Implemented** in their file describe the intended design so the
next implementer does not invent a conflicting one.
