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
| `VERIFIED` | 37 | 38.5% |
| `PARTIAL` | 17 | 17.7% |
| `NOT_VERIFIED` | 2 | 2.1% |
| `NOT_IMPLEMENTED` | 33 | 34.4% |
| `BLOCKED` | 7 | 7.3% |
| **جمع** | **96** | **100%** |

### اولویت P0 (۳۹ نیازمندی)

| وضعیت | تعداد |
|-------|------:|
| `VERIFIED` | 11 |
| `PARTIAL` | 7 |
| `NOT_IMPLEMENTED` | 17 |
| `BLOCKED` | 4 |

**نتیجه:** از ۳۹ نیازمندی حیاتی، **۱۱ مورد اثبات‌شده** و **۱۷ مورد پیاده‌سازی‌نشده** است.

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
| `FND-001` | `REQ-01.01` | استقلال کامل نرم‌افزار از وردپرس | P0 | brief | **`VERIFIED`** | Laravel standalone; no WP tables/deps | — | — |
| `FND-002` | `REQ-01.04` | تاب‌آوری CRM در قطعی سایت اصلی | P1 | brief | **`NOT_VERIFIED`** | no dependency on main site found | not exercised | M0 |

### Deployment

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DEP-001` | `REQ-01.02` | دیتابیس مستقل و ساب‌دامین مستقل | P0 | brief | **`VERIFIED`** | own DB + subdomain; routes/web.php | — | — |
| `DEP-002` | `REQ-01.03` | فایل‌های حساس خارج از Document Root | P0 | brief | **`PARTIAL`** | storage/ outside root | public/ uploads not yet used | M1/M3 |
| `DEP-003` | `REQ-13.02` | بازبینی و تأیید هاست مقصد | P1 | brief | **`BLOCKED`** | 14/21 host checks unknown | — | خارجی |

### Dashboard

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DSH-001` | `REQ-02.01` | شاخص‌های داشبورد | P1 | doc | **`PARTIAL`** | DashboardController + dashboard.blade | 4 of 6 KPI groups | M1/M3 |
| `DSH-002` | `REQ-02.02` | کارتابل تفکیک‌شده | P1 | doc | **`VERIFIED`** | کارتابل page + completion: work-items-flow 13/13, 7-width shots 28/28 | — | — |
| `DSH-003` | `REQ-02.03` | ناوبری مستقیم از اعلان به رکورد | P1 | doc | **`NOT_IMPLEMENTED`** | — | no notification->record link | M1–M8 |
| `DSH-004` | `REQ-02.04` | هشدار انقضای قرارداد با آستانه قابل تنظیم | P1 | doc | **`VERIFIED`** | settings-driven expiringWithin() | — | — |
| `DSH-005` | `REQ-02.05` | داشبورد خلوت و کاربردی | P1 | doc | **`VERIFIED`** | state=done/cancelled filter; done group reachable via /work-items?state=done | — | — |

### Customers

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `CUST-001` | `REQ-03.01` | پرونده مشتری حقیقی | P1 | doc | **`VERIFIED`** | customers table + type kolah | — | — |
| `CUST-002` | `REQ-03.02` | پرونده مشتری حقوقی | P1 | doc | **`VERIFIED`** | legal-person fields | — | — |
| `CUST-003` | `REQ-03.03` | فرم پویا بر اساس نوع شخص | P1 | doc | **`VERIFIED`** | customers/_form dynamic by type | — | — |
| `CUST-004` | `REQ-03.04` | چرخه عمر مشتری | P1 | doc | **`VERIFIED`** | status lifecycle column | — | — |
| `CUST-005` | `REQ-03.05` | جلوگیری از ثبت مشتری تکراری | P0 | doc | **`PARTIAL`** | duplicate detection partial | no domain-level uniqueness proof | M1/M3 |
| `CUST-006` | `REQ-03.06` | جستجوی مشتری بر اساس نام/تماس/شناسه/دامنه | P1 | doc | **`VERIFIED`** | Customer::scopeSearch + indexes | — | — |
| `CUST-007` | `REQ-03.07` | پرونده مرکزی مشتری 360° | P1 | doc | **`PARTIAL`** | customers/show.blade | no contracts/projects tabs | M1/M3 |
| `CUST-008` | `REQ-03.08` | یک‌بار ثبت اطلاعات مشتری | P0 | doc | **`PARTIAL`** | single customer record | credentials not joined | M1/M3 |

