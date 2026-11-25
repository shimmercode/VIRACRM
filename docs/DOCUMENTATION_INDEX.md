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
| ۳ | `DATABASE_SCHEMA.md` | `docs/DATABASE_SCHEMA.md` (تولیدشده از پایگاه، ۵۷ جدول) + `docs/ERD.md` (۹۳ کلید خارجی) | `COVERED` — هر دو از پایگاه تولید می‌شوند و `docs-integrity` هم‌خوانی‌شان را می‌سنجد |
| ۴ | `API_DOCUMENTATION.md` | `docs/API_DOCUMENTATION.md` | `PARTIAL` — سند موجود است، ولی محتوایش این واقعیت است که **API وجود ندارد**: `routes/api.php` نیست، Sanctum/Passport نصب نیست، از ۱۰۵ مسیر هیچ‌کدام API نیست. قرارداد OpenAPI ساخته نشد چون چیزی برای توصیف نبود |
| ۵ | `RBAC_PERMISSION_MATRIX.md` | `docs/RBAC_PERMISSION_MATRIX.md` | `COVERED` — ماتریس کامل نقش×مجوز، تولیدشده از `permissions`/`role_permissions` (۴ نقش × ۵۵ مجوز) با ستون مجوزهای حساس |
| ۶ | `SECURITY_AUDIT.md` | `docs/SECURITY_REVIEW.md` (۲۰ کنترل) + `docs/SECURITY_ARCHITECTURE.md` | `COVERED` — سند دوم معماری را از کد می‌خواند: ترتیب میدل‌ور، CSP، AES-256-GCM، سیاست رمز، و گپ‌های باز |
| ۷ | `DEPLOYMENT_CPANEL.md` | `docs/DEPLOYMENT_GUIDE.md` + `docs/HOST_COMPATIBILITY.md` | `COVERED` |
| ۸ | `ENVIRONMENT_CONFIGURATION.md` | `docs/phase-1/18-environment-configuration.md` + `.env.example` | `COVERED` |
| ۹ | `ASSET_LOADING_AND_BASE_PATH.md` | `docs/ASSET_LOADING_REMEDIATION.md` | `COVERED` |
| ۱۰ | `ACCOUNTING_ARCHITECTURE.md` | `docs/phase-3/ACCOUNTING_DESIGN.md` (۲۲ KB) | `PARTIAL` — **طراحی است، کد نیست**؛ OD-41 تأیید نشده |
| ۱۱ | `FILE_WORKSPACE_ARCHITECTURE.md` | `docs/FILE_STORAGE_ARCHITECTURE.md` | `PARTIAL` — طراحی کامل، **صفر کد** |
| ۱۲ | `pdf/PDF_ENGINE.md` | — | `PRESENT` — mPDF نصب و تأیید شده؛ چهار خروجی با تست (pdf-flow 22/22) |
| ۱۳ | `RESPONSIVE_QA_REPORT.md` | `docs/CRM_REVIEW_RESPONSIVE.md` + نتیجهٔ `ui-shots` | `COVERED` |
| ۱۴ | `REGRESSION_REPORT.md` | `docs/REGRESSION_LOCK.md` + خروجی زندهٔ `run-all.mjs` | `COVERED` |
| ۱۵ | `BACKUP_AND_RESTORE.md` | `docs/BACKUP_RESTORE_GUIDE.md` + `docs/BACKUP_RECOVERY.md` | `PARTIAL` — راهنما نوشته شد، اما **صفر کد بکاپ** وجود دارد: جدول `backup_runs` ساخته نشده، هیچ فرمان کنسولی نیست و بازیابی هرگز آزموده نشده. روش دستیِ مستندشده جواب می‌دهد، خودکار نیست |
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
| `COVERED` (محتوا کامل، نام متفاوت) | **۱۱** |
| `PRESENT` (سند با همان نام ساخته شده) | **۱** |
| `PARTIAL` (محتوا هست، شکاف دارد) | **۵** |
| `MISSING` (محتوا نیست) | **۰** |
| **جمع ۱۷ سند نام‌گذاری‌شده** | **۱۷** |

> این عددها با شمارش مستقیم ستون «وضعیت» جدول بالا به دست آمده‌اند
> (۱۱ + ۱ + ۵ + ۰ = ۱۷). پیش‌تر این جمع‌بندی ۱۲/۴/۱ بود که با جدول نمی‌خواند:
> یک `PRESENT` زیر `COVERED` شمرده شده بود و یک `PARTIAL` جا افتاده بود. عددی که
> با جدول خودش نمی‌خواند، دقیقاً همان چیزی است که این سند علیه‌اش نوشته شده.

به‌علاوهٔ ۹ سندی که از ابتدا نام درست داشتند → **۲۱ از ۲۶** مورد محتوای واقعی دارند.

