# Dashboard — `GET /`

**Status:** Implemented · **Route:** `dashboard` · **Template:** `resources/views/dashboard.blade.php`

## Purpose

Answer "what needs my attention today?" in one screen. Not a reporting page — the
reports module will own analysis. This is a triage surface.

## Structure

```
Page head  ──  "شب بخیر، مدیر سامانه" + today's Jalali date
Stat row   ──  5 cards: customers · active contracts · expiring in 30d ·
               active projects · overdue tasks
Two-column ──  right: expiring contracts (next 30 days)
               left:  open work items, grouped by urgency band
```

## Rules specific to this page

1. **Five stat cards, one row at ≥1024px**, `minmax(0,1fr)` each, collapsing to two
   columns at 767 and one at 479. The card count is fixed; do not make it dynamic
   without changing the grid.
2. **`--warn` / `--danger` variants** are applied by threshold, not by taste:
   overdue > 0 → danger; expiring > 0 → warn; otherwise neutral.
3. **Every figure is real.** No placeholder or sample numbers in production code —
   the demo seeder is dev-only and marked as such.
4. **Dates in the work-item list are Jalali** via `<x-fa-date>`. This page is where
   the Gregorian-date bug was first seen (`2026-11-16`), which is why the guard exists.
5. **Work items are grouped** as overdue / today / upcoming, matching Monday's
   urgency bands rather than a flat chronological list.
6. **Greeting uses the user's display name** and the Tehran-local hour, so "صبح بخیر"
   / "شب بخیر" are correct rather than decorative.

## Permissions

Each block is permission-gated independently: a user without `contracts.view` sees no
contract stats or list at all, not zeros. Zeros would imply "none exist" when the
truth is "you may not see them".

## Responsive

| Width | Behaviour |
|-------|-----------|
| 1440 | 5 stat cards in one row; content split 2 columns |
| 1024 | Cards wrap to 3+2; columns remain side by side |
| 768 | Cards 2 per row; columns stack |
| 390 | Cards 1 per row; dashboard scrolls as one page |

## Known gaps

- No date-range selector (the "this month" scope is fixed).
- No chart. Deliberate: with 7 customers and 5 contracts a chart is decoration.
  Revisit when volume justifies it, and then use one accent series with a legend,
  per the skill's charts rules.
