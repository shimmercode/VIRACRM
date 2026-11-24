# FILE_COLLABORATION_RESEARCH — تحقیقات و امکان‌سنجی ماژول فایل

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت سند:** نهایی برای بازبینی کارفرما

> **قاعدهٔ حاکم بر این سند:** هیچ ادعای فنی بدون منبع ثبت نشده است.
> هر جا مستندات رسمی و رفتار واقعی نرم‌افزار متفاوت بوده، **هر دو** ثبت شده‌اند.
> هر جا امکان بررسی نبوده، به‌جای حدس نوشته شده: `NOT TESTED` یا «نیازمند استعلام».

---

## ۰. خلاصهٔ مدیریتی

| # | نتیجه | وضعیت | اثر بر معماری |
|---|-------|-------|----------------|
| ۱ | **Nextcloud** کامل‌ترین محصول است ولی سنگین‌ترین بار عملیاتی را دارد (PHP + SabreDAV + Redis + صف پس‌زمینه + دیتابیس خودش) و مدل مجوز آن با RBAC فعلی CRM موازی می‌شود. | `VERIFIED` | به‌عنوان «سیستم فایل» رد شد؛ به‌عنوان مرجع الگوی طراحی نگه داشته شد. |
| ۲ | **Seafile CE** قفل فایل، Audit Log و مجوز پوشه‌ای ریزدانه ندارد؛ این‌ها Pro هستند. | `VERIFIED` | رد — الزامات FR-VERSION-006 و FR-SEC-018 را برآورده نمی‌کند. |
| ۳ | **ownCloud Core** مجوز AGPL و نسخهٔ 10.16.3 دارد؛ قفل از 10.5 «دستی» است. | `VERIFIED` | رد به همان دلیل معماری موازی. |
| ۴ | **ONLYOFFICE CE** سقف **۲۰ اتصال هم‌زمان** دارد و WOPI از نسخهٔ 6.4 با `wopi.enable=false` پیش‌فرض. | `VERIFIED` | F3؛ در F1 مستقر نمی‌شود (تصمیم قطعی کارفرما §2.3). |
| ۵ | **Collabora CODE** مجوز MPLv2، بدون SLA (rolling release)؛ ظرفیت ≈۱۰ کاربر به‌ازای هر Thread و ۵۰MB RAM به‌ازای هر کاربر. | `VERIFIED` | گزینهٔ F3 در کنار ONLYOFFICE؛ هیچ‌کدام در F1. |
| ۶ | **MinIO Community Edition** بایگانی شده و نگهداری نمی‌شود. | `VERIFIED` | **به‌عنوان Backend پیشنهاد نمی‌شود.** |
| ۷ | **sabre/dav 4.7.1** (۲۰۲۶-۰۷-۰۷)، مجوز BSD-3-Clause، فعال؛ RFC 4918 و قفل را پشتیبانی می‌کند. | `VERIFIED` | **انتخاب‌شده** برای سرویس WebDAV. |
| ۸ | سرویس **WebClient ویندوز از نوامبر ۲۰۲۳ Deprecated** است و به‌صورت پیش‌فرض اجرا نمی‌شود. | `VERIFIED` | انتظارات کاربران باید مدیریت شود؛ §7 سند `WINDOWS_WEBDAV_COMPATIBILITY.md`. |
| ۹ | **Laravel 12 روی دیسک `local` با `serve => true` از `temporaryUrl()` پشتیبانی می‌کند.** | `VERIFIED` (اجرا شد) | تصحیح یک فرض قبلی پروژه؛ لینک امضاشدهٔ کوتاه‌عمر ممکن است. |
| ۱۰ | همان مسیر **Range Request را پشتیبانی نمی‌کند** و Content-Type را از **محتوا** (نه پسوند) تعیین می‌کند. | `VERIFIED` (اجرا شد) | مسیر دانلود اختصاصی لازم است؛ پیش‌نمایش نباید به این Route تکیه کند. |
| ۱۱ | هیچ محصول ایرانی بررسی‌شده، مستندات عمومی WebDAV/API/قیمت منتشر نکرده است. | `NEEDS INQUIRY` | «نیازمند استعلام» — هیچ عددی حدس زده نشده. |

---

## ۱. روش‌شناسی و دامنهٔ اعتبار

| مورد | مقدار |
|------|-------|
| تاریخ بررسی | ۲۰۲۶-۱۱-۲۳ (۲ آذر ۱۴۰۵) |
| محیط بررسی واقعی | همین مخزن: Laravel **12.40.0** · PHP `^8.2` (اجراشده روی php-wasm 8.3) |
| روش | (۱) مستندات رسمی، (۲) مخزن کد رسمی، (۳) **اجرای واقعی کد در همین مخزن** برای ادعاهای Laravel |
| محیطی که بررسی نشد | هاست واقعی cPanel · VPS واقعی · ویندوز واقعی · سرور ONLYOFFICE/Collabora |

