# نقشهٔ دیتابیس — VIRA CRM

**اندازه‌گیری واقعی:** ۲ آذر ۱۴۰۵ · از `sqlite_master` + `PRAGMA table_info`

---

## ۱. اعداد واقعی

| سنجه | مقدار |
|------|-------|
| جداول کسب‌وکار | **۴۱** |
| جداول فریم‌ورک | ۶ (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) |
| جداول دارای مدل | ۱۸ |
| **جداول بدون مدل** | **۲۳** |
| فایل Migration | ۹ |

> **تصحیح:** گزارش‌های قبلی «۳۴ جدول» می‌گفتند. شمارش واقعی **۴۱** است.
> تفاوت ناشی از جداول pivot و framework بود.

---

## ۲. جداول دارای داده (۱۴)

| جدول | ردیف |
|------|-----:|
| `audit_logs` | ۲۳۲ |
| `role_permissions` | ۵۴ |
| `permissions` | ۳۹ |
| `sessions` | ۲۲ |
| `work_items` | ۹ |
| `customers` | ۷ |
| `customer_websites` | ۶ |
| `contracts` | ۵ |
| `contract_types` | ۴ |
| `roles` | ۴ |
| `settings` | ۴ |
| `projects` | ۳ |
| `user_roles` | ۲ |
| `users` | ۲ |

**بقیهٔ ۲۷ جدول خالی‌اند.**

---

## ۳. ۲۳ جدول بدون مدل — طبقه‌بندی صادقانه

داشتن جدول بدون مدل **خودِ خود نقص نیست** — ممکن است عمدی و داخلی باشد. اما
**هیچ‌کدام نباید بی‌توضیح بماند.**

### ۳.۱ پایهٔ فریم‌ورک‌مانند — توجیه‌شده (۵)

| جدول | توجیه |
|-------|-------|
| `sessions` | Laravel session driver |
| `password_reset_tokens` | استاندارد Laravel (بلااستفاده — reset وب نداریم) |
| `role_permissions` | pivot — با `DB::table` کار می‌کند |
| `user_roles` | pivot |
| `job_runs` | دفتر اجرای cron (طراحی §11) |

### ۳.۲ ماژول آینده — عمداً خالی (۱۸)

| گروه | جداول |
|------|-------|
| پرسنل/حقوق (۶) | `employees`, `employee_contracts`, `payroll_periods`, `payroll_items`, `payroll_rules`, `payslips`, `payslip_versions` |
| اعتبارنامه (۴) | `credential_types`, `credential_requirements`, `credential_values`, `project_credentials` |
| پروژه (۵) | `project_types`, `project_assignments`, `project_keywords`, `seo_project_details`, `design_project_details` |
| سایر (۳) | `seo_packages`, `scheduled_events` |

**نکتهٔ مهم:** این جداول با Migration ساخته شده‌اند اما **هیچ کدی از آن‌ها
استفاده نمی‌کند**. یعنی Schema از پیاده‌سازی جلوتر است. این **ریسک** است:
Schema بدون کد، تست نمی‌شود و ممکن است اشتباه باشد. در M3/M6 هنگام ساخت مدل،
Schema بازبینی خواهد شد.

---

## ۴. شاخص‌های موجود (نمونه)

| جدول | شاخص |
|------|------|
| `audit_logs` | `(occurred_at)`, `(actor_id)` |
| `contracts` | `(customer_id)`, `(status)` |
| `customers` | `(status)` |
| `work_items` | `(state)` |
| `sessions` | `(user_id)` |

---

## ۵. شاخص‌های پیشنهادی (تأییدشده در Gate A، هنوز ساخته نشده)

| شاخص | دلیل |
|-------|------|
| `contracts(status, end_date)` | `Expiring` scope — پرکاربردترین فیلتر |
| `work_items(due_date, state)` | داشبورد و یادآوری |
| `scheduled_events(fire_date, kind)` | cron هر ۵ دقیقه |

**ثلاثی که بدون آن‌ها با رشد داده کند می‌شوند** — نه فوری، اما پیش از M5.

---

## ۶. جداول جدید تأییدشده (هنوز ساخته نشده)

| جدول | ماژول | وضعیت |
|------|-------|-------|
| `contract_placeholders` | M1 | طراحی‌شده |
| `backup_runs` | M8 | طراحی‌شده |
| `journal_entries` + `journal_entry_lines` | حسابداری | **فقط طراحی — نیازمند تأیید** |

---

## ۷. قواعد تغییرناپذیر

| قاعده | وضعیت |
|-------|-------|
| Migration مخرب صفر | ✅ رعایت شده |
| پول = `BIGINT` ریال | ✅ رعایت شده |
| نسخه‌ها append-only | ✅ طراحی‌شده |
| `audit_logs` فقط append | ✅ رعایت شده |
| حذف نرم (`deleted_at`) برای مشتری/قرارداد | ✅ رعایت شده |
| FK با `restrict` برای سوابق مالی | ⏳ فقط طراحی |

---

## ۸. بازتولید اعداد

```bash
PHP_WASM_PATH=/tmp/phpwasm/node_modules \
  node docs/tools/php-cli.mjs /tmp/audit2.php
```

هر عدد در این سند از همان اسکریپت می‌آید — دستی شمارش نشده.