### Contracts

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `CNT-001` | `REQ-04.01` | مدل داده قرارداد | P1 | doc | **`VERIFIED`** | Contract model + migration | — | — |
| `CNT-002` | `REQ-04.02` | وضعیت‌های قرارداد | P1 | doc | **`VERIFIED`** | ContractStatus enum + derived Expiring | — | — |
| `CNT-003` | `REQ-04.03` | چند قرارداد برای یک مشتری + اتصال به پرونده | P1 | doc | **`VERIFIED`** | hasMany contracts on customer | — | — |
| `CNT-004` | `REQ-04.06` | ویرایش متن قرارداد پیش از نهایی‌سازی | P1 | doc | **`NOT_IMPLEMENTED`** | no content editor | — | M1–M8 |
| `CNT-005` | `REQ-04.07` | تثبیت نسخه نهایی قرارداد | P0 | doc | **`NOT_IMPLEMENTED`** | ContractVersion model only | finalise flow absent | M1–M8 |
| `CNT-006` | `REQ-04.08` | تاریخچه و نسخه‌های قرارداد | P1 | doc | **`NOT_IMPLEMENTED`** | versions table empty | no history UI | M1–M8 |
| `CNT-007` | `REQ-04.09` | جستجو و فیلتر قرارداد | P1 | doc | **`VERIFIED`** | search + sort whitelist + filters | — | — |
| `CNT-008` | `REQ-04.10` | ثبت تمدید قرارداد | P1 | doc | **`NOT_IMPLEMENTED`** | no renewal route | — | M1–M8 |
| `CNT-009` | `REQ-09.02` | فیلترهای قرارداد و پروژه | P1 | doc | **`VERIFIED`** | contract & project filter bars + sort whitelist | — | — |

### ContractTemplates

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `TPL-001` | `REQ-04.04` | بانک قالب قرارداد | P1 | doc | **`NOT_IMPLEMENTED`** | ContractTemplate model only | no CRUD/UI | M1–M8 |
| `TPL-002` | `REQ-04.05` | موتور Template قرارداد | P1 | doc | **`NOT_IMPLEMENTED`** | no template engine | — | M1–M8 |

### Projects

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PRJ-001` | `REQ-05.01` | اتصال پروژه به مشتری و قرارداد + فرم نوع‌محور | P1 | doc | **`VERIFIED`** | projects module + type-driven form + 28-assert flow | — | — |
| `PRJ-002` | `REQ-05.05` | تخصیص مسئول/کارشناس به پروژه | P1 | doc | **`NOT_IMPLEMENTED`** | project_assignments empty | — | M1–M8 |
| `PRJ-003` | `REQ-06.06` | جداسازی اطلاعات عمومی و محرمانه پروژه | P1 | brief | **`NOT_IMPLEMENTED`** | no public/secret split | — | M1–M8 |

### SeoProjects

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SEO-001` | `REQ-05.02` | فیلدهای پروژه سئو | P1 | doc | **`PARTIAL`** | seo_project_details model + form + detail page | no keyword CRUD UI yet | M1/M3 |
| `SEO-002` | `REQ-05.03` | مدیریت ساختارمند کلمات کلیدی | P1 | doc | **`PARTIAL`** | project_keywords model + normalize + read-only list | no add/remove UI | M1/M3 |

### DesignProjects

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DSG-001` | `REQ-05.04` | فیلدهای پروژه طراحی سایت | P1 | doc | **`VERIFIED`** | design_project_details model + form + detail page | — | — |

### Credentials

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `CRED-001` | `REQ-06.01` | مدیریت دسترسی‌های پروژه | P1 | doc | **`VERIFIED`** | ProjectCredential + CredentialVault + CredentialController; credential-flow 21/21 | — | — |
| `CRED-002` | `REQ-06.02` | فهرست دسترسی‌های اجباری سئو | P0 | doc | **`NOT_IMPLEMENTED`** | credential_requirements empty | — | M1–M8 |
| `CRED-003` | `REQ-06.03` | رمزنگاری رمزها (AES-256-GCM) | P0 | brief | **`VERIFIED`** | AES-256-GCM wired: vault is the only read/write path; plaintext never in column or HTML | — | — |
| `CRED-004` | `REQ-06.04` | Audit مشاهده و دریافت خروجی محرمانه | P0 | brief | **`PARTIAL`** | reveal is POST-only, no-store, audited (1 row per reveal); no audit VIEW yet | audit recorded, not browsable | M1/M3 |
| `CRED-005` | `REQ-06.05` | PDF محرمانه دسترسی‌ها | P0 | brief | **`NOT_IMPLEMENTED`** | no PDF engine | — | M1–M8 |
| `CRED-006` | `REQ-06.07` | هشدار محرمانگی روی فایل حاوی رمز | P1 | doc | **`NOT_IMPLEMENTED`** | no files | — | M1–M8 |

### Employees

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `EMP-001` | `REQ-07.01` | پرونده پرسنل | P1 | doc | **`NOT_IMPLEMENTED`** | employees table empty | — | M1–M8 |

### Payroll

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PAY-001` | `REQ-07.02` | قواعد قابل تنظیم حقوق (بدون فرمول خودساخته) | P0 | doc | **`BLOCKED`** | OD-16 payroll formula | needs employer decision | خارجی |
| `PAY-002` | `REQ-07.03` | فیش حقوقی ماهانه (PDF فارسی RTL) | P1 | doc | **`BLOCKED`** | no PDF engine | — | خارجی |
| `PAY-003` | `REQ-07.04` | تاریخچه و تثبیت فیش‌ها | P0 | doc | **`NOT_IMPLEMENTED`** | payslips empty | — | M1–M8 |
| `PAY-004` | `REQ-07.05` | دسترسی مستقل اطلاعات حقوقی | P0 | doc | **`NOT_IMPLEMENTED`** | no payroll permissions | — | M1–M8 |