**آنچه در این سند «اثبات‌شده» نامیده می‌شود** یعنی در همین مخزن اجرا و مشاهده شده است، نه اینکه در محیط تولید آزموده شده باشد.

---

## ۲. تصحیح ادعاهای قبلی (بازبینی الزامی §3 دستور)

دستور کارفرما صریحاً خواسته ادعاهای قبلی دوباره بررسی شوند و هیچ‌کدام «بر اساس گزارش قبلی» قطعی تلقی نشود.

### ۲.۱. پشتیبانی Range Request در Laravel Storage

| مورد | نتیجه |
|------|-------|
| **ادعای قبلی** | «Laravel Storage از Range پشتیبانی می‌کند» (فرض رایج) |
| **آنچه در کد اجرا شد** | درخواست `Range: bytes=0-99` به مسیر `storage.local` → پاسخ `HTTP 200` کامل، **بدون** `Accept-Ranges` و **بدون** `Content-Range`؛ بدنه ۰ بایت در محیط php-wasm |
| **منبع کد** | `vendor/laravel--framework/src/Illuminate/Filesystem/FilesystemAdapter.php::response()` — از `StreamedResponse` + `fpassthru()` استفاده می‌کند و هدر `Content-Length` را از `size()` می‌گذارد ولی هیچ منطق Range ندارد |
| **نتیجهٔ قابل استفاده** | **`VERIFIED`: مسیر داخلی `storage.local` رنج نمی‌دهد.** برای پیش‌نمایش PDF/ویدیو و دانلود قابل ادامه، یک **Controller دانلود اختصاصی** لازم است که `206 Partial Content` را پیاده کند. |
| **اطمینان** | بالا (کد + اجرا) · **محدودیت:** بدنهٔ ۰ بایتی یک اثر محیط php-wasm است، نه رفتار Laravel؛ هدرها اما مستقل از محیط‌اند. |

### ۲.۲. رفتار Content-Type و Content-Disposition

| مورد | نتیجه |
|------|-------|
| **آنچه اجرا شد** | فایلی با **محتوای HTML** و نام `photo.png` روی دیسک `local` قرار گرفت و از مسیر امضاشده سرو شد |
| **پاسخ واقعی** | `content-type: text/html; charset=utf-8` · `content-disposition: inline; filename=photo.png` · `content-security-policy: default-src 'none'; style-src 'unsafe-inline'; sandbox` |
| **علت در کد** | `FilesystemAdapter::response()` مقدار `Content-Type` را از `mimeType($path)` می‌گیرد و `mimeType()` به `$this->driver->mimeType()` (تشخیص محتوای Flysystem) واگذار می‌شود — **نه پسوند فایل** |
| **نتیجهٔ قابل استفاده** | ۱) **Content-Type از محتوا** تعیین می‌شود؛ بنابراین یک فایل مخرب با پسوند تصویری، به‌صورت `text/html` برگردانده می‌شود. ۲) هدر CSP سخت‌گیرانهٔ `sandbox` جلوی اجرای اسکریپت را در مرورگرهای مدرن می‌گیرد، ولی **لایهٔ دوم** است نه لایهٔ اول. ۳) `inline` پیش‌فرض است و باید برای فایل‌های غیرقابل‌پیش‌نمایش به `attachment` تغییر کند. |
| **اطمینان** | بالا (اجرا + کد) |

### ۲.۳. پشتیبانی واقعی WebDAV از Locking

| مورد | نتیجه |
|------|-------|
| **sabre/dav** | صفحهٔ Standards Support: `rfc4918` = «Supported. Duh.» · صفحهٔ Locks: پلاگین `Sabre\DAV\Locks\Plugin` با Backend از نوع **PDO** (SQLite/MySQL/PostgreSQL) یا File — قفل‌ها **ماندگار** هستند، نه در حافظه |
| **نتیجهٔ قابل استفاده** | قفل واقعی WebDAV در سرویس VPS **قابل پیاده‌سازی** است و می‌تواند همان جدول قفل CRM را تغذیه کند. ولی: قفل WebDAV و قفل UI دو منبع حقیقت نمی‌توانند داشته باشند؛ باید **یک جدول** باشند (سند `FILE_VERSIONING_AND_LOCKING.md` §5). |
| **منبع** | https://sabre.io/dav/standards-support/ · https://sabre.io/dav/locks/ |
| **اطمینان** | بالا (مستند رسمی پروژه) · **`NOT TESTED`:** رفتار قفل با Windows WebClient واقعی |

