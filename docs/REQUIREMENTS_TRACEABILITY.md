# ماتریس ردیابی نیازمندی‌ها — Requirements Traceability

> **این سند به‌صورت خودکار تولید می‌شود.** دستی ویرایش نکنید؛
> `tools/traceability-status.json` را به‌روز کنید و `node tools/generate-traceability.mjs` را اجرا کنید.
> `node tools/generate-traceability.mjs --check` بررسی می‌کند که سند با کاتالوگ هم‌گام است.

**منبع نیازمندی‌ها:** `docs/requirements/requirements.catalog.yaml` (۹۶ نیازمندی)

**مبنا:** بازرسی مستقیم مخزن + اجرای واقعی. هیچ وضعیتی از گزارش قبلی کپی نشده.

---

## ۱. خلاصهٔ اجرایی

| وضعیت | تعداد | درصد |
|-------|------:|-----:|
| `VERIFIED` | 63 | 65.6% |
| `PARTIAL` | 13 | 13.5% |
| `NOT_VERIFIED` | 1 | 1.0% |
| `NOT_IMPLEMENTED` | 15 | 15.6% |
| `BLOCKED` | 4 | 4.2% |
| **جمع** | **96** | **100%** |

### اولویت P0 (۳۹ نیازمندی)

| وضعیت | تعداد |
|-------|------:|
| `VERIFIED` | 20 |
| `PARTIAL` | 6 |
| `NOT_IMPLEMENTED` | 10 |
| `BLOCKED` | 3 |

**نتیجه:** از ۳۹ نیازمندی حیاتی، **۲۰ مورد اثبات‌شده** و **۱۰ مورد پیاده‌سازی‌نشده** است.

### نگاشت شناسه‌ها

دستور کارفرما شناسه‌هایی مانند `AUTH-001` می‌خواست. کاتالوگ موجود از `REQ-xx.yy`
استفاده می‌کند. برای پرهیز از دوباره‌شماری و شکستن ارجاع‌های قبلی، **ستون «شناسه»
نگاشت اضافه شد** و `REQ-xx.yy` مرجع باقی می‌ماند.

---

## ۲. ماتریس تفصیلی

> فیلدها: **شناسه** · **REQ** · **عنوان** · **ماژول** · **اولویت** · **نوع** ·
> **وضعیت** · **شاهد** · **شکاف** · **فاز بعدی**

### Foundation

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `FND-001` | `REQ-01.01` | استقلال کامل نرم‌افزار از وردپرس | P0 | brief | **`VERIFIED`** | Laravel standalone; no WP tables/deps | composer.json; tools/checks/composer-integrity.mjs | — |
| `FND-002` | `REQ-01.04` | تاب‌آوری CRM در قطعی سایت اصلی | P1 | brief | **`NOT_VERIFIED`** | no dependency on main site found | not exercised | M0 |

### Deployment

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DEP-001` | `REQ-01.02` | دیتابیس مستقل و ساب‌دامین مستقل | P0 | brief | **`VERIFIED`** | own DB + subdomain; routes/web.php | routes/web.php; config/database.php | — |
| `DEP-002` | `REQ-01.03` | فایل‌های حساس خارج از Document Root | P0 | brief | **`PARTIAL`** | storage/ outside root | public/ uploads not yet used | M1/M3 |
| `DEP-003` | `REQ-13.02` | بازبینی و تأیید هاست مقصد | P1 | brief | **`BLOCKED`** | 14/21 host checks unknown | — | خارجی |

### Dashboard

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DSH-001` | `REQ-02.01` | شاخص‌های داشبورد | P1 | doc | **`PARTIAL`** | DashboardController + dashboard.blade | 4 of 6 KPI groups | M1/M3 |
| `DSH-002` | `REQ-02.02` | کارتابل تفکیک‌شده | P1 | doc | **`VERIFIED`** | کارتابل page + completion: tools/checks/work-item-flow.mjs 13/13 (run in the roster) | tools/checks/work-item-flow.mjs; tools/checks/run-all.mjs | — |
| `DSH-003` | `REQ-02.03` | ناوبری مستقیم از اعلان به رکورد | P1 | doc | **`VERIFIED`** | کلیک روی هر اعلان داشبورد کاربر را به پروندهٔ مرتبط می‌برد. | tools/checks/phase4-integration.mjs P4-G3: every alert on the dashboard is a link to the record it warns about, not a count — کارتابل tasks → /work-items/{id} (9 in the test run), contract expiry warnings → /contracts/{id}, open notes → /notes/{id}/edit, and the five count tiles → the filtered index behind them. This was a real gap: the کارتابل rows were plain <li> text, so the one thing the task list could not do was take the user to the work it was describing. The anchor carries a new .v-task__link rule in resources/css/pages.css that takes over .v-task's inner flex layout, because wrapping the row without it collapsed the dot, meta and date into one underlined string. | — |
| `DSH-004` | `REQ-02.04` | هشدار انقضای قرارداد با آستانه قابل تنظیم | P1 | doc | **`VERIFIED`** | settings-driven expiringWithin() | app/Models/Contract.php; tests/Feature/FinalizationGateTest.php | — |
| `DSH-005` | `REQ-02.05` | داشبورد خلوت و کاربردی | P1 | doc | **`VERIFIED`** | state=done/cancelled filter; done group reachable via /work-items?state=done | tools/checks/work-item-flow.mjs; tools/checks/work-item-report.mjs | — |

