# 08 — معماری دیتابیس و ERD (Gate A)

**Vira CRM** · Gate A · ۲ آذر ۱۴۰۵

---

## ۱. خبر خوب: Schema موجود از طراحی خوبی برخوردار است

پیش از پیشنهاد هر تغییری، Schema موجود را بازرسی کردم. نتیجه: **پایهٔ داده‌ای
محکم است** و نیازی به بازطراحی ندارد.

شواهد بازرسی:

| معیار | یافته |
|-------|--------|
| Foreign Key صریح | ✅ `->constrained()` در همهٔ روابط |
| رفتار حذف تعریف‌شده | ✅ `cascadeOnDelete` / `nullOnDelete` هر دو استفاده شده |
| Unique constraint | ✅ `contracts.number`, `payslips(period,employee)`, `credential_values(cred,field)`, `project_assignments(project,employee)`, `employees.national_id`, `scheduled_events.unique_key` |
| Index روی ستون‌های جستجو | ✅ `status`, `due_date`, `fire_date`, `[customer_id,type]`, `[type,status]`, `[employee_id,is_active]` |
| اعداد مالی | ✅ `decimal` (نه float) — با `Money` در PHP |
| مبلغ پروژه/قرارداد | ⚠️ `integer` (ریال) — **تصمیم آگاهانه** است نه خطا |
| تاریخ | ✅ `date` برای تاریخ‌خالص، `timestamp` برای رویداد |
| Snapshot نسخه | ✅ `contract_versions`, `payslip_versions` وجود دارند |

**نتیجه: صفر migration تخریبی لازم است.** فقط ۲–۳ جدول جدید ممکن است لازم شود.

---

## ۲. ERD — نمودار موجودیت و رابطه

```
                      ┌──────────┐
                      │  users   │
                      └────┬─────┘
                           │ (owner/created_by/finalized_by/computed_by)
        ┌──────────────────┼───────────────────┬──────────────┐
        │                  │                   │              │
        ▼                  ▼                   ▼              ▼
  ┌───────────┐    ┌──────────────┐    ┌─────────────┐  ┌────────────┐
  │ customers │    │  contracts   │    │  projects   │  │ employees  │
  └─────┬─────┘    └──────┬───────┘    └──────┬──────┘  └─────┬──────┘
        │                 │                   │               │
        │ 1:N             │ N:1               │ N:1           │ 1:N
        ├─────────────────┘                   │               │
        │        (customer_id, cascade)       │               │
        │                                     │               │
        ├──► customer_websites (N)            │               │
        ├──► customer_contacts (N)            │               │
        │                                     │               │
        │                                     ├──► seo_project_details (1:1)
        │                                     ├──► design_project_details (1:1)
        │                                     ├──► project_keywords (N)
        │                                     ├──► project_assignments (N) ◄──┘
        │                                     ├──► project_credentials (N)
        │                                     ├──► scheduled_events (N)
        │                                     └──► attachments (N)
        │
        └──► contracts (N)

┌──────────────────┐
│ contract_types   │──┐
└──────────────────┘  │ N:1
┌──────────────────┐  │
│contract_templates│──┤──► contracts ──► contract_versions (N)
└──────────────────┘  │              └──► contract_files (N)
                      │              └──► contracts.renewed_from_id (self)
                      │
                      ▼
              ┌──────────────┐
              │  payroll_*   │
              └──────────────┘
```

### زنجیرهٔ حقوق

```
employees ──► employee_contracts (N)
         │
         └──► payslips (N)  ◄── payroll_periods (N:1)
                   │
                   ├──► payroll_items (N)  ◄── projects (N:1, optional)
                   └──► payslip_versions (N)   ← تاریخچهٔ قفل‌شده
```

### زنجیرهٔ دسترسی محرمانه (حساس‌ترین)

```
projects ──► project_credentials (N)
                     │
                     └──► credential_values (N, UNIQUE(credential_id, field))
                                    ├── value_ciphertext   ← AES-256-GCM
                                    └── fingerprint        ← برای تشخیص تغییر

credential_types (N)          ← WordPress / Hosting / Registrar / GSC / GA
credential_requirements (N)   ← کدام نوع برای پروژهٔ سئو اجباری است
```

### زنجیرهٔ یادآوری

```
projects ──► work_items (kind: invoice|report, due_date, period, state)  ← ۹ ردیف
                    │
                    └──► scheduled_events (unique_key)
                                ├── event_type
                                ├── subject_type + subject_id (بدون morph کامل)
                                ├── kind: due_soon | due_today | overdue
                                └── fire_date
job_runs  ← هر اجرای scheduler
```

**تأییدشده با خواندن migration:** رویداد فاکتور/گزارش در `work_items` است
(نه جدول جدا). تفکیک «رویداد» (work_items) از «یادآور» (scheduled_events) طراحی
درست است: یک رویداد چند یادآور دارد.

**نکتهٔ طراحی درست:** `scheduled_events` از `subject_type` + `subject_id` + `kind` +
`fire_date` با `unique_key` استفاده می‌کند. این **idempotency بومی** می‌دهد و از
polymorphic کامل Eloquent پرهیز کرده. تصمیم درستی است.

---

## ۳. تصمیم‌های داده‌ای که باید تثبیت شوند