### ۲.۴. محدودیت ۵۰MB و تعداد فایل در پوشه در Windows WebClient

| مورد | نتیجه |
|------|-------|
| `FileSizeLimitInBytes` | پیش‌فرض **50,000,000** بایت (~۴۷.۷ MiB) در `HKLM\SYSTEM\CurrentControlSet\Services\WebClient\Parameters` |
| `FileAttributesLimitInBytes` | پیش‌فرض **1,000,000** بایت — «حدود ۱۰۰۰ فایل در یک پوشه» |
| سقف **تنظیم** | مقدار از نوع `REG_DWORD` (۳۲ بیتی) است، پس **قابل تنظیم** تا حداکثر `4,294,967,295` بایت (۴ GiB منهای ۱ بایت). این سقف **نوع داده** است، نه یک محدودیت اعلام‌شدهٔ انتقال. |
| `BasicAuthLevel` | پیش‌فرض **1** = Basic فقط روی SSL |
| `SupportLocking` | پیش‌فرض **1** |
| منبع رسمی (پیش‌فرض‌ها) | Microsoft Learn — *"You can't access a WebDAV Web folder from a Windows-based client computer"*: `FileAttributesLimitInBytes` = DWORD، پیش‌فرض `1,000,000` — <https://learn.microsoft.com/en-us/troubleshoot/windows-client/networking/cannot-access-webdav-web-folder> · بررسی ۲۰۲۶-۱۱-۲۳ |
| منبع رسمی (سایر کلیدها) | جدول Registry توسط R. McMurray (تیم IIS مایکروسافت) منتشر شده — <https://blogs.iis.net/robert_mcmurray/webdav-redirector-registry-settings/> · بررسی ۲۰۲۶-۱۱-۲۳ · **۲۰۰۸، برای Vista/Server 2008** |
| منبع رسمی (نبودِ سقف حجم) | صفحهٔ Win32 WebDAV Portal <https://learn.microsoft.com/en-us/windows/win32/webdav/webdav-portal> (آخرین به‌روزرسانی ۲۰۲۰-۰۸-۱۹) **هیچ محدودیت حجم فایل، هیچ کلید Registry و هیچ سقف ۴ گیگابایتی را مستند نمی‌کند** |
| **تصحیح مهم** | جدا از سقف حجم، **سرویس WebClient از نوامبر ۲۰۲۳ Deprecated است** — به نقل مستقیم از صفحهٔ رسمی «Deprecated features for Windows client»: «The Webclient (WebDAV) service is deprecated. The Webclient service isn't started by default in Windows.» |
| **نتیجهٔ قابل استفاده** | ۱) «درایو شبکه‌ای ویندوز» بدون تنظیم Registry و بدون شروع دستی سرویس کار نمی‌کند. ۲) پیش‌فرض ۵۰MB عملاً یعنی هر فایل بزرگ‌تر **تا وقتی `FileSizeLimitInBytes` بالا برود** رد می‌شود. ۳) **دربارهٔ ۴ گیگابایت هیچ ادعای قطعی نمی‌کنیم:** نوع `REG_DWORD` سقف *تنظیم* را روی ۴ GiB نگه می‌دارد، اما اینکه انتقال در آن اندازه واقعاً موفق شود **`NOT TESTED`** است. |
| **اطمینان** | **متوسط تا بالا.** پیش‌فرض‌ها: بالا (Microsoft Learn). سقف ۴ GiB: **متوسط — استنتاج از عرض DWORD، نه سند رسمی.** ادعاهای پیش‌فرض فقط برای **Vista/7/Server 2008 به بعد** معتبرند و روی Build هدف باید اندازه‌گیری شوند. **`NOT TESTED`:** رفتار واقعی در ویندوز هدف، از جمله انتقال‌های نزدیک ۴ GiB. |

### ۲.۵. وضعیت نگهداری MinIO Community Edition

| مورد | نتیجه |
|------|-------|
| وضعیت | مخزن Community Edition **بایگانی (archived)** شده و README آن «no longer maintained» است؛ آخرین انتشار `RELEASE.2025-10-15T17-29-55Z` |
| مجوز | AGPL-3.0 |
| مسیر تجاری | AIStor |
| **نتیجهٔ قابل استفاده** | استفاده از MinIO به‌عنوان Backend در یک سیستم سازمانی **توصیه نمی‌شود**: بدون وصلهٔ امنیتی. جایگزین‌ها: **SeaweedFS** (Apache-2.0) یا **S3 مدیریت‌شدهٔ ارائه‌دهنده**. |
| **اطمینان** | بالا (وضعیت مخزن رسمی) |

### ۲.۶. وضعیت نسخه و مجوز sabre/dav

