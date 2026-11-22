# UX Guidelines

Derived from the UI UX Pro Max rule set (141 guidelines, prioritised 1–10) and filtered to
what this product actually needs. Each rule below names the check that enforces it, or is
marked **manual** where no automated gate exists.

## Priority order

The skill's own ranking, kept intact because it is sound:

1. Accessibility (critical) · 2. Touch & interaction (critical) · 3. Performance (high) ·
4. Style selection (high) · 5. Layout & responsive (high) · 6. Typography & colour (medium) ·
7. Animation (medium) · 8. Forms & feedback (medium) · 9. Navigation (high) ·
10. Charts & data (low)

Note that the numbering is a *priority*, not an order of implementation — accessibility
outranks everything, which is why the audit ran the accessibility checks before looking
at how anything looked.

## Applied rules

| Rule (skill) | Severity | How Vira satisfies it | Gate |
|--------------|----------|----------------------|------|
| Contrast 4.5:1 | critical | 8 measured pairs, all ≥ 5.24:1 | `contrast.mjs` |
| Visible focus ring | critical | 2px solid primary, 2px offset, never removed | `T-FOCUS` |
| Modal dialog focus | high | Focus enters, is trapped, Escape exits, returns to trigger | `T-DRAWER-*` |
| Focusable error summary | high | `#error-summary` with `tabindex="-1"`, focused on failure | `T-ERROR-SUMMARY` |
| No horizontal scroll | high | 0px overflow at all six widths | `T-RESP-*` |
| Table handling on mobile | medium | Scroll wrapper; page never scrolls sideways | `T-TABLE-SCROLL` |
| Touch targets | critical | ≥ 28px (WCAG 2.2 AA is 24px); 2 dense table controls at 28px | `T-TOUCH` |
| Font size floor | medium | 12px minimum rendered text | `T-TYPE-MIN` |
| Accessible name on every control | critical | Every visible control has a name | `T-ACCESS-NAME` |
| Live-region announcements | high | `role="alert"` / `role="status"` on all flash + error blocks | `T-ALERT-LIVE` |
| Status not by colour alone | high | Badge colour **and** dot/label | manual |
| Empty states with an action | medium | `<x-empty-state>` on 5 pages | manual |
| Reduced motion | medium | `prefers-reduced-motion` zeroes all durations | `css-audit` |

### Rules deliberately not applied

| Rule | Why not |
|------|---------|
| Skeleton screens / spinners | Pages are server-rendered; there is no client-side fetch to wait on. A spinner would be theatre. The one real wait — form submit — is covered by `initSubmitGuard`, which disables the button and adds `is-loading`. |
| 44×44 touch targets everywhere | Desktop data tables at 44px per row would show ~6 rows on a laptop. Vira uses 28–38px in dense surfaces and 44px in the phone drawer, which is where thumbs actually land. |
| Autocomplete / predictions | Nothing to predict yet: two lists, both small. Revisit when the customer table exceeds a few hundred rows. |
| Bottom navigation (≤5 items) | Vira's primary navigation is a rail with 9 items. A bottom bar would truncate it. The drawer is the mobile equivalent. |

## Forms

1. **Label above field**, always visible. Placeholder is an example of the expected
   value, not a substitute for the label.
2. **Required is marked** with a decorative asterisk; the `required` attribute carries
   the meaning.
3. **Validate on submit, then keep the values.** A failed submit re-renders the form with
   every entered value intact and the error inline. The skill prefers blur-validation;
   it is not implemented because it needs client-side state the Blade forms do not have,
   and a server round-trip is honest about what actually validates.
4. **Summary plus inline.** `#error-summary` lists every error at the top with `role="alert"`;
   each field also shows its own message. Focus moves to the summary.
5. **Guard against double submit.** `initSubmitGuard` disables the submit button on first
   press, so a slow connection cannot produce two customers.
6. **Destructive actions confirm.** `data-confirm` on the delete forms; the confirm text
   names the consequence ("این مشتری و ارتباط‌های آن حذف می‌شود").

## Search, filter, sort

- Filters live in a persistent bar above the table and submit as GET, so every filtered
  view is a shareable URL and survives a refresh.
- Each control preserves the others in the querystring; changing the type filter does not
  silently drop the search term.
- Sort links carry the current filters forward for the same reason.
- Clearing all filters is one action, not four.

## Feedback

| Event | Feedback |
|-------|----------|
| Create / update | `session('success')` → `role="status"` alert at the top of content |
| Delete | Confirm dialog → success flash → row gone |
| Validation failure | Error summary + inline errors + focus move |
| Permission denied | 403 page inside the shell, Persian message, link back to the dashboard |
| Not found | 404 inside the shell — the user never loses navigation |
| Throttled login | 429 inside the shell |

## Navigation

- One active item, marked `aria-current="page"` and filled with `--vira-primary-soft`.
- Items the user lacks permission for are not rendered at all — a greyed-out item that
  can never be enabled is noise. Modules that exist in the requirements but are not built
  yet *are* shown, disabled, with a note explaining that (they explain the product's shape).
- The breadcrumb appears on every detail and form page and ends at the current record.

## Content rules

- Persian throughout. Latin only for identifiers that are genuinely Latin (domains,
  emails, contract numbers) and those carry `dir="ltr"`.
- Numbers are Persian digits everywhere except `dir="ltr"` identifier fields.
- Money uses the Persian thousands separator U+066C (`٬`), not a comma.
- Dates are Jalali, via `<x-fa-date>`. No exceptions.

## Manual checks still required

These have no automated gate and must be re-verified by eye after any layout change:

1. Long Persian company names do not overflow a table cell or a card title.
2. A record with every optional field empty renders em-dashes, not blanks or `null`.
3. Printing / zoom at 200% keeps the primary action reachable.
4. The drawer, at 390px, still scrolls when the navigation list grows.
