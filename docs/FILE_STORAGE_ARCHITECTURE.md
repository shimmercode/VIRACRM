# FILE_STORAGE_ARCHITECTURE — معماری Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> **تصمیم‌های قطعی که این سند دوباره باز نمی‌کند:** CRM روی cPanel می‌ماند · PHP/Laravel · MySQL · Modular Monolith · بدون Node در Production · WebDAV روی **VPS مستقل** · Online Office در **F3**.
>
> **ADR مرتبط:** `docs/adr/ADR-FILE-001-file-workspace-architecture.md`

---

## ۱. مسئلهٔ واقعی

مسئله «آپلود فایل» نیست. مسئله این است:

1. فایل **مالکیت سازمانی** داشته باشد؛ با رفتن کارمند، فایل نرود.
2. **همهٔ** مسیرهای دسترسی (مرورگر، API، دانلود، WebDAV، جستجو) از **یک مدل مجوز** حرف شنوی کنند.
3. مجوز ماژول‌های CRM **دور زده نشود** (فایل پروژه‌ای که کاربر نمی‌تواند ببیند، نباید از Drive قابل خواندن باشد).
4. نسخه، قفل و Audit **قابل اثبات** باشند، نه نمایشی.
5. استقرار روی cPanel **خراب نشود**.

هر معماری‌ای که یکی از این پنج را برآورده نکند، رد می‌شود — حتی اگر آماده‌ترین گزینه باشد.

---

## ۲. سه گزینه و معیار مقایسه

### ۲.۱. معیارها (وزن‌دار)

| # | معیار | وزن | چرا این وزن |
|---|-------|:---:|-------------|
| C1 | هم‌راستایی مجوز با CRM (**بدون منبع دوم**) | ۲۰ | خطر واقعی: دو منبع مجوز = نشت داده |
| C2 | نسخه‌بندی + قفل + Audit قابل اثبات | ۱۵ | الزام P0 |
| C3 | WebDAV واقعی با قفل | ۱۲ | الزام قطعی F1 |
| C4 | سازگاری با cPanel | ۸ | تصحیح قطعی کارفرما |
| C5 | هزینهٔ اولیه + نگهداری | ۱۰ | تیم کوچک، بودجه محدود |
| C6 | بومی‌سازی RTL/فارسی/UI برند | ۸ | هویت محصول |
| C7 | امنیت (سطح حمله، وصله‌پذیری) | ۱۲ | داده سازمانی |
| C8 | قابلیت مهاجرت/عدم وابستگی Vendor | ۵ | پایدار بودن راهکار |
| C9 | آمادگی برای Online Office آینده | ۵ | F3 بدون بازنویسی |
| C10 | سرعت تحویل F1 | ۵ | تعهد زمانی |

### ۲.۲. گزینه A — ساخت کامل (Build)

همه چیز اختصاصی: درخت فایل، نسخه، قفل، مجوز **و سرور WebDAV** از صفر با PHP.

| معیار | امتیاز /۱۰ | توضیح |
|-------|:---:|-------|
| C1 مجوز | ۱۰ | یک منبع، دقیقاً مال ما |
| C2 نسخه/قفل/Audit | ۱۰ | کنترل کامل |
| C3 WebDAV | ۴ | **نوشتن سرور WebDAV از صفر** = ریسک بزرگ؛ RFC 4918 بیش از ۵۰ صفحه و پر از جزئیات قفل/If-header |
| C4 cPanel | ۱۰ | PHP خالص |
| C5 هزینه | ۷ | بدون لایسنس، ولی ساعت توسعهٔ زیاد |
| C6 UI | ۱۰ | کاملاً مال ما |
| C7 امنیت | ۶ | هر اشتباه ما، وصلهٔ ما |
| C8 مهاجرت | ۱۰ | بدون وابستگی |
| C9 Office | ۸ | OK |
| C10 سرعت | ۳ | کُندترین |
| **میانگین وزن‌دار** | **۷.۶** | رد فرض «سرعت» و «WebDAV» |

### ۲.۳. گزینه B — یکپارچه‌سازی محصول آماده (Integrate)

نصب Nextcloud (یا ownCloud/Seafile) و استفاده از CRM فقط برای لینک دادن.