| مورد | نتیجه |
|------|-------|
| آخرین انتشار | **4.7.1** — ۲۰۲۶-۰۷-۰۷ |
| آخرین Push | ۲۰۲۶-۱۱-۰۱ (فعال) |
| مجوز | **BSD-3-Clause** (سازگار با استفادهٔ تجاری و بستهٔ اختصاصی) |
| وابستگی‌ها | PHP خالص، بدون نیاز به Node.js runtime |
| **نتیجهٔ قابل استفاده** | مناسب برای **دقیقاً همان مدل استقرار cPanel+PHP**؛ تنها کتابخانهٔ WebDAV که هم مجوز آزاد دارد، هم فعال است، هم قفل ماندگار دارد. |
| **منبع** | https://api.github.com/repos/sabre-io/dav · https://sabre.io/dav/ |

---

### ۲.۷. سه ادعا — نتیجهٔ دقیق بازبینی (به‌تفکیک)

> این بخش جایگزین هر جمع‌بندی کلی از نوع «تعداد ادعاهای رد‌شده» است. برای هر ادعا دقیقاً یک نتیجه ثبت شده،
> با منبع رسمی، محدودیت نسخه/محیط، و اثر مشخص بر معماری Vira Drive. **هیچ ادعایی با اتکا به گزارش قبلی قطعی فرض نشده است.**

#### الف) ادعا: «MinIO Community Edition یک Backend مناسب و پایدار برای S3 است»

| فیلد | مقدار |
|------|-------|
| **ادعای اصلی** | MinIO CE استاندارد S3 را پیاده می‌کند، مجوز آزاد دارد و می‌تواند بدون هزینهٔ مجوز میزبان بایت‌های Vira Drive باشد. |
| **یافتهٔ بازبینی** | مخزن Community Edition **بایگانی** شده و README «no longer maintained» است؛ آخرین انتشار `RELEASE.2025-10-15T17-29-55Z`. مسیر تجاری به AIStor منتقل شده. |
| **نتیجه** | **رد شد (REJECTED)** — به‌عنوان لایهٔ ذخیره‌سازی سازمانی. |
| **منبع رسمی** | مخزن رسمی MinIO (وضعیت Archive و README) · <https://github.com/minio/minio> · بررسی ۲۰۲۶-۱۱-۲۳ |
| **محدودیت نسخه/محیط** | آخرین نسخهٔ CE = `RELEASE.2025-10-15T17-29-55Z`. **از آن تاریخ هیچ وصلهٔ امنیتی منتشر نمی‌شود.** |
| **اثر بر معماری** | `FILE_STORAGE_ARCHITECTURE.md` و OD-102: MinIO **حذف** شد. جایگزین‌ها: **SeaweedFS (Apache-2.0)** یا **S3 مدیریت‌شدهٔ ارائه‌دهنده**. |
| **اطمینان** | **بالا** (وضعیت مخزن رسمی، قابل مشاهده) |

#### ب) ادعا: «Seafile Community Edition می‌تواند قفل، Audit و مجوز ریزدانهٔ Vira Drive را تأمین کند»

| فیلد | مقدار |
|------|-------|
| **ادعای اصلی** | Seafile CE جایگزین کامل ماژول فایل است: قفل فایل، Audit Log و مجوز پوشه‌ای ریزدانه دارد. |
| **یافتهٔ بازبینی** | این قابلیت‌ها در نسخهٔ **Pro** هستند، نه CE؛ صفحهٔ محصول صریحاً قفل و Audit را از قابلیت‌های CE جدا می‌کند. علاوه بر آن، مدل مجوز Seafile با RBAC فعلی CRM **موازی** می‌شود و نقض «CRM تنها مرجع مجوز» است. |
| **نتیجه** | **رد شد (REJECTED)** — برای F1. |
| **منبع رسمی** | صفحهٔ مقایسهٔ Community/Professional: <https://www.seafile.com/en/product/private_server/> · بررسی ۲۰۲۶-۱۱-۲۳ |
| **محدودیت نسخه/محیط** | ادعا **فقط** دربارهٔ Community Edition است؛ نسخهٔ Pro از نظر مجوز و هزینه ارزیابی نشده و بخشی از تصمیم F1 نیست. |
| **اثر بر معماری** | الزامات FR-VERSION-006 (قفل) و FR-SEC-018 (Audit) برآورده نمی‌شود → Seafile از گزینه‌های OD-101 حذف شد. |
| **اطمینان** | **بالا** (سند رسمی فروشنده) · **`NOT TESTED`:** نصب واقعی Seafile انجام نشد — رد بر پایهٔ مستند قابلیت است، نه آزمون. |

#### ج) ادعا: «WebDAV ویندوز برای Vira Drive یک درایو شبکه‌ای معادل SMB فراهم می‌کند»

