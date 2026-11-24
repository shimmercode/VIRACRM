# FILE_WORKSPACE_TRACEABILITY — ماتریس ردیابی ماژول فایل

> **این سند به‌صورت خودکار تولید می‌شود.** دستی ویرایش نکنید.
> منبع: `docs/FILE_WORKSPACE_REQUIREMENTS.md` · تولید: `node docs/tools/generate-file-traceability.mjs`
> بررسی هم‌گامی: `node docs/tools/generate-file-traceability.mjs --check`

**تاریخ تولید:** 2026-11-23 · **نسخه:** 1.0 · **فاز:** F0

---

## ۱. قواعد خواندن این سند

| ستون | معنا |
|-------|------|
| **Req** | شناسهٔ یکتا از سند نیازمندی‌ها |
| **Component** | مؤلفهٔ معماری که مسئول است (نقشه: `FILE_STORAGE_ARCHITECTURE.md` §5) |
| **Entity** | جدول(های) داده (نقشه: `FILE_WORKSPACE_ERD.md`) |
| **Route/API** | مسیر HTTP (قرارداد: `FILE_STORAGE_ARCHITECTURE.md` §9، `WEBDAV_CRM_AUTHORIZATION_BRIDGE.md`) |
| **Screen** | صفحهٔ UI (نقشه: `FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md` §4) |
| **Control** | کنترل امنیتی K1…K20 (`FILE_SECURITY_THREAT_MODEL.md` §4) |
| **Test** | تست (نقشه: `FILE_WORKSPACE_TEST_PLAN.md`) |
| **Status** | طبق واژه‌نامهٔ §0 سند نیازمندی‌ها |

> **همهٔ وضعیت‌ها `Proposed` هستند.** در F0 هیچ کدی نوشته نشده؛ هیچ نیازی `Implemented` نیست.
> یک نیازمندی تنها زمانی از `Proposed` خارج می‌شود که **تست متناظرش** با شاهد اجرا شود.

---

## ۲. خلاصهٔ شمارش

| گروه | تعداد نیازمندی | Proposed | Approved | Implemented | Tested | Accepted |
|------|---------------:|---------:|---------:|------------:|-------:|---------:|
| فضای کاری و عملیات فایل (FR-FILE / NFR-FILE) | 40 | 40 | 0 | 0 | 0 | 0 |
| مجوز و اشتراک (FR-PERM) | 16 | 16 | 0 | 0 | 0 | 0 |
| نسخه‌بندی (FR-VERSION) | 8 | 8 | 0 | 0 | 0 | 0 |
| WebDAV (FR-WEBDAV / NFR-WEBDAV) | 23 | 23 | 0 | 0 | 0 | 0 |
| امنیت (FR-SEC) | 12 | 12 | 0 | 0 | 0 | 0 |
| **جمع** | **99** | **99** | **0** | **0** | **0** | **0** |

**تفکیک FR / NFR:**

- FR (عملکردی): **81**
- NFR (غیرعملکردی): **18**

---

## 3. فضای کاری و عملیات فایل (FR-FILE / NFR-FILE)

