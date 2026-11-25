# نمودار رابطهٔ موجودیت‌ها

> **تولیدشده.** `node tools/generate-erd.mjs` آن را از `pragma foreign_key_list`
> می‌سازد. ERD دستیِ قدیمی در `docs/diagrams/erd.{png,svg}` هست، اما قراردادها،
> حقوق، فاکتورها و ساختار سازمانی را ندارد — نموداری که جدول ندارد از نبودِ نمودار
> بدتر است، چون کامل خوانده می‌شود.

57 جدول، **93** کلید خارجی اعلان‌شده.

## مراکز

جدول‌هایی که بیشترین ارجاع به آن‌ها وجود دارد.

| جدول | ارجاع‌گیرنده |
|---|---|
| `users` | 27 — `attachments.uploaded_by`، `audit_logs.user_id`، `contract_files.uploaded_by`، `contract_templates.created_by`، `contract_versions.created_by`، `contracts.owner_user_id`، `contracts.finalized_by`، `customers.owner_user_id`، `employees.user_id`، `fiscal_periods.closed_by`، `invoices.issued_by`، `invoices.created_by`، `journal_entries.created_by`، `journal_entries.posted_by`، `notes.assigned_to`، `notes.created_by`، `notes.done_by`، `payments.created_by`، `payroll_periods.published_by`، `payroll_rules.approved_by`، `payslip_versions.created_by`، `payslips.computed_by`، `project_credentials.updated_by`، `projects.owner_user_id`، `settings_history.changed_by`، `user_roles.user_id`، `work_items.done_by` |
| `projects` | 10 — `design_project_details.project_id`، `invoices.project_id`، `journal_lines.project_id`، `notes.project_id`، `payroll_items.project_id`، `project_assignments.project_id`، `project_credentials.project_id`، `project_keywords.project_id`، `seo_project_details.project_id`، `work_items.project_id` |
| `customers` | 8 — `contracts.customer_id`، `customer_contacts.customer_id`، `customer_websites.customer_id`، `invoices.customer_id`، `journal_lines.customer_id`، `notes.customer_id`، `payments.customer_id`، `projects.customer_id` |
| `contracts` | 7 — `contract_files.contract_id`، `contract_versions.contract_id`، `contracts.renewed_from_id`، `invoices.contract_id`، `journal_lines.contract_id`، `notes.contract_id`، `projects.contract_id` |
| `employees` | 5 — `employee_contracts.employee_id`، `employees.manager_id`، `journal_lines.employee_id`، `payslips.employee_id`، `project_assignments.employee_id` |
| `departments` | 4 — `cost_centers.department_id`، `departments.parent_id`، `employees.department_id`، `journal_lines.department_id` |
| `journal_entries` | 4 — `invoices.journal_entry_id`، `journal_entries.reverses_entry_id`، `journal_lines.journal_entry_id`، `payments.journal_entry_id` |
| `invoices` | 3 — `invoice_items.invoice_id`، `payment_allocations.invoice_id`، `payments.invoice_id` |

## کلیدهای خارجی، به تفکیک جدول

### `attachments`

**به این جدول‌ها ارجاع می‌دهد:** `uploaded_by → users`

**این جدول‌ها به آن ارجاع می‌دهند:** `employee_contracts.attachment_id`، `payslips.attachment_id`

### `audit_logs`

**به این جدول‌ها ارجاع می‌دهد:** `user_id → users`

### `contract_files`

**به این جدول‌ها ارجاع می‌دهد:** `uploaded_by → users`، `contract_id → contracts`

### `contract_templates`

**به این جدول‌ها ارجاع می‌دهد:** `created_by → users`، `contract_type_id → contract_types`

**این جدول‌ها به آن ارجاع می‌دهند:** `contracts.template_id`

### `contract_types`

**این جدول‌ها به آن ارجاع می‌دهند:** `contract_templates.contract_type_id`، `contracts.contract_type_id`