> **۵ مورد `PARTIAL`، با دلیل واقعی هر کدام:**
> `API_DOCUMENTATION` (سند هست و صریحاً می‌گوید API پیاده‌سازی نشده؛ OpenAPI نیست
> چون API نیست)، `ACCOUNTING_ARCHITECTURE` (طراحی است، OD-41 تأیید نشده)،
> `FILE_WORKSPACE_ARCHITECTURE` (طراحی کامل، **صفر کد**)، `BACKUP_AND_RESTORE`
> (راهنمای دستی نوشته شد، ولی **صفر کد بکاپ** و بازیابیِ آزموده‌نشده)،
> `FINAL_HANDOVER_REPORT` (تحویل نهایی منوط به هاست است).
>
> **دو موردی که قبلاً اینجا بودند و دیگر نیستند:** `PDF_TEST_REPORT` و
> `RBAC_PERMISSION_MATRIX` — هر دو حالا نوشته شده‌اند
> (`docs/pdf/PDF_TEST_REPORT.md` با ۱۰ باگِ یافت‌شده، و ماتریس نقش×مجوزِ
> تولیدشده از پایگاه).

---

## ۳. اسنادی که این چرخه اضافه کرد

| سند | چرا |
|-----|-----|
| `docs/CREDENTIAL_MODULE.md` | ماژول جدید §۶ که پیش از این سند نداشت |
| `docs/DOCUMENTATION_INDEX.md` | همین فایل |
| `docs/evidence/credentials/` | شاهد اجرایی ماژول دسترسی‌ها |
| `docs/pdf/PDF_ENGINE.md`، `docs/pdf/PDF_TEST_REPORT.md` | موتور PDF و ۱۰ باگِ یافت‌شده |

### چرخهٔ بعد — اسنادی که نام درخواستی داشتند و حالا واقعاً ساخته شدند

| سند | چگونه |
|-----|-----|
| `docs/DATABASE_SCHEMA.md` | **تولیدشده** از `database.sqlite` (۵۷ جدول) با `tools/generate-schema-doc.mjs` |
| `docs/RBAC_PERMISSION_MATRIX.md` | **تولیدشده** از `permissions`/`role_permissions` (۴ نقش × ۵۵ مجوز) |
| `docs/ERD.md` + `docs/database/erd.dot` | **تولیدشده** از `pragma foreign_key_list` (۹۳ کلید خارجی) |
| `docs/RELEASE_MANIFEST.md` | **تولیدشده** از درخت کاری (شمارش + SHA-256 اقلام) |
| `docs/SECURITY_ARCHITECTURE.md` | نوشته‌شده از کد: ترتیب میدل‌ور، CSP، AES-256-GCM، سیاست رمز |
| `docs/AUDIT_LOG_SPECIFICATION.md` | نوشته‌شده از `AuditRecorder` و شمارش واقعی رویدادها |
| `docs/API_DOCUMENTATION.md` | نوشته‌شده — و محتوایش این است که **API وجود ندارد** |
| `docs/BACKUP_RESTORE_GUIDE.md` | روش دستیِ آزموده‌شده، با این واقعیت که **صفر کد بکاپ** هست |
| `docs/REGRESSION_TEST_REPORT.md` | خروجی واقعی `run-all.mjs` (۳۶ سوئیت / ۱۰۷۵ ادعا) |

> چهار مورد اول عمداً تولیدشده‌اند، نه دست‌نویس. ماتریس دسترسی که دستی نوشته شود،
> از اولین باری که کسی مجوزی اضافه کند غلط است و هنوز معتبر به نظر می‌رسد.
> `docs-integrity` هم‌خوانی هر چهار مورد را می‌سنجد، پس کهنه شدنشان شکستِ build است.

---

## ۴. قاعدهٔ نگهداری

این فهرست **دستی** است و باید دستی به‌روز شود. برخلاف
`REQUIREMENTS_TRACEABILITY.md` که تولیدشده است و `--check` دارد، این سند
وابسته به قضاوت است (آیا محتوا «کامل» است؟) و خودکارسازی آن گمراه‌کننده می‌شد.

هر بار سندی اضافه یا ادغام شد، این جدول باید همان کامیت به‌روز شود.

**هشدارِ تجربه‌شده:** ستون «وضعیت» و جمع‌بندی عددی بخش ۲ می‌توانند از جدولِ خودشان
جدا بیفتند — و افتاده بودند. جمع‌بندی ۱۲/۴/۱ ادعا می‌کرد در حالی که جدول
۱۱ `COVERED`، ۱ `PRESENT`، ۵ `PARTIAL` و ۰ `MISSING` داشت. هیچ بررسی خودکاری این
را نمی‌گیرد، چون هیچ‌کس ستون وضعیت را ماشین‌خوان تعریف نکرده. پس هر بار که سطری
را عوض می‌کنید، عددهای بخش ۲ را با دست بشمارید.
