# FILE_WORKSPACE_REQUIREMENTS — نیازمندی‌های Vira Drive (F1)

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed (نیازمند تأیید کارفرما)

> شناسه‌ها **یکتا در کل پروژه** هستند و با هیچ بازهٔ قبلی (`REQ-xx.yy`, `OD-01…47`) برخورد ندارند.
> وضعیت‌ها طبق §23 دستور **تفکیک‌شده‌اند** و هیچ نیازی با عبارت «طراحی شد» بسته نمی‌شود.

---

## ۰. واژه‌نامهٔ وضعیت

| وضعیت | معنا | چه کسی اعلام می‌کند |
|-------|------|----------------------|
| `Proposed` | طراحی شده، تأیید نشده | تیم مهندسی |
| `Approved` | کارفرما تأیید کرده | کارفرما |
| `Implemented` | کد روی شاخه است | تیم مهندسی |
| `Unit Tested` | تست واحد دارد | CI |
| `Integration Tested` | از مسیر HTTP واقعی با ≥۲ کاربر تست شده | CI |
| `Environment Tested` | روی cPanel/VPS/Windows واقعی تست شده | تیم استقرار |
| `Accepted` | کارفرما پذیرفته | کارفرما |

**در F0 همهٔ نیازمندی‌ها حداکثر `Proposed` هستند.** هیچ‌کدام `Implemented` نیست، چون هیچ کد ماژول فایل نوشته نشده است.

---

## ۱. دامنهٔ F1 — آنچه *باید* تحویل شود

### ۱.۱. فضای کاری و ساختار

| ID | نیازمندی | اولویت | فاز | مؤلفه | موجودیت | مسیر/API | صفحهٔ UI | کنترل امنیتی | تست | وضعیت |
|----|----------|:------:|:---:|-------|---------|----------|----------|--------------|-----|-------|
| **FR-FILE-001** | فضای کاری سازمانی به‌عنوان دامنهٔ اصلی محصول (نه ویژگی جانبی). | P0 | F1 | Files module | `file_nodes` | `/files` | S01 | RBAC + Policy | T-FILE-001 | `Proposed` |
| **FR-FILE-002** | مالکیت فایل متعلق به **سازمان** است، نه حساب شخصی کارمند. `owner_org_id` الزامی؛ `created_by` جدا از `owner_user_id` نگه داشته می‌شود. | P0 | F1 | Domain | `file_nodes` | — | S13 | جداسازی مالک/سازنده | T-FILE-002 | `Proposed` |
| **FR-FILE-003** | ناحیه‌های ثابت: My Files، Shared With Me، Shared Workspace، Department، Project، Customer، Contract، HR، Finance، Archive، Trash. **ساختار از داده می‌آید، نه از Enum هاردکدشده.** | P0 | F1 | FolderTree | `file_nodes.workspace_kind` + `file_workspaces` | `/files/{ws}` | S01,S02 | تفکیک دامنه | T-FILE-003 | `Proposed` |
| **FR-FILE-004** | پوشه‌های ناحیه‌های سازمانی فقط توسط کاربر دارای `files.folder_manage` ساخته/تغییر می‌شوند؛ ناحیه‌های سیستمی (Trash/Archive) غیرقابل حذف. | P0 | F1 | FolderTree | `file_nodes.is_system` | — | S10 | Permission | T-FILE-004 | `Proposed` |
| **FR-FILE-005** | Trash: حذف = انتقال به سطل بازیافت با `deleted_at` و `purge_after`؛ Restore با بازگرداندن به مسیر اصلی (یا پوشهٔ جانشین اگر مسیر آزاد نیست). | P0 | F1 | Trash | `file_nodes` (softDeletes) | `/files/trash` | S11 | مجوز Restore | T-FILE-005 | `Proposed` |
| **FR-FILE-006** | Hard Delete فقط با `Manage Retention` + دلیل اجباری + Audit. پیش‌فرض: غیرفعال. | P0 | F1 | Retention | `file_activity` | — | S11 | دو‌مرحله‌ای | T-FILE-006 | `Proposed` |

### ۱.۲. اتصال به CRM

