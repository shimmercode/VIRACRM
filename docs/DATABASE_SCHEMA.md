# ساختار پایگاه داده

> **این فایل تولید می‌شود.** با `node tools/generate-schema-doc.mjs` از
> `database/database.sqlite` بازتولید می‌شود. دستی ویرایش نکنید؛ تغییر در
> مهاجرت‌ها بدهید و دوباره تولید کنید.

منبع حقیقت، مهاجرت‌های `database/migrations/` هستند. این سند آن‌ها را همان‌طور
می‌خواند که اپلیکیشن می‌خواند.

## آنچه SQLite بیان نمی‌کند

این نکته را قبل از نوشتن هر کوئری بخوانید:

1. **نوع ستون تزئینی است.** SQLite هر ستون را می‌پذیرد؛ `integer` فقط یک
   affinity است. اعتبارسنجی واقعی در لاراول انجام می‌شود.
2. **هیچ `ENUM` وجود ندارد.** مقادیری مثل `contracts.status` یا
   `payslips.state` در PHP به enum تبدیل می‌شوند (`App\Enums\*`) و همان‌جا
   فهرست مجاز تعریف می‌شود.
3. **`CHECK` تقریباً هیچ‌جا نیست.** قواعدی مثل «مبلغ منفی نباشد» در سرویس‌ها
   هستند، نه در پایگاه داده.
4. **`UNSIGNED` معنا ندارد.** این یک تلهٔ واقعی بوده: `payroll_items.amount`
   در MySQL علامت‌دار نیست، پس جهت (دریافتی/کسورات) یک **ستون** است
   (`direction`)، نه علامت عدد. روی SQLite عدد منفی کار می‌کند و روی
   MariaDB خطای out-of-range می‌دهد.
5. **حذف نرم.** جدول‌هایی با ستون `deleted_at` هرگز واقعاً حذف نمی‌شوند.
   شماره‌گذاری قرارداد عمداً ردیف‌های حذف‌شده را هم می‌شمارد تا شمارهٔ تکراری
   صادر نشود.

## فهرست جدول‌ها

## هویت و دسترسی

### `users`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `name` | varchar | خیر | — |  |
| `username` | varchar | خیر | — |  |
| `email` | varchar | بله | — |  |
| `mobile` | varchar | بله | — |  |
| `password` | varchar | خیر | — |  |
| `is_superadmin` | tinyint(1) | خیر | `'0'` |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `last_login_at` | datetime | بله | — |  |
| `last_login_ip` | varchar | بله | — |  |
| `failed_login_attempts` | INTEGER | خیر | `'0'` |  |
| `locked_until` | datetime | بله | — |  |
| `password_changed_at` | datetime | بله | — |  |
| `must_change_password` | tinyint(1) | خیر | `'0'` |  |
| `remember_token` | varchar | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
users_is_active_index	CREATE INDEX "users_is_active_index" on "users" ("is_active")
```
```sql
users_username_unique	CREATE UNIQUE INDEX "users_username_unique" on "users" ("username")
```
```sql
users_email_unique	CREATE UNIQUE INDEX "users_email_unique" on "users" ("email")
```
```sql
users_mobile_unique	CREATE UNIQUE INDEX "users_mobile_unique" on "users" ("mobile")
```

### `roles`

ردیف‌های موجود در پایگاه نمونه: **4**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `description` | TEXT | بله | — |  |
| `is_system` | tinyint(1) | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
roles_code_unique	CREATE UNIQUE INDEX "roles_code_unique" on "roles" ("code")
```

### `permissions`