### `contract_versions`

**به این جدول‌ها ارجاع می‌دهد:** `created_by → users`، `contract_id → contracts`

### `contracts`

**به این جدول‌ها ارجاع می‌دهد:** `owner_user_id → users`، `renewed_from_id → contracts`، `finalized_by → users`، `template_id → contract_templates`، `contract_type_id → contract_types`، `customer_id → customers`

**این جدول‌ها به آن ارجاع می‌دهند:** `contract_files.contract_id`، `contract_versions.contract_id`، `contracts.renewed_from_id`، `invoices.contract_id`، `journal_lines.contract_id`، `notes.contract_id`، `projects.contract_id`

### `cost_centers`

**به این جدول‌ها ارجاع می‌دهد:** `department_id → departments`

**این جدول‌ها به آن ارجاع می‌دهند:** `journal_lines.cost_center_id`

### `credential_requirements`

**به این جدول‌ها ارجاع می‌دهد:** `credential_type_id → credential_types`

### `credential_types`

**این جدول‌ها به آن ارجاع می‌دهند:** `credential_requirements.credential_type_id`، `project_credentials.credential_type_id`

### `credential_values`

**به این جدول‌ها ارجاع می‌دهد:** `project_credential_id → project_credentials`

### `customer_contacts`

**به این جدول‌ها ارجاع می‌دهد:** `customer_id → customers`

### `customer_websites`

**به این جدول‌ها ارجاع می‌دهد:** `customer_id → customers`

### `customers`

**به این جدول‌ها ارجاع می‌دهد:** `owner_user_id → users`

**این جدول‌ها به آن ارجاع می‌دهند:** `contracts.customer_id`، `customer_contacts.customer_id`، `customer_websites.customer_id`، `invoices.customer_id`، `journal_lines.customer_id`، `notes.customer_id`، `payments.customer_id`، `projects.customer_id`

### `departments`

**به این جدول‌ها ارجاع می‌دهد:** `parent_id → departments`

**این جدول‌ها به آن ارجاع می‌دهند:** `cost_centers.department_id`، `departments.parent_id`، `employees.department_id`، `journal_lines.department_id`

### `design_project_details`

**به این جدول‌ها ارجاع می‌دهد:** `project_id → projects`

### `employee_contracts`

**به این جدول‌ها ارجاع می‌دهد:** `attachment_id → attachments`، `employee_id → employees`

### `employees`

**به این جدول‌ها ارجاع می‌دهد:** `manager_id → employees`، `department_id → departments`، `user_id → users`

**این جدول‌ها به آن ارجاع می‌دهند:** `employee_contracts.employee_id`، `employees.manager_id`، `journal_lines.employee_id`، `payslips.employee_id`، `project_assignments.employee_id`

### `fiscal_periods`

**به این جدول‌ها ارجاع می‌دهد:** `closed_by → users`

**این جدول‌ها به آن ارجاع می‌دهند:** `journal_entries.fiscal_period_id`

### `invoice_items`

**به این جدول‌ها ارجاع می‌دهد:** `invoice_id → invoices`

### `invoices`

**به این جدول‌ها ارجاع می‌دهد:** `issued_by → users`، `created_by → users`، `journal_entry_id → journal_entries`، `work_item_id → work_items`، `project_id → projects`، `contract_id → contracts`، `customer_id → customers`

**این جدول‌ها به آن ارجاع می‌دهند:** `invoice_items.invoice_id`، `payment_allocations.invoice_id`، `payments.invoice_id`

### `journal_entries`

**به این جدول‌ها ارجاع می‌دهد:** `created_by → users`، `reverses_entry_id → journal_entries`، `posted_by → users`، `fiscal_period_id → fiscal_periods`

**این جدول‌ها به آن ارجاع می‌دهند:** `invoices.journal_entry_id`، `journal_entries.reverses_entry_id`، `journal_lines.journal_entry_id`، `payments.journal_entry_id`

