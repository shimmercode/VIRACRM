# FILE_WORKSPACE_ERD — مدل داده Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — **هیچ Migration نوشته نشده است**

> **هشدار اجرایی:** این سند طرح است، نه اجرا. طبق دستور §25 و §26، تا تأیید کارفرما **هیچ جدولی ساخته نمی‌شود** و دیتابیس موجود دست نمی‌خورد.

---

## ۰. اصولی که Schema باید رعایت کند

| # | اصل | پیامد در طراحی |
|---|-----|------------------|
| P1 | **مالکیت سازمانی** | `owner_org_id` + `created_by` + `owner_user_id` سه ستون جدا |
| P2 | **نسخه تغییرناپذیر** | `file_versions` فقط INSERT؛ هیچ UPDATE روی نسخهٔ تاریخی |
| P3 | **هش به‌عنوان هویت بایت** | `file_blobs.sha256 UNIQUE` → dedup طبیعی |
| P4 | **حذف غیرمخرب** | `deleted_at` + `purge_after`؛ هیچ DELETE کوتاه‌مدت |
| P5 | **مجوز قابل حسابرسی** | `granted_by`, `created_at` روی هر Grant |
| P6 | **Migration بازگشت‌پذیر** | هر فایل `down()` معنادار، بدون از دست دادن داده |
| P7 | **سازگاری با MySQL روی cPanel** | بدون `GEOMETRY`، بدون `JSON` اجباری (MariaDB قدیمی‌تر)، حداکثر `utf8mb4_unicode_ci` |
| P8 | **بدون تغییر جداول موجود** | فقط افزودن جدول؛ مگر تصمیم صریح |

---

## ۱. نمودار موجودیت‌ها

**مرجع معتبر همین سند است و نمودار گراف‌ویزی جداگانه‌ای برای ERD فایل وجود ندارد.**

> **تصحیح:** نسخهٔ قبلی این بند به یک فایل نمودار در `docs/database/` و به فرمان رندر جداگانه
> ارجاع می‌داد؛ آن فایل **هرگز ساخته نشد** و ارجاع به آن یک وعدهٔ توخالی بود. حذف شد.
>
> **چرا نمودار جدا نمی‌سازیم:** مدل داده در جدول‌های همین سند با ستون، نوع، ایندکس و کلید خارجی
> کامل ثبت شده است. یک `.dot` موازی، دومین منبع حقیقت می‌سازد که دیر یا زود با این جدول‌ها ناهم‌خوان می‌شود —
> همان نوع خطایی که در این بند رخ داد. نمودارهای جریان (۰۱ تا ۱۰) در `docs/diagrams/file-module/`
> **رفتار** را نشان می‌دهند، نه ساختار جدول را؛ و آن‌ها با `render-file-diagrams.mjs` قابل بازتولیدند.

```
                         ┌──────────────────┐
                         │  file_workspaces │  (ناحیه‌ها: org/dept/project/personal …)
                         └────────┬─────────┘
                                  │ 1..n
                         ┌────────▼─────────┐
        ┌────────────────┤    file_nodes    ├────────────────┐
        │  parent_id     │  (درخت، مسیر)    │  owner_org_id  │
        │  (self FK)     └────────┬─────────┘                │
        │                         │ 1..n                     │
   ┌────▼──────────┐     ┌────────▼─────────┐        ┌───────▼────────┐
   │ file_shares   │     │  file_versions   │        │ file_relations │──▶ CRM entities
   └───────────────┘     └────────┬─────────┘        └────────────────┘
                                  │ n..1
                         ┌────────▼─────────┐
                         │   file_blobs     │  sha256 UNIQUE
                         └────────┬─────────┘
                                  │
                         ┌────────▼─────────────┐
                         │ file_storage_locations│ (کدام Backend)
                         └───────────────────────┘

  file_permissions ──▶ file_permission_defs
  file_locks · file_activity · file_comments · file_tags · file_node_tags
  file_quotas · file_retention_policies · file_upload_sessions · file_access_tokens
  file_groups · file_group_members · departments · user_departments
```