ردیف‌های موجود در پایگاه نمونه: **55**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `area` | varchar | خیر | — |  |
| `action` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `is_sensitive` | tinyint(1) | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
permissions_area_index	CREATE INDEX "permissions_area_index" on "permissions" ("area")
```
```sql
permissions_code_unique	CREATE UNIQUE INDEX "permissions_code_unique" on "permissions" ("code")
```

### `role_permissions`

ردیف‌های موجود در پایگاه نمونه: **81**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `role_id` | INTEGER | خیر | — |  |
| `permission_id` | INTEGER | خیر | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `permission_id.id → permissions.NO ACTION`، `role_id.id → roles.NO ACTION`

**ایندکس‌ها:**

```sql
role_permissions_role_id_permission_id_unique	CREATE UNIQUE INDEX "role_permissions_role_id_permission_id_unique" on "role_permissions" ("role_id", "permission_id")
```

### `user_roles`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `user_id` | INTEGER | خیر | — |  |
| `role_id` | INTEGER | خیر | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `role_id.id → roles.NO ACTION`، `user_id.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
user_roles_user_id_role_id_unique	CREATE UNIQUE INDEX "user_roles_user_id_role_id_unique" on "user_roles" ("user_id", "role_id")
```

### `password_reset_tokens`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `email` | varchar | خیر | — | PK |
| `token` | varchar | خیر | — |  |
| `created_at` | datetime | بله | — |  |

### `sessions`

ردیف‌های موجود در پایگاه نمونه: **1**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | varchar | خیر | — | PK |
| `user_id` | INTEGER | بله | — |  |
| `ip_address` | varchar | بله | — |  |
| `user_agent` | TEXT | بله | — |  |
| `payload` | TEXT | خیر | — |  |
| `last_activity` | INTEGER | خیر | — |  |

**ایندکس‌ها:**

```sql
sessions_user_id_index	CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id")
```
```sql
sessions_last_activity_index	CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity")
```

## مشتریان

### `customers`

ردیف‌های موجود در پایگاه نمونه: **7**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | بله | — |  |
| `type` | varchar | خیر | — |  |
| `first_name` | varchar | بله | — |  |
| `last_name` | varchar | بله | — |  |
| `father_name` | varchar | بله | — |  |
| `national_id` | varchar | بله | — |  |
| `birth_date` | date | بله | — |  |
| `company_name` | varchar | بله | — |  |
| `registration_number` | varchar | بله | — |  |
| `economic_code` | varchar | بله | — |  |
| `legal_representative` | varchar | بله | — |  |
| `mobile` | varchar | بله | — |  |
| `phone` | varchar | بله | — |  |
| `email` | varchar | بله | — |  |
| `address` | TEXT | بله | — |  |
| `postal_code` | varchar | بله | — |  |
| `city` | varchar | بله | — |  |
| `province` | varchar | بله | — |  |
| `acquaintance_source` | varchar | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `status` | varchar | خیر | `'lead'` |  |
| `owner_user_id` | INTEGER | بله | — |  |
| `search_key` | varchar | بله | — |  |
| `normalized_mobile` | varchar | بله | — |  |
| `normalized_national_id` | varchar | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `owner_user_id.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
customers_type_status_index	CREATE INDEX "customers_type_status_index" on "customers" ("type", "status")
```
```sql
customers_last_name_first_name_index	CREATE INDEX "customers_last_name_first_name_index" on "customers" ("last_name", "first_name")
```
```sql
customers_company_name_index	CREATE INDEX "customers_company_name_index" on "customers" ("company_name")
```
```sql
customers_code_unique	CREATE UNIQUE INDEX "customers_code_unique" on "customers" ("code")
```
```sql
customers_type_index	CREATE INDEX "customers_type_index" on "customers" ("type")
```
```sql
customers_status_index	CREATE INDEX "customers_status_index" on "customers" ("status")
```
```sql
customers_search_key_index	CREATE INDEX "customers_search_key_index" on "customers" ("search_key")
```
```sql
customers_normalized_mobile_index	CREATE INDEX "customers_normalized_mobile_index" on "customers" ("normalized_mobile")
```
```sql
customers_normalized_national_id_index	CREATE INDEX "customers_normalized_national_id_index" on "customers" ("normalized_national_id")
```

### `customer_contacts`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `customer_id` | INTEGER | خیر | — |  |
| `full_name` | varchar | خیر | — |  |
| `position` | varchar | بله | — |  |
| `mobile` | varchar | بله | — |  |
| `phone` | varchar | بله | — |  |
| `email` | varchar | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `is_primary` | tinyint(1) | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
customer_contacts_customer_id_index	CREATE INDEX "customer_contacts_customer_id_index" on "customer_contacts" ("customer_id")
```

### `customer_websites`

ردیف‌های موجود در پایگاه نمونه: **6**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `customer_id` | INTEGER | خیر | — |  |
| `url` | varchar | خیر | — |  |
| `domain` | varchar | خیر | — |  |
| `label` | varchar | بله | — |  |
| `is_primary` | tinyint(1) | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
customer_websites_customer_id_is_primary_index	CREATE INDEX "customer_websites_customer_id_is_primary_index" on "customer_websites" ("customer_id", "is_primary")
```
```sql
customer_websites_domain_index	CREATE INDEX "customer_websites_domain_index" on "customer_websites" ("domain")
```

## پروژه‌ها

### `projects`

ردیف‌های موجود در پایگاه نمونه: **3**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | بله | — |  |
| `customer_id` | INTEGER | خیر | — |  |
| `contract_id` | INTEGER | بله | — |  |
| `type` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `status` | varchar | خیر | `'draft'` |  |
| `amount` | INTEGER | خیر | `'0'` |  |
| `start_date` | date | بله | — |  |
| `end_date` | date | بله | — |  |
| `owner_user_id` | INTEGER | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `owner_user_id.id → users.NO ACTION`، `contract_id.id → contracts.NO ACTION`، `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
projects_customer_id_type_index	CREATE INDEX "projects_customer_id_type_index" on "projects" ("customer_id", "type")
```
```sql
projects_type_status_index	CREATE INDEX "projects_type_status_index" on "projects" ("type", "status")
```
```sql
projects_code_unique	CREATE UNIQUE INDEX "projects_code_unique" on "projects" ("code")
```
```sql
projects_type_index	CREATE INDEX "projects_type_index" on "projects" ("type")
```
```sql
projects_status_index	CREATE INDEX "projects_status_index" on "projects" ("status")
```

### `project_types`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
project_types_code_unique	CREATE UNIQUE INDEX "project_types_code_unique" on "project_types" ("code")
```

### `project_assignments`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_id` | INTEGER | خیر | — |  |
| `employee_id` | INTEGER | خیر | — |  |
| `role` | varchar | بله | — |  |
| `amount` | INTEGER | خیر | `'0'` |  |
| `coefficient` | INTEGER | خیر | `'100'` |  |
| `started_on` | date | بله | — |  |
| `ended_on` | date | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `employee_id.id → employees.NO ACTION`، `project_id.id → projects.NO ACTION`

**ایندکس‌ها:**

```sql
project_assignments_project_id_employee_id_unique	CREATE UNIQUE INDEX "project_assignments_project_id_employee_id_unique" on "project_assignments" ("project_id", "employee_id")
```
```sql
project_assignments_employee_id_is_active_index	CREATE INDEX "project_assignments_employee_id_is_active_index" on "project_assignments" ("employee_id", "is_active")
```

### `project_keywords`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_id` | INTEGER | خیر | — |  |
| `keyword` | varchar | خیر | — |  |
| `normalized` | varchar | خیر | — |  |
| `status` | varchar | خیر | `'pending'` |  |
| `notes` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `project_id.id → projects.NO ACTION`

**ایندکس‌ها:**

```sql
project_keywords_project_id_index	CREATE INDEX "project_keywords_project_id_index" on "project_keywords" ("project_id")
```
```sql
project_keywords_normalized_index	CREATE INDEX "project_keywords_normalized_index" on "project_keywords" ("normalized")
```

### `seo_packages`