| ID | نیازمندی | اولویت | فاز | مؤلفه | موجودیت | مسیر/API | صفحه | کنترل | تست | وضعیت |
|----|----------|:------:|:---:|-------|---------|----------|-------|-------|-----|-------|
| **FR-FILE-007** | هر فایل می‌تواند به **یک یا چند** موجودیت CRM متصل شود: `customer`, `contract`, `project`, `employee`, `accounting_record`. | P0 | F1 | Relations | `file_relations` | `/files/{id}/relations` | S12 | Policy موجودیت مقصد | T-FILE-007 | `Proposed` |
| **FR-FILE-008** | **یک منبع حقیقت**: اتصال، فایل را کپی نمی‌کند؛ `file_relations` فقط ارجاع است. حذف موجودیت CRM → رابطه `orphaned` می‌شود، فایل می‌ماند. | P0 | F1 | Relations | `file_relations` | — | S12 | FK + soft link | T-FILE-008 | `Proposed` |
| **FR-FILE-009** | **دسترسی به فایل هرگز مجوز ماژول CRM را دور نمی‌زند**: اگر کاربر `ProjectPolicy@view` را ندارد، فایل متصل به آن پروژه هم برایش نامرئی است — حتی با URL مستقیم. | P0 | F1 | FileAuthorization | `file_relations` + Policies | همهٔ مسیرها | S01 | Gate ترکیبی | T-FILE-009 | `Proposed` |
| **FR-FILE-010** | نمایش فایل‌های یک موجودیت CRM در صفحهٔ همان موجودیت (تب «فایل‌ها»)، مشروط به همان Policy. | P1 | F1 | Relation view | `file_relations` | `/projects/{p}/files` | S14 | همان Policy | T-FILE-010 | `Proposed` |

### ۱.۳. آپلود و دانلود

