# VIRA CRM — طراحی دیتابیس (فاز ۱)

| مورد | مقدار |
|---|---|
| نسخه | 0.1-draft |
| وضعیت | پیش‌نویس طراحی — وابسته به تأیید فاز ۱ |
| موتور مقصد | MySQL 8.0+ / MariaDB 10.6+ · Charset `utf8mb4` · Collation `utf8mb4_0900_ai_ci` (یا `utf8mb4_unicode_ci` در MariaDB) |
| موتور تست | SQLite (`:memory:`) — Migrationها سازگار با هر دو |
| نمودار | [`erd.png`](../diagrams/erd.png) · منبع [`erd.dot`](../database/erd.dot) |
| تعداد جداول | **۴۱** (۲۴ جدول پیشنهادی سند + ۱۷ جدول لازم برای دامنه واقعی) |

> **اصل:** سند کارفرما فهرست پیشنهادی ۲۴ جدول داده است. این طرح بر اساس روابط واقعی
> بازطراحی شده: جدول `invoices` و `seo_reports` به **رکوردهای دوره‌ای مستقل** تبدیل شده‌اند
> (REQ-12.05)، موتور یادآوری از `reminders` به `scheduled_events` + `job_runs` منتقل شده
> (REQ-12.07)، و جداول `contract_types`/`project_types`/`seo_packages`/`credential_requirements`
> که سند به‌صورت «قابل تعریف در تنظیمات» خواسته، به جداول مرجع تبدیل شده‌اند.

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

## ۴. چرا ۴۱ جدول؟

| دسته | جداول | توضیح |
|---|---|---|
| سند پیشنهادی (۲۴) | users, roles, permissions, role_permissions, user_roles, customers, customer_contacts, customer_websites, contracts, contract_templates, contract_versions, contract_files, projects, project_keywords, project_assignments, project_credentials, credential_types, invoices, seo_reports, notes, employees, employee_contracts, payroll_rules, payslip_versions* | نگاشت مستقیم |
| افزوده (۱۷) | credential_requirements→`is_required_for_seo` (ستون)، contract_types, seo_packages, seo_project_details, design_project_details, scheduled_events, job_runs, payroll_periods, payroll_items, payslips, attachments, audit_logs, settings, settings_history, sessions, password_reset_tokens, failed_jobs | لازم برای «قابل تعریف در تنظیمات»، «Idempotent»، «نسخه‌دار» و امنیت |

\* `payslip_versions` در سند پیشنهادی هست ولی `payslips` و `payroll_items`/`payroll_periods` جدا شده‌اند.

## ۵. داده‌های حساس

| داده | ذخیره‌سازی |
|---|---|
| رمز دسترسی پروژه | AES-256-GCM (`password_enc`) + AAD + `key_id` |
| رمز کاربر | bcrypt/argon2 hash |
| کلید رمزنگاری | `.env` (`CREDENTIALS_KEYS=v1:...`) خارج از DB و Repository |
| شناسه ملی/کد ملی | متن (با صفر ابتدایی)، فقط برای کاربر مجاز، در لیست‌ها ماسک جزئی |
| اطلاعات مالی | ستون‌های `*_cents`، دسترسی مجوز `payroll.*`/`reports.financial` |
| فایل محرمانه | Storage خصوصی، نام uuid، تحویل با کنترلر + Audit |