ردیف‌های موجود در پایگاه نمونه: **3**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `description` | TEXT | بله | — |  |
| `keyword_limit` | INTEGER | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `sort_order` | INTEGER | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
seo_packages_code_unique	CREATE UNIQUE INDEX "seo_packages_code_unique" on "seo_packages" ("code")
```

### `seo_project_details`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_id` | INTEGER | خیر | — |  |
| `seo_package_id` | INTEGER | بله | — |  |
| `keyword_count` | INTEGER | خیر | `'0'` |  |
| `recurrence` | varchar | بله | — |  |
| `reminder_offset_days` | INTEGER | خیر | `'2'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `seo_package_id.id → seo_packages.NO ACTION`، `project_id.id → projects.NO ACTION`

**ایندکس‌ها:**

```sql
seo_project_details_project_id_unique	CREATE UNIQUE INDEX "seo_project_details_project_id_unique" on "seo_project_details" ("project_id")
```

### `design_project_details`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_id` | INTEGER | خیر | — |  |
| `domain` | varchar | بله | — |  |
| `site_type` | varchar | بله | — |  |
| `services` | TEXT | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `project_id.id → projects.NO ACTION`

**ایندکس‌ها:**

```sql
design_project_details_project_id_unique	CREATE UNIQUE INDEX "design_project_details_project_id_unique" on "design_project_details" ("project_id")
```

## قراردادها

### `contracts`

ردیف‌های موجود در پایگاه نمونه: **5**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `number` | varchar | بله | — |  |
| `customer_id` | INTEGER | خیر | — |  |
| `contract_type_id` | INTEGER | بله | — |  |
| `template_id` | INTEGER | بله | — |  |
| `title` | varchar | خیر | — |  |
| `description` | TEXT | بله | — |  |
| `party_one_name` | varchar | بله | — |  |
| `party_two_name` | varchar | بله | — |  |
| `party_one_address` | TEXT | بله | — |  |
| `party_two_address` | TEXT | بله | — |  |
| `amount` | INTEGER | خیر | `'0'` |  |
| `start_date` | date | خیر | — |  |
| `end_date` | date | بله | — |  |
| `status` | varchar | خیر | `'draft'` |  |
| `rendered_body` | TEXT | بله | — |  |
| `finalized_at` | datetime | بله | — |  |
| `finalized_by` | INTEGER | بله | — |  |
| `renewed_from_id` | INTEGER | بله | — |  |
| `owner_user_id` | INTEGER | بله | — |  |
| `internal_notes` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `owner_user_id.id → users.NO ACTION`، `renewed_from_id.id → contracts.NO ACTION`، `finalized_by.id → users.NO ACTION`، `template_id.id → contract_templates.NO ACTION`، `contract_type_id.id → contract_types.NO ACTION`، `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
contracts_status_end_date_index	CREATE INDEX "contracts_status_end_date_index" on "contracts" ("status", "end_date")
```
```sql
contracts_customer_id_status_index	CREATE INDEX "contracts_customer_id_status_index" on "contracts" ("customer_id", "status")
```
```sql
contracts_end_date_index	CREATE INDEX "contracts_end_date_index" on "contracts" ("end_date")
```
```sql
contracts_number_unique	CREATE UNIQUE INDEX "contracts_number_unique" on "contracts" ("number")
```
```sql
contracts_status_index	CREATE INDEX "contracts_status_index" on "contracts" ("status")
```

### `contract_types`

ردیف‌های موجود در پایگاه نمونه: **4**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `description` | TEXT | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `sort_order` | INTEGER | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
contract_types_code_unique	CREATE UNIQUE INDEX "contract_types_code_unique" on "contract_types" ("code")
```

### `contract_templates`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `contract_type_id` | INTEGER | بله | — |  |
| `title` | varchar | خیر | — |  |
| `body` | TEXT | خیر | — |  |
| `version` | INTEGER | خیر | `'1'` |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `is_default` | tinyint(1) | خیر | `'0'` |  |
| `created_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `created_by.id → users.NO ACTION`، `contract_type_id.id → contract_types.NO ACTION`

**ایندکس‌ها:**

```sql
contract_templates_contract_type_id_is_active_index	CREATE INDEX "contract_templates_contract_type_id_is_active_index" on "contract_templates" ("contract_type_id", "is_active")
```

### `contract_versions`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `contract_id` | INTEGER | خیر | — |  |
| `version` | INTEGER | خیر | `'1'` |  |
| `body` | TEXT | خیر | — |  |
| `snapshot` | TEXT | بله | — |  |
| `reason` | varchar | خیر | `'finalize'` |  |
| `created_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `created_by.id → users.NO ACTION`، `contract_id.id → contracts.NO ACTION`

**ایندکس‌ها:**

```sql
contract_versions_contract_id_version_unique	CREATE UNIQUE INDEX "contract_versions_contract_id_version_unique" on "contract_versions" ("contract_id", "version")
```

### `contract_files`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `contract_id` | INTEGER | خیر | — |  |
| `attachment_id` | INTEGER | بله | — |  |
| `label` | varchar | بله | — |  |
| `kind` | varchar | خیر | `'scan'` |  |
| `uploaded_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `uploaded_by.id → users.NO ACTION`، `contract_id.id → contracts.NO ACTION`

**ایندکس‌ها:**

```sql
contract_files_contract_id_index	CREATE INDEX "contract_files_contract_id_index" on "contract_files" ("contract_id")
```

## دسترسی‌های فنی

### `project_credentials`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_id` | INTEGER | خیر | — |  |
| `credential_type_id` | INTEGER | خیر | — |  |
| `label` | varchar | بله | — |  |
| `public_url` | varchar | بله | — |  |
| `username` | varchar | بله | — |  |
| `extra_fields` | TEXT | بله | — |  |
| `is_complete` | tinyint(1) | خیر | `'0'` |  |
| `notes` | TEXT | بله | — |  |
| `updated_by` | INTEGER | بله | — |  |
| `rotated_at` | datetime | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `updated_by.id → users.NO ACTION`، `credential_type_id.id → credential_types.NO ACTION`، `project_id.id → projects.NO ACTION`