---

## ۲. فرهنگ داده (Data Dictionary)

> علامت‌ها: 🔑 کلید اصلی · 🔗 کلید خارجی · ⚠️ ایندکس لازم · 🔒 حساس

### ۲.۱. `file_workspaces` — ناحیه‌های فضای کاری

**هدف:** ساختار از داده بیاید، نه از Enum هاردکدشده (FR-FILE-003).

| ستون | نوع | Null | پیش‌فرض | توضیح |
|------|-----|:----:|---------|-------|
| id 🔑 | bigint unsigned | — | auto | — |
| code ⚠️ | varchar(48) | — | — | `org`, `dept`, `project`, `customer`, `hr`, `finance`, `archive`, `personal` |
| title_fa | varchar(120) | — | — | برچسب فارسی |
| kind | varchar(24) | — | — | `system` \| `org` \| `personal` |
| scope_type | varchar(24) | ✅ | NULL | `department` \| `project` \| `customer` \| NULL |
| scope_id | bigint unsigned | ✅ | NULL | شناسهٔ دامنه (پلیمورفیک سبک، بدون FK به‌خاطر ۴ جدول مختلف) |
| root_node_id 🔗 | bigint unsigned | ✅ | NULL | `file_nodes.id` — ریشهٔ ناحیه (nullable برای حلقه) |
| default_classification | varchar(24) | — | `internal` | پیش‌فرض طبقه‌بندی |
| is_system | boolean | — | false | ناحیه‌های سیستمی حذف‌شدنی نیستند |
| created_at / updated_at | timestamp | ✅ | — | — |

**قیدها:** `UNIQUE(code)` · `INDEX(scope_type, scope_id)`
**چرخه:** ساخته می‌شود با Seeder (ناحیه‌های سیستمی) یا توسط ادمین.
**حذف:** فقط `is_system=false` و فقط وقتی هیچ node فعالی ندارد.
**اثر مجوز:** `root_node_id` نقطهٔ شروع وراثت هر ناحیه است.

### ۲.۲. `file_nodes` — فایل و پوشه (یک جدول، دو نوع)

**هدف:** درخت یکپارچه؛ پوشه و فایل در یک جدول تا وراثت و Move ساده بماند.

| ستون | نوع | Null | پیش‌فرض | توضیح |
|------|-----|:----:|---------|-------|
| id 🔑 | bigint unsigned | — | auto | — |
| uuid ⚠️ | char(36) | — | — | برای URL عمومی/توکن؛ هرگز `id` در URL بیرونی |
| workspace_id 🔗 | bigint unsigned | — | — | `file_workspaces.id` |
| parent_id 🔗 | bigint unsigned | ✅ | NULL | NULL = ریشهٔ ناحیه |
| node_type | varchar(8) | — | — | `folder` \| `file` |
| name 🔒 | varchar(255) | — | — | نام **نمایشی** (فارسی مجاز) |
| name_normalized ⚠️ | varchar(255) | — | — | برای یکتایی و جستجو (lower, NFC) |
| path ⚠️ | varchar(1024) | ✅ | NULL | مسیر مادی‌شده برای پوشه‌ها (`/1/4/9/`) |
| depth | smallint unsigned | — | 0 | عمق درخت (سقف ۳۲) |
| inherit | boolean | — | true | Break-inheritance |
| classification | varchar(24) | — | `internal` | `public_internal`\|`internal`\|`confidential`\|`restricted` |
| current_version_id 🔗 | bigint unsigned | ✅ | NULL | فقط برای `node_type=file` |
| owner_org_id | bigint unsigned | — | — | مالکیت سازمانی (P1) |
| owner_user_id 🔗 | bigint unsigned | ✅ | NULL | مسئول نگهداری (اختیاری) |
| created_by 🔗 ⚠️ | bigint unsigned | — | — | سازنده — **جدا از owner** |
| last_modified_by 🔗 | bigint unsigned | ✅ | NULL | آخرین ویرایشگر |
| size_bytes | bigint unsigned | — | 0 | برای پوشه = مجموع (کش‌شده، بازمحاسبه با Job) |
| mime | varchar(128) | ✅ | NULL | MIME تأییدشده (نه اعلامی) |
| ext | varchar(16) | ✅ | NULL | پسوند مؤثر |
| is_starred | boolean | — | false | — |
| deleted_at ⚠️ | timestamp | ✅ | NULL | سطل بازیافت |
| deleted_by 🔗 | bigint unsigned | ✅ | NULL | — |
| purge_after | timestamp | ✅ | NULL | تاریخ حذف قطعی |
| created_at / updated_at | timestamp | ✅ | — | — |

