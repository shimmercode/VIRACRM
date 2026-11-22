# RTL Guidelines

Persian is the document language; every page sets `<html lang="fa" dir="rtl">`.
These rules exist because each mistake below was made at least once in this project.

## The one rule that matters most

**Never use `direction` to fix layout that `dir` already fixes.**

In an RTL document a flex row places its first child on the **right** automatically.
Writing `flex-direction: row-reverse` "so it looks right" mirrors the layout a second
time and puts the sidebar on the left. The sidebar-118px-from-the-edge bug was not a
sidebar bug at all — `.v-shell` had no rules, so the children stacked; the fix was a
plain `display: flex`, no reversal.

## Logical properties

Use logical properties for anything one-dimensional along the inline axis:

| Wrong | Right |
|-------|-------|
| `margin-left` / `margin-right` | `margin-inline-start` / `margin-inline-end` |
| `padding-left` / `padding-right` | `padding-inline-start` / `padding-inline-end` |
| `left:` / `right:` | `inset-inline-start` / `inset-inline-end` |
| `text-align: left` | `text-align: start` |
| `border-left` | `border-inline-start` |

`text-align: start` is correct **only** when the element inherits the document's
direction. See the next section for when it is not.

## The `dir="ltr"` trap

A Latin value — phone number, national ID, domain, email, contract number — needs
`dir="ltr"` so its characters are ordered correctly. But `dir` also flips what
`start` means:

```html
<!-- in an RTL document -->
<dd dir="ltr">info@arman.example.com</dd>
```

This element is LTR, so `text-align: start` resolves to the **left**. If the `dd` is
full-width inside a right-aligned column, the value jumps to the far edge while its
label stays right — and because `dt` and `dd` are separate block boxes, they appear on
separate lines, reading as two unrelated rows.

**Fix:** explicitly re-align the value to the parent's edge.

```css
:where(.v-dl dd, .v-field .v-input)[dir='ltr'] { text-align: right; }
```

Enforced by `T-DL-ALIGN`, which compares the ink box of the value against its label's
right edge. The check was validated by removing the CSS and confirming it fails.

## Numbers and digits

| Context | Digits | Direction |
|---------|--------|-----------|
| Counts, money, dates, phone in a table | Persian (۰–۹) | `ltr` on the cell if mixed with Latin |
| National ID, registration number, economic code | Persian | `dir="ltr"` + right-aligned |
| Email, domain, URL | Latin | `dir="ltr"` + right-aligned |
| Contract number (`VIRA-C-0001`) | Latin | `dir="ltr"` |

Money uses U+066C (`٬`) as the thousands separator: `۲۴۰٬۰۰۰٬۰۰۰`.
A comma would render as a Latin punctuation mark inside Persian text and break the
line-breaking rules around it.

## Dates

Jalali only, always through `<x-fa-date>`. The year is never above 1600 in Persian
(we are in 1405). `tools/checks/calendar.mjs` treats any visible date with a year
> 1600 as a failure, which is the one reliable signal that a Gregorian date leaked
in — a Persian reader sees `۲۰۲۶` as "wrong", not as "a different calendar".

## Icons

- Directional glyphs (chevron, arrow) carry `.rtl-mirror` and are auto-mirrored.
- Non-directional glyphs (search, calendar, home, user) are **never** mirrored.
- Brand marks are never mirrored.
- The icon component applies this from a fixed list of names, so a new directional
  icon must be added to that list — otherwise it points the wrong way.

## Mixed-direction lines

A line containing both Persian and Latin (e.g. "شماره قرارداد: VIRA-C-0001") is the
most fragile case. Rules:

1. Put the Latin run in its own element with `dir="ltr"`.
2. Do not rely on the bidi algorithm to place punctuation — a trailing colon after a
   Latin run will jump to the wrong side without isolation.
3. In tables, give the whole cell `dir="ltr"` and `text-align: right`; do not mix
   inside one text node.

## Layout specifics

| Element | RTL behaviour |
|---------|--------------|
| Sidebar | Right edge; `flex` row, no reversal |
| Content | To the left of the rail |
| Form labels | Above fields, right-aligned |
| Table | Column order right-to-left; first column is the identity column |
| Pagination | Previous on the right, next on the left |
| Breadcrumb | Right to left, `›` pointing left |
| Drawer | Slides in from the right |
| Progress / timeline | Flows right to left |

## Verification

`T-RTL-*` asserts `dir="rtl"` and `lang="fa"` on every page at every width.
`T-SIDEBAR-*` asserts the rail's right edge equals the viewport's right edge at ≥1024px.
`T-DL-ALIGN` and `T-FA-DATE` cover the two content-level traps above.

**Manual:** after adding any new component, check it at 390px and 1440px with both a
Latin value and a Persian value in the same field, and confirm the two align.