| فیلد | مقدار |
|------|-------|
| **ادعای اصلی** | کاربر ویندوز می‌تواند درایو را Map کند و مانند یک درایو شبکه‌ای معمولی، بدون محدودیت اندازه، کار کند. |
| **یافتهٔ بازبینی** | سه محدودیت مستقل و هم‌زمان وجود دارد: (۱) سرویس **WebClient از نوامبر ۲۰۲۳ Deprecated** است و **پیش‌فرض اجرا نمی‌شود**؛ (۲) `FileSizeLimitInBytes` پیش‌فرض **50,000,000 بایت** است؛ (۳) `FileAttributesLimitInBytes` پیش‌فرض **1,000,000 بایت** است که در پوشه‌های بزرگ، فهرست را ناقص می‌کند. |
| **نتیجه** | **رد شد (REJECTED)** — «معادل SMB» و «بدون محدودیت اندازه» هر دو نادرست‌اند. WebDAV **پروتکل متفاوتی** است و انتظارات باید مدیریت شود. |
| **منبع رسمی** | Deprecation: *Deprecated features for Windows client* — «The Webclient (WebDAV) service is deprecated. The Webclient service isn't started by default in Windows.» · <https://learn.microsoft.com/en-us/windows/whats-new/deprecated-features> · بررسی ۲۰۲۶-۱۱-۲۳ · `FileAttributesLimitInBytes`: <https://learn.microsoft.com/en-us/troubleshoot/windows-client/networking/cannot-access-webdav-web-folder> |
| **محدودیت نسخه/محیط** | Deprecation: نسخه‌های ویندوز کلاینت از **نوامبر ۲۰۲۳** به بعد. مقادیر پیش‌فرض Registry: برای **Vista/7/Server 2008** مستند شده و در Buildهای بعدی تأیید مجدد نشده. **سقف ۴ GiB: استنتاج از عرض `REG_DWORD` — سند رسمی ندارد.** |
| **اثر بر معماری** | OD-116 (مسیر جایگزین رسمی)، OD-117 (هشدار پوشهٔ بزرگ)، OD-120 (Basic Auth) و `WINDOWS_WEBDAV_COMPATIBILITY.md` §7. **بدون آزمون روی ویندوز هدف، هیچ ادعای سازگاری نمی‌شود.** |
| **اطمینان** | Deprecation + پیش‌فرض ۵۰MB: **بالا** · سقف ۴ GiB: **متوسط (استنتاج)** · رفتار واقعی روی Build هدف: **`NOT TESTED`** |

#### جمع‌بندی سه ادعا

| ادعا | نتیجه | سطح اطمینان | اثر معماری |
|------|-------|-------------|-------------|
| MinIO CE به‌عنوان Backend | **رد** | بالا | حذف از OD-102؛ SeaweedFS یا S3 ارائه‌دهنده |
| Seafile CE به‌عنوان سیستم فایل | **رد** | بالا (مستند) / `NOT TESTED` (آزمون) | حذف از OD-101 |
| WebDAV ویندوز = درایو شبکه‌ای | **رد** | بالا (محدودیت‌ها) / متوسط (۴ GiB) | OD-116, OD-117, OD-120 + ۳۰ تست `NOT TESTED` |

> **قاعدهٔ ثبت:** هر سه ادعا **رد** شده‌اند، ولی به سه دلیل متفاوت و با سه سطح اطمینان متفاوت.
> جمع‌کردن آن‌ها در یک جملهٔ «سه ادعا رد شد» **اطلاعات لازم برای تصمیم را حذف می‌کند** — و به همین دلیل این سند آن جمله را ندارد.

---

## ۳. Laravel 12 — مستندات منطبق با نسخهٔ واقعی پروژه

> پروژه: `laravel/framework` = **12.40.0** · `php` = `^8.2` · **`composer.lock` وجود ندارد** (ریسک ثبت‌شده در `OPEN_DECISIONS` — **OD-135**).

