# Customers — list · detail · form

**Status:** Implemented · **Routes:** `customers.index`, `.create`, `.store`, `.show`,
`.edit`, `.update`, `.destroy` · **Templates:** `customers/{index,show,create,edit,_form}`

## List — `GET /customers`

```
Page head  ──  "مشتریان" + "N مشتری ثبت شده است."     [مشتری جدید]
Filter bar ──  search (q) · type · status · [اعمال] · [پاک کردن]
Table      ──  مشتری | نوع | راه تماس | وضعیت | قرارداد | عملیات
```

**Rules**

1. **The header action lives in `.v-pagehead__actions`.** An unwrapped child becomes a
   third flex item and drops to its own row below 1024px, leaving the title alone at the
   top. Enforced by `T-PAGEHEAD-768` / `T-PAGEHEAD-1440`.
2. **Filters submit as GET** and each control preserves the others in the querystring.
3. **Search matches** code, company name, first/last name, national ID, mobile, phone and
   website domain. It is not a `LIKE` on one column.
4. **Status is a chip with a dot** plus the Persian word — never colour alone.
5. **Row actions** are icon links (view, edit) with `aria-label`; delete is only on the
   detail page behind a confirm, so it cannot be hit by accident while scrolling a list.
6. **Empty state** offers the create action, not just "no results".
7. **Pagination** keeps the filters in its links.

**Responsive**

| Width | Table |
|-------|-------|
| ≥1024 | All 6 columns fit |
| 768 | All columns fit; row height increases |
| 390 | Table scrolls inside `.v-tablewrap`; page overflow stays 0px |

The table's `min-width` is what forces the wrapper to scroll. Every ancestor up to
`.v-content` needs `min-width: 0`, or a flex item's default `min-width: auto` expands
the page instead — that was the 319px mobile overflow bug.

## Detail — `GET /customers/{id}`

```
Breadcrumb ── داشبورد › مشتریان › {name}
Page head  ── avatar · name · [status chip] [type chip] · code (ltr)
              [ویرایش] [قرارداد جدید] [حذف]
Stat row   ── contracts · projects · open notes · active contract value
Left col   ── جزئیات پرونده (source, owner, dates) · تاریخچه
Right col  ── اطلاعات تماس · مشخصات هویتی · وب‌سایت‌ها · قراردادها · یادداشت‌ها
```

**Rules**

1. **Money is permission-gated** by `contracts.view_financials`; without it the value
   card is not rendered (not shown as `—`, which would imply a zero).
2. **Every date is Jalali** via `<x-fa-date>`. This page leaked `تاریخ ثبت 2026-11-22`
   until the component existed.
3. **Latin values are right-aligned** to match their labels. `dir="ltr"` alone left
   phone numbers and emails stranded on the far left of a right-aligned column.
4. **Empty optional fields render an em-dash**, never a blank cell or `null`.
5. **Notes are read-only here**; entry is a separate flow.

## Form — `create` / `edit`

```
Page head  ── title + description
Card       ── نوع مشتری (legal | natural) — toggle drives visible fields
              Sections: هویت · اطلاعات تماس · آدرس · وب‌سایت · یادداشت
Footer     ── [ذخیره] [انصراف]
```

**Rules**

1. **Type switching is client-side** (Alpine) and shows/hides field groups; the server
   still validates by type, so a tampered DOM cannot save an invalid record.
2. **Required fields** are marked and enforced on both sides.
3. **Duplicate detection** warns when company name / national ID / mobile match an
   existing customer, and shows the existing record. It warns rather than blocks —
   two distinct companies genuinely can share a switchboard number.
4. **Failed submit preserves every value** and shows inline errors plus the summary.
5. **Submit is guarded** against double posting.
6. Latin inputs (mobile, national ID, postal code, domain, email) are `dir="ltr"` with
   `inputmode` set so a phone opens the numeric keypad, while staying right-aligned.