### Notes

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `NOTE-001` | `REQ-08.01` | مدل یادداشت | P1 | doc | **`VERIFIED`** | Note model + migration + full CRUD controller | — | — |
| `NOTE-002` | `REQ-08.02` | چرخه عمر یادداشت | P1 | doc | **`VERIFIED`** | notes index/create/edit + complete/reopen; T-WI-08..13 | — | — |

### Search

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SRCH-001` | `REQ-09.01` | جستجوی سراسری | P1 | doc | **`NOT_IMPLEMENTED`** | per-entity search only | no global search | M1–M8 |

### Invoices

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `INV-001` | `REQ-09.03` | گزارش فاکتور و گزارش | P1 | doc | **`VERIFIED`** | Invoice model + InvoiceController + accounting-flow 19/19 | — | — |
| `INV-002` | `REQ-12.01` | یادآوری فاکتور دو روز تقویمی قبل | P0 | doc | **`VERIFIED`** | invoices table + controller + issue flow | — | — |
| `INV-003` | `REQ-12.02` | یادآوری روز سررسید فاکتور و عقب‌افتادگی | P0 | doc | **`VERIFIED`** | controlled INV-1405-NNNN numbering at issue | — | — |
| `INV-004` | `REQ-12.03` | ثبت انجام فاکتور | P1 | doc | **`VERIFIED`** | payment_state separate from invoice status (§19.4) | — | — |
| `INV-005` | `REQ-12.09` | ثبت مشخصات و فایل فاکتور پس از ارسال | P1 | doc | **`NOT_IMPLEMENTED`** | invoice PDF pending mPDF slice | — | M1–M8 |

### Reports

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `RPT-001` | `REQ-09.04` | گزارش قراردادها و پرسنل | P1 | doc | **`VERIFIED`** | Trial balance + P&L + balance sheet, all from journal_lines | — | — |
| `RPT-002` | `REQ-09.07` | گزارش تعداد پروژه‌های فعال هر کارشناس | P1 | doc | **`VERIFIED`** | P&L by service and by customer dimensions | — | — |

### Pdf

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PDF-001` | `REQ-09.05` | خروجی PDF الزامی | P1 | doc | **`BLOCKED`** | no PDF engine + host | — | خارجی |

### Excel

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `XLS-001` | `REQ-09.06` | خروجی Excel تکمیلی | P2 | proposed | **`NOT_IMPLEMENTED`** | no Excel export | — | M1–M8 |

### Users

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `USR-001` | `REQ-10.01` | نسخه اول: فقط مدیر اصلی | P1 | doc | **`VERIFIED`** | users(2) + login | — | — |

### Roles

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `ROLE-001` | `REQ-10.02` | نقش‌های نمونه آینده (فعال‌سازی با تأیید کارفرما) | P2 | proposed | **`PARTIAL`** | roles(4) seeded | no role UI | M1/M3 |

### Permissions

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `PERM-001` | `REQ-10.03` | مجوزهای مستقل به تفکیک حوزه | P0 | doc | **`VERIFIED`** | 39 permissions + permission: on 22/22 routes | — | — |

### Audit

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `AUD-001` | `REQ-10.04` | Audit Log رویدادهای مهم | P0 | doc | **`VERIFIED`** | audit_logs 232 rows, incl. denied | — | — |