### D-1 — ذخیرهٔ پول: `integer` ریال یا `decimal`؟

**وضعیت فعلی:** `contracts.amount` و `projects.amount` از نوع `integer` (ریال) هستند.
`Money` در PHP با ریال صحیح کار می‌کند و `MoneyTest` ۵۲ اَسِرشِن دارد.

| گزینه | مزیت | عیب |
|-------|------|-----|
| **A — `integer` ریال** (فعلی) | بدون خطای گردکردن، ساده، تست‌شده | تا ~۹۲۲ میلیارد تومان در BIGINT؛ برای ریال کافی است |
| B — `decimal(18,2)` تومان | خواناتر | ریسک float در PHP اگر مراقب نباشند |

**پیشنهاد: گزینهٔ A حفظ شود.** ریال عدد صحیح است، تبدیل به تومان فقط در نمایش
(`Money::toToman()`). تغییر آن یعنی بازنویسی همهٔ مبالغ — بدون سود.

> **نیازمند تأیید (OD-27):** آیا نمایش به «تومان» یا «ریال» باشد؟ سند صریح نیست.

### D-2 — `employee_contracts` در برابر `contracts`

دو جدول جدا هستند. `contracts` قرارداد **مشتری** است، `employee_contracts` قرارداد
**استخدامی**. این تفکیک درست است و نگه داشته می‌شود.

### D-3 — `attachments` بدون polymorphic

`attachments` یک جدول مرکزی است با `attachable_type`/`attachable_id`؟ باید بررسی شود.

### D-4 — آیا جدول جدید لازم است؟

| جدول جدید | لازم؟ | چرا |
|-----------|-------|-----|
| `contract_placeholders` | **بله** | فهرست placeholderهای مجاز هر قالب (REQ-04.05) |
| `report_definitions` | **نه** | `scheduled_events` + `project_events` کافی است |
| `audit_log_exports` | **نه** | `audit_logs` خودش export را ثبت می‌کند |
| `backup_runs` | **بله** | ثبت اجرا/وضعیت بکاپ (REQ-15.02) |
| `notification_preferences` | **شاید** | وابسته به OD-11 (SMTP) |

**حداکثر ۲ جدول جدید، صفر تغییر تخریبی.**

---

## ۴. سیاست تاریخ — تثبیت‌شده

| موضوع | قاعده |
|-------|-------|
| ذخیره در DB | **میلادی ISO** (`date` برای تاریخ‌خالص) |
| نمایش به کاربر | **شمسی** با `JalaliDate` |
| تنها لایهٔ تبدیل | `App\Support\JalaliDate` — هرگز `$date->format('Y/m/d')` در View |
| فیلد ورودی | `<x-fa-date>` + ماسک `data-jalali` |
| روز تقویمی | `addDays`/`subDays` روی date-only، **نه** `subHours(48)` |
| Timezone | `app.timezone` — **تصمیم OD-07** |

**اثبات موجود:** `ReminderScheduleTest` با ۴۳ اَسِرشِن نشان می‌دهد D-2 دو روز
تقویمی است، نه ۴۸ ساعت. `docs/src/d2-daymath-verify.php` هم مستقل این را ثابت می‌کند.

---

## ۵. سیاست حذف و نگهداری تاریخچه

| موجودیت | رفتار | دلیل |
|---------|-------|------|
| `customers` | SoftDelete + `cascade` به قرارداد | مشتری حذف نمی‌شود، غیرفعال می‌شود |
| `contracts` نهایی‌شده | **SoftDelete ممنوع** | سند حقوقی |
| `contract_versions` | Append-only | تاریخچه |
| `payslips` تأییدشده | **SoftDelete ممنوع** + قفل دوره | مالی |
| `payslip_versions` | Append-only | تاریخچه |
| `audit_logs` | Append-only، بدون delete | امنیت |
| `scheduled_events` | حذف/بازسازی در تغییر تاریخ (در تراکنش) | idempotency |
| `project_credentials` | SoftDelete + audit | امنیت |

**قاعدهٔ سخت:** هیچ فایل/رکورد مالی یا حقوقی تأییدشده **هرگز** hard-delete نمی‌شود.

---

## ۶. ایندکس‌هایی که باید اضافه شوند (پس از پُرشدن داده)

| جدول | ایندکس | چرا | الان لازم است؟ |
|------|--------|-----|----------------|
| `audit_logs` | `(event, created_at)` | فیلتر Audit | وقتی >۱۰k ردیف |
| `contracts` | `(status, end_date)` | هشدار انقضا | ✅ **الان** |
| `project_events` | `(due_date, state)` | کارتل | ✅ **الان** |
| `payslips` | `(payroll_period_id, employee_id)` ✅ موجود | — | — |
| `scheduled_events` | `(fire_date, kind)` | کارتل | ✅ **الان** |

**قاعده:** ایندکس بدون Query متناظر اضافه نمی‌شود. هر سه مورد بالا Query دارند.

---

## ۷. آنچه تغییر **نمی‌کند**

- نام جدول‌ها و ستون‌های موجود.
- نوع `integer` ریال.
- `search_key` نرمال‌شده.
- `unique_key` در `scheduled_events`.
- تفکیک `seo_project_details` / `design_project_details` (طراحی درست — فیلد سئو به پروژهٔ طراحی تحمیل نمی‌شود).