**قیدها:**
- `UNIQUE(uuid)`
- `UNIQUE(parent_id, name_normalized, deleted_at)` — جلوگیری از دو نام یکسان در یک پوشه (با در نظر گرفتن حذف)
- `INDEX(workspace_id, parent_id, deleted_at)` — فهرست پوشه
- `INDEX(path)` — زیردرخت
- `INDEX(classification, deleted_at)` — فیلتر
- `CHECK(node_type IN ('folder','file'))` در سطح اپلیکیشن (MySQL قدیمی CHECK را نادیده می‌گیرد)

**چرخه:** `active` → `trashed` (`deleted_at`) → `restored` (NULL) یا `purged` (DELETE)
**اثر مجوز:** وراثت از `parent_id`؛ `inherit=false` زنجیره را قطع می‌کند؛ `classification=restricted` مسیر جدا.
**حذف:** هرگز CASCADE فیزیکی بدون Audit و مجوز `manage_retention`.

### ۲.۳. `file_versions` — تاریخچهٔ تغییرناپذیر

| ستون | نوع | Null | توضیح |
|------|-----|:----:|-------|
| id 🔑 | bigint unsigned | — | — |
| node_id 🔗 ⚠️ | bigint unsigned | — | `file_nodes.id` |
| version_number ⚠️ | int unsigned | — | از ۱ شروع، هرگز بازاستفاده نمی‌شود |
| blob_id 🔗 | bigint unsigned | — | `file_blobs.id` |
| size_bytes | bigint unsigned | — | تکرار عمدی (blob ممکن است مشترک باشد) |
| sha256 ⚠️ | char(64) | — | تکرار عمدی برای دفاع در عمق |
| original_name | varchar(255) | — | نام در لحظهٔ آپلود (اگر تغییر کرد) |
| mime | varchar(128) | ✅ | — |
| change_note | varchar(500) | ✅ | یادداشت اختیاری |
| source | varchar(16) | — | `upload` \| `webdav` \| `restore` \| `copy` |
| restored_from_version | int unsigned | ✅ | اگر `source=restore` |
| created_by 🔗 | bigint unsigned | — | — |
| created_at ⚠️ | timestamp | — | `useCurrent` |
| retained_until | timestamp | ✅ | از سیاست نگهداری |
| is_current | boolean ⚠️ | — | `true` فقط برای یکی در هر node |
| purged_at | timestamp | ✅ | اگر پاک شد |

**قیدها:**
- `UNIQUE(node_id, version_number)`
- `INDEX(node_id, is_current)` — یافتن نسخهٔ جاری
- `INDEX(sha256)`
- `INDEX(purged_at)` — گزارش پاک‌سازی

**قاعدهٔ سخت:** هیچ `UPDATE` روی ردیفی که `is_current=false` شده و `purged_at` ندارد، مگر برای `retained_until`/`purged_at` توسط Job نگهداری.
**بازگردانی:** نسخهٔ جدید با `source=restore` و `restored_from_version` ساخته می‌شود — **هیچ ردیفی بازنویسی نمی‌شود**.

### ۲.۴. `file_blobs` — بایت‌ها (content-addressed)