| معیار | امتیاز /۱۰ | توضیح |
|-------|:---:|-------|
| C1 مجوز | **۲** | **Nextcloud کاربر/گروه خودش را دارد.** هر تغییر مجوز باید در دو جا اعمال شود. الزام FR-FILE-009 (عدم دور زدن مجوز CRM) به‌سختی قابل تضمین است. |
| C2 نسخه/قفل/Audit | ۹ | بالغ |
| C3 WebDAV | ۹ | کامل |
| C4 cPanel | ۴ | PHP + Redis + worker + DB دوم روی هاست اشتراکی، شکننده |
| C5 هزینه | ۵ | لایسنس آزاد، ولی بار نگهداری بالا |
| C6 UI | ۳ | UI خودش؛ برند VIRA و RTL واقعی نه |
| C7 امنیت | ۷ | بالغ ولی سطح حملهٔ بزرگ |
| C8 مهاجرت | **۳** | وابستگی سنگین |
| C9 Office | ۹ | با سرویس جدا |
| C10 سرعت | **۹** | سریع‌ترین راه به «کار می‌کند» |
| **میانگین وزن‌دار** | **۴.۶** | رد به‌دلیل C1 و C6 |

> **چرا با وجود سرعت بالا رد شد؟** چون معیار با وزن ۲۰ (C1) شکست می‌خورد. یک سیستم فایل که مجوزهایش با CRM یکی نیست، دقیقاً همان چیزی است که کارفرما گفته **نباید** باشد: «CRM مرجع اصلی کاربران، گروه‌ها، Roleها و مجوزها باقی می‌ماند.»

### ۲.۴. گزینه C — ترکیبی (Hybrid) ✅ **انتخاب‌شده**

- **لایهٔ تجربهٔ کاربری، مدل مجوز، متادیتا، نسخه، قفل و Audit:** اختصاصی، داخل CRM، باDB موجود.
- **لایهٔ پروتکل WebDAV:** کتابخانهٔ بالغ **sabre/dav** روی VPS، ولی **بدون مدل مجوز مستقل** — هر درخواست از CRM می‌پرسد.
- **لایهٔ بایت‌ها:** Object Storage سازگار S3 (SeaweedFS یا ارائه‌دهندهٔ S3) — قابل تعویض.

| معیار | امتیاز /۱۰ | توضیح |
|-------|:---:|-------|
| C1 مجوز | **۱۰** | تنها Resolver، در CRM است |
| C2 نسخه/قفل/Audit | ۱۰ | اختصاصی، دقیقاً الزامات |
| C3 WebDAV | ۹ | sabre/dav 4.7.1 بالغ و فعال |
| C4 cPanel | ۹ | CRM فقط PHP؛ WebDAV بیرون از هاست |
| C5 هزینه | ۸ | لایسنس صفر + یک VPS کوچک |
| C6 UI | ۱۰ | برند VIRA، RTL واقعی |
| C7 امنیت | ۸ | سطح حملهٔ کوچک‌تر از Nextcloud |
| C8 مهاجرت | ۸ | Storage قابل تعویض؛ WebDAV قابل جایگزینی |
| C9 Office | ۹ | Endpoint آماده برای WOPI |
| C10 سرعت | ۷ | بیشتر از A، کمتر از B |
| **میانگین وزن‌دار** | **۹.۱** | انتخاب شد |

---

## ۳. تصمیم معماری (ADR فشرده)

> **ADR-FILE-001:** مدل داده و منطق مجوز/نسخه/قفل/متادیتا **اختصاصی در CRM** ساخته می‌شود. پروتکل WebDAV با **sabre/dav 4.7.x** در **سرویس مستقل روی VPS** ارائه می‌شود که **هیچ جدول مجوزی ندارد** و هر عملیات را از API مجوز CRM تأیید می‌گیرد. بایت‌ها در **Object Storage سازگار S3** نگه داشته می‌شوند (نه MinIO).

**دلایل (به ترتیب وزن):**

1. **C1** — تنها این گزینه «یک منبع حقیقت مجوز» را تضمین می‌کند.
2. **C3** — نوشتن WebDAV از صفر (گزینه A) ریسک پذیرش پروتکل در Windows Explorer را بالا می‌برد؛ کتابخانه‌ای که هزاران نصب دارد، این ریسک را حذف می‌کند.
3. **C4** — هیچ بخشی از CRM به قابلیت غیرcPanel نیاز ندارد.

**چیزهایی که رد شدند:**

| رد‌شده | دلیل رد |
|--------|---------|
| Nextcloud/ownCloud به‌عنوان سیستم فایل | مدل مجوز موازی (C1) |
| Seafile CE | قفل/Audit/مجوز ریزدانه ندارد |
| SMB عمومی | ممنوعیت صریح کارفرما؛ خطر امنیتی |
| MinIO | بایگانی‌شده، بدون وصله |
| ONLYOFFICE/Collabora در F1 | تصمیم قطعی کارفرما |