### Customers

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `CUST-001` | `REQ-03.01` | پرونده مشتری حقیقی | P1 | doc | **`VERIFIED`** | customers table + type kolah | database/migrations/2026_01_01_000200_create_customer_tables.php; tests/Feature/CustomerDeduplicationTest.php | — |
| `CUST-002` | `REQ-03.02` | پرونده مشتری حقوقی | P1 | doc | **`VERIFIED`** | legal-person fields | app/Models/Customer.php; tests/Feature/CustomerDeduplicationTest.php | — |
| `CUST-003` | `REQ-03.03` | فرم پویا بر اساس نوع شخص | P1 | doc | **`VERIFIED`** | customers/_form dynamic by type | resources/views/customers/_form.blade.php; tools/checks/customer-create.mjs | — |
| `CUST-004` | `REQ-03.04` | چرخه عمر مشتری | P1 | doc | **`VERIFIED`** | status lifecycle column | app/Models/Customer.php; tools/checks/customer-create.mjs | — |
| `CUST-005` | `REQ-03.05` | جلوگیری از ثبت مشتری تکراری | P0 | doc | **`PARTIAL`** | duplicate detection partial | no domain-level uniqueness proof | M1/M3 |
| `CUST-006` | `REQ-03.06` | جستجوی مشتری بر اساس نام/تماس/شناسه/دامنه | P1 | doc | **`VERIFIED`** | Customer::scopeSearch + indexes | app/Models/Customer.php; tools/checks/global-search.mjs | — |
| `CUST-007` | `REQ-03.07` | پرونده مرکزی مشتری 360° | P1 | doc | **`VERIFIED`** | customers/show.blade is the dossier hub: contracts AND projects listed as links to their records, notes, work items, permission-gated create actions | resources/views/customers/show.blade.php; tools/checks/crud-flow.mjs — project rows were unlinked until this round; fixed in 049088d | — |
| `CUST-008` | `REQ-03.08` | یک‌بار ثبت اطلاعات مشتری | P0 | doc | **`PARTIAL`** | single customer record | credentials not joined | M1/M3 |

### Contracts

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `CNT-001` | `REQ-04.01` | مدل داده قرارداد | P1 | doc | **`VERIFIED`** | Contract model + migration | app/Models/Contract.php; database/migrations/2026_01_01_000300_create_contract_tables.php | — |
| `CNT-002` | `REQ-04.02` | وضعیت‌های قرارداد | P1 | doc | **`VERIFIED`** | ContractStatus enum + derived Expiring | app/Models/Contract.php; tools/checks/contract-report.mjs | — |
| `CNT-003` | `REQ-04.03` | چند قرارداد برای یک مشتری + اتصال به پرونده | P1 | doc | **`VERIFIED`** | hasMany contracts on customer | app/Models/Customer.php; tests/Feature/DatabaseSchemaTest.php | — |
| `CNT-004` | `REQ-04.06` | ویرایش متن قرارداد پیش از نهایی‌سازی | P1 | doc | **`VERIFIED`** | template editor with placeholder palette; T-CTR-16..18 | resources/views/contracts; tools/checks/contracts-flow.mjs | — |
| `CNT-005` | `REQ-04.07` | تثبیت نسخه نهایی قرارداد | P0 | doc | **`VERIFIED`** | rendered_body frozen at finalisation; template edits cannot change it; T-CTR-12/18 | app/Services/Contracts/ContractRenderer.php; tests/Feature/FinalizationGateTest.php | — |
| `CNT-006` | `REQ-04.08` | تاریخچه و نسخه‌های قرارداد | P1 | doc | **`VERIFIED`** | contract_versions snapshot on finalize/renew/amend; T-CTR-14/22 | database/migrations/2026_01_01_000300_create_contract_tables.php; tools/checks/contracts-flow.mjs | — |
| `CNT-007` | `REQ-04.09` | جستجو و فیلتر قرارداد | P1 | doc | **`VERIFIED`** | search + sort whitelist + filters | app/Http/Controllers/Contracts/ContractController.php; tools/checks/contracts-flow.mjs | — |
| `CNT-008` | `REQ-04.10` | ثبت تمدید قرارداد | P1 | doc | **`VERIFIED`** | contracts.renew + finalizer copies terms, original untouched; T-CTR-19..22 | app/Http/Controllers/Contracts/ContractController.php; tools/checks/contracts-flow.mjs | — |
| `CNT-009` | `REQ-09.02` | فیلترهای قرارداد و پروژه | P1 | doc | **`VERIFIED`** | contract & project filter bars + sort whitelist | resources/views/contracts; tools/checks/contracts-flow.mjs | — |

### ContractTemplates

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `TPL-001` | `REQ-04.04` | بانک قالب قرارداد | P1 | doc | **`VERIFIED`** | ContractTemplate model + controller + templates index/form; T-CTR-01..05 | app/Models/ContractTemplate.php; tools/checks/contracts-flow.mjs | — |
| `TPL-002` | `REQ-04.05` | موتور Template قرارداد | P1 | doc | **`VERIFIED`** | ContractRenderer whitelist (no eval); unknown token refused at save; ContractRendererTest | app/Services/Contracts/ContractRenderer.php; tests/Feature/ContractRendererTest.php | — |

