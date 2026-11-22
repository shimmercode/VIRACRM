# VIRA CRM — طراحی دیتابیس (فاز ۱)

| مورد | مقدار |
|---|---|
| نسخه | 0.1-draft |
| وضعیت | پیش‌نویس طراحی — وابسته به تأیید فاز ۱ |
| موتور مقصد | MySQL 8.0+ / MariaDB 10.6+ · Charset `utf8mb4` · Collation `utf8mb4_0900_ai_ci` (یا `utf8mb4_unicode_ci` در MariaDB) |
| موتور تست | SQLite (`:memory:`) — Migrationها سازگار با هر دو |
| نمودار | [`erd.png`](../diagrams/erd.png) · منبع [`erd.dot`](../database/erd.dot) |
| تعداد جداول | **۴۰** (۲۴ جدول پیشنهادی سند + ۱۶ جدول افزوده برای دامنه واقعی) |

> **اصل:** سند کارفرما فهرست پیشنهادی ۲۴ جدول داده است. این طرح بر اساس روابط واقعی
> بازطراحی شده: جدول `invoices` و `seo_reports` به **رکوردهای دوره‌ای مستقل** تبدیل شده‌اند
> (REQ-12.05)، موتور یادآوری از `reminders` به `scheduled_events` + `job_runs` منتقل شده
> (REQ-12.07)، و `contract_types`/`seo_packages` که سند به‌صورت «قابل تعریف در تنظیمات»
> خواسته، به جداول مرجع تبدیل شده‌اند.
>
> **دو مورد که آگاهانه «جدول» نشدند (نه حذف، بلکه ادغام — طبق قاعده «هیچ چیز بی‌صدا حذف نمی‌شود»):**
> - `credential_requirements` → به ستون `credential_types.is_required_for_seo` تبدیل شد؛
>   چون رابطه «نوع دسترسی ↔ الزام برای سئو» یک‌به‌یک است و جدول جدا فقط یک JOIN بی‌فایده اضافه می‌کرد.
> - `project_types` → به `projects.type` با `ENUM(seo, website_design)` تبدیل شد؛
>   نوع پروژه در سند فقط دو مقدار دارد و در تنظیمات قابل‌افزودن نیست، پس جدول مرجع لازم نیست.
>   اگر کارفرما بعداً «نوع پروژه قابل‌تعریف در تنظیمات» بخواهد، این تصمیم باید بازبینی شود (رجوع به Q-33).

## ۱. اصول مدل داده

| # | اصل | اعمال |
|---|---|---|
| D1 | پول به‌صورت **عدد صحیح سنت/ریال** (`BIGINT *_cents`) + محاسبه با `bcmath` | همه فیلدهای مالی |
| D2 | شناسه‌های متنی (کد ملی، شناسه ملی، شماره تماس، شماره فاکتور، صفر ابتدایی) **`VARCHAR`** | `customers.national_id`, `invoices.invoice_number`, ... |
| D3 | تاریخ‌ها **`DATE`/`DATETIME` میلادی UTC/Tehran**؛ نمایش شمسی در لایه نمایش | همه تاریخ‌ها |
| D4 | `DECIMAL` برای درصد/ضریب، **هرگز `FLOAT`** | `payroll_rules.count_multiplier DECIMAL(8,4)` |
| D5 | حذف نرم فقط برای موجودیت‌های غیرمالی؛ مالی/حقوقی **قفل نسخه** | `contracts`(soft), `payslips`(finalize), `payslip_versions` |
| D6 | Audit append-only (بدون update/delete در کد) | `audit_logs` |
| D7 | Idempotency در Scheduler با `UNIQUE KEY` | `scheduled_events.unique_key` |
| D8 | Snapshot برای نسخه‌های نهایی (قالب قرارداد، فیش حقوقی) | `data_snapshot JSON`, `items_snapshot JSON` |
| D9 | FK واقعی + `ON DELETE RESTRICT` برای اسناد مالی | همه FKها |
| D10 | کریدور رمزنگاری با `key_id` برای چرخش کلید | `project_credentials.key_id` |

## ۲. جدول‌ها

### ۲.۱ هویت و دسترسی