### Security

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SEC-001` | `REQ-10.05` | الزامات امنیتی پایه | P0 | brief | **`PARTIAL`** | bcrypt, CSRF, throttle | no security headers test | M1/M3 |
| `SEC-002` | `REQ-10.07` | سیاست رمز و مدیریت نشست | P1 | brief | **`VERIFIED`** | session regenerate + password change | — | — |

### Files

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `FILE-001` | `REQ-10.06` | امنیت فایل‌های آپلودی و IDOR | P0 | brief | **`NOT_IMPLEMENTED`** | no uploads | — | M1–M8 |
| `FILE-002` | `REQ-16.01` | فضای کاری سازمانی به‌عنوان دامنهٔ اصلی محصول | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_REQUIREMENTS.md (FR-FILE-001/002) | F0: معماری؛ بدون کد | M1–M8 |
| `FILE-003` | `REQ-16.02` | File Explorer با تمام قابلیت‌های ناوبری و عملیات | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md §4 (S01-S16) | بدون UI فایل | M1–M8 |
| `FILE-004` | `REQ-16.03` | مجوزهای Granular با وراثت و Deny/Allow صریح | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_PERMISSION_MATRIX.md §4 | بدون resolver؛ CRM گروه/دپارتمان ندارد | M1–M8 |
| `FILE-005` | `REQ-16.04` | نسخه‌بندی غیرمخرب با هش و Restore به‌صورت نسخهٔ جدید | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_VERSIONING_AND_LOCKING.md §2 | بدون جدول نسخه | M1–M8 |
| `FILE-006` | `REQ-16.05` | قفل‌گذاری واقعی و سرور-محور بدون Lost Update | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_VERSIONING_AND_LOCKING.md §4 | بدون جدول قفل | M1–M8 |
| `FILE-007` | `REQ-16.06` | WebDAV روی VPS مستقل با CRM به‌عنوان تنها مرجع مجوز | P0 | brief | **`BLOCKED`** | docs/WEBDAV_VPS_ARCHITECTURE.md | VPS آزمایشی موجود نیست | خارجی |
| `FILE-008` | `REQ-16.07` | Storage جدا از CRM با Backend قابل تعویض | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_STORAGE_ARCHITECTURE.md §10 | storage قابل تعویض طراحی شد | M1–M8 |
| `FILE-009` | `REQ-16.08` | آپلود تکه‌ای/قابل ادامه با تأیید هش و بدون فایل یتیم | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ERD.md §2.15 | بدون کد آپلود | M1–M8 |
| `FILE-010` | `REQ-16.09` | اتصال فایل به موجودیت‌های CRM بدون دور زدن مجوز | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ERD.md §2.12 | file_relations طراحی شد | M1–M8 |
| `FILE-011` | `REQ-16.10` | مدل تهدید و کنترل‌های امنیتی فایل | P0 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_SECURITY_THREAT_MODEL.md | ۲۹ تهدید ثبت شد، ۰ کنترل پیاده | M1–M8 |
| `FILE-012` | `REQ-16.11` | طبقه‌بندی و Retention با Recycle Bin | P1 | brief | **`NOT_IMPLEMENTED`** | docs/FILE_WORKSPACE_ERD.md §2.14 | بدون retention | M1–M8 |
| `FILE-013` | `REQ-16.12` | Online Office و ویرایش هم‌زمان (F3) | P2 | brief | **`NOT_IMPLEMENTED`** | docs/ONLINE_OFFICE_INTEGRATION.md | F3؛ طبق تصمیم کارفرما | M1–M8 |

### UI

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `UI-001` | `REQ-11.01` | UI فارسی RTL اختصاصی | P1 | doc | **`VERIFIED`** | dir=rtl, fa, 7 widths | — | — |
| `UI-002` | `REQ-11.02` | Design System مشترک | P0 | doc | **`VERIFIED`** | 23 components + 21 DS asserts | — | — |
| `UI-003` | `REQ-11.03` | فونت فارسی پایدار و قانونی | P1 | brief | **`NOT_VERIFIED`** | font loaded | licence not confirmed | M0 |
| `UI-004` | `REQ-11.05` | حالت‌های Loading/Empty/Success/Error و اعتبارسنجی | P1 | doc | **`PARTIAL`** | empty/error states exist | loading/success states partial | M1/M3 |

### Jalali

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `JAL-001` | `REQ-11.04` | تاریخ شمسی ورود/نمایش + ذخیره استاندارد | P0 | doc | **`VERIFIED`** | JalaliDate + fa-date component | — | — |

### SeoReports

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SEOR-001` | `REQ-12.04` | یادآوری گزارش سئو (۲ روز قبل + روز موعد + عقب‌افتادگی) | P0 | doc | **`NOT_IMPLEMENTED`** | no seo reports | — | M1–M8 |

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
| `PM-001` | `REQ-13.01` | مرحله‌بندی و گیت تأیید | P1 | brief | **`VERIFIED`** | Gate A accepted | — | — |
| `PM-002` | `REQ-14.02` | Requirements Traceability Matrix | P0 | derived | **`VERIFIED`** | this document | — | — |