| ID | نیازمندی | اولویت | فاز | مؤلفه | تست | وضعیت |
|----|----------|:------:|:---:|-------|-----|-------|
| **FR-FILE-011** | آپلود **تک‌مرحله‌ای** تا سقف پیکربندی‌شده (پیش‌فرض ۳۲MB) با نوار پیشرفت و تلاش مجدد. | P0 | F1 | UploadController | T-FILE-011 | `Proposed` |
| **FR-FILE-012** | آپلود **تکه‌ای و قابل ادامه** برای فایل بزرگ‌تر از آستانه (پیش‌فرض ۳۲MB، تکه ۸MB) با `file_upload_sessions`. | P0 | F1 | UploadController | T-FILE-012 | `Proposed` |
| **FR-FILE-013** | **هش SHA-256 اجباری** روی سرور محاسبه و با هش اعلامی کلاینت مقایسه می‌شود؛ عدم تطابق = رد. | P0 | F1 | ValidationService | T-FILE-013 | `Proposed` |
| **FR-FILE-014** | آپلود ناقص **هرگز** به‌عنوان نسخهٔ معتبر ثبت نمی‌شود؛ نشست ناتمام پس از TTL پاک می‌شود. | P0 | F1 | UploadService | T-FILE-014 | `Proposed` |
| **FR-FILE-015** | اعتبارسنجی سه‌لایه: **پسوند** (allowed list) + **MIME اعلامی** + **امضای واقعی فایل**. | P0 | F1 | ValidationService | T-FILE-015 | `Proposed` |
| **FR-FILE-016** | نام ذخیره‌سازی هرگز از نام کاربر ساخته نمی‌شود؛ نام نمایشی جدا ذخیره می‌شود (الگوی فعلی `Attachment`). | P0 | F1 | BlobStore | T-FILE-016 | `Proposed` |
| **FR-FILE-017** | نام نمایشی فارسی/Unicode مجاز است ولی کاراکترهای کنترلی، `\0`، `/`، `\` و نام‌های رزرو ویندوز (`CON`, `PRN`, `AUX`, `NUL`, `COM1..9`, `LPT1..9`) نرمال‌سازی می‌شوند. | P0 | F1 | ValidationService | T-FILE-017 | `Proposed` |
| **FR-FILE-018** | دانلود از مسیر اختصاصی با **Range**، `Content-Length`، `Content-Disposition` قابل انتخاب، `X-Content-Type-Options: nosniff`. | P0 | F1 | DownloadController | T-FILE-018 | `Proposed` |
| **FR-FILE-019** | فایل بدون نوع پیش‌نمایش‌پذیر همیشه `attachment` است، هرگز `inline`. | P0 | F1 | DownloadController | T-FILE-019 | `Proposed` |
| **FR-FILE-020** | سقف حجم **به‌ازای هر فایل**، سهمیهٔ **هر کاربر**، سهمیهٔ **هر ناحیه/پروژه**؛ عبور از سهمیه = رد پیش از ذخیره. | P0 | F1 | QuotaService | T-FILE-020 | `Proposed` |

### ۱.۴. جستجو و پیش‌نمایش

| ID | نیازمندی | اولویت | فاز | تست | وضعیت |
|----|----------|:------:|:---:|-----|-------|
| **FR-FILE-021** | جستجو بر اساس: نام، پسوند، نوع، مالک، سازنده، آخرین ویرایشگر، تاریخ، پوشه، پروژه، مشتری، دپارتمان، طبقه‌بندی، نسخه، برچسب، یادداشت. | P0 | F1 | T-FILE-021 | `Proposed` |
| **FR-FILE-022** | **جستجو Permission-aware است**: نتایج پیش از نمایش فیلتر می‌شوند؛ فایل Restricted غیرمجاز **حتی به‌صورت نام یا شمارش** ظاهر نمی‌شود. | P0 | F1 | T-FILE-022 | `Proposed` |
| **FR-FILE-023** | جستجوی **متن کامل محتوا** جزو F1 نیست؛ نیازمند موتور جدا، هزینه و ریسک مستقل — به F3 منتقل شد. | P2 | F3 | — | `Proposed` |
| **FR-FILE-024** | پیش‌نمایش امن: PDF، تصویر (به‌جز SVG)، متن ساده. | P0 | F1 | T-FILE-024 | `Proposed` |
| **FR-FILE-025** | **SVG و HTML هرگز inline سرو نمی‌شوند**؛ همیشه `attachment` یا از دامنهٔ ایزوله. | P0 | F1 | T-FILE-025 | `Proposed` |
| **FR-FILE-026** | پیش‌نمایش در `<iframe sandbox>` با CSP سخت‌گیرانه روی مسیر پیش‌نمایش، مستقل از CSP کلی برنامه. | P0 | F1 | T-FILE-026 | `Proposed` |

### ۱.۵. حجم، طبقه‌بندی، نگهداری

| ID | نیازمندی | اولویت | فاز | تست | وضعیت |
|----|----------|:------:|:---:|-----|-------|
| **FR-FILE-027** | چهار سطح طبقه‌بندی: `public_internal` / `internal` / `confidential` / `restricted`. | P0 | F1 | T-FILE-027 | `Proposed` |
| **FR-FILE-028** | `restricted` هرگز از طریق اشتراک گروهی یا ناحیهٔ عمومی قابل دسترسی نیست؛ فقط Grant صریح به کاربر. | P0 | F1 | T-FILE-028 | `Proposed` |
| **FR-FILE-029** | سیاست نگهداری قابل تنظیم بر اساس ناحیه/طبقه‌بندی: حداکثر تعداد نسخه، حداکثر عمر، `keep_forever`، `legal_hold`. | P1 | F1 | T-FILE-029 | `Proposed` |
| **FR-FILE-030** | هیچ نسخه‌ای «بی‌ردپا» حذف نمی‌شود: پاک‌سازی نسخه همیشه Audit + گزارش پیش‌اجرا (dry-run). | P0 | F1 | T-FILE-030 | `Proposed` |

---

## ۲. نیازمندی‌های مجوز (FR-PERM-XXX)

| ID | نیازمندی | اولویت | فاز | تست | وضعیت |
|----|----------|:------:|:---:|-----|-------|
| **FR-PERM-001** | مجموعهٔ ۱۸ مجوز پایه: View, Download, Upload, Create Folder, Edit, Rename, Move, Copy, Delete, Restore, Share, Manage Permissions, Lock, Unlock, View Versions, Restore Version, View Audit, Manage Retention. | P0 | F1 | T-PERM-001 | `Proposed` |
| **FR-PERM-002** | مجوزها **قابل توسعه‌اند**: نگهداری در جدول `file_permission_defs`، نه Enum در کد. | P0 | F1 | T-PERM-002 | `Proposed` |
| **FR-PERM-003** | دامنهٔ Grant: user, role, group, team, department, project, customer, organization. | P0 | F1 | T-PERM-003 | `Proposed` |
| **FR-PERM-004** | وراثت از پوشهٔ والد به فرزند، با پرچم `inherit` روی هر پوشه. | P0 | F1 | T-PERM-004 | `Proposed` |
| **FR-PERM-005** | Break-inheritance: پرچم `inherit=0` روی پوشه، زنجیرهٔ والد **بالاتر از همان نقطه** را قطع می‌کند؛ مجوزهای همان پوشه معتبر می‌مانند. | P0 | F1 | T-PERM-005 | `Proposed` |
| **FR-PERM-006** | **Deny مقدم بر Allow** است. یک Deny صریح در هر سطح، Allow همان قابلیت را باطل می‌کند. | P0 | F1 | T-PERM-006 | `Proposed` |
| **FR-PERM-007** | نبود هر قاعده = **Deny** (default-deny). | P0 | F1 | T-PERM-007 | `Proposed` |
| **FR-PERM-008** | Deny روی یک **جد**، دسترسی فرزندانش را نمی‌بندد مگر خودش هم Deny داشته باشد — **مگر** برای `restricted` که مسیر جدا دارد. | P0 | F1 | T-PERM-008 | `Proposed` |
| **FR-PERM-009** | Grant گروهی هرگز مجوزی بزرگ‌تر از مجوز خود گروه به کاربر نمی‌دهد (بدون تشدید امتیاز). | P0 | F1 | T-PERM-009 | `Proposed` |
| **FR-PERM-010** | کاربر نمی‌تواند مجوزی به دیگری بدهد که خودش ندارد (`Share` مشروط به داشتن قابلیت). | P0 | F1 | T-PERM-010 | `Proposed` |
| **FR-PERM-011** | **هر درخواست** (UI، API، دانلود، WebDAV، جستجو) از همان Resolver رد می‌شود؛ هیچ مسیر جانبی مجاز نیست. | P0 | F1 | T-PERM-011 | `Proposed` |
| **FR-PERM-012** | با Revocation، نسخهٔ کش مجوز باطل می‌شود؛ لینک‌های امضاشدهٔ قبلی حداکثر تا TTL خود (≤۵ دقیقه) معتبرند و **قابل ابطال فوری** از طریق یک `revoked_at` روی توکن. | P0 | F1 | T-PERM-012 | `Proposed` |
| **FR-PERM-013** | صفحهٔ «چه کسی به این فایل دسترسی دارد؟» با محاسبهٔ مؤثر (effective) و منبع هر مجوز. | P1 | F1 | T-PERM-013 | `Proposed` |
| **FR-PERM-014** | اشتراک عمومی/بیرونی **پیش‌فرض غیرفعال**؛ فعال‌سازی فقط با سوئیچ ادمین. | P0 | F1 | T-PERM-014 | `Proposed` |
| **FR-PERM-015** | در صورت فعال‌سازی: انقضا، رمز، محدودیت دانلود، ابطال، Audit، `noindex`، Rate Limit. | P1 | F3 | T-PERM-015 | `Proposed` |
| **FR-PERM-016** | کاربر غیرفعال (`is_active=0`) بلافاصله از همهٔ مسیرها از جمله WebDAV حذف می‌شود. | P0 | F1 | T-PERM-016 | `Proposed` |

---

## ۳. نیازمندی‌های نسخه‌بندی (FR-VERSION-XXX)

| ID | نیازمندی | اولویت | فاز | تست | وضعیت |
|----|----------|:------:|:---:|-----|-------|
| **FR-VERSION-001** | هر فایل تاریخچهٔ نسخهٔ **تغییرناپذیر** دارد: `version_number`, `created_by`, `created_at`, `size`, `sha256`, `change_note`, `storage_ref`. | P0 | F1 | T-VER-001 | `Proposed` |
| **FR-VERSION-002** | آپلود نسخهٔ جدید شماره را +1 می‌کند؛ شماره هرگز بازاستفاده نمی‌شود. | P0 | F1 | T-VER-002 | `Proposed` |
| **FR-VERSION-003** | دانلود نسخهٔ قدیمی مجاز و **Audit‌شده** است (نیازمند `View Versions`). | P0 | F1 | T-VER-003 | `Proposed` |
| **FR-VERSION-004** | Restore = ایجاد **نسخهٔ جدید** با ارجاع به نسخهٔ مبنا؛ هیچ ردیفی بازنویسی نمی‌شود. | P0 | F1 | T-VER-004 | `Proposed` |
| **FR-VERSION-005** | Dedup: اگر `sha256` برابر نسخهٔ موجود باشد، به‌جای ذخیرهٔ دوباره، `file_blobs` موجود ارجاع داده می‌شود (بدون از دست دادن نسخه). | P1 | F1 | T-VER-005 | `Proposed` |
| **FR-VERSION-006** | سیاست نگهداری نسخه قابل تنظیم؛ `legal_hold` پاک‌سازی را **مطلقاً** مسدود می‌کند. | P0 | F1 | T-VER-006 | `Proposed` |
| **FR-VERSION-007** | هش هر نسخه در DB نگه داشته می‌شود و صحت آن در بازیابی بررسی می‌شود. | P0 | F1 | T-VER-007 | `Proposed` |
| **FR-VERSION-008** | مقایسهٔ متادیتای دو نسخه (حجم/هش/سازنده/تاریخ) در UI. | P2 | F1 | T-VER-008 | `Proposed` |

---

## ۴. نیازمندی‌های WebDAV (FR-WEBDAV-XXX)

> تصمیم قطعی کارفرما: **سرویس مستقل روی VPS**؛ داخل cPanel اجرا نمی‌شود.

| ID | نیازمندی | اولویت | فاز | تست | وضعیت |
|----|----------|:------:|:---:|-----|-------|
| **FR-WEBDAV-001** | Endpoint مستقل با TLS معتبر روی دامنهٔ جدا (مثلاً `dav.example.com`). | P0 | F1 | T-DAV-001 | `Proposed` |
| **FR-WEBDAV-002** | پشتیبانی Method: OPTIONS, PROPFIND, PROPPATCH, GET, HEAD, PUT, DELETE, MKCOL, MOVE, COPY, LOCK, UNLOCK. | P0 | F1 | T-DAV-002 | `Proposed` |
| **FR-WEBDAV-003** | **CRM تنها مرجع مجوز** است؛ سرویس هیچ جدول مجوز مستقلی ندارد. | P0 | F1 | T-DAV-003 | `Proposed` |
| **FR-WEBDAV-004** | هر Method حساس پیش از اجرا مجوز متناظر CRM را می‌پرسد (نگاشت در `WEBDAV_CRM_AUTHORIZATION_BRIDGE.md` §4). | P0 | F1 | T-DAV-004 | `Proposed` |
| **FR-WEBDAV-005** | **Fail-Closed**: قطع CRM / پاسخ نامعتبر / HMAC بد / Token منقضی → **رد**، هرگز مجوز قدیمی. | P0 | F1 | T-DAV-005 | `Proposed` |
| **FR-WEBDAV-006** | احراز هویت: Basic روی TLS **یا** App-Token؛ رمز هرگز بدون TLS. | P0 | F1 | T-DAV-006 | `Proposed` |
| **FR-WEBDAV-007** | App-Token: کوتاه‌عمر، Scope‌دار، قابل Revocation، متصل به کاربر و دستگاه، مقاوم در برابر Replay. | P0 | F1 | T-DAV-007 | `Proposed` |
| **FR-WEBDAV-008** | قفل واقعی ماندگار (RFC 4918) با `lock_token`، `timeout`، `refresh`، `owner`. | P0 | F1 | T-DAV-008 | `Proposed` |
| **FR-WEBDAV-009** | هر عملیات نوشتن یک **نسخهٔ جدید** در CRM تولید می‌کند، نه بازنویسی فایل. | P0 | F1 | T-DAV-009 | `Proposed` |
| **FR-WEBDAV-010** | همهٔ عملیات در `file_activity` با Actor=کاربر CRM، Client=`webdav` ثبت می‌شود. | P0 | F1 | T-DAV-010 | `Proposed` |
| **FR-WEBDAV-011** | حداکثر حجم آپلود سرویس قابل تنظیم و مستند. | P0 | F1 | T-DAV-011 | `Proposed` |
| **FR-WEBDAV-012** | **هیچ پورت دیتابیس یا SMB به اینترنت باز نیست.** | P0 | F1 | T-DAV-012 | `Proposed` |
| **FR-WEBDAV-013** | Health check و Alerting برای سرویس. | P0 | F1 | T-DAV-013 | `Proposed` |
| **FR-WEBDAV-014** | سازگاری با Windows Explorer **فقط پس از تست واقعی** اعلام می‌شود. | P0 | F1 | T-DAV-014 | `Proposed` |
| **FR-WEBDAV-015** | محدودیت‌های ویندوز (۵۰MB پیش‌فرض، ۴GB مطلق، WebClient Deprecated) در راهنمای کاربر مستند. | P0 | F1 | T-DAV-015 | `Proposed` |

---

## ۵. نیازمندی‌های امنیتی (FR-SEC-XXX)

| ID | نیازمندی | اولویت | فاز | تست | وضعیت |
|----|----------|:------:|:---:|-----|-------|
| **FR-SEC-001** | فایل در **بیرون از Public Root** ذخیره می‌شود؛ هیچ URL عمومی مستقیم وجود ندارد. | P0 | F1 | T-SEC-F01 | `Proposed` |
| **FR-SEC-002** | **مجوزسنجی سمت سرور پیش از هر** دانلود/پیش‌نمایش، بدون استثنا. | P0 | F1 | T-SEC-F02 | `Proposed` |
| **FR-SEC-003** | مسیر ذخیره‌سازی هرگز از ورودی کاربر ساخته نمی‌شود + اعتبارسنجی `node_id` تعلق. | P0 | F1 | T-SEC-F03 | `Proposed` |
| **FR-SEC-004** | لینک موقت: امضاشده، TTL ≤ ۵ دقیقه، متصل به کاربر، قابل ابطال. | P0 | F1 | T-SEC-F04 | `Proposed` |
| **FR-SEC-005** | اعتبارسنجی امضای فایل (magic bytes)، نه فقط پسوند/MIME اعلامی. | P0 | F1 | T-SEC-F05 | `Proposed` |
| **FR-SEC-006** | پیش‌نمایش HTML/SVG: بدون اجرای اکتیو کانتنت. | P0 | F1 | T-SEC-F06 | `Proposed` |
| **FR-SEC-007** | کنترل آرشیو: سقف نسبت فشرده‌سازی، حداکثر تعداد فایل، سقف حجم بازشده. | P0 | F1 | T-SEC-F07 | `Proposed` |
| **FR-SEC-008** | Rate Limit روی آپلود، دانلود و ورود WebDAV. | P0 | F1 | T-SEC-F08 | `Proposed` |
| **FR-SEC-009** | Audit کامل **بدون** محتوای فایل، رمز یا توکن خام. | P0 | F1 | T-SEC-F09 | `Proposed` |
| **FR-SEC-010** | هیچ Secret در Git/Log/فایل عمومی؛ چرخش دوره‌ای کلید. | P0 | F1 | T-SEC-F10 | `Proposed` |
| **FR-SEC-011** | اسکن بدافزار «در صورت امکان» — ارزیابی در Threat Model، تصمیم در OD-107. | P1 | F1 | T-SEC-F11 | `Proposed` |
| **FR-SEC-012** | جستجو و لیست‌ها هیچ نشانی از فایل غیرمجاز لو نمی‌دهند (شامل شمارش و زمان پاسخ). | P0 | F1 | T-SEC-F12 | `Proposed` |

---

## ۶. نیازمندی‌های غیرعملکردی

### ۶.۱. NFR-FILE (CRM / cPanel)

| ID | نیازمندی | هدف | وضعیت |
|----|----------|-----|-------|
| **NFR-FILE-001** | بدون Node.js runtime در Production. | صفر پردازهٔ Node | `Proposed` |
| **NFR-FILE-002** | بدون `composer.lock` فعلی — افزودن وابستگی جدید باید توجیه شود. | هر وابستگی مستند | `Proposed` |
| **NFR-FILE-003** | صفحهٔ فهرست پوشه ≤ ۱ ثانیه در P95 برای ۵۰۰۰ آیتم (با Pagination). | ۱۰۰۰ms | `Proposed` |
| **NFR-FILE-004** | آپلود ۳۲MB در ≤ ۱۰ ثانیه روی اتصال ۱۰۰Mbps. | ۱۰s | `Proposed` |
| **NFR-FILE-005** | مصرف حافظهٔ آپلود **مستقل از حجم فایل** (Streaming). | ≤۳۲MB | `Proposed` |
| **NFR-FILE-006** | هر Migration **بازگشت‌پذیر** و **غیرمخرب**. | ۱۰۰٪ | `Proposed` |
| **NFR-FILE-007** | هیچ پرس‌وجو در فهرست پوشه باعث N+1 نشود. | ۰ مورد | `Proposed` |
| **NFR-FILE-008** | پشتیبانی RTL/فارسی و ۷ عرض صفحه (تا ۳۹۰px). | ۷ عرض | `Proposed` |
| **NFR-FILE-009** | بدون تغییر دیتابیس مشترک در تست‌ها. | ایزوله | `Proposed` |
| **NFR-FILE-010** | سهمیهٔ ذخیره‌سازی قابل مشاهده برای ادمین و کاربر. | گزارش | `Proposed` |

### ۶.۲. NFR-WEBDAV (VPS)

| ID | نیازمندی | هدف | وضعیت |
|----|----------|-----|-------|
| **NFR-WEBDAV-001** | پشتیبانی حداقل **۲۵ کاربر هم‌زمان** فهرست‌گیری. | ۲۵ | `NOT TESTED` |
| **NFR-WEBDAV-002** | PROPFIND با ۵۰۰ آیتم ≤ ۲ ثانیه. | ۲۰۰۰ms | `NOT TESTED` |
| **NFR-WEBDAV-003** | آپلود ۱۰۰MB از ویندوز (با Registry افزایش‌یافته) ≤ ۳ دقیقه. | ۱۸۰s | `NOT TESTED` |
| **NFR-WEBDAV-004** | کاهش Uptime سالانه ≤ ۰.۵٪ (≈۴۳ ساعت). | 99.5% | `Proposed` |
| **NFR-WEBDAV-005** | Recovery پس از قطع CRM ≤ ۳۰ ثانیه پس از بازگشت. | ۳۰s | `Proposed` |
| **NFR-WEBDAV-006** | هر عملیات ≤ ۳ درخواست شبکه‌ای به CRM (authorize + commit + audit). | ۳ | `Proposed` |
| **NFR-WEBDAV-007** | Cache مجوز ≤ ۳۰ ثانیه با ابطال فوری. | ۳۰s | `Proposed` |
| **NFR-WEBDAV-008** | قفل کهنه پس از TTL آزاد شود، بدون دخالت ادمین. | ۱۰ دقیقه | `Proposed` |

---

## ۷. خارج از دامنهٔ F1 (صریح)

| مورد | چرا |
|------|-----|
| Online Office / Document Server | تصمیم قطعی کارفرما §2.3 — F3 |
| اشتراک عمومی/بیرونی | پیش‌فرض غیرفعال؛ فعال‌سازی F3 |
| SMB عمومی | ممنوع (§7.7 دستور) |
| جستجوی متن کامل | F3، هزینهٔ مستقل |
| امضای دیجیتال / گردش تأیید | F2 (Collaboration) |
| پورتال مشتری | F3 |
| نسخهٔ موبایل Native | خارج از دامنه؛ وب واکنش‌گرا کافی است |

---

## ۸. ماتریس پذیرش فازها

| فاز | آنچه باید تحویل شود | معیار پایان |
|------|---------------------|--------------|
| **F0** | این سند + ۱۸ سند همراه + ADR + نمودارها | تأیید کارفرما روی معماری (OD-101…OD-120) |
| **F1** | Core Workspace + WebDAV + Windows | همهٔ تست‌های `FILE_WORKSPACE_TEST_PLAN.md` با وضعیت `Environment Tested` |
| **F2** | Collaboration | گردش تأیید **بدون** تحمیل منطق کسب‌وکار جدید |
| **F3** | Office + Advanced | تصمیم جدا + بودجهٔ جدا |

---

## ۹. ردیابی

این سند منبع نیازمندی است؛ ردیابی کامل (Requirement → Component → Entity → Route → Screen → Control → Test → Evidence) در `FILE_WORKSPACE_TRACEABILITY.md` تولید شده است.

**شمارش:** ۳۰ FR-FILE · ۱۶ FR-PERM · ۸ FR-VERSION · ۱۵ FR-WEBDAV · ۱۲ FR-SEC · ۱۸ NFR = **۹۹ نیازمندی**.