| Req | عنوان | Component | Entity | Route/API | Screen | Control | Test | Status |
|-----|-------|-----------|--------|-----------|--------|---------|------|--------|
| `FR-FILE-001` | فضای کاری سازمانی به‌عنوان دامنهٔ اصلی محصول (نه ویژگی جانبی). | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-001 | `Proposed` |
| `FR-FILE-002` | مالکیت فایل متعلق به سازمان است، نه حساب شخصی کارمند. owner_org_id الزامی؛ created_by جدا از owner_user_id نگه داشته می‌شود. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-002 | `Proposed` |
| `FR-FILE-003` | ناحیه‌های ثابت: My Files، Shared With Me، Shared Workspace، Department، Project، Customer، Contract، HR، Finance، Archive، Trash. ساختار از داده می‌آید، نه از Enum هاردکدشده. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-003 | `Proposed` |
| `FR-FILE-004` | پوشه‌های ناحیه‌های سازمانی فقط توسط کاربر دارای files.folder_manage ساخته/تغییر می‌شوند؛ ناحیه‌های سیستمی (Trash/Archive) غیرقابل حذف. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-004 | `Proposed` |
| `FR-FILE-005` | Trash: حذف = انتقال به سطل بازیافت با deleted_at و purge_after؛ Restore با بازگرداندن به مسیر اصلی (یا پوشهٔ جانشین اگر مسیر آزاد نیست). | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-005 | `Proposed` |
| `FR-FILE-006` | Hard Delete فقط با Manage Retention + دلیل اجباری + Audit. پیش‌فرض: غیرفعال. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-006 | `Proposed` |
| `FR-FILE-007` | هر فایل می‌تواند به یک یا چند موجودیت CRM متصل شود: customer, contract, project, employee, accounting_record. | Modules/Files · FolderTree + QuotaService | file_relations | `/files/{node}/relations` | S12 | K1,K2 | T-FILE-007 | `Proposed` |
| `FR-FILE-008` | یک منبع حقیقت: اتصال، فایل را کپی نمی‌کند؛ file_relations فقط ارجاع است. حذف موجودیت CRM → رابطه orphaned می‌شود، فایل می‌ماند. | Modules/Files · FolderTree + QuotaService | file_relations | `/files` | S01 | K1,K2 | T-FILE-008 | `Proposed` |
| `FR-FILE-009` | دسترسی به فایل هرگز مجوز ماژول CRM را دور نمی‌زند: اگر کاربر ProjectPolicy@view را ندارد، فایل متصل به آن پروژه هم برایش نامرئی است — حتی با URL مستقیم. | FileAuthorization + CRM Gate | file_nodes, file_workspaces | `/files` | S01 | K2,K14 | T-FILE-009 | `Proposed` |
| `FR-FILE-010` | نمایش فایل‌های یک موجودیت CRM در صفحهٔ همان موجودیت (تب «فایل‌ها»)، مشروط به همان Policy. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/projects/{p}/files` | S14 | K1,K2 | T-FILE-010 | `Proposed` |
| `FR-FILE-011` | آپلود تک‌مرحله‌ای تا سقف پیکربندی‌شده (پیش‌فرض ۳۲MB) با نوار پیشرفت و تلاش مجدد. | UploadController | file_nodes, file_workspaces | `/files/upload` | S01 | K1,K2 | T-FILE-011 | `Proposed` |
| `FR-FILE-012` | آپلود تکه‌ای و قابل ادامه برای فایل بزرگ‌تر از آستانه (پیش‌فرض ۳۲MB، تکه ۸MB) با file_upload_sessions. | UploadController + sessions | file_upload_sessions | `/files/upload/{id}/chunk/{n}` | S01 | K1,K2 | T-FILE-012 | `Proposed` |
| `FR-FILE-013` | هش SHA-256 اجباری روی سرور محاسبه و با هش اعلامی کلاینت مقایسه می‌شود؛ عدم تطابق = رد. | ValidationService (hash) | file_nodes, file_workspaces | `/files` | S01 | K4 | T-FILE-013 | `Proposed` |
| `FR-FILE-014` | آپلود ناقص هرگز به‌عنوان نسخهٔ معتبر ثبت نمی‌شود؛ نشست ناتمام پس از TTL پاک می‌شود. | Modules/Files · FolderTree + QuotaService | file_upload_sessions | `/files` | S01 | K4 | T-FILE-014 | `Proposed` |
| `FR-FILE-015` | اعتبارسنجی سه‌لایه: پسوند (allowed list) + MIME اعلامی + امضای واقعی فایل. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-015 | `Proposed` |
| `FR-FILE-016` | نام ذخیره‌سازی هرگز از نام کاربر ساخته نمی‌شود؛ نام نمایشی جدا ذخیره می‌شود (الگوی فعلی Attachment). | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-016 | `Proposed` |
| `FR-FILE-017` | نام نمایشی فارسی/Unicode مجاز است ولی کاراکترهای کنترلی، \0، /، \ و نام‌های رزرو ویندوز (CON, PRN, AUX, NUL, COM1..9, LPT1..9) نرمال‌سازی می‌شوند. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-017 | `Proposed` |
| `FR-FILE-018` | دانلود از مسیر اختصاصی با Range، Content-Length، Content-Disposition قابل انتخاب، X-Content-Type-Options: nosniff. | DownloadController | file_nodes, file_workspaces | `/files/{node}/download` | S01 | K15,K17 | T-FILE-018 | `Proposed` |
| `FR-FILE-019` | فایل بدون نوع پیش‌نمایش‌پذیر همیشه attachment است، هرگز inline. | DownloadController | file_nodes, file_workspaces | `/files` | S01 | K17 | T-FILE-019 | `Proposed` |
| `FR-FILE-020` | سقف حجم به‌ازای هر فایل، سهمیهٔ هر کاربر، سهمیهٔ هر ناحیه/پروژه؛ عبور از سهمیه = رد پیش از ذخیره. | QuotaService | file_quotas | `/files` | S01 | K10 | T-FILE-020 | `Proposed` |
| `FR-FILE-021` | جستجو بر اساس: نام، پسوند، نوع، مالک، سازنده، آخرین ویرایشگر، تاریخ، پوشه، پروژه، مشتری، دپارتمان، طبقه‌بندی، نسخه، برچسب، یادداشت. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-021 | `Proposed` |
| `FR-FILE-022` | جستجو Permission-aware است: نتایج پیش از نمایش فیلتر می‌شوند؛ فایل Restricted غیرمجاز حتی به‌صورت نام یا شمارش ظاهر نمی‌شود. | SearchGuard | file_nodes, file_workspaces | `/files/search` | S04 | K2 | T-PERM-028 | `Proposed` |
| `FR-FILE-023` | جستجوی متن کامل محتوا جزو F1 نیست؛ نیازمند موتور جدا، هزینه و ریسک مستقل — به F3 منتقل شد. | Modules/Files · FolderTree + QuotaService | file_nodes, file_workspaces | `/files` | S01 | K1,K2 | T-FILE-023 | `Proposed` |
| `FR-FILE-024` | پیش‌نمایش امن: PDF، تصویر (به‌جز SVG)، متن ساده. | PreviewController | file_nodes, file_workspaces | `/files/{node}/preview` | S07 | K16 | T-SEC-F06 | `Proposed` |
| `FR-FILE-025` | SVG و HTML هرگز inline سرو نمی‌شوند؛ همیشه attachment یا از دامنهٔ ایزوله. | PreviewController | file_nodes, file_workspaces | `/files` | S01 | K16,K17 | T-SEC-F06 | `Proposed` |
| `FR-FILE-026` | پیش‌نمایش در <iframe sandbox> با CSP سخت‌گیرانه روی مسیر پیش‌نمایش، مستقل از CSP کلی برنامه. | PreviewController | file_nodes, file_workspaces | `/files` | S01 | K16 | T-SEC-F06 | `Proposed` |
| `FR-FILE-027` | چهار سطح طبقه‌بندی: public_internal / internal / confidential / restricted. | Classification | file_nodes, file_workspaces | `/files` | S13 | K1,K2 | T-FILE-027 | `Proposed` |
| `FR-FILE-028` | restricted هرگز از طریق اشتراک گروهی یا ناحیهٔ عمومی قابل دسترسی نیست؛ فقط Grant صریح به کاربر. | PermissionResolver (step 2) | file_nodes, file_workspaces | `/files` | S01 | K2,K14 | T-FILE-028 | `Proposed` |
| `FR-FILE-029` | سیاست نگهداری قابل تنظیم بر اساس ناحیه/طبقه‌بندی: حداکثر تعداد نسخه، حداکثر عمر، keep_forever، legal_hold. | RetentionService | file_retention_policies | `/files` | S09 | K1,K2 | T-FILE-029 | `Proposed` |
| `FR-FILE-030` | هیچ نسخه‌ای «بی‌ردپا» حذف نمی‌شود: پاک‌سازی نسخه همیشه Audit + گزارش پیش‌اجرا (dry-run). | RetentionService | file_retention_policies | `/files` | S01 | K9 | T-VER-005 | `Proposed` |
| `NFR-FILE-001` | بدون Node.js runtime در Production. | Platform (no Node, streaming) | — | `—` | — | K1 | T-UI-001 | `Proposed` |
| `NFR-FILE-002` | بدون composer.lock فعلی — افزودن وابستگی جدید باید توجیه شود. | Composer lock policy | — | `—` | — | K12 | T-UI-002 | `Proposed` |
| `NFR-FILE-003` | صفحهٔ فهرست پوشه ≤ ۱ ثانیه در P95 برای ۵۰۰۰ آیتم (با Pagination). | Folder listing + indexes | file_nodes | `—` | — | K1 | T-UI-003 | `Proposed` |
| `NFR-FILE-004` | آپلود ۳۲MB در ≤ ۱۰ ثانیه روی اتصال ۱۰۰Mbps. | Platform (no Node, streaming) | — | `—` | — | K1 | T-UI-004 | `Proposed` |
| `NFR-FILE-005` | مصرف حافظهٔ آپلود مستقل از حجم فایل (Streaming). | Streaming upload | — | `—` | — | K1 | T-UI-005 | `Proposed` |
| `NFR-FILE-006` | هر Migration بازگشت‌پذیر و غیرمخرب. | Platform (no Node, streaming) | — | `—` | — | K1 | T-UI-006 | `Proposed` |
| `NFR-FILE-007` | هیچ پرس‌وجو در فهرست پوشه باعث N+1 نشود. | Platform (no Node, streaming) | — | `—` | — | K1 | T-UI-007 | `Proposed` |
| `NFR-FILE-008` | پشتیبانی RTL/فارسی و ۷ عرض صفحه (تا ۳۹۰px). | Platform (no Node, streaming) | — | `—` | — | K1 | T-UI-008 | `Proposed` |
| `NFR-FILE-009` | بدون تغییر دیتابیس مشترک در تست‌ها. | Test harness (isolated DB) | — | `—` | — | K1 | T-UI-009 | `Proposed` |
| `NFR-FILE-010` | سهمیهٔ ذخیره‌سازی قابل مشاهده برای ادمین و کاربر. | Platform (no Node, streaming) | — | `—` | — | K1 | T-UI-010 | `Proposed` |

## 4. مجوز و اشتراک (FR-PERM)

| Req | عنوان | Component | Entity | Route/API | Screen | Control | Test | Status |
|-----|-------|-----------|--------|-----------|--------|---------|------|--------|
| `FR-PERM-001` | مجموعهٔ ۱۸ مجوز پایه: View, Download, Upload, Create Folder, Edit, Rename, Move, Copy, Delete, Restore, Share, Manage Permissions, Lock, Unlock, View Versions, Restore Version, View Audit, Manage Retention. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-001 | `Proposed` |
| `FR-PERM-002` | مجوزها قابل توسعه‌اند: نگهداری در جدول file_permission_defs، نه Enum در کد. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-002 | `Proposed` |
| `FR-PERM-003` | دامنهٔ Grant: user, role, group, team, department, project, customer, organization. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-003 | `Proposed` |
| `FR-PERM-004` | وراثت از پوشهٔ والد به فرزند، با پرچم inherit روی هر پوشه. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-004 | `Proposed` |
| `FR-PERM-005` | Break-inheritance: پرچم inherit=0 روی پوشه، زنجیرهٔ والد بالاتر از همان نقطه را قطع می‌کند؛ مجوزهای همان پوشه معتبر می‌مانند. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-005 | `Proposed` |
| `FR-PERM-006` | Deny مقدم بر Allow است. یک Deny صریح در هر سطح، Allow همان قابلیت را باطل می‌کند. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-006 | `Proposed` |
| `FR-PERM-007` | نبود هر قاعده = Deny (default-deny). | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-007 | `Proposed` |
| `FR-PERM-008` | Deny روی یک جد، دسترسی فرزندانش را نمی‌بندد مگر خودش هم Deny داشته باشد — مگر برای restricted که مسیر جدا دارد. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-008 | `Proposed` |
| `FR-PERM-009` | Grant گروهی هرگز مجوزی بزرگ‌تر از مجوز خود گروه به کاربر نمی‌دهد (بدون تشدید امتیاز). | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-009 | `Proposed` |
| `FR-PERM-010` | کاربر نمی‌تواند مجوزی به دیگری بدهد که خودش ندارد (Share مشروط به داشتن قابلیت). | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-010 | `Proposed` |
| `FR-PERM-011` | هر درخواست (UI، API، دانلود، WebDAV، جستجو) از همان Resolver رد می‌شود؛ هیچ مسیر جانبی مجاز نیست. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-011 | `Proposed` |
| `FR-PERM-012` | با Revocation، نسخهٔ کش مجوز باطل می‌شود؛ لینک‌های امضاشدهٔ قبلی حداکثر تا TTL خود (≤۵ دقیقه) معتبرند و قابل ابطال فوری از طریق یک revoked_at روی توکن. | PermissionResolver + cache | file_access_tokens | `/files/{node}/permissions` | S05 | K3,K2 | T-PERM-012 | `Proposed` |
| `FR-PERM-013` | صفحهٔ «چه کسی به این فایل دسترسی دارد؟» با محاسبهٔ مؤثر (effective) و منبع هر مجوز. | FileAuthorization · PermissionResolver | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2,K14 | T-PERM-013 | `Proposed` |
| `FR-PERM-014` | اشتراک عمومی/بیرونی پیش‌فرض غیرفعال؛ فعال‌سازی فقط با سوئیچ ادمین. | ShareService (disabled) | file_shares | `/files/{node}/permissions` | S08 | K2,K14 | T-PERM-014 | `Proposed` |
| `FR-PERM-015` | در صورت فعال‌سازی: انقضا، رمز، محدودیت دانلود، ابطال، Audit، noindex، Rate Limit. | ShareService (F3) | file_shares | `/files/{node}/permissions` | S08 | K2,K14 | T-PERM-015 | `Proposed` |
| `FR-PERM-016` | کاربر غیرفعال (is_active=0) بلافاصله از همهٔ مسیرها از جمله WebDAV حذف می‌شود. | AuthGuard | file_permissions, file_permission_defs | `/files/{node}/permissions` | S05 | K2 | T-PERM-016 | `Proposed` |

## 5. نسخه‌بندی (FR-VERSION)

| Req | عنوان | Component | Entity | Route/API | Screen | Control | Test | Status |
|-----|-------|-----------|--------|-----------|--------|---------|------|--------|
| `FR-VERSION-001` | هر فایل تاریخچهٔ نسخهٔ تغییرناپذیر دارد: version_number, created_by, created_at, size, sha256, change_note, storage_ref. | VersionService | file_versions, file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-001 | `Proposed` |
| `FR-VERSION-002` | آپلود نسخهٔ جدید شماره را +1 می‌کند؛ شماره هرگز بازاستفاده نمی‌شود. | VersionService | file_versions, file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-002 | `Proposed` |
| `FR-VERSION-003` | دانلود نسخهٔ قدیمی مجاز و Audit‌شده است (نیازمند View Versions). | VersionService | file_versions, file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-003 | `Proposed` |
| `FR-VERSION-004` | Restore = ایجاد نسخهٔ جدید با ارجاع به نسخهٔ مبنا؛ هیچ ردیفی بازنویسی نمی‌شود. | VersionService | file_versions, file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-004 | `Proposed` |
| `FR-VERSION-005` | Dedup: اگر sha256 برابر نسخهٔ موجود باشد، به‌جای ذخیرهٔ دوباره، file_blobs موجود ارجاع داده می‌شود (بدون از دست دادن نسخه). | BlobStore (dedup) | file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-005 | `Proposed` |
| `FR-VERSION-006` | سیاست نگهداری نسخه قابل تنظیم؛ legal_hold پاک‌سازی را مطلقاً مسدود می‌کند. | RetentionService | file_retention_policies | `/files/{node}/versions` | S06 | K9 | T-VER-006 | `Proposed` |
| `FR-VERSION-007` | هش هر نسخه در DB نگه داشته می‌شود و صحت آن در بازیابی بررسی می‌شود. | VersionService | file_versions, file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-007 | `Proposed` |
| `FR-VERSION-008` | مقایسهٔ متادیتای دو نسخه (حجم/هش/سازنده/تاریخ) در UI. | VersionService | file_versions, file_blobs | `/files/{node}/versions` | S06 | K9 | T-VER-008 | `Proposed` |

## 6. WebDAV (FR-WEBDAV / NFR-WEBDAV)

| Req | عنوان | Component | Entity | Route/API | Screen | Control | Test | Status |
|-----|-------|-----------|--------|-----------|--------|---------|------|--------|
| `FR-WEBDAV-001` | Endpoint مستقل با TLS معتبر روی دامنهٔ جدا (مثلاً dav.example.com). | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-001 | `Proposed` |
| `FR-WEBDAV-002` | پشتیبانی Method: OPTIONS, PROPFIND, PROPPATCH, GET, HEAD, PUT, DELETE, MKCOL, MOVE, COPY, LOCK, UNLOCK. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-002 | `Proposed` |
| `FR-WEBDAV-003` | CRM تنها مرجع مجوز است؛ سرویس هیچ جدول مجوز مستقلی ندارد. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-003 | `Proposed` |
| `FR-WEBDAV-004` | هر Method حساس پیش از اجرا مجوز متناظر CRM را می‌پرسد (نگاشت در WEBDAV_CRM_AUTHORIZATION_BRIDGE.md §4). | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-004 | `Proposed` |
| `FR-WEBDAV-005` | Fail-Closed: قطع CRM / پاسخ نامعتبر / HMAC بد / Token منقضی → رد، هرگز مجوز قدیمی. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-005 | `Proposed` |
| `FR-WEBDAV-006` | احراز هویت: Basic روی TLS یا App-Token؛ رمز هرگز بدون TLS. | CrmAuthBackend | file_locks, file_access_tokens | `/api/dav/*` | S15 | K13 | T-DAV-006 | `Proposed` |
| `FR-WEBDAV-007` | App-Token: کوتاه‌عمر، Scope‌دار، قابل Revocation، متصل به کاربر و دستگاه، مقاوم در برابر Replay. | AppTokenService | file_access_tokens | `/api/dav/*` | S15 | K3 | T-DAV-007 | `Proposed` |
| `FR-WEBDAV-008` | قفل واقعی ماندگار (RFC 4918) با lock_token، timeout، refresh، owner. | LockService + sabre Locks | file_locks | `/api/dav/*` | S15 | K19,K20 | T-DAV-008 | `Proposed` |
| `FR-WEBDAV-009` | هر عملیات نوشتن یک نسخهٔ جدید در CRM تولید می‌کند، نه بازنویسی فایل. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-009 | `Proposed` |
| `FR-WEBDAV-010` | همهٔ عملیات در file_activity با Actor=کاربر CRM، Client=webdav ثبت می‌شود. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-010 | `Proposed` |
| `FR-WEBDAV-011` | حداکثر حجم آپلود سرویس قابل تنظیم و مستند. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-011 | `Proposed` |
| `FR-WEBDAV-012` | هیچ پورت دیتابیس یا SMB به اینترنت باز نیست. | UFW + network | file_locks, file_access_tokens | `/api/dav/*` | S15 | K13 | T-DAV-012 | `Proposed` |
| `FR-WEBDAV-013` | Health check و Alerting برای سرویس. | WEBDAV service (VPS) · BridgeClient | file_locks, file_access_tokens | `/api/dav/*` | S15 | K19,K20 | T-DAV-013 | `Proposed` |
| `FR-WEBDAV-014` | سازگاری با Windows Explorer فقط پس از تست واقعی اعلام می‌شود. | E3 test env | file_locks, file_access_tokens | `/api/dav/*` | S16 | K19,K20 | T-DAV-014 | `Proposed` |
| `FR-WEBDAV-015` | محدودیت‌های ویندوز (۵۰MB پیش‌فرض، ۴GB مطلق، WebClient Deprecated) در راهنمای کاربر مستند. | User guide | file_locks, file_access_tokens | `/api/dav/*` | S16 | K19,K20 | T-DAV-015 | `Proposed` |
| `NFR-WEBDAV-001` | پشتیبانی حداقل ۲۵ کاربر هم‌زمان فهرست‌گیری. | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-001 | `Proposed` |
| `NFR-WEBDAV-002` | PROPFIND با ۵۰۰ آیتم ≤ ۲ ثانیه. | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-002 | `Proposed` |
| `NFR-WEBDAV-003` | آپلود ۱۰۰MB از ویندوز (با Registry افزایش‌یافته) ≤ ۳ دقیقه. | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-003 | `Proposed` |
| `NFR-WEBDAV-004` | کاهش Uptime سالانه ≤ ۰.۵٪ (≈۴۳ ساعت). | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-004 | `Proposed` |
| `NFR-WEBDAV-005` | Recovery پس از قطع CRM ≤ ۳۰ ثانیه پس از بازگشت. | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-005 | `Proposed` |
| `NFR-WEBDAV-006` | هر عملیات ≤ ۳ درخواست شبکه‌ای به CRM (authorize + commit + audit). | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-006 | `Proposed` |
| `NFR-WEBDAV-007` | Cache مجوز ≤ ۳۰ ثانیه با ابطال فوری. | Permission cache | — | `/api/dav/*` | — | K19 | T-PERF-007 | `Proposed` |
| `NFR-WEBDAV-008` | قفل کهنه پس از TTL آزاد شود، بدون دخالت ادمین. | VPS sizing + cache | — | `/api/dav/*` | — | K19,K20 | T-PERF-008 | `Proposed` |

## 7. امنیت (FR-SEC)

| Req | عنوان | Component | Entity | Route/API | Screen | Control | Test | Status |
|-----|-------|-----------|--------|-----------|--------|---------|------|--------|
| `FR-SEC-001` | فایل در بیرون از Public Root ذخیره می‌شود؛ هیچ URL عمومی مستقیم وجود ندارد. | Storage contract | file_activity, file_access_tokens | `—` | — | K1 | T-SEC-F001 | `Proposed` |
| `FR-SEC-002` | مجوزسنجی سمت سرور پیش از هر دانلود/پیش‌نمایش، بدون استثنا. | FileAuthorization | file_activity, file_access_tokens | `—` | — | K2 | T-SEC-F002 | `Proposed` |
| `FR-SEC-003` | مسیر ذخیره‌سازی هرگز از ورودی کاربر ساخته نمی‌شود + اعتبارسنجی node_id تعلق. | PathGuard + NameSanitizer | file_activity, file_access_tokens | `—` | — | K5,K6 | T-SEC-F003 | `Proposed` |
| `FR-SEC-004` | لینک موقت: امضاشده، TTL ≤ ۵ دقیقه، متصل به کاربر، قابل ابطال. | AccessTokenService | file_access_tokens | `—` | — | K3 | T-SEC-F004 | `Proposed` |
| `FR-SEC-005` | اعتبارسنجی امضای فایل (magic bytes)، نه فقط پسوند/MIME اعلامی. | ValidationService | file_activity, file_access_tokens | `—` | — | K4 | T-SEC-F005 | `Proposed` |
| `FR-SEC-006` | پیش‌نمایش HTML/SVG: بدون اجرای اکتیو کانتنت. | PreviewController | file_activity, file_access_tokens | `—` | — | K16,K17 | T-SEC-F006 | `Proposed` |
| `FR-SEC-007` | کنترل آرشیو: سقف نسبت فشرده‌سازی، حداکثر تعداد فایل، سقف حجم بازشده. | ArchiveGuard | file_activity, file_access_tokens | `—` | — | K4,K10 | T-SEC-F007 | `Proposed` |
| `FR-SEC-008` | Rate Limit روی آپلود، دانلود و ورود WebDAV. | RateLimiter | file_activity, file_access_tokens | `—` | — | K8 | T-SEC-F008 | `Proposed` |
| `FR-SEC-009` | Audit کامل بدون محتوای فایل، رمز یا توکن خام. | AuditWriter | file_activity | `—` | — | K9,K18 | T-SEC-F009 | `Proposed` |
| `FR-SEC-010` | هیچ Secret در Git/Log/فایل عمومی؛ چرخش دوره‌ای کلید. | SecretMgmt | file_activity, file_access_tokens | `—` | — | K12 | T-SEC-F010 | `Proposed` |
| `FR-SEC-011` | اسکن بدافزار «در صورت امکان» — ارزیابی در Threat Model، تصمیم در OD-107. | ScanService | file_activity, file_access_tokens | `—` | — | K7 | T-SEC-F011 | `Proposed` |
| `FR-SEC-012` | جستجو و لیست‌ها هیچ نشانی از فایل غیرمجاز لو نمی‌دهند (شامل شمارش و زمان پاسخ). | SearchGuard | file_activity, file_access_tokens | `—` | — | K2 | T-SEC-F012 | `Proposed` |

---

## 8. بلوکه‌کننده‌ها — چه چیزی نمی‌گذارد وضعیت جلو برود

| مورد | چه نیازمندی‌هایی را بلوکه می‌کند |
|------|-------------------------------------|
| **هیچ کد ماژول فایل وجود ندارد** | همهٔ ۹۹ نیازمندی (F1) |
| **VPS آزمایشی تهیه نشده** | FR-WEBDAV-001…013 · NFR-WEBDAV-001…008 |
| **ویندوز واقعی در دسترس نیست** | FR-WEBDAV-014 · FR-WEBDAV-015 |
| **تصمیم دپارتمان/گروه (OD-104/105)** | FR-PERM-003 · FR-PERM-009 |
| **تصمیم اسکن بدافزار (OD-127)** | FR-SEC-011 |
| **بدون کد بکاپ** | FR-FILE-030 (پاک‌سازی نسخه بدون بکاپ امن نیست) |

---

## 9. وضعیت‌ها — معیار خروج از `Proposed`

| از | به | شرط |
|----|----|-----|
| `Proposed` | `Approved` | کارفرما تأیید کند (ثبت در `OPEN_DECISIONS.md`) |
| `Approved` | `Implemented` | کد روی شاخه + Migration بازگشت‌پذیر |
| `Implemented` | `Unit Tested` | تست واحد سبز در CI |
| `Unit Tested` | `Integration Tested` | تست از **HTTP واقعی** با ≥۲ کاربر مستقل |
| `Integration Tested` | `Environment Tested` | اجرا روی E2 (VPS) یا E3 (ویندوز) |
| `Environment Tested` | `Accepted` | پذیرش کارفرما با شاهد |

> **قاعدهٔ سخت:** هیچ جهشی از `Proposed` به `Accepted` مجاز نیست.