### `journal_lines`

**به این جدول‌ها ارجاع می‌دهد:** `cost_center_id → cost_centers`، `department_id → departments`، `employee_id → employees`، `project_id → projects`، `contract_id → contracts`، `customer_id → customers`، `ledger_account_id → ledger_accounts`، `journal_entry_id → journal_entries`

### `ledger_accounts`

**به این جدول‌ها ارجاع می‌دهد:** `parent_id → ledger_accounts`

**این جدول‌ها به آن ارجاع می‌دهند:** `journal_lines.ledger_account_id`، `ledger_accounts.parent_id`

### `notes`

**به این جدول‌ها ارجاع می‌دهد:** `assigned_to → users`، `created_by → users`، `done_by → users`، `project_id → projects`، `contract_id → contracts`، `customer_id → customers`

### `payment_allocations`

**به این جدول‌ها ارجاع می‌دهد:** `invoice_id → invoices`، `payment_id → payments`

### `payments`

**به این جدول‌ها ارجاع می‌دهد:** `created_by → users`، `journal_entry_id → journal_entries`، `invoice_id → invoices`، `customer_id → customers`

**این جدول‌ها به آن ارجاع می‌دهند:** `payment_allocations.payment_id`

### `payroll_items`

**به این جدول‌ها ارجاع می‌دهد:** `project_id → projects`، `payslip_id → payslips`

### `payroll_periods`

**به این جدول‌ها ارجاع می‌دهد:** `published_by → users`، `payroll_rule_id → payroll_rules`

**این جدول‌ها به آن ارجاع می‌دهند:** `payslips.payroll_period_id`

### `payroll_rules`

**به این جدول‌ها ارجاع می‌دهد:** `approved_version_of → payroll_rules`، `approved_by → users`

**این جدول‌ها به آن ارجاع می‌دهند:** `payroll_periods.payroll_rule_id`، `payroll_rules.approved_version_of`، `payslips.payroll_rule_id`

### `payslip_versions`

**به این جدول‌ها ارجاع می‌دهد:** `created_by → users`، `payslip_id → payslips`

### `payslips`

**به این جدول‌ها ارجاع می‌دهد:** `attachment_id → attachments`، `computed_by → users`، `payroll_rule_id → payroll_rules`، `employee_id → employees`، `payroll_period_id → payroll_periods`

**این جدول‌ها به آن ارجاع می‌دهند:** `payroll_items.payslip_id`، `payslip_versions.payslip_id`

### `permissions`

**این جدول‌ها به آن ارجاع می‌دهند:** `role_permissions.permission_id`

### `project_assignments`

**به این جدول‌ها ارجاع می‌دهد:** `employee_id → employees`، `project_id → projects`

### `project_credentials`

**به این جدول‌ها ارجاع می‌دهد:** `updated_by → users`، `credential_type_id → credential_types`، `project_id → projects`

**این جدول‌ها به آن ارجاع می‌دهند:** `credential_values.project_credential_id`

### `project_keywords`

**به این جدول‌ها ارجاع می‌دهد:** `project_id → projects`

### `projects`

**به این جدول‌ها ارجاع می‌دهد:** `owner_user_id → users`، `contract_id → contracts`، `customer_id → customers`

**این جدول‌ها به آن ارجاع می‌دهند:** `design_project_details.project_id`، `invoices.project_id`، `journal_lines.project_id`، `notes.project_id`، `payroll_items.project_id`، `project_assignments.project_id`، `project_credentials.project_id`، `project_keywords.project_id`، `seo_project_details.project_id`، `work_items.project_id`

### `role_permissions`

**به این جدول‌ها ارجاع می‌دهد:** `permission_id → permissions`، `role_id → roles`

### `roles`

**این جدول‌ها به آن ارجاع می‌دهند:** `role_permissions.role_id`، `user_roles.role_id`

### `seo_packages`

**این جدول‌ها به آن ارجاع می‌دهند:** `seo_project_details.seo_package_id`