| جدول | ستون‌های کلیدی | نکته |
|---|---|---|
| `users` | id, name, email(unique), password, is_active, last_login_at, two_factor_* | فقط مدیر اصلی در نسخه ۱؛ ساختار آماده چندکاربری |
| `roles` | id, name(unique), label_fa | مدیر کل / مدیر پروژه / کارشناس / مالی / محدود (غیرفعال تا تأیید) |
| `permissions` | id, name(unique), area | `customers.*`, `contracts.*`, `credentials.reveal`, `payroll.*`, ... |
| `role_permissions` | role_id, permission_id (PK مرکب) | spatie |
| `user_roles` | model_id, role_id | spatie |
| `sessions` *(اختیاری، Laravel)* | id, user_id, payload, last_activity | مدیریت خروج همه نشست‌ها |
| `password_reset_tokens` | email, token, created_at | استاندارد Laravel |
| `failed_jobs` | id, uuid(unique), connection, queue, payload, exception, failed_at | استاندارد Laravel؛ برای دیدن شکست Jobهای یادآوری (REQ-12.07) |

### ۲.۲ مشتریان

| جدول | ستون‌ها | قیدها |
|---|---|---|
| `customers` | id · type ENUM(individual,company) · first_name · last_name · company_name · national_id · company_reg_id · phone_primary · address · referral_source · internal_notes · archived_at · timestamps | `national_id` UNIQUE (partial/nullable) · `phone_primary` INDEX · `type` CHECK: حقیقی→first_name/last_name الزامی، حقوقی→company_name + company_reg_id |
| `customer_contacts` | id · customer_id FK · label · phone · is_primary | INDEX(customer_id) · UNIQUE(customer_id, phone) |
| `customer_websites` | id · customer_id FK · domain · url · is_primary | INDEX(domain) برای جستجو و تشخیص تکرار |

> **تشخیص تکرار (REQ-03.05):** سه کلید نرمال‌شده: `national_id/company_reg_id`،
> `digits(phone)`، `registrable_domain(domain)`. هشدار پیش از ذخیره، با امکان «ثبت با تأیید مدیر»
> و ثبت در `audit_logs`.

### ۲.۳ قرارداد و قالب

| جدول | ستون‌ها | قیدها / نکته |
|---|---|---|
| `contract_types` | id · name · slug(unique) · is_active | مدیریت در تنظیمات |
| `contract_templates` | id · name · body · version · is_active · created_by · timestamps | body شامل `{{...}}` |
| `contracts` | id · customer_id · contract_type_id · template_id? · title · party_one · party_two · amount_cents · start_date · end_date · status ENUM · renewed_from_id? · notes · timestamps · deleted_at | `status` INDEX · `end_date` INDEX (هشدار انقضا) |
| `contract_versions` | id · contract_id · version_no · body_snapshot · data_snapshot JSON · is_final · finalized_at · finalized_by | UNIQUE(contract_id, version_no) — نسخه نهایی قفل |
| `contract_files` | id · contract_id · attachment_id · kind ENUM(generated,signed,scan,other) | FK RESTRICT |

### ۲.۴ پروژه‌ها

| جدول | ستون‌ها | نکته |
|---|---|---|
| `projects` | id · customer_id · contract_id? · type ENUM(seo,website_design) · name · domain · status · owner_id(users) · start_date · end_date · amount_cents · is_finalized · finalized_at · timestamps | `type` INDEX؛ فیلدهای مشترک اینجا |
| `seo_project_details` | project_id PK/FK · package_id · financial_notes · renewal_date · internal_notes | 1:0..1 با projects |
| `design_project_details` | project_id PK/FK · website_type · services_description · extra_info · internal_notes | 1:0..1 |
| `seo_packages` | id · name · keyword_limit · is_active | تنظیمات |
| `project_keywords` | id · project_id · keyword · status · note | **تعداد کلمات = `COUNT(*)`** (REQ-05.03) |
| `project_assignments` | id · project_id · employee_id · role_on_project · assigned_at | UNIQUE(project_id, employee_id) |

> **نهایی‌سازی پروژه سئو (REQ-06.02):** سرویس `SeoProjectFinalizer` بررسی می‌کند برای هر
> `credential_type` با `is_required_for_seo=1` یک `project_credentials` **کامل** وجود دارد؛
> در غیر این صورت `422` با فهرست موارد ناقص. `is_finalized=false` و `status=draft` متمایز نمایش داده می‌شود.

### ۲.۵ دسترسی‌ها (Credentials)