**ایندکس‌ها:**

```sql
project_credentials_project_id_credential_type_id_index	CREATE INDEX "project_credentials_project_id_credential_type_id_index" on "project_credentials" ("project_id", "credential_type_id")
```

### `credential_types`

ردیف‌های موجود در پایگاه نمونه: **6**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `fields_schema` | TEXT | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `sort_order` | INTEGER | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
credential_types_code_unique	CREATE UNIQUE INDEX "credential_types_code_unique" on "credential_types" ("code")
```

### `credential_values`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_credential_id` | INTEGER | خیر | — |  |
| `field` | varchar | خیر | `'password'` |  |
| `ciphertext` | TEXT | خیر | — |  |
| `fingerprint` | varchar | بله | — |  |
| `created_at` | datetime | خیر | `CURRENT_TIMESTAMP` |  |

**کلیدهای خارجی:** `project_credential_id.id → project_credentials.NO ACTION`

**ایندکس‌ها:**

```sql
credential_values_project_credential_id_field_unique	CREATE UNIQUE INDEX "credential_values_project_credential_id_field_unique" on "credential_values" ("project_credential_id", "field")
```

### `credential_requirements`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_type` | varchar | خیر | `'seo'` |  |
| `credential_type_id` | INTEGER | خیر | — |  |
| `is_required` | tinyint(1) | خیر | `'1'` |  |
| `blocks_finalize` | tinyint(1) | خیر | `'1'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `credential_type_id.id → credential_types.NO ACTION`

**ایندکس‌ها:**

```sql
credential_requirements_project_type_credential_type_id_unique	CREATE UNIQUE INDEX "credential_requirements_project_type_credential_type_id_unique" on "credential_requirements" ("project_type", "credential_type_id")
```

## حسابداری

### `ledger_accounts`

ردیف‌های موجود در پایگاه نمونه: **41**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `parent_id` | INTEGER | بله | — |  |
| `level` | INTEGER | خیر | `'1'` |  |
| `type` | varchar | خیر | — |  |
| `normal_balance` | varchar | خیر | — |  |
| `is_postable` | tinyint(1) | خیر | `'1'` |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `is_system` | tinyint(1) | خیر | `'0'` |  |
| `description` | varchar | بله | — |  |
| `sort_order` | INTEGER | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `parent_id.id → ledger_accounts.NO ACTION`

**ایندکس‌ها:**

```sql
ledger_accounts_type_is_active_index	CREATE INDEX "ledger_accounts_type_is_active_index" on "ledger_accounts" ("type", "is_active")
```
```sql
ledger_accounts_code_unique	CREATE UNIQUE INDEX "ledger_accounts_code_unique" on "ledger_accounts" ("code")
```
```sql
ledger_accounts_type_index	CREATE INDEX "ledger_accounts_type_index" on "ledger_accounts" ("type")
```

### `fiscal_periods`

ردیف‌های موجود در پایگاه نمونه: **3**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `starts_on` | date | خیر | — |  |
| `ends_on` | date | خیر | — |  |
| `state` | varchar | خیر | `'open'` |  |
| `closed_at` | datetime | بله | — |  |
| `closed_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `closed_by.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
fiscal_periods_code_unique	CREATE UNIQUE INDEX "fiscal_periods_code_unique" on "fiscal_periods" ("code")
```
```sql
fiscal_periods_state_index	CREATE INDEX "fiscal_periods_state_index" on "fiscal_periods" ("state")
```

### `journal_entries`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `number` | varchar | خیر | — |  |
| `fiscal_period_id` | INTEGER | خیر | — |  |
| `entry_date` | date | خیر | — |  |
| `memo` | varchar | خیر | — |  |
| `source_type` | varchar | خیر | — |  |
| `source_id` | INTEGER | بله | — |  |
| `status` | varchar | خیر | `'draft'` |  |
| `posted_at` | datetime | بله | — |  |
| `posted_by` | INTEGER | بله | — |  |
| `reverses_entry_id` | INTEGER | بله | — |  |
| `created_by` | INTEGER | بله | — |  |
| `reversed_by_entry_id` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `created_by.id → users.NO ACTION`، `reverses_entry_id.id → journal_entries.NO ACTION`، `posted_by.id → users.NO ACTION`، `fiscal_period_id.id → fiscal_periods.NO ACTION`

**ایندکس‌ها:**

```sql
journal_entries_source_unique	CREATE UNIQUE INDEX "journal_entries_source_unique" on "journal_entries" ("source_type", "source_id")
```
```sql
journal_entries_status_entry_date_index	CREATE INDEX "journal_entries_status_entry_date_index" on "journal_entries" ("status", "entry_date")
```
```sql
journal_entries_number_unique	CREATE UNIQUE INDEX "journal_entries_number_unique" on "journal_entries" ("number")
```
```sql
journal_entries_entry_date_index	CREATE INDEX "journal_entries_entry_date_index" on "journal_entries" ("entry_date")
```
```sql
journal_entries_status_index	CREATE INDEX "journal_entries_status_index" on "journal_entries" ("status")
```