| ستون | نوع | Null | توضیح |
|------|-----|:----:|-------|
| id 🔑 | bigint unsigned | — | — |
| sha256 ⚠️ **UNIQUE** | char(64) | — | هویت بایت |
| size_bytes | bigint unsigned | — | — |
| storage_location_id 🔗 | bigint unsigned | — | کدام Backend |
| storage_key ⚠️ | varchar(512) | — | مسیر در Backend (`blobs/ab/<sha>`) |
| ref_count | int unsigned | — | تعداد نسخه‌های ارجاع‌دهنده (کش) |
| verified_at | timestamp | ✅ | آخرین بررسی صحت |
| created_at | timestamp | — | — |

**چرخه:** `created` → `referenced` → `unreferenced` → `deleted` (فقط وقتی `ref_count=0` **و** backup گرفته شده).
**جداافتادگی (orphan):** Job هفتگی `orphans` را گزارش می‌دهد؛ حذف فقط با تصمیم ادمین + Audit.

### ۲.۵. `file_storage_locations` — کجا ذخیره می‌شود

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| code ⚠️ UNIQUE | varchar(48) | `vps-object`, `cpanel-local`, `nas-01` |
| driver | varchar(24) | `s3` \| `local` \| `sftp` |
| config_encrypted 🔒 | text | رمزنگاری‌شده با `VIRA_CREDENTIAL_KEY` (OD-29) |
| is_default | boolean | فقط یکی |
| is_read_only | boolean | برای Archive/مهاجرت |
| capacity_bytes | bigint unsigned | ✅ |
| used_bytes | bigint unsigned | کش، بازمحاسبه دوره‌ای |
| created_at | timestamp | — |

**نکتهٔ امنیتی:** `config_encrypted` هرگز در Log یا خروجی dump بدون ماسک نمی‌رود.

### ۲.۶. `file_permissions` — Grant/Deny

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| node_id 🔗 ⚠️ | bigint unsigned | — |
| principal_type ⚠️ | varchar(16) | `user`\|`role`\|`group`\|`team`\|`dept`\|`project`\|`customer`\|`org` |
| principal_id | bigint unsigned | ۰ برای `org` |
| capability ⚠️ | varchar(32) | 🔗 به `file_permission_defs.code` |
| effect ⚠️ | varchar(8) | `allow` \| `deny` |
| granted_by 🔗 | bigint unsigned | — |
| expires_at | timestamp | ✅ |
| created_at | timestamp | — |

**قیدها:** `UNIQUE(node_id, principal_type, principal_id, capability)`
**ایندکس:** `(principal_type, principal_id, capability)` — برای «همهٔ چیزهایی که این کاربر می‌بیند»
**اثر:** Deny مقدم؛ جزئیات در `FILE_PERMISSION_MATRIX.md` §4.

### ۲.۷. `file_permission_defs` — قابلیت‌های قابل توسعه

| ستون | نوع | توضیح |
|------|-----|-------|
| code 🔑 | varchar(32) | `view`, `download`, … |
| label_fa | varchar(64) | برچسب |
| is_sensitive | boolean | نمایش هشدار در UI |
| depends_on | varchar(128) | CSV قابلیت‌های وابسته |
| sort | smallint | ترتیب نمایش |

### ۲.۸. `file_groups` و `file_group_members`

| جدول | ستون‌ها |
|-------|---------|
| `file_groups` | id 🔑 · code ⚠️ UNIQUE · title_fa · description · is_system · created_by · timestamps |
| `file_group_members` | id 🔑 · group_id 🔗 · user_id 🔗 · added_by · created_at · `UNIQUE(group_id,user_id)` |

**توضیح:** گروه «مخزن عملیات» است، نه «نقش». اگر OD-105 تصمیم بگیرد گروه لازم نیست، این دو جدول حذف می‌شوند **بدون** تأثیر روی `file_permissions` (چون `principal_type` رشته است).

### ۲.۹. `file_shares` — اشتراک‌گذاری بیرونی (F3)

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| node_id 🔗 | bigint unsigned | — |
| token_hash 🔒 ⚠️ | char(64) | **هش** توکن، نه خودش |
| created_by 🔗 | bigint unsigned | — |
| password_hash 🔒 | varchar(255) | ✅ |
| expires_at ⚠️ | timestamp | الزامی در F3 |
| max_downloads | int unsigned | ✅ |
| download_count | int unsigned | پیش‌فرض ۰ |
| is_active | boolean | — |
| last_accessed_at | timestamp | ✅ |
| created_at | timestamp | — |