| جدول | ستون‌ها | نکته |
|---|---|---|
| `credential_types` | id · name · slug · fields_schema JSON · is_required_for_seo · is_active | تعریف سرویس جدید با فیلدهای قابل تنظیم (REQ-06.01) |
| `project_credentials` | id · project_id · credential_type_id · label · login_url · username · password_enc TEXT · key_id · notes · last_revealed_at · timestamps | **هیچ ستون plaintext**؛ `password_enc = v1.<nonce>.<tag>.<cipher>` با AAD=`credential:{id}` |

### ۲.۶ فاکتور، گزارش سئو و زمان‌بندی

| جدول | ستون‌ها | نکته |
|---|---|---|
| `invoices` | id · project_id · period_key VARCHAR(7) (مثل `1405-01`) · planned_send_date · status ENUM(pending,sent,overdue,cancelled) · invoice_number · amount_cents · issued_date · attachment_id? · completed_at · notes | **UNIQUE(project_id, period_key)** — چند دوره مستقل (REQ-12.05) |
| `seo_reports` | id · project_id · period_key · planned_report_date · status ENUM(pending,prepared,sent,overdue) · prepared_at · sent_at · attachment_id? · notes | UNIQUE(project_id, period_key) |
| `scheduled_events` | id · event_type ENUM(invoice_due,report_due,note_due,contract_expiry) · subject_type · subject_id · due_date · fire_date · lead_days · unique_key CHAR(64) UNIQUE · state ENUM(pending,dismissed,done) · processed_at · meta JSON | **کلید idempotency**: `sha256(event_type|subject_id|period|fire_date)` |
| `job_runs` | id · job_name · started_at · finished_at · status ENUM(running,success,failed) · created_count · error TEXT | ثبت خطای Scheduler (REQ-12.07) |

> **محاسبه D-2:** `fire_date = due_date − lead_days` روی **روز تقویمی Asia/Tehran**؛
> چون `due_date` از نوع `DATE` است، ساعت اجرای Job هیچ اثری بر روز محاسبه ندارد (نه ۴۸ ساعت شناور).
> تغییر تاریخ ← `scheduled_events` دوره قبلی `state=dismissed` + `superseded_by_id` و رکورد
> جدید با `fire_date` جدید؛ به‌دلیل `unique_key`، اعلان تکراری ساخته نمی‌شود (REQ-12.06).

### ۲.۷ یادداشت‌ها

| جدول | ستون‌ها | نکته |
|---|---|---|
| `notes` | id · title · body · due_date · priority ENUM(low,normal,high,urgent) · status ENUM(open,done) · done_at · customer_id? · contract_id? · project_id? | تبدیل به `scheduled_events(event_type=note_due)` برای نمایش در کارتابل |

### ۲.۸ پرسنل و حقوق

| جدول | ستون‌ها | نکته |
|---|---|---|
| `employees` | id · user_id? · full_name · national_id(unique) · phone · position · hired_at · employment_status · base_salary_cents | فایل قرارداد در `employee_contracts` |
| `employee_contracts` | id · employee_id · attachment_id · start_at · end_at | نسخه‌دار |
| `payroll_rules` | id · employee_id? (null=عمومی) · effective_from · per_project_cents · count_multiplier DECIMAL(8,4) · bonus_default_cents · overtime_rate DECIMAL(8,4) · deduction_default_cents · advance_limit_cents · version · created_by | **تاریخچه‌دار، مؤثر از تاریخ** — فرمول نهایی منتظر تأیید (Q-08) |
| `payroll_periods` | id · year_jalali · month_jalali · status ENUM(open,calculated,finalized) | UNIQUE(year_jalali,month_jalali) |
| `payroll_items` | id · period_id · employee_id · project_count · base_cents · project_amount_cents · bonus_cents · overtime_cents · deduction_cents · advance_cents · net_cents | UNIQUE(period_id, employee_id) |
| `payslips` | id · period_id · employee_id · status ENUM(draft,final) · finalized_at · finalized_by | پس از Final تغییر ممنوع |
| `payslip_versions` | id · payslip_id · version_no · items_snapshot JSON · reason · created_by · created_at | اصلاحیه = نسخه جدید |

#### ۲.۸.۱ ستون‌های **پیشنهادی** برای پوشش کامل انواع پرداخت