---

## ۴. نمودارها

نمودارها به‌صورت SVG و PNG در `docs/diagrams/file-module/` موجودند:

| نمودار | فایل |
|--------|------|
| System Context | `01-system-context.{svg,png}` |
| Component | `02-component.{svg,png}` |
| Deployment | `03-deployment.{svg,png}` |
| WebDAV Auth Sequence | `04-webdav-auth-sequence.{svg,png}` |
| WebDAV File Operation | `05-webdav-file-operation.{svg,png}` |
| Permission Resolution | `06-permission-resolution.{svg,png}` |
| Upload Lifecycle | `07-upload-lifecycle.{svg,png}` |
| Version Lifecycle | `08-version-lifecycle.{svg,png}` |
| Locking Lifecycle | `09-locking-lifecycle.{svg,png}` |
| Backup / Restore | `10-backup-restore.{svg,png}` |

منبع قابل ویرایش هر نمودار فایل `.dot` کنار آن است. بازتولید: `node docs/tools/render-file-diagrams.mjs`.

---

## ۵. مرزهای ماژول (Modular Monolith)

```
app/Modules/Files/            ← تنها جای منطق فایل
├── Domain/
│   ├── Models/               file_nodes, file_versions, file_blobs, ...
│   ├── Authorization/        FileAuthorization, PermissionResolver
│   ├── Locking/              LockService
│   ├── Versioning/           VersionService
│   ├── Storage/              StorageContract + Local/S3 adapters
│   └── Quota/                QuotaService
├── Http/
│   ├── Controllers/          Browse, Upload, Download, Version, Share, Dav
│   ├── Requests/             فرم‌درخواست‌های معتبرساز
│   └── Resources/            خروجی JSON
├── Policies/                 FileNodePolicy, FileSharePolicy
├── Jobs/                     PurgeExpiredSessions, SweepStaleLocks, PurgeVersions
└── routes/files.php
```

**قواعد سختی که نباید شکسته شوند:**

| قاعده | چرا |
|-------|-----|
| هیچ Controller خارج از `Modules/Files` به `file_nodes` مستقیم دسترسی ندارد | جلوگیری از پراکندگی مجوز |
| هیچ کوئری فایل بدون `FileAuthorization` اجرا نمی‌شود | FR-PERM-011 |
| ماژول فایل به `Project`, `Contract`, `Customer` **فقط از طریق Policy** وابسته است | عدم دور زدن مجوز CRM |
| Storage از طریق interface است، نه `Storage::disk('local')` مستقیم | جابه‌جایی Backend |

---

## ۶. جریان داده‌ها (خلاصهٔ اجرایی)

### ۶.۱. مرورگر

```
Browser ──HTTPS──▶ Laravel (cPanel)
                    │
                    ├─ FileAuthorization::decide(user, node, cap)
                    ├─ Metadata ──▶ MySQL (file_*)
                    ├─ Bytes ────▶ object storage (presigned, TTL≤۵min)
                    └─ Audit ────▶ audit_logs
```

### ۶.۲. Windows Explorer (WebDAV)

```
Explorer ─HTTPS─▶ nginx(VPS) ─▶ sabre/dav ─▶ bridge ─HTTPS+HMAC─▶ CRM /api/dav/authorize
                    │                              │
                    │                              └─ deny? → 403/503 (fail-closed)
                    └─ bytes ─▶ object storage (S3 API، اعتبارنامهٔ محدود سرویس)
```

**هیچ‌گاه:** WebDAV → MySQL مستقیم. **هیچ‌گاه:** WebDAV → مسیر عمومی فایل.

---

## ۷. جریان احراز هویت و مجوز

| مسیر | احراز هویت | مجوزسنجی | ابطال |
|------|------------|----------|-------|
| مرورگر | Session + CSRF | `FileAuthorization` | فوری |
| دانلود موقت | URL امضاشده + تعلق به کاربر | همان + `revoked_at` | فوری (کش نسخه باطل) |
| WebDAV (Basic) | Basic روی TLS | Bridge → CRM | ≤۳۰ ثانیه (کش) |
| WebDAV (App-Token) | Bearer token | Bridge → CRM | فوری |
| API داخلی | HMAC + mTLS | همان | فوری |

---

## ۸. چرخهٔ حیات فایل، نسخه و قفل

