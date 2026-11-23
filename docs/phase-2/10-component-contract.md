# Component contract

**Purpose:** state how each Design System component is meant to be used, so a new screen
cannot quietly invent a variant. This is the document that keeps the system from drifting —
gap **G6** in the gap matrix.

**Rule that governs everything here:** a screen may use the components below, and may not add
a new colour, spacing value, radius or font size outside `tokens.css`. That rule is enforced
mechanically by `tools/checks/tokens.mjs` and `tools/checks/css-audit.mjs`, not by review.

---

## Colour

| Token | Value | Use |
|-------|-------|-----|
| `--vira-primary` | `#450073` | Primary actions, active nav, focus ring. **One primary action per view.** |
| `--vira-gold` | `#D2AB67` | Decorative accent only. Never body text on white (≈2.1:1). |
| `--vira-gold-ink` | `#7A5A1D` | Any text that must read as gold. |
| `--vira-ink` | `#211B2B` | Body text, tooltips. |
| `--vira-text-muted` | — | Secondary/metadata. Never for interactive labels. |
| `--vira-canvas` | `#F6F5F8` | Page background. |
| `--vira-surface` | `#FFFFFF` | Cards, tables, modals. |

**Brand colours `#450073` and `#D2AB67` do not change without the user's explicit approval.**

---

## Button

```html
<button class="v-btn v-btn--primary">…</button>
<a class="v-btn v-btn--secondary">…</a>
```

| Variant | When |
|---------|------|
| `--primary` | The one action a screen exists for ("مشتری جدید", "ورود"). |
| `--secondary` | Alternative actions. |
| `--ghost` | Tertiary/cancel. |
| `--danger` | Destructive only. |

**Loading is automatic.** Any form with `data-guard` gets `.is-loading` on its submit button:
label hidden, 16px ring, `aria-busy` on the form, and a `role="status"` announcement.
Do not hand-roll a spinner. Verified by `T-DS-SPINNER-*`.

---

## Icon button

```html
<a class="v-iconbtn v-iconbtn--sm"
   aria-label="مشاهدهٔ …" title="مشاهده" data-tooltip="مشاهده">…</a>
```

**All three attributes are required.** `aria-label` is the accessible name (it must include
the record, so a screen reader announces *which* record); `title` is the mouse fallback;
`data-tooltip` is the visible tooltip that also appears on `:focus-visible`, which is what
makes it keyboard-reachable. Verified by `T-DS-TIP-*`.

---

## Checkbox

```html
<label class="v-check">
    <input type="checkbox" name="…" value="1">
    <span>برچسب</span>
</label>
```

Named **`.v-check`**, not `.v-checkbox`. The input is restyled (16px, not the 13px browser
default) and the whole label is the hit area. Verified by `T-DS-CHECK-*`.

---

## Field / validation

```html
<input class="v-input is-invalid" aria-invalid="true" aria-describedby="hint email-error">
<p class="v-field__hint" id="hint">…</p>
<p class="v-field__error" id="email-error">…</p>
```

`is-invalid` alone is not enough — the class is cosmetic. The two ARIA attributes are what a
screen reader relies on, and the error summary at the top of the form receives focus on
re-render. Verified by `T-FIELD-INVALID` and `T-ERROR-SUMMARY`.

---

## Tooltip

```html
<span data-tooltip="متن">…</span>
```

CSS-only, so it works without JavaScript. **Never the only carrier of information** — it
supplements an existing `aria-label`/visible text. Positioning uses logical properties and
mirrors in RTL. Verified by `T-DS-TIP-*` and `T-DS-RTL-tip`.

---

## Table

Above 768px: a real `<table class="v-table">` inside `.v-tablewrap`.
Below 768px: rows become cards automatically (`table-cards.js`).

**Constraints that are asserted, not requested:**

- `min-width: min(720px, 100%)` — the floor yields to available space. A fixed floor caused a
  33px overflow at exactly 1024px (`T-OVF-*`).
- Sortable headers must preserve active filters in the querystring.
- The actions cell carries `.v-table__actions`; card view depends on that class to find it.
- Every row's first cell carries `.v-table__primary` (the record link) and optionally
  `.v-table__sub`; card view uses them as the card title and subtitle.

---

## Not present, and why

| Component | Decision |
|-----------|----------|
| **Tabs** (`.v-tabs`) | **No requirement names tabs** and no screen uses them. The single CSS rule is dead. Recommendation: remove it. Until then `T-DS-TABS-absent` asserts nothing uses it. |
| **Skeleton** (`.v-skeleton`) | Every page is server-rendered, so there is no client-side wait for a skeleton to fill. Building it now would be untested code. `T-DS-SKELETON-absent` asserts nothing uses it. |
| **Generic Spinner component** | Not needed: the only real wait is a form submit, and that is handled by the button's `.is-loading` state. A free-standing spinner would have no user. |
| **Modal** | Exists as the drawer (`.v-drawer__panel`), focus-trapped. A centred dialog has no current use. |

---

## How this contract is enforced

| Rule | Enforced by | Fails when |
|------|-------------|------------|
| No invented colour/token | `tokens.mjs`, `css-audit.mjs` | an undefined `var(--vira-*)` or a raw hex outside tokens |
| Every icon button is labelled | `a11y.mjs` (`T-ACCESS-NAME`) | a visible control without an accessible name |
| Every invalid field is described | `a11y.mjs` (`T-FIELD-INVALID`) | `aria-invalid` without `aria-describedby` |
| Loading state is real | `design-system.mjs` | `.is-loading` without a painted ring |
| Tables do not overflow | `overflow.mjs` | any wrapper overflow at 768/1024/1280/1440 |
| Cards replace tables on phones | `table-cards.mjs` | fields missing, or the table still shown |
| Unused components stay unused | `design-system.mjs` | someone adds `.v-tabs`/`.v-skeleton` without a decision |