> **وضعیت:** این‌ها **الزام سند کارفرما نیستند**. سند فقط «حقوق و دستمزد» را به‌صورت کلی
> خواسته. موارد زیر پیشنهاد تیم برای پاسخ‌دادن به انواع رایج پرداخت در یک آژانس است
> و **تا تأیید کارفرما (Q-08) در migration اجرا نمی‌شوند**. هیچ فرمولی هم در این‌جا
> تعریف نشده — فقط ظرفیت مدل داده.

| جدول | ستون پیشنهادی | چرا لازم است (سناریو) | الزام مرتبط |
|---|---|---|---|
| `employees` | `pay_type` ENUM(fixed,hourly,project,commission,mixed) | حقوق ثابت، ساعتی، پروژه‌ای و پورسانتی هم‌زمان در یک آژانس رخ می‌دهد | REQ-09.01 |
| `employees` | `hourly_rate_cents` | برای همکار پارت‌تایم که فقط ساعت کار می‌کند | REQ-09.01 |
| `employees` | `payment_method` ENUM(bank,cash,other) + `bank_iban` | فیش حقوقی باید مقصد پرداخت را نشان دهد | REQ-09.03 |
| `employee_timesheets` *(جدید)* | id · employee_id · work_date · hours DECIMAL(5,2) · project_id? · note · approved_by · approved_at | مبنای حقوق ساعتی؛ بدون آن `hourly` قابل محاسبه نیست | REQ-09.01 |
| `payroll_rules` | `commission_rate` DECIMAL(6,4) | درصد پورسانت از مبلغ قرارداد/پروژه (مقدار را کارفرما تعیین می‌کند) | REQ-09.02 |
| `payroll_rules` | `commission_base` ENUM(contract_amount,invoice_paid,project_amount) | پورسانت روی «مبلغ قرارداد» یا «فاکتور پرداخت‌شده» محاسبه شود؟ **تفاوت مالی بزرگ** | REQ-09.02 |
| `payroll_rules` | `overtime_rate` DECIMAL(8,4) — موجود | ضریب اضافه‌کاری | REQ-09.02 |
| `payroll_rules` | `overtime_basis` ENUM(hourly,daily,monthly) | اضافه‌کاری از نرخ ساعتی یا روزانه محاسبه شود؟ | REQ-09.02 |
| `payroll_items` | `hours_worked` DECIMAL(6,2) · `overtime_hours` DECIMAL(6,2) | ورودی‌های محاسبه در snapshot فیش بمانند | REQ-09.03 |
| `payroll_items` | `commission_base_amount_cents` · `commission_rate_applied` | شفافیت: «۴٪ از ۲۰۰ میلیون» به‌جای یک عدد جادویی | REQ-09.03 |
| `payroll_items` | `advance_balance_cents` | مانده مساعده پس از کسر | REQ-09.04 |
| `payroll_items` | `calc_inputs_snapshot` JSON | همه ورودی‌های محاسبه، برای بازتولید نتیجه یک سال بعد | REQ-09.05 |
| `employee_advances` *(جدید)* | id · employee_id · amount_cents · requested_at · paid_at · installment_count · settled_at · note | مساعده یک موجودیت مستقل با چرخه عمر است، نه یک عدد در فیش | REQ-09.04 |

**دو نکته طراحی که باید کارفرما بداند:**

1. **`commission_base` مهم‌ترین تصمیم مالی این جدول است.** اگر پورسانت روی «مبلغ قرارداد»
   باشد، کارشناس فروش حتی اگر مشتری پرداخت نکند پورسانت می‌گیرد. اگر روی «فاکتور
   پرداخت‌شده» باشد، پورسانت با نقدینگی هم‌راستا می‌شود. **این تصمیم تیم فنی نیست.**
2. **`employee_timesheets` یک جدول جدید است (شمارش ۴۰ → ۴۱).** چون اگر اضافه شود،
   شمارش جداول تغییر می‌کند و باید در سند و ERD هم‌زمان اصلاح شود. تا تأیید نشدن،
   شمارش رسمی همان **۴۰** است.

**آنچه عمداً تعریف نشده:**
- هیچ فرمول (`net = base + ...`) در این سند نیست. فرمول‌ها در سرویس `PayrollCalculator`
  و **پس از** دریافت قواعد واقعی کارفرما نوشته می‌شوند.
- هیچ نرخ مالیات/بیمه‌ای فرض نشده (در ایران متغیر و مبتنی بر قانون سالانه است).
- هیچ گردکردنی (rounding) فرض نشده؛ در سیستم پولی از `bcmath` و «ریال صحیح» استفاده می‌شود.

