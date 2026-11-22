# Component Guidelines

Every component below exists in `resources/views/components/` or as a `.v-*` block in
`resources/css/components.css`. Where a class is documented but has no rule, the
`unstyled` gate fails — that is how `.v-stats` was caught rendering as plain text.

## Button (`.v-btn`)

| Variant | Use |
|---------|-----|
| `.v-btn--primary` | The single most important action on the screen |
| `.v-btn--secondary` | Supporting actions (edit, cancel) |
| `.v-btn--ghost` | Dense areas, row actions, icon-adjacent |
| `.v-btn--danger` | Destructive only, and always behind a confirm |
| `.v-btn--link` | Inline text action inside a sentence |
| `.v-btn--sm` / `--lg` | Density variants |
| `.v-btn--block` | Full-width, used in phone layouts |

**States**

| State | Treatment |
|-------|-----------|
| Default | `--vira-primary` fill, white text |
| Hover | `--vira-primary-hover`, 180ms |
| Active | `--vira-primary-active` |
| Focus | 2px solid `--vira-primary` outline, 2px offset — never removed |
| Disabled | 50% opacity, `cursor: not-allowed`, not clickable |
| Loading | `is-loading` + `disabled`; the submit guard sets both so a double-tap cannot double-post |

**Rules**

- Exactly one `--primary` per view. Two primaries means no primary.
- Minimum height 38px. Icon-only buttons are 38×38 and require `aria-label`.
- In RTL the button's icon precedes its label visually on the right — the markup
  order is icon-then-text, which is correct once `dir="rtl"` is on the document.

## Table (`.v-table` / `.v-tablewrap`)

- Columns are declared in priority order, right-to-left: identity first, then status,
  then metadata, then actions.
- The wrapper scrolls; the page does not. `.v-tablewrap { overflow-x: auto }` with
  `min-width: 0` on every ancestor up to `.v-content` — a flex item's default
  `min-width: auto` otherwise defeats the table's `min-width`.
- `th` carries `scope="col"`. Numeric cells are `dir="ltr"` for digit order but
  `text-align: right` so they align with their header (see `rtl-guidelines.md`).
- Row hover uses `--vira-surface-hover`; row focus uses the standard ring.
- Empty state is a `<x-empty-state>`, never a header-only table.

## Field (`.v-field`, `.v-input`, `.v-label`)

- Label is always visible. Placeholder is an example, never the label.
- Required fields show `<span class="v-req" aria-hidden="true">*</span>` — the
  asterisk is decorative because the `required` attribute carries the semantics.
- Error text sits directly under the field and the input references it:
  `aria-invalid="true"` plus `aria-describedby="<field>-hint <field>-error"`.
- Hint text sits between label and input when it explains *what to type*
  (e.g. "همان‌طور که در اسناد رسمی آمده است").
- On failed submit the page renders `#error-summary` with
  `role="alert" tabindex="-1"` and focus moves to it (`initErrorSummary`).
  Inline errors remain — the summary complements, never replaces them.

## Alert (`.v-alert`)

`<x-alert type="success|danger|warning|info" :dismissible="true">`

- `danger` and `warning` → `role="alert"` (assertive)
- `success` and `info` → `role="status"` (polite)
- `$attributes` **must** be merged onto the root element. When it was not, a caller's
  `id` and `tabindex` were silently dropped and the error summary could never be focused.

## Badge / status (`.v-badge`, `<x-status-badge>`)

- Colour plus a dot **or** a word. Never colour alone (the skill's `Map Symbology`
  rule generalises to every status chip).
- `.v-badge__dot` is a real element. `dot` is declared in `@props`; an undeclared prop
  becomes a literal HTML attribute, which is how `dot="dot"` once appeared in the DOM.

## Drawer (mobile navigation)

`.v-drawer[hidden]` → shown with `role="dialog" aria-modal="true"`.

- Opening moves focus to the first control **inside `.v-drawer__panel`**. Querying the
  whole drawer grabs the scrim `<button>` first, which put focus behind the panel.
- Tab and Shift+Tab cycle inside the panel. The scrim is excluded: it is a dismiss
  target, not part of the dialog.
- Escape closes; focus returns to the element that opened it.
- The drawer repeats the sidebar footer. Below 1024px the rail is `display: none`,
  so anything omitted here is unreachable — sign-out was.

## Empty state (`<x-empty-state>`)

Icon, one sentence explaining *why* it is empty, and one action that resolves it.
"The list is empty" alone is a dead end.

## Date (`<x-fa-date>`)

The only way a view may render a date. Accepts a Carbon instance or string, optional
`withTime`, and a `fallback` for null. Never call `->format('Y-m-d')` in Blade —
that is how Gregorian dates reached three screens. `tools/checks/calendar.mjs` fails
on any visible date whose year exceeds 1600.

## Icon (`<x-icon name size>`)

Single 24×24 SVG sprite, `stroke-width: 1.75`, `currentColor`. Decorative icons are
`aria-hidden="true"`; an icon that is the only content of a control requires the
control to carry `aria-label`. No emoji, ever.