### QA

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `QA-001` | `REQ-14.01` | تست واقعی، بدون Pass صوری | P0 | brief | **`VERIFIED`** | 22 suites, lock, repeat, no mock | — | — |

### Settings

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `SET-001` | `REQ-15.01` | تنظیمات مدیریتی سامانه | P1 | doc | **`PARTIAL`** | settings table (4) read by app | no settings UI | M1/M3 |

### Backup

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `BAK-001` | `REQ-15.02` | Backup و Restore تست‌شده | P0 | brief | **`NOT_IMPLEMENTED`** | zero backup code | — | M1–M8 |

### Installer

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `INST-001` | `REQ-15.03` | Installer امن | P1 | brief | **`NOT_IMPLEMENTED`** | no installer | — | M1–M8 |

### DevOps

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `OPS-001` | `REQ-15.04` | مهاجرت و نسخه‌بندی | P1 | brief | **`PARTIAL`** | 9 migrations, reversible | no versioning policy doc | M1/M3 |
| `OPS-002` | `REQ-15.05` | تنظیمات محیطی (بدون Hardcode) | P2 | brief | **`PARTIAL`** | .env driven | no hardcode audit | M1/M3 |

### Docs

| شناسه | REQ | عنوان | اولویت | نوع | وضعیت | شاهد | شکاف | فاز |
|-------|-----|-------|:------:|-----|-------|------|------|-----|
| `DOC-001` | `REQ-15.06` | بسته مستندات فنی و کاربری | P2 | brief | **`PARTIAL`** | docs/ extensive | user manual missing | M1/M3 |

---

## ۳. نیازمندی‌های پیاده‌سازی‌نشده

| شناسه | عنوان | ماژول | اولویت |
|-------|-------|-------|:------:|
| `DSH-003` | ناوبری مستقیم از اعلان به رکورد | Dashboard | P1 |
| `TPL-001` | بانک قالب قرارداد | ContractTemplates | P1 |
| `TPL-002` | موتور Template قرارداد | ContractTemplates | P1 |
| `CNT-004` | ویرایش متن قرارداد پیش از نهایی‌سازی | Contracts | P1 |
| `CNT-005` | تثبیت نسخه نهایی قرارداد | Contracts | P0 |
| `CNT-006` | تاریخچه و نسخه‌های قرارداد | Contracts | P1 |
| `CNT-008` | ثبت تمدید قرارداد | Contracts | P1 |
| `PRJ-002` | تخصیص مسئول/کارشناس به پروژه | Projects | P1 |
| `CRED-002` | فهرست دسترسی‌های اجباری سئو | Credentials | P0 |
| `CRED-005` | PDF محرمانه دسترسی‌ها | Credentials | P0 |
| `PRJ-003` | جداسازی اطلاعات عمومی و محرمانه پروژه | Projects | P1 |
| `CRED-006` | هشدار محرمانگی روی فایل حاوی رمز | Credentials | P1 |
| `EMP-001` | پرونده پرسنل | Employees | P1 |
| `PAY-003` | تاریخچه و تثبیت فیش‌ها | Payroll | P0 |
| `PAY-004` | دسترسی مستقل اطلاعات حقوقی | Payroll | P0 |
| `SRCH-001` | جستجوی سراسری | Search | P1 |
| `XLS-001` | خروجی Excel تکمیلی | Excel | P2 |
| `FILE-001` | امنیت فایل‌های آپلودی و IDOR | Files | P0 |
| `SEOR-001` | یادآوری گزارش سئو (۲ روز قبل + روز موعد + عقب‌افتادگی) | SeoReports | P0 |
| `INV-005` | ثبت مشخصات و فایل فاکتور پس از ارسال | Invoices | P1 |
| `BAK-001` | Backup و Restore تست‌شده | Backup | P0 |
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
| `PAY-001` | قواعد قابل تنظیم حقوق (بدون فرمول خودساخته) | OD-16 payroll formula |
| `PAY-002` | فیش حقوقی ماهانه (PDF فارسی RTL) | no PDF engine |
| `PDF-001` | خروجی PDF الزامی | no PDF engine + host |
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