### `seo_project_details`

**به این جدول‌ها ارجاع می‌دهد:** `seo_package_id → seo_packages`، `project_id → projects`

### `settings_history`

**به این جدول‌ها ارجاع می‌دهد:** `changed_by → users`

### `user_roles`

**به این جدول‌ها ارجاع می‌دهد:** `role_id → roles`، `user_id → users`

### `users`

**این جدول‌ها به آن ارجاع می‌دهند:** `attachments.uploaded_by`، `audit_logs.user_id`، `contract_files.uploaded_by`، `contract_templates.created_by`، `contract_versions.created_by`، `contracts.owner_user_id`، `contracts.finalized_by`، `customers.owner_user_id`، `employees.user_id`، `fiscal_periods.closed_by`، `invoices.issued_by`، `invoices.created_by`، `journal_entries.created_by`، `journal_entries.posted_by`، `notes.assigned_to`، `notes.created_by`، `notes.done_by`، `payments.created_by`، `payroll_periods.published_by`، `payroll_rules.approved_by`، `payslip_versions.created_by`، `payslips.computed_by`، `project_credentials.updated_by`، `projects.owner_user_id`، `settings_history.changed_by`، `user_roles.user_id`، `work_items.done_by`

### `work_items`

**به این جدول‌ها ارجاع می‌دهد:** `generated_from_id → work_items`، `done_by → users`، `project_id → projects`

**این جدول‌ها به آن ارجاع می‌دهند:** `invoices.work_item_id`، `work_items.generated_from_id`

## جدول‌های بدون کلید خارجی

این جدول‌ها هیچ رابطهٔ اعلان‌شده‌ای ندارند. بعضی عمداً مستقل‌اند (تنظیمات،
گزارش رویدادها)؛ بعضی دیگر رابطه‌شان فقط در کد است، نه در پایگاه داده — و همین
جایی است که یک JOIN اشتباه ساخته می‌شود.

- `job_runs` — 0 ردیف
- `project_types` — 2 ردیف
- `scheduled_events` — 0 ردیف
- `settings` — 4 ردیف

## محدودیت‌های این نمودار

1. **SQLite کلید خارجی را فقط با `PRAGMA foreign_keys = ON` اعمال می‌کند.**
   لاراول این را در هر اتصال روشن می‌کند؛ یک کوئری خارج از لاراول ممکن است نادیده
   بگیرد.
2. **همهٔ رابطه‌ها اعلان نشده‌اند.** چند ستون مثل `contracts.renewed_from_id`
   رابطه را در کد بیان می‌کنند. هر جا رابطهٔ اعلان‌نشده پیدا کردید، این نمودار
   آن را نشان نمی‌دهد.
3. **چندریختی جدول‌های پیوست** (`attachments.attachable_type/id`) با کلید خارجی
   قابل بیان نیست و در این نمودار یال ندارد.
4. **ستون‌ها در نمودار نیستند** تا خوانا بماند. فهرست ستون‌ها در
   [`DATABASE_SCHEMA.md`](../DATABASE_SCHEMA.md) است.

## وضعیت خروجی گراف‌ویز

فایل `docs/database/erd.dot` تولید شده و از نظر ساختاری بررسی شده است
(پرانتزهای متوازن، ۵۷ گره اعلان‌شده، ۹۳ یال، هیچ یالی با گرهٔ اعلان‌نشده).
**Graphviz در محیط توسعه نصب نیست**، پس خروجی تصویری تولید و بازبینی نشده است.
تصاویر قدیمی `docs/diagrams/erd.{png,svg}` مربوط به نمودار دستی قبلی‌اند و
نباید با این یکی اشتباه شوند.

## بازتولید تصویر

```bash
dot -Tsvg docs/database/erd.dot -o docs/diagrams/erd.svg
dot -Tpng docs/database/erd.dot -o docs/diagrams/erd.png
```