### `journal_lines`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `journal_entry_id` | INTEGER | خیر | — |  |
| `ledger_account_id` | INTEGER | خیر | — |  |
| `debit` | INTEGER | خیر | `'0'` |  |
| `credit` | INTEGER | خیر | `'0'` |  |
| `description` | varchar | بله | — |  |
| `customer_id` | INTEGER | بله | — |  |
| `contract_id` | INTEGER | بله | — |  |
| `project_id` | INTEGER | بله | — |  |
| `employee_id` | INTEGER | بله | — |  |
| `department_id` | INTEGER | بله | — |  |
| `cost_center_id` | INTEGER | بله | — |  |
| `service_type` | varchar | بله | — |  |
| `line_no` | INTEGER | خیر | `'1'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `cost_center_id.id → cost_centers.NO ACTION`، `department_id.id → departments.NO ACTION`، `employee_id.id → employees.NO ACTION`، `project_id.id → projects.NO ACTION`، `contract_id.id → contracts.NO ACTION`، `customer_id.id → customers.NO ACTION`، `ledger_account_id.id → ledger_accounts.NO ACTION`، `journal_entry_id.id → journal_entries.NO ACTION`

**ایندکس‌ها:**

```sql
journal_lines_account_idx	CREATE INDEX "journal_lines_account_idx" on "journal_lines" ("ledger_account_id", "journal_entry_id")
```
```sql
journal_lines_customer_idx	CREATE INDEX "journal_lines_customer_idx" on "journal_lines" ("customer_id", "journal_entry_id")
```
```sql
journal_lines_project_idx	CREATE INDEX "journal_lines_project_idx" on "journal_lines" ("project_id", "journal_entry_id")
```

### `invoices`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `number` | varchar | بله | — |  |
| `customer_id` | INTEGER | خیر | — |  |
| `contract_id` | INTEGER | بله | — |  |
| `project_id` | INTEGER | بله | — |  |
| `service_type` | varchar | خیر | `'other'` |  |
| `issue_date` | date | خیر | — |  |
| `due_date` | date | خیر | — |  |
| `subtotal` | INTEGER | خیر | `'0'` |  |
| `discount` | INTEGER | خیر | `'0'` |  |
| `tax_rate_bp` | INTEGER | خیر | `'0'` |  |
| `tax_amount` | INTEGER | خیر | `'0'` |  |
| `total` | INTEGER | خیر | `'0'` |  |
| `paid_amount` | INTEGER | خیر | `'0'` |  |
| `status` | varchar | خیر | `'draft'` |  |
| `payment_state` | varchar | خیر | `'unpaid'` |  |
| `title` | varchar | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `void_reason` | varchar | بله | — |  |
| `voided_at` | datetime | بله | — |  |
| `work_item_id` | INTEGER | بله | — |  |
| `journal_entry_id` | INTEGER | بله | — |  |
| `created_by` | INTEGER | بله | — |  |
| `issued_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `issued_by.id → users.NO ACTION`، `created_by.id → users.NO ACTION`، `journal_entry_id.id → journal_entries.NO ACTION`، `work_item_id.id → work_items.NO ACTION`، `project_id.id → projects.NO ACTION`، `contract_id.id → contracts.NO ACTION`، `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
invoices_customer_id_status_index	CREATE INDEX "invoices_customer_id_status_index" on "invoices" ("customer_id", "status")
```
```sql
invoices_payment_state_due_date_index	CREATE INDEX "invoices_payment_state_due_date_index" on "invoices" ("payment_state", "due_date")
```
```sql
invoices_number_unique	CREATE UNIQUE INDEX "invoices_number_unique" on "invoices" ("number")
```
```sql
invoices_issue_date_index	CREATE INDEX "invoices_issue_date_index" on "invoices" ("issue_date")
```
```sql
invoices_due_date_index	CREATE INDEX "invoices_due_date_index" on "invoices" ("due_date")
```
```sql
invoices_status_index	CREATE INDEX "invoices_status_index" on "invoices" ("status")
```
```sql
invoices_payment_state_index	CREATE INDEX "invoices_payment_state_index" on "invoices" ("payment_state")
```

### `invoice_items`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `invoice_id` | INTEGER | خیر | — |  |
| `line_no` | INTEGER | خیر | `'1'` |  |
| `description` | varchar | خیر | — |  |
| `quantity_milli` | INTEGER | خیر | `'1000'` |  |
| `unit_price` | INTEGER | خیر | — |  |
| `line_total` | INTEGER | خیر | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `invoice_id.id → invoices.NO ACTION`

### `payments`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `number` | varchar | خیر | — |  |
| `direction` | varchar | خیر | — |  |
| `customer_id` | INTEGER | بله | — |  |
| `invoice_id` | INTEGER | بله | — |  |
| `amount` | INTEGER | خیر | — |  |
| `paid_on` | date | خیر | — |  |
| `method` | varchar | خیر | `'transfer'` |  |
| `reference` | varchar | بله | — |  |
| `bank_account` | varchar | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `journal_entry_id` | INTEGER | بله | — |  |
| `created_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `created_by.id → users.NO ACTION`، `journal_entry_id.id → journal_entries.NO ACTION`، `invoice_id.id → invoices.NO ACTION`، `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
payments_number_unique	CREATE UNIQUE INDEX "payments_number_unique" on "payments" ("number")
```
```sql
payments_direction_index	CREATE INDEX "payments_direction_index" on "payments" ("direction")
```
```sql
payments_paid_on_index	CREATE INDEX "payments_paid_on_index" on "payments" ("paid_on")
```

### `payment_allocations`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `payment_id` | INTEGER | خیر | — |  |
| `invoice_id` | INTEGER | خیر | — |  |
| `amount` | INTEGER | خیر | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `invoice_id.id → invoices.NO ACTION`، `payment_id.id → payments.NO ACTION`

**ایندکس‌ها:**

```sql
payment_allocations_payment_id_invoice_id_unique	CREATE UNIQUE INDEX "payment_allocations_payment_id_invoice_id_unique" on "payment_allocations" ("payment_id", "invoice_id")
```

### `cost_centers`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `department_id` | INTEGER | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `department_id.id → departments.NO ACTION`

**ایندکس‌ها:**

```sql
cost_centers_code_unique	CREATE UNIQUE INDEX "cost_centers_code_unique" on "cost_centers" ("code")
```

## منابع انسانی

### `employees`

ردیف‌های موجود در پایگاه نمونه: **1**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | بله | — |  |
| `user_id` | INTEGER | بله | — |  |
| `first_name` | varchar | خیر | — |  |
| `last_name` | varchar | خیر | — |  |
| `national_id` | varchar | بله | — |  |
| `mobile` | varchar | بله | — |  |
| `email` | varchar | بله | — |  |
| `address` | TEXT | بله | — |  |
| `position` | varchar | بله | — |  |
| `start_date` | date | بله | — |  |
| `end_date` | date | بله | — |  |
| `status` | varchar | خیر | `'active'` |  |
| `notes` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |
| `department_id` | INTEGER | بله | — |  |
| `manager_id` | INTEGER | بله | — |  |

**کلیدهای خارجی:** `manager_id.id → employees.NO ACTION`، `department_id.id → departments.NO ACTION`، `user_id.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
employees_code_unique	CREATE UNIQUE INDEX "employees_code_unique" on "employees" ("code")
```
```sql
employees_national_id_unique	CREATE UNIQUE INDEX "employees_national_id_unique" on "employees" ("national_id")
```
```sql
employees_status_index	CREATE INDEX "employees_status_index" on "employees" ("status")
```
```sql
employees_department_id_index	CREATE INDEX "employees_department_id_index" on "employees" ("department_id")
```

### `departments`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `description` | varchar | بله | — |  |
| `parent_id` | INTEGER | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `sort_order` | INTEGER | خیر | `'0'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `parent_id.id → departments.NO ACTION`

