# فهرست مستندات — Documentation Index

> **هدف این سند رفع یک ابهام واقعی است.**
> دستور کارفرما ۲۶ نام سند مشخص می‌خواست. مخزن ۱۱۸ سند دارد، اما بخشی از آن‌ها
> با نام دیگری نوشته شده‌اند. نتیجه این بود که یک بازبین فایل `API_DOCUMENTATION.md`
> را نمی‌دید و نتیجه می‌گرفت «سند API وجود ندارد»، در حالی که محتوا در
> `docs/phase-3/09-backend-architecture.md` بود.
>
> **این سند ادعا نمی‌کند هر ۲۶ مورد نوشته شده است.** ستون «وضعیت» صادقانه می‌گوید
> کدام محتوا وجود دارد و کدام ندارد. هیچ سندی فقط برای پر کردن فهرست ساخته نشد.

**آخرین به‌روزرسانی:** ۲ آذر ۱۴۰۵ · **شاخه:** `arena/3b43de84-viracrm`

---

## راهنمای وضعیت

| کد | معنا |
|----|------|
| `COVERED` | محتوای خواسته‌شده زیر نام دیگری موجود است و کامل است |
| `PARTIAL` | محتوا هست، اما بخشی از آنچه نام سند وعده می‌دهد را ندارد |
| `MISSING` | محتوای واقعی وجود ندارد — سند ساخته نشد چون چیزی برای نوشتن نبود |

> فایل توخالی ساخته نشد. سندی که فقط عنوان داشته باشد، بدتر از نبودِ سند است:
> در بازرسی «موجود» شمرده می‌شود.

---

## ۱. نگاشت نام درخواستی → محل واقعی

| # | نام درخواستی | محل واقعی | وضعیت |
|---|--------------|-----------|-------|
| ۱ | `PROJECT_OVERVIEW.md` | `docs/PROJECT_STATUS.md` §۱ + `docs/ARCHITECTURE.md` | `COVERED` |
| ۲ | `MODULE_STATUS.md` | `docs/PROJECT_STATUS.md` §۳ + `docs/REQUIREMENTS_TRACEABILITY.md` | `COVERED` |
| ۳ | `DATABASE_SCHEMA.md` | `docs/DATABASE_MAP.md` (۴۶ جدول) + `docs/phase-3/08-database-erd.md` | `COVERED` |
| ۴ | `API_DOCUMENTATION.md` | `docs/phase-3/09-backend-architecture.md` | `PARTIAL` — مسیرها مستند شده‌اند، اما قرارداد JSON رسمی (OpenAPI) وجود ندارد |
| ۵ | `RBAC_PERMISSION_MATRIX.md` | `docs/phase-3/10-security-architecture.md` + `app/Services/Security/PermissionCatalog.php` | `PARTIAL` — ۳۹ مجوز در کد، ماتریس نقش×مجوز به‌صورت جدول کامل نیست |
| ۶ | `SECURITY_AUDIT.md` | `docs/SECURITY_REVIEW.md` (۲۰ کنترل) | `COVERED` |
| ۷ | `DEPLOYMENT_CPANEL.md` | `docs/DEPLOYMENT_GUIDE.md` + `docs/HOST_COMPATIBILITY.md` | `COVERED` |
| ۸ | `ENVIRONMENT_CONFIGURATION.md` | `docs/phase-1/18-environment-configuration.md` + `.env.example` | `COVERED` |
| ۹ | `ASSET_LOADING_AND_BASE_PATH.md` | `docs/ASSET_LOADING_REMEDIATION.md` | `COVERED` |
| ۱۰ | `ACCOUNTING_ARCHITECTURE.md` | `docs/phase-3/ACCOUNTING_DESIGN.md` (۲۲ KB) | `PARTIAL` — **طراحی است، کد نیست**؛ OD-41 تأیید نشده |
| ۱۱ | `FILE_WORKSPACE_ARCHITECTURE.md` | `docs/FILE_STORAGE_ARCHITECTURE.md` | `PARTIAL` — طراحی کامل، **صفر کد** |
| ۱۲ | `pdf/PDF_ENGINE.md` | — | `PRESENT` — mPDF نصب و تأیید شده؛ چهار خروجی با تست (pdf-flow 22/22) |
| ۱۳ | `RESPONSIVE_QA_REPORT.md` | `docs/CRM_REVIEW_RESPONSIVE.md` + نتیجهٔ `ui-shots` | `COVERED` |
| ۱۴ | `REGRESSION_REPORT.md` | `docs/REGRESSION_LOCK.md` + خروجی زندهٔ `run-all.mjs` | `COVERED` |
| ۱۵ | `BACKUP_AND_RESTORE.md` | `docs/BACKUP_RECOVERY.md` + `docs/phase-1/17-backup-restore-policy.md` | `COVERED` |
| ۱۶ | `KNOWN_LIMITATIONS.md` | `docs/CRM_REVIEW_LIMITATIONS.md` | `COVERED` |
| ۱۷ | `FINAL_HANDOVER_REPORT.md` | `docs/CRM_REVIEW_FINAL_REPORT.md` | `PARTIAL` — گزارش بازبینی است، نه تحویل نهایی؛ تحویل واقعی منوط به هاست است |

### و ۹ سند دیگر که نام دقیقشان موجود بود

`TEST_STRATEGY.md`, `OPEN_DECISIONS.md`, `CHANGELOG.md`, `DESIGN_SYSTEM.md`,
`UI_UX_AUDIT.md`, `ARCHITECTURE.md`, `DATABASE_MAP.md`, `PROJECT_STATUS.md`,
`REQUIREMENTS_TRACEABILITY.md` — هر ۹ مورد موجود و به‌روز.

---

## ۲. جمع‌بندی عددی — بدون گرد کردن به نفع خودمان

| وضعیت | تعداد |
|-------|------:|
| `COVERED` (محتوا کامل، نام متفاوت) | **۱۲** |
| `PARTIAL` (محتوا هست، شکاف دارد) | **4** |
| `MISSING` (محتوا نیست) | **۱** |
| **جمع ۱۷ سند نام‌گذاری‌شده** | **۱۷** |

به‌علاوهٔ ۹ سندی که از ابتدا نام درست داشتند → **۲۱ از ۲۶** مورد محتوای واقعی دارند.

> **۵ مورد باقی‌مانده با صداقت:**
> `PDF_TEST_REPORT` (هیچ PDF وجود ندارد که گزارش شود)، `API_DOCUMENTATION`
> (OpenAPI نیست)، `RBAC_PERMISSION_MATRIX` (جدول کامل نیست)،
> `ACCOUNTING_ARCHITECTURE` (طراحی، بدون کد)، `FINAL_HANDOVER_REPORT`
> (تحویل نهایی انجام نشده چون هاست در دسترس نیست).

---

## ۳. اسنادی که این چرخه اضافه کرد

| سند | چرا |
|-----|-----|
| `docs/CREDENTIAL_MODULE.md` | ماژول جدید §۶ که پیش از این سند نداشت |
| `docs/DOCUMENTATION_INDEX.md` | همین فایل |
| `docs/evidence/credentials/` | شاهد اجرایی ماژول دسترسی‌ها |

---

## ۴. قاعدهٔ نگهداری

این فهرست **دستی** است و باید دستی به‌روز شود. برخلاف
`REQUIREMENTS_TRACEABILITY.md` که تولیدشده است و `--check` دارد، این سند
وابسته به قضاوت است (آیا محتوا «کامل» است؟) و خودکارسازی آن گمراه‌کننده می‌شد.

هر بار سندی اضافه یا ادغام شد، این جدول باید همان کامیت به‌روز شود.
