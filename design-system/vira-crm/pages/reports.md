# گزارش‌ها — Reports

**Status: Not Implemented.** The module appears in the sidebar as a disabled row with the
note «به‌زودی» — `config/navigation.php` sets `enabled => false`. No route, controller or
view exists; the audit report lists the complete route table as proof.

This file records the agreed design so the eventual implementation does not invent a
pattern that conflicts with the rest of the product.

## Intended structure

- Read-only: no destructive actions on any report screen.
- Charts follow the skill's charts rules: one accent series, legend always visible,
  colour never the sole encoder (pair with labels or patterns), colourblind-safe ramp.
- The date-range picker is Jalali and defaults to the current Jalali month.
- Export (CSV/PDF) carries the same permission gate as the on-screen figures — an export
  that bypasses a hidden column is the classic reporting leak.

## Shared rules that already apply

Everything in `MASTER.md` §2–§5 holds: brand palette, Vazirmatn, 12px type floor, Jalali
dates via `<x-fa-date>`, money with U+066C, drawer navigation below 1024px, focus-visible
rings, AA contrast, and 0px horizontal overflow at 390/430/768/1024/1440.

## Acceptance before this page can leave "Not Implemented"

1. Route registered behind `web,auth,password.settled` with a `permission:` guard.
2. Added to `PAGES` in `tools/checks/ui-shots.mjs` so it is measured at all six widths.
3. Added to `tools/checks/calendar.mjs` so Jalali rendering is proven, not assumed.
4. Its primary action wrapped in `.v-pagehead__actions` (checked by `T-PAGEHEAD-*`).
5. A controller test plus a flow test, matching the existing CRUD flow.
