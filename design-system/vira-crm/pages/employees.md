# پرسنل — Employees

**Status: Not Implemented.** The module appears in the sidebar as a disabled row with the
note «به‌زودی» — `config/navigation.php` sets `enabled => false`. No route, controller or
view exists; the audit report lists the complete route table as proof.

This file records the agreed design so the eventual implementation does not invent a
pattern that conflicts with the rest of the product.

## Intended structure

- `GET /employees` — list with role, status and contract type.
- Detail: personal info, employment dates, and salary **hidden unless `payroll.view`**.
- Role assignment uses the four seeded roles; the UI must not offer a role the acting
  user cannot grant, and a user cannot escalate their own role.
- This is the only screen where `users.update` and `personnel.view` differ.

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