### Projects

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PRJ-001` | `REQ-05.01` | اتصال پروژه به مشتری و قرارداد + فرم نوع‌محور | P1 | doc | **`VERIFIED`** | projects module + type-driven form + 28-assert flow | app/Http/Controllers/Projects/ProjectController.php; tools/checks/project-flow.mjs | — |
| `PRJ-002` | `REQ-05.05` | تخصیص مسئول/کارشناس به پروژه | P1 | doc | **`VERIFIED`** | Both halves of the requirement. Attaching: POST/PUT/DELETE /projects/{project}/assignments behind projects.update, with a form on the project page offering only employable staff; duplicates, reversed date ranges and impossible Jalali dates are field errors, and an assignment reached through another project's URL is a 404. Viewing: GET /reports/expert-workload. tools/checks/project-assignment.mjs 15/15 and tools/checks/expert-workload.mjs 14/14; T-PA-12 is the link between them, reading the report's own figure before and after an attach (1 -> 2 -> 1). | tools/checks/project-assignment.mjs; tools/checks/expert-workload.mjs | — |
| `PRJ-003` | `REQ-06.06` | جداسازی اطلاعات عمومی و محرمانه پروژه | P1 | brief | **`VERIFIED`** | ProjectPdf PUBLIC_FIELDS allow-list; T-PDF-08/08b prove no credential data | app/Services/Pdf; tests/Feature/PdfDocumentTest.php | — |

### SeoProjects

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SEO-001` | `REQ-05.02` | فیلدهای پروژه سئو | P1 | doc | **`VERIFIED`** | Every field §5-1 names for an SEO project now has storage and a form field: نام پروژه, نام مشتری, قرارداد, پکیج/سطح خدمات, مبلغ, تاریخ شروع/پایان and the internal note already existed; the keyword list is REQ-05.03; the owner/expert is REQ-05.04; the invoice-issue and report dates and the state of scheduled actions live on work_items. The two that had nowhere to go were «آدرس وب‌سایت» and «توضیحات مالی مرتبط». design_project_details.domain belongs to the design fieldset and projects.notes is the internal note named in a different bullet, so neither could carry them; migration 2026_02_02_000100 adds nullable seo_project_details.website_url and .financial_notes instead. Both appear in the SEO fieldset only, are validated by SeoProjectDetail::websiteError, and render on the project page. | tools/checks/seo-website.mjs 14/14: bare domain accepted (Laravel's own `url` rule would reject it), URL with port/path/query/fragment stored as typed, Persian IDN in Arabic script, a space refused with no row written, a doubled scheme refused, empty stored as NULL, a record written before the column existed still loads and saves with keyword_count intact, edit replaces the value, nothing leaks into design_project_details.domain, financial and internal notes kept in separate columns. tools/checks/migration-safety.mjs 11/11: up/down/re-up on a disposable database, a second up is a no-op, a pre-existing row survives the migration untouched and reads NULL, and the probe refuses the shared database and path traversal. Mutation: disabling the space rejection drops the suite to 13/14. PENDING HOST ACTION — the migration has deliberately NOT been applied to the shared database/database.sqlite, which needs explicit authorization; until it runs, creating or editing an SEO project against that database fails with "no column named website_url". Command: node docs/tools/php-cli.mjs tools/migrate.php | — |
| `SEO-002` | `REQ-05.03` | مدیریت ساختارمند کلمات کلیدی | P1 | doc | **`VERIFIED`** | POST/PUT/DELETE /projects/{project}/keywords behind projects.update, with an add form, an inline status control and a delete button on the SEO project page. Uniqueness is enforced on ProjectKeyword::normalize() output, so ZWNJ, doubled whitespace and Arabic ي/ك cannot create duplicates; the package keyword_limit is a ceiling on real rows. The count on the page is $project->keywords->count() and T-KW-04 sets seo_project_details.keyword_count to 99 to prove the typed field has no effect on it. tools/checks/project-keyword.mjs 15/15. | app/Http/Controllers/Projects/ProjectController.php; tools/checks/project-keyword.mjs | — |

### DesignProjects

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DSG-001` | `REQ-05.04` | فیلدهای پروژه طراحی سایت | P1 | doc | **`VERIFIED`** | design_project_details model + form + detail page | database/migrations/2026_01_01_000400_create_project_tables.php; tools/checks/project-flow.mjs | — |

### Credentials

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `CRED-001` | `REQ-06.01` | مدیریت دسترسی‌های پروژه | P1 | doc | **`VERIFIED`** | ProjectCredential + CredentialVault + CredentialController; credential-flow 21/21 | app/Http/Controllers/Credentials/CredentialController.php; tools/checks/credential-flow.mjs | — |
| `CRED-002` | `REQ-06.02` | فهرست دسترسی‌های اجباری سئو | P0 | doc | **`VERIFIED`** | credential_requirements seeded (3 blocking types); FinalizationGate service; enforced in Store/UpdateProjectRequest; T-PRJ-09b (refused) + T-PRJ-09f (released); FinalizationGateTest 20 assertions | app/Services/Projects/FinalizationGate.php; tests/Feature/FinalizationGateTest.php — list is data-driven per §15; the three seeded kinds need client confirmation | — |
| `CRED-003` | `REQ-06.03` | رمزنگاری رمزها (AES-256-GCM) | P0 | brief | **`VERIFIED`** | AES-256-GCM wired: vault is the only read/write path; plaintext never in column or HTML | app/Services/Security/CredentialVault.php; tests/Feature/CredentialVaultTest.php | — |
| `CRED-004` | `REQ-06.04` | Audit مشاهده و دریافت خروجی محرمانه | P0 | brief | **`VERIFIED`** | reveal POST-only, no-store, one audit row per reveal; audit log is browsable at GET /audit (super-admin only), audit-log-page 16/16 | resources/views/audit/index.blade.php; tools/checks/audit-log-page.mjs — the missing audit view was built in the security stage | — |
| `CRED-005` | `REQ-06.05` | PDF محرمانه دسترسی‌ها | P0 | brief | **`VERIFIED`** | credentials PDF: POST-only, credentials.export, audit row, no-store, empty refused (T-PDF-09); credential-export 13/13; no *.pdf under storage/ | app/Services/Pdf; tools/checks/credential-export-audit.mjs — residual is REQ-06.07 (warning band on a stored file), tracked separately | — |
| `CRED-006` | `REQ-06.07` | هشدار محرمانگی روی فایل حاوی رمز | P1 | doc | **`NOT_IMPLEMENTED`** | no files | — | M1–M8 |

### Employees

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `EMP-001` | `REQ-07.01` | پرونده پرسنل | P1 | doc | **`VERIFIED`** | EmployeeController CRUD + policy + 7-width shots + payroll-flow T-PAY-02..06 | app/Http/Controllers/Hr/EmployeeController.php; tools/checks/payroll-flow.mjs | — |

### Payroll

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PAY-001` | `REQ-07.02` | قواعد قابل تنظیم حقوق (بدون فرمول خودساخته) | P0 | doc | **`VERIFIED`** | OD-16 formula in PayrollCalculator; payroll_rules.is_approved gate; projectsTotalNeverEntersTheFormula | app/Services/Payroll/PayrollCalculator.php; tests/Feature/PayrollTest.php | — |
| `PAY-002` | `REQ-07.03` | فیش حقوقی ماهانه (PDF فارسی RTL) | P1 | doc | **`VERIFIED`** | PayslipPdf all required fields + signature lines; T-PDF-10/10b (single page) | app/Services/Pdf; tests/Feature/PdfDocumentTest.php | — |
| `PAY-003` | `REQ-07.04` | تاریخچه و تثبیت فیش‌ها | P0 | doc | **`VERIFIED`** | draft→computed→published state machine + payslip_versions snapshot on amend; T-PAY-25..27 | app/Models/Payslip.php; tools/checks/payroll-flow.mjs | — |
| `PAY-004` | `REQ-07.05` | دسترسی مستقل اطلاعات حقوقی | P0 | doc | **`VERIFIED`** | payroll.* are sensitive permissions; manager 403 on /payroll proven with two sessions (T-PAY-22..24) | tools/checks/route-permissions.php; tests/Feature/AuthorizationTest.php | — |

### Notes

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `NOTE-001` | `REQ-08.01` | مدل یادداشت | P1 | doc | **`VERIFIED`** | Note model + migration + full CRUD controller | app/Models/Note.php; database/migrations/2026_01_01_000600_create_personnel_and_payroll_tables.php | — |
| `NOTE-002` | `REQ-08.02` | چرخه عمر یادداشت | P1 | doc | **`VERIFIED`** | notes index/create/edit + complete/reopen; T-WI-08..13 | resources/views/notes; tests/Feature/WorkItemAndNoteLifecycleTest.php | — |

### Search

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SRCH-001` | `REQ-09.01` | جستجوی سراسری | P1 | doc | **`VERIFIED`** | topbar search form rendered by layouts/app.blade.php on every authenticated page, GET customers.index?q=; Customer::scopeSearch covers search_key/first/last/company name, normalized_mobile, normalized_national_id and customer_websites.domain (all four fields §3 line 56 names); tools/checks/global-search.mjs 9/9 — box present on /projects, /notes and /audit; hits found by company name, by Persian-digit mobile, by national ID and by domain; result opens the customer file; no-match empty state; SQL metacharacters harmless | resources/views/layouts/app.blade.php; tools/checks/global-search.mjs | — |

### Invoices

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `INV-001` | `REQ-09.03` | گزارش فاکتور و گزارش | P1 | doc | **`VERIFIED`** | Invoice model + InvoiceController + accounting-flow 19/19 | app/Http/Controllers/Invoices/InvoiceController.php; tools/checks/accounting-flow.mjs | — |
| `INV-002` | `REQ-12.01` | یادآوری فاکتور دو روز تقویمی قبل | P0 | doc | **`VERIFIED`** | invoices table + controller + issue flow | app/Http/Controllers/Invoices/InvoiceController.php; tools/checks/accounting-flow.mjs | — |
| `INV-003` | `REQ-12.02` | یادآوری روز سررسید فاکتور و عقب‌افتادگی | P0 | doc | **`VERIFIED`** | controlled INV-1405-NNNN numbering at issue | app/Models/Invoice.php; tools/checks/accounting-flow.mjs | — |
| `INV-004` | `REQ-12.03` | ثبت انجام فاکتور | P1 | doc | **`VERIFIED`** | payment_state separate from invoice status (§19.4) | app/Models/Invoice.php; tests/Feature/AccountingLedgerTest.php | — |
| `INV-005` | `REQ-12.09` | ثبت مشخصات و فایل فاکتور پس از ارسال | P1 | doc | **`PARTIAL`** | پس از ارسال فاکتور، شماره، مبلغ و تاریخ صدور ثبت می‌شود؛ فایل فاکتور نه. | The traceability summary previously read "invoice PDF pending mPDF slice", which misstated the requirement: §12-9 asks for the invoice number, amount, issue date AND the invoice file to be recorded after sending. It is not a PDF-generation requirement — §9-7 names four PDFs (contract, project dossier, access information, payslip) and an invoice is not among them. Three of the four are implemented: POST /work-items/{id}/complete requires invoice_number and invoice_amount for an invoice item, validates the amount through Money::parse rather than silently storing 0, and stores invoice_issue_date. The fourth is not: work_items.document_attachment_id is fillable but nothing in app/ ever writes it, because recording a file needs the upload subsystem (REQ-10.06), which is not built. Marked PARTIAL rather than NOT_IMPLEMENTED because the previous status understated what works, and rather than VERIFIED because the file half genuinely does not. | M1/M3 |

### Reports

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `RPT-001` | `REQ-09.04` | گزارش قراردادها و پرسنل | P1 | doc | **`VERIFIED`** | GET /reports/contracts (permission reports.view) classifies every contract into one mutually exclusive bucket — near-expiry, overdue, active, finished, draft — and reconciles their sum against the contract total on the page itself. Near-expiry is computed from end_date through the same expiringWithin() scope the dashboard uses, over the configurable contracts.expiry_warning_days window; amounts are gated behind contracts.view_financials. tools/checks/contract-report.mjs 14/14, with every bucket count recomputed in independent SQL and T-CR-07 driving the window from 30 to 2 days through the real settings screen to prove the setting moves a contract between buckets. | tools/checks/work-item-report.mjs 13/13. The report classifies every scheduled action (work_items — the invoices and reports §9-4 names) into six mutually exclusive buckets: overdue, due today, upcoming, unscheduled, done, cancelled. Each tile is asserted against an independent SQL count and the six must sum to the row count; the page states that reconciliation itself and says so when it breaks. Counting decisions, each tested: a cancelled item whose due date has passed is counted as cancelled and NOT as overdue, so the late-work number is not inflated (mutating the bucket to include cancelled rows makes the sum 15 against a total of 14 and drops the suite to 8/13); `unscheduled` reads 0 because work_items.due_date is NOT NULL and exists so the reconciliation still holds if that is ever relaxed. Completing an invoice through the real endpoint moves it between tiles. Kind filter, due-date range, sorting (with the sort column whitelisted — an injected column falls back rather than reaching SQL) and pagination all recomputed and re-reconciled. Amounts are gated on invoices.view; a viewer gets 403. §9-3's «مسئول» filter — the one project filter that was missing — is covered here too. Also fixed while writing this: ContractReportController eager-loaded `customer:id,legal_name,…`, and legal_name is not a column on customers (the legal name is company_name); displayName() branches on `type`, which was not selected either, so every legal customer rendered as «مشتری بدون نام» on a report marked VERIFIED. Both reports now select id,type,company_name,first_name,last_name and show the real company name. | — |
| `RPT-002` | `REQ-09.07` | گزارش تعداد پروژه‌های فعال هر کارشناس | P1 | doc | **`VERIFIED`** | GET /reports/expert-workload (permission reports.view) — grouped aggregate over project_assignments joined to employees and projects; active count, all-assignments count and effective amount per expert; filters on project status, employment status and name; sorting; pagination; empty state. tools/checks/expert-workload.mjs 14/14, including reconciliation of every figure against an independent SQL count. | app/Http/Controllers/Reports; tools/checks/expert-workload.mjs | — |

### Pdf

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PDF-001` | `REQ-09.05` | خروجی PDF الزامی | P1 | doc | **`VERIFIED`** | four PDF exports: contract, project, credentials, payslip; pdf-flow 22/22 | app/Services/Pdf; tools/checks/pdf-flow.mjs | — |

### Excel

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `XLS-001` | `REQ-09.06` | خروجی Excel تکمیلی | P2 | proposed | **`NOT_IMPLEMENTED`** | no Excel export | — | M1–M8 |

### Users

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `USR-001` | `REQ-10.01` | نسخه اول: فقط مدیر اصلی | P1 | doc | **`VERIFIED`** | users(2) + login | app/Http/Controllers/Auth/LoginController.php; tools/checks/login-flow.mjs | — |

### Roles

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `ROLE-001` | `REQ-10.02` | نقش‌های نمونه آینده (فعال‌سازی با تأیید کارفرما) | P2 | proposed | **`VERIFIED`** | نقش‌ها داده‌اند نه کد: جدول roles + permissions + role_permissions با رابط مدیریتی کامل. | resources/views/settings/roles; tools/checks/admin-config.mjs — The four seeded roles were data from the start but there was no way to change them — the only writer was the seeder. /settings/roles now lists every role with its permission and user counts and edits the permission matrix, behind users.manage, which no seeded role holds, so out of the box only a superadmin can reach it. tools/checks/admin-config.mjs T-CFG-RO-01..06: a permission granted to a role really reaches a user on it (200 on /customers), a permission the role lacks really returns 403 (/payroll), a role people are attached to cannot be deleted, and the screen is closed to anyone without users.manage. The authorization architecture is unchanged: User::hasPermission still resolves through user_roles → role_permissions, and is_superadmin still implies every permission. Nothing escalates by accident — RoleRequest::escalationAttempt refuses to hand out a permission the actor does not hold, and removing that guard lets an editor grant themselves payroll.approve, which drops the suite to 23/24. The client's own words are «فعال‌سازی با تأیید کارفرما», so the role list staying editable data rather than an enum is the requirement being met, not deferred. | — |

### Permissions

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PERM-001` | `REQ-10.03` | مجوزهای مستقل به تفکیک حوزه | P0 | doc | **`VERIFIED`** | 55 permissions; permission: middleware on every employee/payroll/organization/accounting route; 2-session 403 proofs | tools/checks/route-permissions.php; tests/Feature/AuthorizationTest.php | — |

### Audit

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `AUD-001` | `REQ-10.04` | Audit Log رویدادهای مهم | P0 | doc | **`VERIFIED`** | audit_logs 232 rows, incl. denied | app/Services/Security/AuditRecorder.php; tools/checks/audit-log-page.mjs | — |

### Security

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SEC-001` | `REQ-10.05` | الزامات امنیتی پایه | P0 | brief | **`PARTIAL`** | all code-side controls implemented and asserted; HTTPS itself is deployment-side | security-headers.mjs 16/16 now asserts the controls over real HTTP: X-Content-Type-Options nosniff, X-Frame-Options DENY, Referrer-Policy same-origin, X-Permitted-Cross-Domain-Policies none, Permissions-Policy with all four device APIs disabled, and a CSP carrying all seven directives including frame-ancestors none and a script-src without unsafe-inline. Session cookie proven HttpOnly, SameSite=lax, Path=/, and Secure when SESSION_SECURE_COOKIE=true. That suite also found and fixed a real bug: SecurityHeaders guarded Cache-Control with `if (! has(...))`, which was always false because Symfony sets a default, so the intended no-store never shipped — pages were no-cache (revalidate) but still storeable. config/session.php now defaults Secure to true when APP_ENV=production and the variable is absent, verified by booting the real config both ways (local => false, production => true). Remaining gap: HTTPS termination itself cannot be exercised in this sandbox, so the transport half of §10-5 stays unproven here; every control that depends on it (TRUSTED_PROXIES, Secure cookie) is implemented and asserted. | M1/M3 |
| `SEC-002` | `REQ-10.07` | سیاست رمز و مدیریت نشست | P1 | brief | **`VERIFIED`** | session regenerate + password change | app/Http/Controllers/Auth/LoginController.php; tools/checks/security-deployment.mjs | — |

### Files

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `FILE-001` | `REQ-10.06` | امنیت فایل‌های آپلودی و IDOR | P0 | brief | **`NOT_IMPLEMENTED`** | no uploads | — | M1–M8 |
| `FILE-002` | `REQ-16.01` | فضای کاری سازمانی به‌عنوان دامنهٔ اصلی محصول | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_REQUIREMENTS.md (FR-FILE-001/002) | F0: معماری؛ بدون کد SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-003` | `REQ-16.02` | File Explorer با تمام قابلیت‌های ناوبری و عملیات | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md §4 (S01-S16) | بدون UI فایل SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-004` | `REQ-16.03` | مجوزهای Granular با وراثت و Deny/Allow صریح | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_PERMISSION_MATRIX.md §4 | بدون resolver؛ CRM گروه/دپارتمان ندارد SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-005` | `REQ-16.04` | نسخه‌بندی غیرمخرب با هش و Restore به‌صورت نسخهٔ جدید | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_VERSIONING_AND_LOCKING.md §2 | بدون جدول نسخه SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-006` | `REQ-16.05` | قفل‌گذاری واقعی و سرور-محور بدون Lost Update | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_VERSIONING_AND_LOCKING.md §4 | بدون جدول قفل SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-007` | `REQ-16.06` | WebDAV روی VPS مستقل با CRM به‌عنوان تنها مرجع مجوز | P0 | brief | **`BLOCKED`** | docs/WEBDAV_VPS_ARCHITECTURE.md | VPS آزمایشی موجود نیست SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | خارجی |
| `FILE-008` | `REQ-16.07` | Storage جدا از CRM با Backend قابل تعویض | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_STORAGE_ARCHITECTURE.md §10 | storage قابل تعویض طراحی شد SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-009` | `REQ-16.08` | آپلود تکه‌ای/قابل ادامه با تأیید هش و بدون فایل یتیم | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ERD.md §2.15 | بدون کد آپلود SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-010` | `REQ-16.09` | اتصال فایل به موجودیت‌های CRM بدون دور زدن مجوز | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ERD.md §2.12 | file_relations طراحی شد SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-011` | `REQ-16.10` | مدل تهدید و کنترل‌های امنیتی فایل | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_SECURITY_THREAT_MODEL.md | ۲۹ تهدید ثبت شد، ۰ کنترل پیاده SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-012` | `REQ-16.11` | طبقه‌بندی و Retention با Recycle Bin | P1 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ERD.md §2.14 | بدون retention SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |
| `FILE-013` | `REQ-16.12` | Online Office و ویرایش هم‌زمان (F3) | P2 | brief | **`NOT_IMPLEMENTED`** | docs/ONLINE_OFFICE_INTEGRATION.md | F3؛ طبق تصمیم کارفرما SCOPE: the binding source, docs/requirements/source/Vira_CRM_Requirements_FA.txt, ends at «۱۵. مواردی که در طراحی نهایی باید قابل تنظیم باشند» followed by «جمع‌بندی» — there is no §16 in it. REQ-16.x comes from the separate organisational file-workspace / online-office specification, so it is tracked here for completeness but is not one of the fifteen sections the client document defines. Nothing in the delivered product claims to implement it. | M1–M8 |

### UI

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `UI-001` | `REQ-11.01` | UI فارسی RTL اختصاصی | P1 | doc | **`VERIFIED`** | dir=rtl, fa, 7 widths | resources/views/layouts/app.blade.php; tools/checks/ui-modules.mjs | — |
| `UI-002` | `REQ-11.02` | Design System مشترک | P0 | doc | **`VERIFIED`** | 23 components + 21 DS asserts | resources/views/components; tools/checks/design-system.mjs | — |
| `UI-003` | `REQ-11.03` | فونت فارسی پایدار و قانونی | P1 | brief | **`VERIFIED`** | Vazirmatn self-hosted, OFL licence shipped | public/build/fonts/OFL.txt; resources/css/tokens.css — security: resources/css/tokens.css:13-32 declares three @font-face rules for Vazirmatn (400/500/700) all pointing at local ../fonts/*.woff2 — Vazirmatn-Regular 50684 B, -Medium 51128 B, -Bold 51020 B all present. No fonts.googleapis.com or CDN reference anywhere in resources/css. font-display: swap keeps text visible while the font loads. The SIL Open Font License 1.1 for Vazirmatn ships at public/build/fonts/OFL.txt (93 lines, "Copyright 2015 The Vazirmatn Project Authors"), so the font is both self-hosted and licensed — the two things §11-3 asks for. Previous evidence ("licence not confirmed") was stale: the licence file was already in the repo. | — |
| `UI-004` | `REQ-11.05` | حالت‌های Loading/Empty/Success/Error و اعتبارسنجی | P1 | doc | **`PARTIAL`** | empty/error states exist | loading/success states partial | M1/M3 |

### Jalali

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `JAL-001` | `REQ-11.04` | تاریخ شمسی ورود/نمایش + ذخیره استاندارد | P0 | doc | **`VERIFIED`** | JalaliDate + fa-date component | app/Services/I18n; tests/Unit/JalaliDateTest.php | — |

### SeoReports

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SEOR-001` | `REQ-12.04` | یادآوری گزارش سئو (۲ روز قبل + روز موعد + عقب‌افتادگی) | P0 | doc | **`VERIFIED`** | WorkItem::KINDS includes 'report'; work_items carries kind/due_date/period; ReminderSchedule D-2 returns 2026-03-19 for due 2026-03-21; ReminderScheduleTest 43 assertions; W-04d dashboard 0->1->0 | app/Models/WorkItem.php; tests/Unit/ReminderScheduleTest.php | — |

### Reminders

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `REM-001` | `REQ-12.05` | استقلال یادآوری فاکتور و گزارش + چند دوره | P0 | doc | **`PARTIAL`** | ReminderSchedule unique_key design | not wired | M1/M3 |
| `REM-002` | `REQ-12.06` | تغییر تاریخ: بازمحاسبه بدون تکرار، حفظ سوابق | P0 | doc | **`PARTIAL`** | reschedule logic designed | not wired | M1/M3 |

### Scheduler

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SCH-001` | `REQ-12.07` | زمان‌بندی واقعی، Idempotent و قابل مشاهده | P0 | doc | **`BLOCKED`** | H-15 host cron unavailable | no scheduler wired | خارجی |
| `SCH-002` | `REQ-12.08` | تعریف روز تقویمی و Timezone | P0 | doc | **`BLOCKED`** | OD-07 timezone decision pending | — | خارجی |

### PM

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PM-001` | `REQ-13.01` | مرحله‌بندی و گیت تأیید | P1 | brief | **`VERIFIED`** | Gate A accepted | docs/CRM_REVIEW_CLIENT_DECISIONS.md | — |
| `PM-002` | `REQ-14.02` | Requirements Traceability Matrix | P0 | derived | **`VERIFIED`** | this document | docs/REQUIREMENT_CLOSURE.md | — |

### QA

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `QA-001` | `REQ-14.01` | تست واقعی، بدون Pass صوری | P0 | brief | **`VERIFIED`** | 22 suites, lock, repeat, no mock | tools/checks/phase4-integration.mjs 25/25 walks the nine integration chains end to end over HTTP with the resulting database state asserted at each link: A customer→contract→template→finalisation→PDF→project (the template is attached at finalisation, not at creation — StoreContractRequest has no template_id rule and the body is snapshotted by ContractFinalizer, so an unfinalised contract returns 409 by design); B project→expert→required accesses→finalisation gate, refused while accesses are missing and accepted once the three blocking kinds exist; C settings→validation→persistence→consumption, an invalid threshold refused with the stored value unchanged; D threshold→dashboard warning→contract report, the near-expiry tile matching independent SQL at both a 1-day and a 30-day window; E employee→assignment→workload report→payroll, one payslip per active employee; F keyword→SEO project→website address→search; G note→dashboard→completion→activity history; H global search→navigation→record authorization; I backup→integrity→restore→reconciliation, driving the real vira:backup and vira:restore commands and comparing every table's row count after the round trip (audit_logs may grow by one because the restore records itself). Three real defects came out of it. vira:restore prompts "Proceed?" and, with stdin closed, aborts while still exiting 0 — an unattended restore therefore needs --no-interaction, and a check that only read the exit code would have called a restore that wrote nothing a success. The dashboard had no notes panel at all, although §8 puts free notes on the dashboard and §2 asks for tasks near their due date; open notes are now listed there. And the dashboard's closing line still claimed the project, credentials, personnel, payroll and report modules "will be enabled in the next phase", which has not been true for several releases; it is removed. | — |

### Settings

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SET-001` | `REQ-15.01` | تنظیمات مدیریتی سامانه | P1 | doc | **`PARTIAL`** | Six of the seven §15 configuration areas now have a screen, and each is proven by an effect somewhere other than the screen that made the change: contract types (/settings/contract-types), project types (/settings/project-types), SEO packages (/settings/seo-packages), mandatory accesses (/settings/access-requirements), payroll rules (/settings/payroll-rules) and roles with their permission matrix (/settings/roles). The tables already existed and most were already read by real code; what was missing was any way to change them, so each list was frozen at whatever the seeder wrote. No new configuration structure was created for any of them. "Project amounts" is not a separate area — §15 puts it in the same bullet as payroll calculation («نحوه محاسبه حقوق و مبالغ مرتبط با تعداد پروژه‌ها»), and those amounts already exist as payroll_items rows carrying a project_id plus payslips.projects_total. The one open §15 bullet is the PDF template: contract templates are editable, but the project, access and payslip PDFs still have fixed layouts. | tools/checks/admin-config.mjs 24/24. Contract types: a type created here is offered by the contract form, a duplicate code is a field error, retiring it removes it from the form. Project types: a new type is selectable AND a project is really created with it (the validator and the model-level HasDomainConstraints guard both read project_types now, so they cannot disagree); retiring a type stops new projects of that kind; the code of a type projects use cannot change. Mandatory accesses: adding a blocking requirement for DESIGN immediately blocks activating a design project — proven on a type other than seo, because all three seeded requirements are for seo and a hard-coded list would have passed for the wrong reason; removing it lifts the block; "blocks" without "required" is refused since blockingFor() needs both. Payroll: a new period is stamped with the approved rule, an unapproved rule is never used even when it is the only one left (storePeriod previously filtered on is_active alone), a method outside PayrollRule::METHODS is refused so no formula can be introduced against OD-16, and approving needs payroll.approve rather than settings.manage. Roles: a granted permission really reaches a user and a missing one really returns 403; a user cannot hand out a permission they do not hold — removing that guard lets an editor grant themselves payroll.approve and the suite drops to 23/24; a role people are attached to cannot be deleted; the screen is closed to anyone without users.manage, which no seeded role holds. route-smoke covers all 30 new routes (152 routes, 0 skipped). FinalizationGate::message() no longer says «پروژه سئو», because which types are gated is data. | M1/M3 |

### Backup

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `BAK-001` | `REQ-15.02` | Backup و Restore تست‌شده | P0 | brief | **`VERIFIED`** | app/Services/Backup (4 classes: Cipher/Builder/Restorer/RetentionPolicy); 3 console commands; BackupRestoreTest 96 assertions; 7 verification gates; tamper refusals proven at gate 3 | app/Services/Backup; tests/Feature/BackupRestoreTest.php — scheduling is REQ-12.07 (BLOCKED, no cron on host) — code is not the gap | — |

### Installer

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `INST-001` | `REQ-15.03` | Installer امن | P1 | brief | **`NOT_IMPLEMENTED`** | no installer | — | M1–M8 |

### DevOps

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `OPS-001` | `REQ-15.04` | مهاجرت و نسخه‌بندی | P1 | brief | **`PARTIAL`** | 14 migrations, reversible | no versioning policy doc | M1/M3 |
| `OPS-002` | `REQ-15.05` | تنظیمات محیطی (بدون Hardcode) | P2 | brief | **`PARTIAL`** | .env driven | no hardcode audit | M1/M3 |

### Docs

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DOC-001` | `REQ-15.06` | بسته مستندات فنی و کاربری | P2 | brief | **`PARTIAL`** | docs/ extensive | user manual missing | M1/M3 |

---

## ۳. نیازمندی‌های پیاده‌سازی‌نشده

| شناسه | عنوان | ماژول | اولویت |
|-------|-------|-------|:------:|
| `CRED-006` | هشدار محرمانگی روی فایل حاوی رمز | Credentials | P1 |
| `XLS-001` | خروجی Excel تکمیلی | Excel | P2 |
| `FILE-001` | امنیت فایل‌های آپلودی و IDOR | Files | P0 |
| `INST-001` | Installer امن | Installer | P1 |
| `FILE-002` | فضای کاری سازمانی به‌عنوان دامنهٔ اصلی محصول | Files | P0 |
| `FILE-003` | File Explorer با تمام قابلیت‌های ناوبری و عملیات | Files | P0 |
| `FILE-004` | مجوزهای Granular با وراثت و Deny/Allow صریح | Files | P0 |
| `FILE-005` | نسخه‌بندی غیرمخرب با هش و Restore به‌صورت نسخهٔ جدید | Files | P0 |
| `FILE-006` | قفل‌گذاری واقعی و سرور-محور بدون Lost Update | Files | P0 |
| `FILE-008` | Storage جدا از CRM با Backend قابل تعویض | Files | P0 |
| `FILE-009` | آپلود تکه‌ای/قابل ادامه با تأیید هش و بدون فایل یتیم | Files | P0 |
| `FILE-010` | اتصال فایل به موجودیت‌های CRM بدون دور زدن مجوز | Files | P0 |
| `FILE-011` | مدل تهدید و کنترل‌های امنیتی فایل | Files | P0 |
| `FILE-012` | طبقه‌بندی و Retention با Recycle Bin | Files | P1 |
| `FILE-013` | Online Office و ویرایش هم‌زمان (F3) | Files | P2 |

---

## ۴. نیازمندی‌های مسدود (BLOCKED)

| شناسه | عنوان | دلیل انسداد |
|-------|-------|-------------|
| `SCH-001` | زمان‌بندی واقعی، Idempotent و قابل مشاهده | H-15 host cron unavailable |
| `SCH-002` | تعریف روز تقویمی و Timezone | OD-07 timezone decision pending |
| `DEP-003` | بازبینی و تأیید هاست مقصد | 14/21 host checks unknown |
| `FILE-007` | WebDAV روی VPS مستقل با CRM به‌عنوان تنها مرجع مجوز | docs/WEBDAV_VPS_ARCHITECTURE.md |

---

## ۵. قواعد این ماتریس

| قاعده | رعایت |
|-------|-------|
| هر ۹۶ نیازمندی نگاشت شده | ✅ (اسکریپت با نبود نگاشت شکست می‌خورد) |
| `PASS` فقط با شاهد اجرایی | ✅ |
| تست mock/skip هرگز `PASS` نیست | ✅ |
| هیچ نیازمندی به‌خاطر دشواری حذف نشد | ✅ |
| شاهد ناموجود، ادعا نمی‌شود | ✅ |
| تفکیک نیاز کارفرما / پیشنهاد توسعه‌دهنده | ✅ ستون «نوع» |