| # | ادعا | منبع | وضعیت در پروژه |
|---|------|------|-----------------|
| L1 | دیسک `local` پیش‌فرض `storage/app/private` است. | laravel.com/docs/12.x/filesystem#the-local-driver | `VERIFIED` در `config/filesystems.php` |
| L2 | برای فعال‌سازی `temporaryUrl()` روی `local` باید `serve => true` باشد. | همان، §Enabling Local Temporary URLs | `VERIFIED` — همین‌طور تنظیم شده |
| L3 | `temporaryUrl()` روی `local` و `s3` پشتیبانی می‌شود. | همان، §Temporary URLs | `VERIFIED` — **در همین مخزن اجرا شد و لینک امضاشده تولید کرد** |
| L4 | برای دیسکی که از آن پشتیبانی نمی‌کند، `buildTemporaryUrlsUsing()` راه رسمی است. | همان، §Customizing Temporary URLs | `PROPOSED` برای آیندهٔ NAS |
| L5 | `putFile()`/`store()` نام فایل را تولید می‌کنند و پسوند را از MIME می‌گیرند؛ مسیر برگشتی باید در DB ذخیره شود. | همان، §Automatic Streaming | الگوی موجود `Attachment` همین است |
| L6 | `download()` پاسخ با `Content-Disposition: attachment` می‌سازد. | همان، §Downloading Files | `PROPOSED` برای دانلود غیرقابل‌پیش‌نمایش |
| L7 | `Storage::path()` مسیر مطلق می‌دهد (فقط local). | همان، §File Paths | استفاده نشود در کد؛ نقض قابلیت جابه‌جایی Backend |
| L8 | `s3` نیازمند `league/flysystem-aws-s3-v3` است | همان، §S3 Driver | نصب نشده؛ `MIT`، فعال (push 2026-11-07) |

### ۳.۱. یافتهٔ امنیتی که در مستندات نیست ولی در اجرا دیده شد

| یافته | شاهد |
|-------|------|
| Flysystem **Path Traversal را در سطح کتابخانه** مسدود می‌کند | `../../../etc/passwd`، `probe/../../../../etc/passwd` و `..\..\windows\win.ini` هر سه با `League\Flysystem\PathTraversalDetected` رد شدند |
| مسیر مطلق **بیرون از Root نمی‌رود** | `'/etc/passwd'` به `/…/storage/app/private/etc/passwd` نگاشت شد (بدون اثر روی `/etc/passwd` واقعی) |
| این محافظت **کافی نیست** | ادعای «Path Traversal حل شده» فقط در سطح Storage معتبر است؛ لایهٔ **مسیر منطقی/IDOR** (انتخاب `node_id` متعلق به دیگری) هیچ محافظت خودکاری ندارد |
| **نتیجه** | `FR-SEC-003` باید دو لایه داشته باشد: (۱) نام ذخیره‌سازی هرگز از ورودی کاربر ساخته نشود (الگوی فعلی `Attachment`)، (۲) هر درخواست با `node_id` باید از `FileAuthorization` بگذرد. |

### ۳.۲. نرخ هش — ورودی طراحی Chunked Upload

| سنجه | مقدار اندازه‌گیری‌شده |
|------|----------------------|
| SHA-256 جریانی (۱MiB chunk) | **۱۰۷ MB/s** |
| حافظهٔ اوج | **۲۰ MB** ثابت (مستقل از حجم فایل) |
| نتیجه | تأیید هش برای فایل ۱GB ≈ **۱۰ ثانیه** CPU — قابل قبول برای الزام «هش اجباری» |

> این عدد روی **php-wasm در سندباکس** اندازه‌گیری شده، نه روی هاست تولید. طبق قاعدهٔ §19 دستور، **به‌عنوان تضمین Production ارائه نمی‌شود** (`NOT TESTED` روی هاست).

---

## ۴. مقایسهٔ محصولات موجود

### ۴.۱. Nextcloud

| مورد | داده |
|------|-----|
| مجوز | AGPL-3.0 |
| زبان/پشته | PHP 8.2+، SabreDAV، Redis (قفل تراکنشی)، صف پس‌زمینه، DB خودش |
| قابلیت‌ها | نسخه‌بندی، سطل بازیافت، قفل فایل، اشتراک‌گذاری، WebDAV کامل، Office (با سرویس جدا) |
| محدودیت کلیدی برای ما | **مدل مجوز مستقل** (کاربر/گروه خودش) — CRM مرجع مجوز است؛ هر تغییری باید در دو جا اعمال شود |
| هزینهٔ عملیاتی | بالا: Redis + cron/worker + به‌روزرسانی‌های پرتکرار + ارتقای major سالانه |
| نتیجه | `REJECTED` به‌عنوان سیستم فایل · **الگوی طراحی پذیرفته‌شده**: content-addressed blobs، نسخه‌بندی غیرمخرب، سطل بازیافت |

### ۴.۲. ownCloud

| مورد | داده |
|------|-----|
| مجوز | AGPL-3.0 (مسیر بازمجوز‌دهی به Apache-2.0 اعلام‌شده توسط OSPO) |
| نسخه | 10.16.3 (۲۰۲۶-۰۶-۱۶) |
| قفل | از 10.5 «دستی» (manual locking) |
| نتیجه | `REJECTED` — همان مشکل موازی‌سازی مجوز با Nextcloud |

### ۴.۳. Seafile (Community)