**ایندکس‌ها:**

```sql
departments_code_unique	CREATE UNIQUE INDEX "departments_code_unique" on "departments" ("code")
```

### `employee_contracts`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `employee_id` | INTEGER | خیر | — |  |
| `attachment_id` | INTEGER | بله | — |  |
| `title` | varchar | بله | — |  |
| `start_date` | date | بله | — |  |
| `end_date` | date | بله | — |  |
| `agreed_amount` | INTEGER | بله | — |  |
| `notes` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `attachment_id.id → attachments.NO ACTION`، `employee_id.id → employees.NO ACTION`

**ایندکس‌ها:**

```sql
employee_contracts_employee_id_index	CREATE INDEX "employee_contracts_employee_id_index" on "employee_contracts" ("employee_id")
```

### `payroll_periods`

ردیف‌های موجود در پایگاه نمونه: **1**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `jalali_year` | varchar | خیر | — |  |
| `jalali_month` | varchar | خیر | — |  |
| `state` | varchar | خیر | `'draft'` |  |
| `payroll_rule_id` | INTEGER | بله | — |  |
| `published_at` | datetime | بله | — |  |
| `published_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `published_by.id → users.NO ACTION`، `payroll_rule_id.id → payroll_rules.NO ACTION`

**ایندکس‌ها:**

```sql
payroll_periods_jalali_year_jalali_month_unique	CREATE UNIQUE INDEX "payroll_periods_jalali_year_jalali_month_unique" on "payroll_periods" ("jalali_year", "jalali_month")
```

### `payroll_rules`

ردیف‌های موجود در پایگاه نمونه: **1**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `code` | varchar | خیر | — |  |
| `title` | varchar | خیر | — |  |
| `method` | varchar | خیر | — |  |
| `params` | TEXT | بله | — |  |
| `is_active` | tinyint(1) | خیر | `'1'` |  |
| `is_approved` | tinyint(1) | خیر | `'0'` |  |
| `approved_by` | INTEGER | بله | — |  |
| `approved_at` | datetime | بله | — |  |
| `approved_version_of` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `approved_version_of.id → payroll_rules.NO ACTION`، `approved_by.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
payroll_rules_code_unique	CREATE UNIQUE INDEX "payroll_rules_code_unique" on "payroll_rules" ("code")
```

### `payslips`

ردیف‌های موجود در پایگاه نمونه: **1**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `payroll_period_id` | INTEGER | خیر | — |  |
| `employee_id` | INTEGER | خیر | — |  |
| `base_salary` | INTEGER | خیر | `'0'` |  |
| `worked_days` | INTEGER | بله | — |  |
| `bonus` | INTEGER | خیر | `'0'` |  |
| `overtime` | INTEGER | خیر | `'0'` |  |
| `advance` | INTEGER | خیر | `'0'` |  |
| `deductions` | INTEGER | خیر | `'0'` |  |
| `input_notes` | TEXT | بله | — |  |
| `projects_total` | INTEGER | خیر | `'0'` |  |
| `total_earnings` | INTEGER | خیر | `'0'` |  |
| `total_deductions` | INTEGER | خیر | `'0'` |  |
| `net_payable` | INTEGER | خیر | `'0'` |  |
| `payroll_rule_id` | INTEGER | بله | — |  |
| `computed_at` | datetime | بله | — |  |
| `computed_by` | INTEGER | بله | — |  |
| `attachment_id` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `attachment_id.id → attachments.NO ACTION`، `computed_by.id → users.NO ACTION`، `payroll_rule_id.id → payroll_rules.NO ACTION`، `employee_id.id → employees.NO ACTION`، `payroll_period_id.id → payroll_periods.NO ACTION`

**ایندکس‌ها:**

```sql
payslips_payroll_period_id_employee_id_unique	CREATE UNIQUE INDEX "payslips_payroll_period_id_employee_id_unique" on "payslips" ("payroll_period_id", "employee_id")
```

### `payroll_items`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `payslip_id` | INTEGER | خیر | — |  |
| `project_id` | INTEGER | بله | — |  |
| `label` | varchar | بله | — |  |
| `amount` | INTEGER | خیر | `'0'` |  |
| `coefficient` | INTEGER | خیر | `'100'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `direction` | varchar | خیر | `'earning'` |  |

**کلیدهای خارجی:** `project_id.id → projects.NO ACTION`، `payslip_id.id → payslips.NO ACTION`

**ایندکس‌ها:**