### ۲.۹ فایل، Audit، تنظیمات

| جدول | ستون‌ها | نکته |
|---|---|---|
| `attachments` | id · disk · path · original_name · stored_name · mime · size · sha256 · visibility ENUM(private,public) · attachable_type/id · uploaded_by · timestamps | چندریختی؛ پیش‌فرض private |
| `audit_logs` | id · user_id? · action · entity_type · entity_id · ip · user_agent · meta JSON · created_at | append-only؛ بدون `updated_at` |
| `settings` | id · key(unique) · value JSON · type · updated_by · updated_at | تنظیمات سند بخش ۲۰ |
| `settings_history` | id · setting_id · old_value · new_value · changed_by · changed_at | تغییر تنظیمات قابل ردیابی |
| `failed_jobs` | استاندارد Laravel | خطاهای Job |

## ۳. ایندکس‌های ضروری

| جدول | ایندکس | دلیل |
|---|---|---|
| `customers` | `idx_type_archived` · `uniq_national_id` · `idx_phone_primary` | فیلتر پرونده، جستجو، ضدتکرار |
| `customer_websites` | `idx_domain` | جستجوی دامنه |
| `contracts` | `idx_customer_status` · `idx_status_end_date` | پرونده مشتری، هشدار انقضا |
| `projects` | `idx_customer_type` · `idx_owner_status` | فیلتر پروژه |
| `invoices` / `seo_reports` | `uniq(project_id, period_key)` · `idx_status_planned` | دوره مستقل، کارتابل |
| `scheduled_events` | `uniq(unique_key)` · `idx_state_fire_date` | Idempotency + کارتابل |
| `project_keywords` | `idx_project_id` | شمارش کلمات |
| `audit_logs` | `idx_entity` · `idx_user_created` | بررسی سوابق |
| `project_credentials` | `idx_project_type` | نهایی‌سازی سئو |
| `employees` | `uniq_national_id` · `idx_employment_status` | ضدتکرار، فیلتر پرسنل فعال |
| `payroll_periods` | `uniq(year_jalali, month_jalali)` | یک دوره در هر ماه |
| `payroll_items` | `uniq(period_id, employee_id)` · `idx_employee_period` | یک فیش در هر دوره، تاریخچه کارمند |
| `payslips` | `uniq(period_id, employee_id)` · `idx_status` | جلوگیری از فیش تکراری |
| `payslip_versions` | `uniq(payslip_id, version_no)` | نسخه‌بندی بدون تکرار |
| `notes` | `idx_due_status` · `idx_customer` · `idx_project` | کارتابل، پرونده مشتری/پروژه |
| `contract_versions` | `uniq(contract_id, version_no)` | نسخه نهایی یکتا |
| `contract_files` | `idx_contract` | فهرست فایل قرارداد |
| `attachments` | `idx_attachable` · `uniq_sha256_path` | پیوست چندریختی، ضدتکرار فایل |
| `job_runs` | `idx_job_started` | بررسی شکست‌های Scheduler |
| `settings` | `uniq_key` | دسترسی O(1) به تنظیمات |
| `users` | `uniq_email` · `idx_is_active` | ورود، فهرست |
| `sessions` | `idx_user_id` · `idx_last_activity` | خروج همه دستگاه‌ها |
| `failed_jobs` | `uniq_uuid` | Laravel |
| `customer_contacts` | `uniq(customer_id, phone)` | تماس تکراری در یک مشتری |
| `project_assignments` | `uniq(project_id, employee_id)` | تخصیص تکراری |

### ۳.۱ قواعد ایندکس که رعایت شده‌اند

| قاعده | دلیل |
|---|---|
| هر ستون FK ایندکس دارد | MySQL برای FK ایندکس می‌سازد، ولی ایندکس مرکب برای کوئری‌های واقعی لازم است |
| ایندکس مرکب با ستون گزینشی‌تر **اول** | `(customer_id, status)` نه `(status, customer_id)` |
| هیچ ایندکس روی ستون `TEXT` بدون پیشوند | جلوگیری از ایندکس غول‌آسا |
| ایندکس روی `archived_at` ندارد | فیلتر آرشیو در کوئری حذف می‌شود (`WHERE archived_at IS NULL`) و ایندکس مرکب `idx_type_archived` کافی است |
| `unique_key` از نوع `CHAR(64)` (hex) | ذخیره بهینه‌تر از `VARCHAR(255)` |