**وضعیت F1:** جدول ساخته می‌شود ولی **هیچ مسیری به آن وصل نمی‌شود** (پیش‌فرض خاموش، FR-PERM-014). دلیل ساخت زودهنگام: Migration بازگشت‌پذیر بهتر از Migration دیرهنگام روی دادهٔ واقعی است. **تصمیم نهایی در OD-108.**

### ۲.۱۰. `file_locks` — قفل سرور-محور

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| node_id 🔗 ⚠️ | bigint unsigned | — |
| lock_kind | varchar(16) | `shared` \| `exclusive` |
| origin | varchar(8) | `app` \| `webdav` |
| owner_user_id 🔗 | bigint unsigned | — |
| lock_token 🔒 ⚠️ | char(64) | یکتا، برای WebDAV `Lock-Token` |
| depth | varchar(8) | `0` \| `infinity` (WebDAV) |
| acquired_at | timestamp | — |
| expires_at ⚠️ | timestamp | — |
| refreshed_at | timestamp | ✅ |
| released_at | timestamp | ✅ |
| released_by 🔗 | bigint unsigned | ✅ |
| release_reason | varchar(24) | ✅ `owner`\|`expired`\|`force`\|`logout` |

**قید حیاتی برای جلوگیری از Race:**
```
UNIQUE(node_id, active)  ← با ستون تولیدشدهٔ active = (released_at IS NULL)
```
یا (سازگارتر با MariaDB قدیمی): `UNIQUE` جزئی شبیه‌سازی‌شده با ستون `active TINYINT NULL` که هنگام آزادسازی `NULL` می‌شود (NULL در UNIQUE تکرارپذیر است).

**اثر:** خواندن آزاد، نوشتن ممنوع اگر قفل فعالِ دیگری وجود دارد.

### ۲.۱۱. `file_activity` — رخدادها

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| node_id 🔗 ⚠️ | bigint unsigned | ✅ (حذف node → NULL، رخداد می‌ماند) |
| action ⚠️ | varchar(32) | `create`\|`view`\|`download`\|`upload`\|`edit`\|`rename`\|`move`\|`copy`\|`delete`\|`restore`\|`share`\|`revoke_share`\|`permission_change`\|`lock`\|`unlock`\|`version_restore`\|`permission_denied`\|`dav_auth_failure` |
| actor_user_id 🔗 ⚠️ | bigint unsigned | ✅ (کاربر حذف‌شده → NULL، رخداد می‌ماند) |
| actor_ip | varchar(45) | ✅ |
| client | varchar(24) | `browser`\|`webdav`\|`api`\|`job` |
| result | varchar(8) | `ok` \| `denied` |
| subject_type / subject_id | varchar(48) / bigint | موجودیت مرتبط CRM |
| meta | json | ✅ (بدون محتوای فایل/رمز) |
| correlation_id ⚠️ | char(36) | ✅ | زنجیرهٔ یک درخواست |
| created_at ⚠️ | timestamp | — |

**قیدها:** `INDEX(node_id, created_at)` · `INDEX(actor_user_id, created_at)` · `INDEX(action, created_at)`
**جدا از `audit_logs` عمومی:** `audit_logs` برای رخدادهای CRM است؛ `file_activity` برای رخدادهای فایل با فیلدهای مخصوص. **تصمیم در OD-109** (ادغام یا تفکیک).

### ۲.۱۲. `file_relations` — اتصال به CRM

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| node_id 🔗 ⚠️ | bigint unsigned | — |
| entity_type ⚠️ | varchar(32) | `customer`\|`contract`\|`project`\|`employee`\|`accounting_record` |
| entity_id ⚠️ | bigint unsigned | — |
| relation | varchar(24) | `attachment`\|`deliverable`\|`evidence`\|`template` |
| state | varchar(12) | `active`\|`orphaned` |
| created_by 🔗 | bigint unsigned | — |
| created_at | timestamp | — |