```sql
payroll_items_payslip_id_index	CREATE INDEX "payroll_items_payslip_id_index" on "payroll_items" ("payslip_id")
```

### `payslip_versions`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `payslip_id` | INTEGER | خیر | — |  |
| `version` | INTEGER | خیر | `'1'` |  |
| `snapshot` | TEXT | خیر | — |  |
| `reason` | TEXT | خیر | — |  |
| `created_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `created_by.id → users.NO ACTION`، `payslip_id.id → payslips.NO ACTION`

**ایندکس‌ها:**

```sql
payslip_versions_payslip_id_version_unique	CREATE UNIQUE INDEX "payslip_versions_payslip_id_version_unique" on "payslip_versions" ("payslip_id", "version")
```

## اسناد و پیوست‌ها

### `attachments`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `uuid` | varchar | خیر | — |  |
| `disk` | varchar | خیر | `'private'` |  |
| `path` | varchar | خیر | — |  |
| `original_name` | varchar | خیر | — |  |
| `mime` | varchar | خیر | — |  |
| `size` | INTEGER | خیر | — |  |
| `sha256` | varchar | خیر | — |  |
| `category` | varchar | خیر | `'general'` |  |
| `is_confidential` | tinyint(1) | خیر | `'0'` |  |
| `uploaded_by` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `uploaded_by.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
attachments_category_index	CREATE INDEX "attachments_category_index" on "attachments" ("category")
```
```sql
attachments_uuid_unique	CREATE UNIQUE INDEX "attachments_uuid_unique" on "attachments" ("uuid")
```
```sql
attachments_sha256_index	CREATE INDEX "attachments_sha256_index" on "attachments" ("sha256")
```

## عملیات و گزارش

### `work_items`

ردیف‌های موجود در پایگاه نمونه: **9**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `project_id` | INTEGER | خیر | — |  |
| `kind` | varchar | خیر | — |  |
| `due_date` | date | خیر | — |  |
| `period` | varchar | بله | — |  |
| `state` | varchar | خیر | `'pending'` |  |
| `invoice_number` | varchar | بله | — |  |
| `invoice_amount` | INTEGER | بله | — |  |
| `invoice_issue_date` | date | بله | — |  |
| `document_attachment_id` | INTEGER | بله | — |  |
| `done_at` | datetime | بله | — |  |
| `done_by` | INTEGER | بله | — |  |
| `completion_notes` | TEXT | بله | — |  |
| `recurrence` | varchar | بله | — |  |
| `generated_from_id` | INTEGER | بله | — |  |
| `reminder_offset_days` | INTEGER | خیر | `'2'` |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `generated_from_id.id → work_items.NO ACTION`، `done_by.id → users.NO ACTION`، `project_id.id → projects.NO ACTION`

**ایندکس‌ها:**

```sql
work_items_project_id_kind_period_unique	CREATE UNIQUE INDEX "work_items_project_id_kind_period_unique" on "work_items" ("project_id", "kind", "period")
```
```sql
work_items_state_due_date_index	CREATE INDEX "work_items_state_due_date_index" on "work_items" ("state", "due_date")
```
```sql
work_items_kind_index	CREATE INDEX "work_items_kind_index" on "work_items" ("kind")
```
```sql
work_items_due_date_index	CREATE INDEX "work_items_due_date_index" on "work_items" ("due_date")
```
```sql
work_items_state_index	CREATE INDEX "work_items_state_index" on "work_items" ("state")
```
```sql
work_items_open_idx	CREATE INDEX "work_items_open_idx" on "work_items" ("done_at", "due_date")
```
```sql
work_items_period_idx	CREATE INDEX "work_items_period_idx" on "work_items" ("project_id", "kind", "period")
```

### `notes`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `title` | varchar | خیر | — |  |
| `description` | TEXT | بله | — |  |
| `due_date` | date | بله | — |  |
| `priority` | varchar | خیر | `'normal'` |  |
| `status` | varchar | خیر | `'open'` |  |
| `customer_id` | INTEGER | بله | — |  |
| `contract_id` | INTEGER | بله | — |  |
| `project_id` | INTEGER | بله | — |  |
| `done_at` | datetime | بله | — |  |
| `done_by` | INTEGER | بله | — |  |
| `created_by` | INTEGER | بله | — |  |
| `assigned_to` | INTEGER | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |
| `deleted_at` | datetime | بله | — |  |

**کلیدهای خارجی:** `assigned_to.id → users.NO ACTION`، `created_by.id → users.NO ACTION`، `done_by.id → users.NO ACTION`، `project_id.id → projects.NO ACTION`، `contract_id.id → contracts.NO ACTION`، `customer_id.id → customers.NO ACTION`

**ایندکس‌ها:**

```sql
notes_status_due_date_index	CREATE INDEX "notes_status_due_date_index" on "notes" ("status", "due_date")
```
```sql
notes_due_date_index	CREATE INDEX "notes_due_date_index" on "notes" ("due_date")
```
```sql
notes_priority_index	CREATE INDEX "notes_priority_index" on "notes" ("priority")
```
```sql
notes_status_index	CREATE INDEX "notes_status_index" on "notes" ("status")
```
```sql
notes_status_due_idx	CREATE INDEX "notes_status_due_idx" on "notes" ("status", "due_date")
```
```sql
notes_assignee_idx	CREATE INDEX "notes_assignee_idx" on "notes" ("assigned_to", "status")
```

### `audit_logs`

ردیف‌های موجود در پایگاه نمونه: **232**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `user_id` | INTEGER | بله | — |  |
| `event` | varchar | خیر | — |  |
| `subject_type` | varchar | بله | — |  |
| `subject_id` | INTEGER | بله | — |  |
| `description` | varchar | بله | — |  |
| `changes` | TEXT | بله | — |  |
| `ip` | varchar | بله | — |  |
| `user_agent` | varchar | بله | — |  |
| `created_at` | datetime | خیر | `CURRENT_TIMESTAMP` |  |

**کلیدهای خارجی:** `user_id.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
audit_logs_subject_type_subject_id_index	CREATE INDEX "audit_logs_subject_type_subject_id_index" on "audit_logs" ("subject_type", "subject_id")
```
```sql
audit_logs_created_at_index	CREATE INDEX "audit_logs_created_at_index" on "audit_logs" ("created_at")
```
```sql
audit_logs_event_index	CREATE INDEX "audit_logs_event_index" on "audit_logs" ("event")
```

### `scheduled_events`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `unique_key` | varchar | خیر | — |  |
| `event_type` | varchar | خیر | — |  |
| `subject_type` | varchar | بله | — |  |
| `subject_id` | INTEGER | بله | — |  |
| `kind` | varchar | خیر | — |  |
| `fire_date` | date | خیر | — |  |
| `created_at` | datetime | خیر | `CURRENT_TIMESTAMP` |  |

**ایندکس‌ها:**

```sql
scheduled_events_subject_type_subject_id_index	CREATE INDEX "scheduled_events_subject_type_subject_id_index" on "scheduled_events" ("subject_type", "subject_id")
```
```sql
scheduled_events_fire_date_index	CREATE INDEX "scheduled_events_fire_date_index" on "scheduled_events" ("fire_date")
```
```sql
scheduled_events_unique_key_unique	CREATE UNIQUE INDEX "scheduled_events_unique_key_unique" on "scheduled_events" ("unique_key")
```

### `job_runs`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `job` | varchar | خیر | — |  |
| `status` | varchar | خیر | — |  |
| `started_at` | datetime | خیر | — |  |
| `finished_at` | datetime | بله | — |  |
| `processed` | INTEGER | خیر | `'0'` |  |
| `created` | INTEGER | خیر | `'0'` |  |
| `skipped` | INTEGER | خیر | `'0'` |  |
| `error` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
job_runs_job_index	CREATE INDEX "job_runs_job_index" on "job_runs" ("job")
```