| مورد | داده |
|------|-----|
| مجوز | AGPL-3.0 (CE) |
| مدل | Library (نه درخت پوشهٔ آزاد)، Dedup در سطح بلوک ۱MB |
| **کمبودهای CE** | قفل فایل، Audit Log، مجوز پوشه‌ای ریزدانه، جستجوی متن کامل، Backend S3، HA — **همه Pro** |
| نتیجه | `REJECTED` — الزامات P0 را ندارد؛ خرید Pro وابستگی Vendor و هزینهٔ ارزی ایجاد می‌کند و باز هم مجوز موازی می‌سازد |

### ۴.۴. ONLYOFFICE Docs CE

| مورد | داده |
|------|-----|
| مجوز | AGPL-3.0 |
| **سقف** | **۲۰ اتصال هم‌زمان** — در باینری اعمال می‌شود |
| WOPI | از نسخهٔ **6.4**؛ `wopi.enable` پیش‌فرض **false**؛ از 10.0 یک قاعدهٔ IP-Filter که میزبان WOPI را نام ببرد الزامی است |
| منابع | توصیهٔ رسمی ۴GB+ RAM |
| نتیجه | `DEFERRED` به F3 — تصمیم قطعی کارفرما (§2.3) |

### ۴.۵. Collabora CODE

| مورد | داده |
|------|-----|
| مجوز | MPLv2 |
| سقف | بدون سقف اتصال، ولی **rolling release بدون SLA** (خودشان برای Production توصیه نمی‌کنند) |
| ظرفیت | ≈۱۰ کاربر به ازای هر CPU Thread؛ ۵۰MB RAM/کاربر + ~1GB پایه؛ یک پردازه به ازای هر سند باز |
| نتیجه | `DEFERRED` به F3 — گزینهٔ دوم در `ONLINE_OFFICE_INTEGRATION.md` |

### ۴.۶. MinIO

در §2.5. **`REJECTED`** به‌دلیل بایگانی‌شدن.

### ۴.۷. جدول جمع‌بندی — کدام قابلیت کجاست؟

| قابلیت | Nextcloud | ownCloud | Seafile CE | sabre/dav | ساخت اختصاصی |
|--------|:---------:|:--------:|:----------:|:---------:|:-------------:|
| WebDAV کامل (RFC 4918) | ✅ | ✅ | ✅ | ✅ | باید ساخته شود |
| قفل ماندگار | ✅ (Redis) | ⚠️ دستی | ❌ | ✅ (PDO) | ✅ (DB) |
| نسخه‌بندی | ✅ | ✅ | ✅ | ❌ | ✅ |
| Audit | ✅ | ✅ | ❌ | ❌ | ✅ |
| مجوز ریزدانه با CRM | ❌ | ❌ | ❌ | ✅ (پلاگین سفارشی) | ✅ |
| بدون Node در Production | ✅ | ✅ | ✅ (Python) | ✅ | ✅ |
| بدون DB دوم | ❌ | ❌ | ❌ | ✅ | ✅ |

---

## ۵. محصولات ایرانی — فقط واقعیت قابل‌بررسی

> **هیچ قیمت، SLA یا قابلیت فنی حدس زده نشده است.** هر جا مستندات عمومی نبوده: «نیازمند استعلام».

| محصول | سازنده | آنچه واقعاً منتشر شده | وضعیت |
|-------|--------|------------------------|-------|
| **برید درایو** | برید (پارگار) | فضای ذخیره‌سازی اشتراکی درون‌سازمانی (on-prem) در خانوادهٔ پرگار | `نیازمند استعلام` — API/WebDAV/قیمت منتشر نشده |
| **داکتیو (Docteav)** | داکتیو | مدیریت محتوای سازمانی/اسناد، خدمات‌محور، ادعای یکپارچگی با CRM | `نیازمند استعلام` — مجوز/قیمت/API نامشخص |
| **فراگستر DMS** | فراگستر | سیستم مدیریت اسناد | `نیازمند استعلام` |
| **آبان** | هیراد | حفاظت/تکثیر/رمزنگاری فایل | `نیازمند استعلام` |
| **درسان کلود** | درسان | فضای ابری | `نیازمند استعلام` |
| **رایورز سگال‌سان** | رایورز | ذخیره‌سازی/NAS سازمانی | `نیازمند استعلام` |

**نتیجهٔ روشن:** هیچ‌کدام از این محصولات، مستندات عمومی کافی برای **ارزیابی فنی** منتشر نکرده‌اند. انتخاب هرکدام بدون استعلام رسمی، **حدس** است و طبق قاعدهٔ کارفرما ممنوع.

**سؤال‌های استعلام (آماده برای ارسال):**