### ۳.۲ تصمیم‌های حذف/نگهداری جدول (شفاف، بدون حذف بی‌صدا)

| جدول | تصمیم | دلیل |
|---|---|---|
| `credential_requirements` | **حذف شد** (به ستون تبدیل) | رابطه یک‌به‌یک با `credential_types`؛ جدول جدا فقط JOIN اضافه می‌کرد |
| `project_types` | **حذف شد** (به `ENUM` تبدیل) | سند فقط دو نوع دارد؛ Q-33 باز است تا اگر کارفرما قابل‌افزودن خواست بازبینی شود |
| `reminders` (سند پیشنهادی) | **جایگزین شد** با `scheduled_events` + `job_runs` | idempotency و ردیابی اجرا لازم بود (REQ-12.07) |
| `cache`, `cache_locks` | **ساخته نمی‌شود** | کش `file`؛ اگر Redis آمد، تصمیم بازبینی می‌شود |
| `jobs`, `job_batches` | **ساخته نمی‌شود** | صف `sync` در نسخه ۱ (Q-34) |
| `employee_timesheets`, `employee_advances` | **پیشنهاد، ساخته نمی‌شود** | نیازمند تأیید Q-41 و Q-08 |

## ۴. چرا ۴۰ جدول؟

| دسته | جداول | توضیح |
|---|---|---|
| سند پیشنهادی (۲۴) | users, roles, permissions, role_permissions, user_roles, customers, customer_contacts, customer_websites, contracts, contract_templates, contract_versions, contract_files, projects, project_keywords, project_assignments, project_credentials, credential_types, invoices, seo_reports, notes, employees, employee_contracts, payroll_rules, payslip_versions* | نگاشت مستقیم |
| افزوده (۱۶) | contract_types, seo_packages, seo_project_details, design_project_details, scheduled_events, job_runs, payroll_periods, payroll_items, payslips, attachments, audit_logs, settings, settings_history, sessions, password_reset_tokens, failed_jobs | لازم برای «قابل تعریف در تنظیمات»، «Idempotent»، «نسخه‌دار» و امنیت |
| **جمع** | **۴۰ = ۲۴ + ۱۶** | شمارش دقیق؛ با فهرست بخش ۲ و نام‌گذاری ERD یکی است |

\* `payslip_versions` در سند پیشنهادی هست ولی `payslips` و `payroll_items`/`payroll_periods` جدا شده‌اند.

> **تفکیک جدول‌های چارچوب از جدول‌های دامنه:** چهار جدول از این ۴۰ جدول، متعلق به خود Laravel
> هستند و منطق دامنه ندارند: `sessions`، `password_reset_tokens`، `failed_jobs` و ستون‌های
> چارچوب در `users`. این‌ها با `php artisan migrate` ساخته می‌شوند و در ERD با رنگ/یادداشت جدا
> مشخص شده‌اند. بنابراین «۳۶ جدول دامنه + ۴ جدول چارچوب = ۴۰» خوانش درست است.
>
> **جدول‌هایی که عمداً ساخته نمی‌شوند:** `cache`/`cache_locks` (درایور کش = `file`)،
> `jobs`/`job_batches` (صف = `sync` در نسخه ۱؛ اگر کارفرما صف دیتابیسی بخواهد، هر دو جدول
> اضافه می‌شوند و شمارش به ۴۲ می‌رسد — این یک تصمیم باز است، Q-34).

## ۵. داده‌های حساس

| داده | ذخیره‌سازی |
|---|---|
| رمز دسترسی پروژه | AES-256-GCM (`password_enc`) + AAD + `key_id` |
| رمز کاربر | bcrypt/argon2 hash |
| کلید رمزنگاری | `.env` (`CREDENTIALS_KEYS=v1:...`) خارج از DB و Repository |
| شناسه ملی/کد ملی | متن (با صفر ابتدایی)، فقط برای کاربر مجاز، در لیست‌ها ماسک جزئی |
| اطلاعات مالی | ستون‌های `*_cents`، دسترسی مجوز `payroll.*`/`reports.financial` |
| فایل محرمانه | Storage خصوصی، نام uuid، تحویل با کنترلر + Audit |