### `settings`

ردیف‌های موجود در پایگاه نمونه: **4**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `key` | varchar | خیر | — |  |
| `value` | TEXT | بله | — |  |
| `type` | varchar | خیر | `'string'` |  |
| `group` | varchar | خیر | `'general'` |  |
| `title` | varchar | بله | — |  |
| `description` | TEXT | بله | — |  |
| `created_at` | datetime | بله | — |  |
| `updated_at` | datetime | بله | — |  |

**ایندکس‌ها:**

```sql
settings_key_unique	CREATE UNIQUE INDEX "settings_key_unique" on "settings" ("key")
```
```sql
settings_group_index	CREATE INDEX "settings_group_index" on "settings" ("group")
```

### `settings_history`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `key` | varchar | خیر | — |  |
| `old_value` | TEXT | بله | — |  |
| `new_value` | TEXT | بله | — |  |
| `changed_by` | INTEGER | بله | — |  |
| `created_at` | datetime | خیر | `CURRENT_TIMESTAMP` |  |

**کلیدهای خارجی:** `changed_by.id → users.NO ACTION`

**ایندکس‌ها:**

```sql
settings_history_key_index	CREATE INDEX "settings_history_key_index" on "settings_history" ("key")
```

## زیرساخت

### `migrations`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `migration` | varchar | خیر | — |  |
| `batch` | INTEGER | خیر | — |  |

### `cache`

ردیف‌های موجود در پایگاه نمونه: **2**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `key` | varchar | خیر | — | PK |
| `value` | TEXT | خیر | — |  |
| `expiration` | INTEGER | خیر | — |  |

### `cache_locks`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `key` | varchar | خیر | — | PK |
| `owner` | varchar | خیر | — |  |
| `expiration` | INTEGER | خیر | — |  |

### `jobs`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `queue` | varchar | خیر | — |  |
| `payload` | TEXT | خیر | — |  |
| `attempts` | INTEGER | خیر | — |  |
| `reserved_at` | INTEGER | بله | — |  |
| `available_at` | INTEGER | خیر | — |  |
| `created_at` | INTEGER | خیر | — |  |

**ایندکس‌ها:**

```sql
jobs_queue_index	CREATE INDEX "jobs_queue_index" on "jobs" ("queue")
```

### `job_batches`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | varchar | خیر | — | PK |
| `name` | varchar | خیر | — |  |
| `total_jobs` | INTEGER | خیر | — |  |
| `pending_jobs` | INTEGER | خیر | — |  |
| `failed_jobs` | INTEGER | خیر | — |  |
| `failed_job_ids` | TEXT | خیر | — |  |
| `options` | TEXT | بله | — |  |
| `cancelled_at` | INTEGER | بله | — |  |
| `created_at` | INTEGER | خیر | — |  |
| `finished_at` | INTEGER | بله | — |  |

### `failed_jobs`

ردیف‌های موجود در پایگاه نمونه: **۰**

| ستون | نوع | NULL مجاز | پیش‌فرض | کلید |
|---|---|---|---|---|
| `id` | INTEGER | خیر | — | PK |
| `uuid` | varchar | خیر | — |  |
| `connection` | TEXT | خیر | — |  |
| `queue` | TEXT | خیر | — |  |
| `payload` | TEXT | خیر | — |  |
| `exception` | TEXT | خیر | — |  |
| `failed_at` | datetime | خیر | `CURRENT_TIMESTAMP` |  |

**ایندکس‌ها:**

```sql
failed_jobs_uuid_unique	CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid")
```

## بررسی هم‌خوانی

```bash
node tools/generate-schema-doc.mjs --check
```

اگر این سند با پایگاه داده هم‌خوان نباشد، کد خروج ۱ است. مقایسه روی
پایگاهِ نمونه انجام می‌شود؛ روی سرور مشتری، `php artisan migrate` منبع است.