**قیدها:** `UNIQUE(node_id, entity_type, entity_id)` · `INDEX(entity_type, entity_id)`
**قاعده:** رابطهٔ `orphaned` دسترسی از مسیر موجودیت را **قطع** می‌کند ولی فایل را حذف نمی‌کند (FR-FILE-008).

### ۲.۱۳. `file_quotas`

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| scope_type | varchar(16) | `user`\|`workspace`\|`department`\|`org` |
| scope_id | bigint unsigned | — |
| max_bytes | bigint unsigned | — |
| max_file_bytes | bigint unsigned | ✅ |
| used_bytes | bigint unsigned | کش |
| enforced | boolean | — |

**قید:** `UNIQUE(scope_type, scope_id, enforced)` (برای اجازهٔ چند سقف هم‌زمان با اولویت).

### ۲.۱۴. `file_retention_policies`

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | bigint unsigned | — |
| scope_type / scope_id | varchar(16) / bigint | — |
| max_versions | smallint | ✅ |
| max_age_days | int | ✅ (نسخه) |
| trash_days | smallint | پیش‌فرض ۳۰ |
| keep_forever | boolean | — |
| legal_hold | boolean | مسدودکنندهٔ مطلق |
| dry_run_only | boolean | پیش‌فرض **true** تا ادمین تأیید کند |
| created_by | bigint | — |

### ۲.۱۵. `file_upload_sessions`

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | char(36) | UUID |
| user_id 🔗 | bigint unsigned | — |
| node_id 🔗 | bigint unsigned | ✅ (آپلود نسخهٔ جدید) |
| target_name | varchar(255) | — |
| expected_size | bigint unsigned | — |
| expected_sha256 | char(64) | ✅ |
| chunk_size | int unsigned | — |
| chunks_total | smallint | — |
| chunks_received | smallint | — |
| temp_key | varchar(512) | مسیر موقت در Backend |
| status ⚠️ | varchar(12) | `open`\|`assembling`\|`completed`\|`aborted`\|`expired` |
| expires_at ⚠️ | timestamp | — |
| created_at / updated_at | timestamp | — |

**قید:** `INDEX(status, expires_at)` — Job پاک‌سازی.
**قاعدهٔ سخت:** ردیف `file_versions` **فقط** پس از `completed` و تطابق هش ساخته می‌شود.

### ۲.۱۶. `file_access_tokens` — لینک کوتاه‌عمر

| ستون | نوع | توضیح |
|------|-----|-------|
| id 🔑 | char(36) | — |
| node_id 🔗 | bigint unsigned | — |
| version_id 🔗 | bigint unsigned | ✅ |
| user_id 🔗 | bigint unsigned | — (لینک همیشه به کاربر بسته است) |
| purpose | varchar(16) | `download`\|`preview` |
| revoked_at ⚠️ | timestamp | ✅ |
| expires_at ⚠️ | timestamp | ≤۵ دقیقه |
| created_at | timestamp | — |

**قید:** `INDEX(user_id, expires_at)`
**قاعده:** `revoked_at` در **هر** درخواست بررسی می‌شود (FR-PERM-012).

### ۲.۱۷. `file_comments` و `file_tags` و `file_node_tags`

| جدول | ستون‌ها | فاز |
|-------|---------|:---:|
| `file_comments` | id · node_id · version_id ✅ · user_id · body (متن ساده، HTML مجاز نیست) · parent_id ✅ · resolved_at ✅ · timestamps | F2 |
| `file_tags` | id · name ⚠️ UNIQUE · color · created_by | F1 |
| `file_node_tags` | node_id + tag_id (کلید مرکب) · tagged_by | F1 |

**نکته:** `file_comments.body` هرگز به‌عنوان HTML رندر نمی‌شود؛ فقط متن با Escape.

---

## ۳. روابط کلیدی و رفتار حذف

