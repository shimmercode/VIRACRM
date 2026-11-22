# Contracts — list · detail · form · templates

**Status:** list, detail and form **Implemented**. Template management **Not Implemented**.
**Routes:** `contracts.index`, `.create`, `.store`, `.show`, `.edit`, `.update`, `.destroy`
**Templates:** `contracts/{index,show,create,edit,_form}`

## List — `GET /contracts`

```
Page head  ── "قراردادها" + count                    [قرارداد جدید]
Filter bar ── search · type · status · [اعمال]
Table      ── شماره | عنوان | مشتری | نوع | بازه | مبلغ | وضعیت | عملیات
```

**Rules**

1. **Contract number is `dir="ltr"`** (`VIRA-C-0001`) and right-aligned.
2. **The date range is two Jalali dates**, `۱۴۰۵/۰۷/۰۱ — ۱۴۰۶/۰۲/۱۱`, rendered through
   `<x-fa-date>`. Both leaked Gregorian until the component was introduced.
3. **Amount is a money column**: tabular numerals, Persian separator, right-aligned.
   Hidden entirely without `contracts.view_financials`.
4. **Status is derived**, not stored — a contract whose `end_date` has passed shows
   "منقضی" even if the row still says `active`. `displayStatus()` owns this; the view
   never recomputes it.

## Detail — `GET /contracts/{id}`

```
Breadcrumb ── داشبورد › قراردادها › {title}
Page head  ── title · number (ltr) · status chip        [ویرایش] [حذف]
Card       ── مشخصات قرارداد   (2-col definition list)
Card       ── طرفین            (party_one_name/address, divider, party_two_* — read-only)
Card       ── نسخه‌ها          (version list with Jalali timestamps)
Card       ── فایل‌ها / یادداشت‌ها
Card       ── اطلاعات ثبتی     (created/updated dates)
```

**Rules**

1. **Definition lists are 2-column above 767px, 1-column below.** Each item is
   `<div><dt>label</dt><dd>value</dd></div>` — label above value, both on the same edge.
2. **`dir="ltr"` values are re-aligned right.** Without the explicit rule, `text-align:
   start` sends them to the left edge and they detach from their labels. Enforced by
   `T-DL-ALIGN`.
3. **The money value is large** (`--vira-text-lg`, 600) with the unit (`ریال`) demoted —
   the number is the information, the unit is context.
4. **Party addresses are long-form** and wrap; they are not truncated with an ellipsis,
   because a partial address is useless.
5. **Version timestamps are Jalali with time** (`date · time`), since two versions can
   land on the same day.

## Form — `create` / `edit`

```
Page head  ── title
Card       ── مشتری · نوع قرارداد · عنوان            (customer_id, contract_type_id, title)
              تاریخ شروع · تاریخ پایان              (start_date, end_date — Jalali inputs)
              مبلغ · وضعیت                          (amount, status)
              شرح موضوع                             (description)
              یادداشت داخلی                         (internal_notes)
Footer     ── [ذخیره] [انصراف]
```

The shared partial is `contracts/_form.blade.php`, used by both `create` and `edit`.

**Rules**

1. **Jalali input, Jalali validation.** The date mask formats as `YYYY/MM/DD` in Persian
   digits and the server rejects an impossible date (e.g. month 13) with a Persian
   message — not a 500 and not a silent rollover.
2. **End date must be after start date**; validated server-side and reported inline.
3. **Amount accepts Persian or Latin digits** and normalises to an integer before
   storage. A comma or the Persian separator is stripped, not rejected.
4. **Customer is a select**, searchable above ~20 options. Until then a native select is
   both faster and accessible.
5. **Parties are stored on the contract, not entered on this form.** The detail card
   reads `party_one_name/address` and `party_two_name/address`; the form has no fields
   for them yet, so they default to the Vira party. Adding party editing is an open gap.
6. **`internal_notes` is staff-facing only** and must never appear on a rendered
   contract body or a client-facing export. It is visually separated for that reason.

## Contract templates — **Not Implemented**

Intended design, so the next implementer does not invent a conflicting one:

- `GET /contract-templates` — list of templates with title, type, last edited.
- Detail = a rich-text body editor with merge fields (`{{customer.name}}`,
  `{{contract.amount}}`) inserted from a picker.
- Preview renders the merged body using the same typography as the contract detail.
- Deletion is soft and blocked while a contract references the template.

The `contract_templates` table and `ContractTemplate` model already exist, so this is a
UI-only gap.