1. آیا WebDAV استاندارد (RFC 4918) با قفل ارائه می‌دهید؟ نسخه و Endpoint؟
2. آیا API برای اتصال به مجوزهای یک سیستم بیرونی (CRM) دارید؟ مستندات آن؟
3. مدل صدور مجوز: کاربر/گروه/پروژه؟ قفل و نسخه‌بندی؟
4. استقرار: on-prem یا ابری؟ cPanel پشتیبانی می‌شود؟
5. هزینهٔ اولیه، سالانه، به‌ازای کاربر و به‌ازای حجم ذخیره‌سازی؟
6. SLA، پشتیبانی فارسی، RTO/RPO و شرایط بازیابی؟
7. امکان تست آزمایشی (POC) با دادهٔ خودمان؟

---

## ۶. جمع‌بندی و انتقال به تصمیم

| پرسش | پاسخ مستند |
|------|-------------|
| آیا محصول آمادهٔ «فقط نصب کن» وجود دارد که مجوز CRM را مرجع بداند؟ | **خیر.** هیچ محصول بررسی‌شده مدل مجوز بیرونی را مرجع نمی‌داند. |
| آیا می‌توان WebDAV را با کتابخانهٔ مجوز-آزاد و فعال ساخت؟ | **بله** — sabre/dav 4.7.1، BSD-3. |
| آیا می‌توان فایل را بدون سرویس خارجی روی cPanel ذخیره کرد؟ | **بله** برای پایلوت؛ برای F1 طبق تصمیم کارفرما داده روی VPS می‌رود. |
| آیا «درایو شبکه‌ای ویندوز» بدون اصطکاک ممکن است؟ | **خیر** — WebClient در حال Deprecation است، سقف ۵۰MB پیش‌فرض و سقف ۴GB مطلق. |

**تصمیم مستند در:** `docs/FILE_STORAGE_ARCHITECTURE.md` §10 و ADR با شناسهٔ **ADR-FILE-001**.

---

## ۷. فهرست منابع

| # | منبع | URL | تاریخ بررسی |
|---|------|-----|-------------|
| S1 | Laravel 12 — File Storage | https://laravel.com/docs/12.x/filesystem | 2026-11-23 |
| S2 | Laravel 12 — Filesystem source (`FilesystemAdapter::response`) | `vendor/laravel--framework/.../FilesystemAdapter.php` | 2026-11-23 |
| S3 | Laravel 12 — `ServeFile` | `vendor/laravel--framework/src/Illuminate/Filesystem/ServeFile.php` | 2026-11-23 |
| S4 | sabre/dav — Repository metadata | https://api.github.com/repos/sabre-io/dav | 2026-11-23 |
| S5 | sabre/dav — Standards Support | https://sabre.io/dav/standards-support/ | 2026-11-23 |
| S6 | sabre/dav — Locks | https://sabre.io/dav/locks/ | 2026-11-23 |
| S7 | RFC 4918 — HTTP Extensions for WebDAV | https://www.rfc-editor.org/rfc/rfc4918 | 2026-11-23 |
| S8 | Microsoft — Deprecated features for Windows client (WebClient) | https://learn.microsoft.com/en-us/windows/whats-new/deprecated-features | 2026-11-23 |
| S9 | Microsoft (IIS blog) — WebDAV Redirector Registry Settings | https://blogs.iis.net/robert_mcmurray/webdav-redirector-registry-settings/ | 2026-11-23 |
| S10 | ONLYOFFICE — WOPI overview | https://api.onlyoffice.com/docs/docs-api/using-wopi/overview | 2026-11-23 |
| S11 | Collabora — FAQ (sizing/licence) | https://collaboraonline.com/faqs/ | 2026-11-23 |
| S12 | MinIO Community repository status | https://github.com/minio/minio | 2026-11-23 |
| S13 | Nextcloud server | https://github.com/nextcloud/server | 2026-11-23 |
| S14 | Seafile CE vs Pro feature matrix | https://www.seafile.com/en/product/private_server/ | 2026-11-23 |
| S15 | ownCloud core | https://github.com/owncloud/core | 2026-11-23 |
| S16 | Flysystem (path-traversal guard) | https://flysystem.thephpleague.com/docs/ | 2026-11-23 |

---

## ۸. آنچه این سند اثبات نکرده است

- رفتار واقعی WebDAV روی **ویندوز هدف سازمان** (`NOT TESTED`).
- عملکرد سرویس VPS با بار ۲۵/۵۰ کاربر (`NOT TESTED` — `FILE_STORAGE_CAPACITY_PLAN.md` §6).
- رفتار هاست واقعی cPanel با آپلود بزرگ (`BLOCKED` — `HOST_COMPATIBILITY.md`).
- صحت ادعاهای محصولات ایرانی (`NEEDS INQUIRY`).