- **فایل:** `active` → `trashed` → (`restored` | `purged`)
- **نسخه:** `created` → `current` / `superseded` → `retained` → `purged` (با Audit و dry-run)
- **قفل:** `free` → `locked` → (`released` | `expired` | `force_released`)
- **قفل کهنه:** cron هر ۵ دقیقه چک می‌کند و با Audit آزاد می‌کند.

جزئیات در `FILE_VERSIONING_AND_LOCKING.md`.

---

## ۹. API Contracts (قرارداد سطح بالا)

| مسیر | Method | ورودی | خروجی | مجوز لازم |
|------|--------|-------|-------|-----------|
| `/files` | GET | `workspace`, `folder`, `page` | لیست (فیلترشده) | `files.view` |
| `/files/folder` | POST | `parent_id`, `name` | node | Create Folder |
| `/files/{node}` | PATCH | `name`, `parent_id` | node | Rename/Move |
| `/files/upload/init` | POST | `node_id?`, `name`, `size`, `sha256` | `upload_id`, `chunk_size` | Upload |
| `/files/upload/{id}/chunk/{n}` | PUT | binary | `received` | Upload |
| `/files/upload/{id}/complete` | POST | `sha256` | version | Upload |
| `/files/{node}/download` | GET | `version?`, Range | stream | Download |
| `/files/{node}/preview` | GET | — | safe stream | View |
| `/files/{node}/versions` | GET | — | list | View Versions |
| `/files/{node}/versions/{v}/restore` | POST | `note` | version | Restore Version |
| `/files/{node}/lock` | POST/DELETE | `kind`, `timeout` | lock | Lock/Unlock |
| `/files/{node}/permissions` | GET/POST/DELETE | grant | — | Manage Permissions |
| `/files/{node}/activity` | GET | — | timeline | View Audit |
| `/api/dav/authorize` | POST | `{user, method, path, lock}` | `{allow, node, caps}` | سرویس‌به‌سرویس |
| `/api/dav/commit` | POST | `{node, sha, size, temp_key}` | `{version}` | سرویس‌به‌سرویس |

**قواعد مشترک:** همهٔ پاسخ‌های خطا بدون افشای وجود فایل؛ `404` و `403` یکسان از دید کاربر؛ هیچ شناسهٔ داخلی در URL عمومی.

---

## ۱۰. استقرار و هزینه (خلاصه)

| جزء | محل | حداقل | پیشنهادی |
|-----|-----|-------|----------|
| CRM | cPanel موجود | — | — |
| WebDAV | VPS | ۲ vCPU / ۲GB / ۲۰GB | ۴ vCPU / ۸GB / ۴۰GB |
| Object Storage | VPS جدا یا ارائه‌دهنده | ۵۰۰GB | ۲TB |
| Backup | فضای خارج | همان حجم فعال | ۲۰٪ رشد |

هزینهٔ دقیق → `FILE_STORAGE_CAPACITY_PLAN.md` (هیچ عددی حدس زده نشده؛ برحسب سناریو).

---

## ۱۱. سناریوهای خرابی

| خرابی | رفتار | اثر کاربر | بازیابی |
|-------|-------|-----------|---------|
| CRM down | WebDAV **fail-closed** | ۵۰۳ + Retry-After | خودکار پس از بازگشت CRM |
| VPS down | UI کار می‌کند، WebDAV نه | بنر وضعیت | Health check + Alert |
| Object Storage down | آپلود/دانلود خطا، متادیتا سالم | خطای صریح | بازگشت سرویس |
| DB down | همه چیز متوقف | ۵۰۳ | بازگردانی |
| تکه آپلود ناتمام | نشست منقضی، پاک‌سازی | دوباره‌آپلود | TTL |
| هش ناهمخوان | رد + یک تلاش مجدد | پیام روشن | Re-init |
| قفل کهنه | آزادسازی خودکار | ادامهٔ کار | Cron |

---

## ۱۲. آنچه هنوز اثبات نشده

| مورد | وضعیت |
|------|-------|
| کارکرد sabre/dav روی VPS واقعی با Windows | `NOT TESTED` |
| کارایی با ۲۵/۵۰ کاربر | `NOT TESTED` |
| رفتار cPanel با آپلود بزرگ | `BLOCKED` (دسترسی به هاست نیست) |
| هزینهٔ واقعی VPS برای کارفرما | `NEEDS APPROVAL` |
| هویت دامنهٔ `dav.*` | `NEEDS APPROVAL` (OD-103) |