| والد | فرزند | رفتار حذف | چرا |
|------|-------|-----------|-----|
| `file_nodes` (parent) | `file_nodes` (child) | **Restrict** | حذف پوشه با محتوا فقط با انتقال همه به Trash |
| `file_nodes` | `file_versions` | **Restrict** | نسخه بدون node معنا ندارد؛ node فقط soft-delete می‌شود |
| `file_blobs` | `file_versions` | **Restrict** | blob فقط وقتی حذف می‌شود که ref_count=۰ |
| `file_nodes` | `file_permissions` | `cascadeOnDelete` | مجوز بدون node بی‌معنا (فقط در purge فیزیکی) |
| `file_nodes` | `file_relations` | `cascadeOnDelete` | — |
| `file_nodes` | `file_activity` | **nullOnDelete** | تاریخچه باید بماند |
| `users` | هر FK | **nullOnDelete** | حذف کاربر نباید تاریخچه را بشوید |
| `file_permission_defs` | `file_permissions.capability` | **Restrict** | قابلیت در حال استفاده حذف نمی‌شود |

---

## ۴. تأثیر بر دیتابیس موجود (تحلیل پیش از Migration)

| # | خطر | تحلیل | اقدام |
|---|-----|--------|------|
| 1 | تداخل با `attachments` | `attachments` جدول مستقل با ستون‌های مشابه ولی معنای متفاوت است | **تصمیم در OD-110**؛ دو گزینه: (الف) `attachments` به `file_nodes` مهاجرت کند، (ب) موازی بمانند. **پیشنهاد: (ب) در F1، مهاجرت در F2 با ابزار.** |
| 2 | تداخل `audit_logs` | `audit_logs` موجود `subject_type/subject_id` دارد | استفادهٔ مجدد با `subject_type='file_node'`؛ ستون جدید لازم نیست |
| 3 | FK به `users` | حذف کاربر نباید فایل را بشوید | `nullOnDelete` در همهٔ جا |
| 4 | `contract_files.attachment_id` | FK به `attachments` است، نه `file_nodes` | در F1 دست نمی‌خورد؛ در F2 با `file_relations` پل می‌شود |
| 5 | حجم جداول | پیش‌بینی ۱۲ ماه: ~۵۰۰k نسخه | ایندکس‌های §2 برای همین طراحی شده‌اند |
| 6 | Charset | `utf8mb4` روی MariaDB قدیمی سقف ایندکس ۷۶۷ بایت دارد | `path varchar(1024)` ایندکس کامل نمی‌شود → از `path(255)` استفاده شود |
| 7 | Migration بازگشت‌پذیر | هر `up()` باید `down()` داشته باشد | بازبینی در PR |

---

## ۵. تعداد جداول و ترتیب Migration پیشنهادی

| # | Migration | جداول | وابسته به |
|---|-----------|-------|-----------|
| 1 | `..._create_file_core_tables` | `file_workspaces`, `file_nodes`, `file_storage_locations`, `file_blobs`, `file_versions` | `users` |
| 2 | `..._create_file_permission_tables` | `file_permission_defs`, `file_permissions`, `file_groups`, `file_group_members` | 1 |
| 3 | `..._create_file_ops_tables` | `file_locks`, `file_activity`, `file_relations`, `file_quotas`, `file_retention_policies`, `file_upload_sessions`, `file_access_tokens` | 1,2 |
| 4 | `..._create_file_collab_tables` | `file_comments`, `file_tags`, `file_node_tags`, `file_shares` | 1,2 |
| 5 | `..._seed_file_workspaces` | Seeder، نه Migration | 1 |

**کل:** ۱۷ جدول اصلی + ۲ (departments, user_departments اگر OD-104 تأیید شود) = **حداکثر ۱۹**.

---

## ۶. آنچه هنوز تصمیم نشده

| مورد | OD |
|------|-----|
| سرنوشت `attachments` | OD-110 |
| ادغام یا تفکیک `file_activity`/`audit_logs` | OD-109 |
| منبع دپارتمان | OD-104 |
| گروه لازم است؟ | OD-105 |
| ساخت `file_shares` در F1 یا F3 | OD-108 |
| سقف عمق درخت (پیشنهاد ۳۲) | OD-111 |
