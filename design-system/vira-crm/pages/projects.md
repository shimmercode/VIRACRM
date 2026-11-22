# پروژه‌ها — Projects

**Status: Not Implemented.** The module appears in the sidebar as a disabled row with the
note «به‌زودی» — `config/navigation.php` sets `enabled => false`. No route, controller or
view exists; the audit report lists the complete route table as proof.

This file records the agreed design so the eventual implementation does not invent a
pattern that conflicts with the rest of the product.

## Intended structure

- `GET /projects` — list filtered by type (SEO | website), status and customer.
- Detail: project header (code, type, customer, contract link), a work-item board
  grouped by period (monthly SEO reports, invoices), and a credentials tab.
- `work_items` enforces `unique(project_id, kind, period)`, so the UI must surface a
  duplicate-period conflict inline rather than letting the DB error reach the user.
- Money columns are hidden without `contracts.view_financials`.
- Project credentials are a separate permission (`credentials.reveal`); the list must
  not leak a credential value into a hover title or a copy button.

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
