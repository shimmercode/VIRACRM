# F0 — گزارش نهایی امکان‌سنجی و معماری Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **شاخه:** `arena/3b43de84-viracrm`
**دامنه:** F0 (Feasibility & Architecture) — **هیچ کد Production تغییر نکرد؛ هیچ Migration نوشته نشد؛ دیتابیس دست نخورد.**

> **قاعدهٔ خواندن این گزارش:** هر جملهٔ فنی یک برچسب دارد:
> `VERIFIED` (اجرا/مستند رسمی) · `PROPOSED` (طراحی‌شده) · `BLOCKED` (پیش‌نیاز نیست) · `NOT TESTED` (اجرا نشده) · `NEEDS APPROVAL` (تصمیم کارفرما).

---

## A — خلاصهٔ مدیریتی

**معماری پیشنهادی:** ترکیبی (Hybrid). مدل داده/مجوز/نسخه/قفل/UI اختصاصی در CRM؛ WebDAV با **sabre/dav 4.7.1** روی **VPS مستقل**؛ بایت‌ها روی **Object Storage سازگار S3**. `PROPOSED` · `NEEDS APPROVAL` (OD-101)

**چرا نه Nextcloud؟** چون کاربر/گروه خودش را دارد. اگر مجوز در دو جا باشد، دیر یا زود نشت می‌کند. `VERIFIED` (بررسی محصول) + `PROPOSED` (ارزیابی ریسک)

**خبر خوب:** یک تصحیح مهم نسبت به فرض قبلی پروژه — **Laravel 12 روی دیسک `local` با `serve=true` از `temporaryUrl()` پشتیبانی می‌کند.** در همین مخزن اجرا شد و لینک امضاشده تولید کرد. `VERIFIED`

**خبر بد:** همان مسیر **Range Request نمی‌دهد** و Content-Type را از **محتوا** تعیین می‌کند، نه پسوند. `VERIFIED` → مسیر دانلود اختصاصی الزامی است.

**خبر تلخ:** Internet Explorer... نه، ویندوز: **سرویس WebClient (WebDAV) از نوامبر ۲۰۲۳ Deprecated است** و به‌صورت پیش‌فرض اجرا نمی‌شود. سقف ۵۰MB پیش‌فرض و **۴GB سقف مطلق** دارد. `VERIFIED` (Microsoft Learn) → «درایو شبکه‌ای بی‌دردسر» وجود ندارد.

| # | نتیجهٔ کلیدی | وضعیت |
|---|---------------|-------|
| 1 | Hybrid انتخاب شد (امتیاز ۹.۱/۱۰ در برابر A=۷.۶ و B=۴.۶) | `PROPOSED` |
| 2 | sabre/dav مناسب و فعال است (BSD-3، 4.7.1) | `VERIFIED` |
| 3 | MinIO به‌عنوان Storage **رد** شد (بایگانی‌شده) | `VERIFIED` |
| 4 | Seafile CE **رد** شد (قفل/Audit/مجوز ندارد) | `VERIFIED` |
| 5 | Laravel `temporaryUrl` روی local کار می‌کند | `VERIFIED` (اجرا) |
| 6 | Laravel `storage.local` Range ندارد → Controller لازم | `VERIFIED` (اجرا) |
| 7 | CRM **گروه و دپارتمان ندارد** → تصمیم سازمانی لازم | `VERIFIED` (مخزن) |
| 8 | ۹۹ نیازمندی، ۳۰ تصمیم باز، ۲۴۰ تست — همه `NOT TESTED` | `PROPOSED` |
| 9 | ۰ ادعای سازگاری ویندوز | `NOT TESTED` |
| 10 | ۰ ادعای Production-Ready | `NOT TESTED` |

---

## B — معماری پیشنهادی نهایی

```
┌─────────────────── cPanel (CRM · control plane) ────────────────────┐
│  Browser ──▶ Laravel 12 ──▶ FileAuthorization (تنها Resolver)       │
│                    │                                                  │
│                    ├──▶ MySQL: file_* + audit_logs                    │
│                    ├──▶ Object Storage (presigned، TTL ≤۵ دقیقه)      │
│                    └──▶ API /api/dav/* (HMAC + mTLS)                  │
└────────────────────────────────┬─────────────────────────────────────┘
                                 │ HTTPS (fail-closed)
┌────────────────────────────────▼─────────────────────────────────────┐
│  VPS (data plane): nginx + PHP-FPM + sabre/dav 4.7 ──▶ S3 API        │
│  Explorer ──HTTPS──▶ PROPFIND/GET/PUT/LOCK ──▶ bridge ──▶ CRM       │
└───────────────────────────────────────────────────────────────────────┘
```

جزئیات: `FILE_STORAGE_ARCHITECTURE.md` · نمودارها: `docs/diagrams/file-module/01…03`

---

## C — مقایسهٔ Build / Integrate / Hybrid

| معیار (وزن) | A: Build | B: Integrate | C: Hybrid |
|--------------|:--------:|:------------:|:---------:|
| مجوز یکپارچه با CRM (۲۰) | ۱۰ | **۲** | **۱۰** |
| نسخه/قفل/Audit (۱۵) | ۱۰ | ۹ | ۱۰ |
| WebDAV واقعی (۱۲) | ۴ | ۹ | ۹ |
| سازگاری cPanel (۸) | ۱۰ | ۴ | ۹ |
| هزینه (۱۰) | ۷ | ۵ | ۸ |
| UI/برند/RTL (۸) | ۱۰ | ۳ | ۱۰ |
| امنیت (۱۲) | ۶ | ۷ | ۸ |
| عدم وابستگی (۵) | ۱۰ | ۳ | ۸ |
| آمادگی Office (۵) | ۸ | ۹ | ۹ |
| سرعت تحویل (۵) | ۳ | **۹** | ۷ |
| **میانگین وزن‌دار** | **۷.۶** | **۴.۶** | **۹.۱** |

**نکتهٔ مهم:** B سریع‌ترین است و به همین دلیل وسوسه‌انگیز — ولی در سنگین‌ترین معیار (۲۰) شکست می‌خورد. `PROPOSED`

جزئیات: `FILE_STORAGE_ARCHITECTURE.md` §2

---

## D — انتخاب WebDAV Server و دلیل

| گزینه | مجوز | قفل | فعال | نتیجه |
|-------|------|-----|:----:|-------|
| **sabre/dav 4.7.1** | BSD-3 | ✅ PDO | ✅ | **انتخاب** |
| Apache mod_dav | Apache-2.0 | محدود | ✅ | رد |
| Nextcloud (فقط WebDAV) | AGPL-3 | ✅ | ✅ | رد (بار عملیاتی) |
| rclone serve webdav | MIT | ❌ | ✅ | رد (مجوز ریزدانه ندارد) |
| nginx dav module | BSD | ❌ | ❌ حذف‌شده | رد |

**دلیل:** RFC 4918 «Supported» · قفل ماندگار با PDO · مجوز تجاری‌دوست · PHP خالص (همان مهارت تیم) · فعال (push 2026-11-01). `VERIFIED`

---

## E — نحوهٔ اتصال امن WebDAV به CRM

| کنترل | مقدار | وضعیت |
|-------|-------|-------|
| پروتکل | HTTPS 443 فقط، TLS 1.2+ | `PROPOSED` |
| احراز هویت سرویس | HMAC-SHA256 + timestamp ±۶۰s + nonce | `PROPOSED` |
| mTLS | گواهی کلاینت VPS روی CRM | `PROPOSED` |
| Allowlist | `/api/dav/*` فقط از IP ثابت VPS | `PROPOSED` |
| Rate limit | ۶۰۰/دقیقه | `PROPOSED` |
| Timeout | ۲ ثانیه | `PROPOSED` |
| Circuit breaker | ۲۰ خطای متوالی → ۵۰۳ | `PROPOSED` |
| پورت DB | **هرگز** از اینترنت | `PROPOSED` |

قرارداد کامل: `WEBDAV_CRM_AUTHORIZATION_BRIDGE.md`

---

## F — نحوهٔ Permission Enforcement

```
decide(user, node, capability):
  0  کاربر فعال؟                    → Deny
  1  دروازهٔ ماژول CRM (Policy)     → Deny   ← مهم‌ترین
  2  restricted بدون Grant صریح     → Deny
  3  زنجیرهٔ والد → جمع Grantها
  4  inherit=0 → قطع والد
  5  Deny مقدم بر Allow → پیش‌فرض Deny
  6  وابستگی قابلیت
  7  قفل دیگران → Deny نوشتن
```

| Method WebDAV | مجوز |
|----------------|------|
| PROPFIND | `view` (per-item) |
| GET | `download` |
| PUT | `upload` / `edit` |
| MKCOL | `create_folder` |
| MOVE | `move` مبدأ + `upload` مقصد |
| DELETE | `delete` |
| LOCK/UNLOCK | `lock` / `unlock` |

`PROPOSED` · جزئیات: `FILE_PERMISSION_MATRIX.md` §4

---

## G — سیاست Fail-Closed

| حالت | پاسخ |
|------|------|
| CRM down / timeout | `503` + `Retry-After` |
| HMAC نامعتبر | `503` |
| پاسخ نامعتبر | `503` |
| کاربر غیرفعال | `401` |
| Token منقضی | `401` |
| وضعیت فایل نامشخص | `503` |

**قاعدهٔ کد:** `authorize()` فقط `Allow` یا استثنا برمی‌گرداند. هیچ `catch → true` مجاز نیست. `PROPOSED`

---

## H — سازگاری Windows و محدودیت‌ها

| مورد | واقعیت | وضعیت |
|------|--------|-------|
| سرویس WebClient | **Deprecated (Nov 2023)**، پیش‌فرض اجرا نمی‌شود | `VERIFIED` |
| `FileSizeLimitInBytes` | 50,000,000 (۵۰MB) پیش‌فرض | `VERIFIED` |
| سقف مطلق | `4,294,967,295` ≈ ۴GB — **قابل افزایش نیست** | `VERIFIED` |
| `FileAttributesLimitInBytes` | 1,000,000 (~۱۰۰۰ فایل در پوشه) | `VERIFIED` |
| `BasicAuthLevel` | ۱ = Basic فقط روی SSL | `VERIFIED` |
| `SupportLocking` | ۱ | `VERIFIED` |
| تست ویندوز واقعی | **۰ از ۳۰ ردیف اجرا شده** | `NOT TESTED` |

**نتیجه:** ما **هیچ ادعای سازگاری کامل نمی‌کنیم**. تا تست واقعی، جملهٔ مجاز این است: «پیاده‌سازی بر پایهٔ استاندارد RFC 4918 انجام شده؛ آزمون ویندوز در انتظار است.» `PROPOSED`

جزئیات + ۳۰ تست: `WINDOWS_WEBDAV_COMPATIBILITY.md`

---

## I — Storage Strategy

| گزینه | نتیجه |
|-------|-------|
| **Object Storage سازگار S3** | **انتخاب** — مقیاس، جداسازی، بکاپ |
| Private Local (cPanel) | رد برای F1؛ مناسب پایلوت |
| NAS | رد — cPanel قابلیت mount پایدار ندارد |
| Dedicated File Server | رد — هزینه/پیچیدگی |
| **MinIO** | **رد** — CE بایگانی‌شده `VERIFIED` |
| SeaweedFS | جایگزین پیشنهادی (Apache-2.0) `PROPOSED` |

**قواعد سخت:** خارج از public · بدون URL عمومی · دسترسی فقط از طریق authorization · Storage قابل تعویض.

---

## J — ERD

۱۷ جدول اصلی (حداکثر ۱۹ با دپارتمان/گروه):

`file_workspaces` · `file_nodes` · `file_versions` · `file_blobs` · `file_permissions` · `file_permission_defs` · `file_groups` · `file_group_members` · `file_shares` · `file_locks` · `file_activity` · `file_comments` · `file_tags` · `file_node_tags` · `file_relations` · `file_quotas` · `file_storage_locations` · `file_retention_policies` · `file_upload_sessions` · `file_access_tokens`

**اصول:** مالکیت سازمانی · نسخه تغییرناپذیر · هش = هویت بایت · حذف غیرمخرب · Migration بازگشت‌پذیر · **بدون تغییر جداول موجود**.

`PROPOSED` · جزئیات کامل با ستون‌ها و ایندکس‌ها: `FILE_WORKSPACE_ERD.md`

---

## K — Permission Matrix

۱۸ قابلیت × ۸ دامنه × ۴ طبقه‌بندی × ۱۰ ناحیه.

**تصمیم‌های نرماتیو:**
- Deny مقدم بر Allow
- Default-deny
- Break-inheritance = قطع والد، نه حذف آن
- `restricted` هرگز از مسیر گروهی
- ۶ سناریوی ضد تشدید امتیاز

**یافتهٔ ساختاری:** CRM **گروه و دپارتمان ندارد** (`VERIFIED` از migration). `NEEDS APPROVAL` (OD-104/105)

جزئیات + ۶ مثال کارشده + ۱۰ حالت مرزی: `FILE_PERMISSION_MATRIX.md`

---

## L — Versioning و Locking Design

| موضوع | طراحی |
|-------|-------|
| نسخه | append-only؛ شماره یکتا؛ Restore = نسخهٔ جدید |
| Dedup | `sha256` یکتا؛ نسخه‌های متعدد یک blob |
| Lost Update | `lockForUpdate` + `UNIQUE(node_id, active)` + `If-Match` + `expected_version` |
| قفل | یک جدول برای UI و WebDAV (نه دو) |
| TTL | ۱۰ دقیقه (DAV) / ۳۰ (UI)؛ force-unlock با Audit |
| Retention | `dry_run_only=true` پیش‌فرض؛ `legal_hold` مطلق |

`PROPOSED` · ۱۴ سناریوی قفل + ۱۲ تست: `FILE_VERSIONING_AND_LOCKING.md`

---

## M — Security Threat Model

**۲۹ تهدید** در ۵ دسته، هر کدام با سناریو/اثر/احتمال/کنترل/تست/ریسک باقی‌مانده.

**۶ یافتهٔ واقعی همین پروژه:**
1. `temporaryUrl` روی local **قابل ابطال نیست** (فقط exp+signature).
2. امضای نامعتبر → ۳۰۲ به login (امن، ولی UX گمراه‌کننده).
3. بدنه ۰ بایت در php-wasm (اثر محیط، نه باگ).
4. Content-Type از محتوا → `photo.png` با HTML = `text/html`.
5. CSP `sandbox` جلوی XSS را می‌گیرد، ولی `inline` است.
6. Flysystem `..` را رد می‌کند، ولی IDOR را **نه**.

**۸ ریسک پذیرفته‌شده** (به‌ویژه: بدون اسکن بدافزار، کلاینت ویندوز همیشه `If-Match` نمی‌فرستد).

`PROPOSED` · جزئیات: `FILE_SECURITY_THREAT_MODEL.md`

---

## N — VPS Requirements و هزینهٔ تخمینی

| منبع | حداقل | پیشنهادی | ۵۰ کاربر |
|------|-------|----------|----------|
| vCPU | ۲ | ۴ | ۸ |
| RAM | ۲GB | ۸GB | ۱۶GB |
| Disk | ۲۰GB | ۴۰GB | ۸۰GB |
| Bandwidth | ۱TB | ۴TB | ۱۰TB |
| Storage | ۵۰۰GB | ۲TB | ۵TB |

**هیچ قیمتی در این گزارش ثبت نشده** — دستور می‌گوید قیمت حدس زده نشود. ساختار هزینه + روش استعلام در `FILE_STORAGE_CAPACITY_PLAN.md` §7. `NEEDS APPROVAL` (OD-119)

**ظرفیت سال اول (برآورد با فرض صریح):** ≈۶۵۰GB برای ۱۵ کارمند. `NOT TESTED`

---

## O — Backup و Recovery

| لایه | چیست | RPO | RTO |
|------|------|-----|-----|
| L1 | Snapshot + binlog | دقیقه | دقیقه |
| L2 | Dump روزانه + manifest | ۲۴h | ۴h |
| L3 | آفلاین هفتگی | هفته | ۷۲h |

**قطعهٔ کلیدی:** **manifest** — بدون آن، بازیابی یک امید است. **الزام:** ۱۰ آزمون بازیابی (R1…R8) تا سیستم «بکاپ‌دار» اعلام نشود.

**وضعیت امروز: صفر کد بکاپ.** `PROPOSED` · `NEEDS APPROVAL` (OD-131)

---

## P — Test Plan

| گروه | تعداد | اجراشده |
|------|------:|--------:|
| T-FILE | ۳۴ | ۰ |
| T-PERM | ۳۰ | ۰ |
| T-VER | ۱۲ | ۰ |
| T-LOCK | ۱۴ | ۰ |
| T-DAV | ۳۴ | ۰ |
| T-SEC | ۲۸ | ۰ |
| T-UP | ۱۶ | ۰ |
| T-BAK | ۱۰ | ۰ |
| T-PERF | ۱۲ | ۰ |
| T-UI | ۲۰ | ۰ |
| T-WIN | ۳۰ | ۰ |
| **جمع** | **۲۴۰** | **۰** |

**۸ بلوکه‌کننده** ثبت شده. **۰ تست Skip به‌عنوان Pass.**

`PROPOSED` · جزئیات: `FILE_WORKSPACE_TEST_PLAN.md`

---

## Q — Acceptance Criteria

۱۰ حوزه · ۷۲ معیار · نقشهٔ ۱۶ صفحهٔ UI با کد S01…S16.

**سیاست:** `Proposed → Approved → Implemented → Unit Tested → Integration Tested → Environment Tested → Accepted`. هیچ جهشی مجاز نیست.

`PROPOSED` · جدول کامل: `FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md`

---

## R — Risk Register

| # | ریسک | احتمال | اثر | کاهش | صاحب |
|---|------|:-------:|-----|------|------|
| R1 | Windows WebClient Deprecated → حذف در آینده | M | H | مسیر جایگزین کلاینت سوم‌شخص؛ مستندسازی | کارفرما |
| R2 | سقف ۵۰MB/۴GB ویندوز | H | M | Registry + راهنما + آپلود مرورگر | کارفرما |
| R3 | سازمان بدون دپارتمان/گروه | H | H | OD-104/105 | کارفرما |
| R4 | بدون اسکن بدافزار | M | H | OD-127؛ پذیرش مکتوب | کارفرما |
| R5 | cPanel محدودیت ناشناخته | H | M | سنجش هاست پیش از F1 | فنی |
| R6 | بدون `composer.lock` | M | M | افزودن lock | فنی |
| R7 | کارایی WebDAV اندازه‌گیری نشده | H | M | E2 + k6 | فنی |
| R8 | بدون کد بکاپ | H | H | F1 الزامی | فنی |
| R9 | هزینهٔ VPS/Storage نامشخص | H | M | استعلام ۳+۱ | کارفرما |
| R10 | دو سیستم = پیچیدگی عملیاتی | M | M | Runbook + مانیتورینگ | فنی |

---

## S — Open Decisions

**۳۰ تصمیم** در ۵ گروه · **۱۳ مسدودکنندهٔ F1**.

**مسدودکننده‌های اصلی:** OD-101 (معماری) · OD-102 (Storage) · OD-104/105 (دپارتمان/گروه) · OD-106 (ماتریس) · OD-110 (attachments) · OD-113 (قفل) · OD-120 (Basic/Token) · OD-121/122 (mTLS/Secret) · OD-127 (اسکن) · OD-128 (رشد) · OD-131 (RPO/RTO) · OD-135 (lock)

`NEEDS APPROVAL` · جدول کامل: `FILE_WORKSPACE_OPEN_DECISIONS.md`

**بازهٔ `OD-01…47` دست نخورد** (تعارض شماره‌گذاری تکرار نمی‌شود).

---

## T — برنامهٔ اجرایی F1

| گام | کار | پیش‌نیاز | تخمین |
|-----|-----|----------|-------|
| 1 | تأیید ۳۰ تصمیم | کارفرما | — |
| 2 | تأیید معماری (ADR) | ۱ | — |
| 3 | Migration روی بکاپ | ۲ | ۲ روز |
| 4 | مدل + Storage contract | ۳ | ۳ روز |
| 5 | Resolver + تست | ۴ | ۵ روز |
| 6 | آپلود تکه‌ای | ۴ | ۴ روز |
| 7 | دانلود + preview | ۴ | ۳ روز |
| 8 | نسخه + قفل | ۵،۶ | ۴ روز |
| 9 | UI Explorer | ۶،۷ | ۸ روز |
| 10 | اتصال CRM | ۵ | ۲ روز |
| 11 | WebDAV روی VPS | ۵ | ۶ روز |
| 12 | پل مجوز | ۱۱ | ۵ روز |
| 13 | بکاپ و بازیابی | ۳ | ۴ روز |
| 14 | تست ویندوز | ۱۱،۱۲ | ۲ روز |
| 15 | تست کارایی | ۱۱ | ۲ روز |
| **جمع** | — | — | **~۵۰ روز مهندسی** |

**هشدار:** این تخمین است، نه تعهد. `NEEDS APPROVAL` (OD-130)

`PROPOSED` · جزئیات: `FILE_WORKSPACE_DEPLOYMENT.md` بخش د

---

## ضمیمه ۱ — چه چیزهایی **اثبات شده**؟ `VERIFIED`

| # | ادعا | شاهد |
|---|------|------|
| 1 | Laravel 12 روی `local` با `serve=true` `temporaryUrl()` می‌دهد | اجرا: URL با `expires` + `signature` |
| 2 | امضای نامعتبر رد می‌شود | curl: ۳۰۲ به login، بدون داده |
| 3 | `storage.local` **Range** نمی‌دهد | curl: ۲۰۰ بدون `Content-Range` |
| 4 | Content-Type از محتوا (نه پسوند) | `photo.png` با HTML → `text/html` |
| 5 | CSP `sandbox` روی مسیر storage | هدر پاسخ |
| 6 | Flysystem `..` را مسدود می‌کند | ۳ الگوی حمله رد شد |
| 7 | مسیر مطلق بیرون root نمی‌رود | `realpath` بررسی شد |
| 8 | SHA-256 جریانی ۱۰۷MB/s در ۲۰MB | benchmark |
| 9 | sabre/dav 4.7.1 · BSD-3 · فعال | GitHub API |
| 10 | sabre/dav قفل PDO ماندگار دارد | مستند رسمی |
| 11 | WebClient Deprecated (Nov 2023) | Microsoft Learn |
| 12 | ۵۰MB / ۴GB / ~۱۰۰۰ فایل | مستندات ماکروسافت/مرجع |
| 13 | MinIO CE بایگانی‌شده | مخزن رسمی |
| 14 | Seafile CE قفل/Audit ندارد | مقایسهٔ رسمی |
| 15 | ONLYOFFICE CE سقف ۲۰ | مستندات |
| 16 | CRM گروه/دپارتمان ندارد | migration |
| 17 | `attachments` موجود با sha256 | migration |

## ضمیمه ۲ — چه چیزهایی فقط **طراحی** شده؟ `PROPOSED`

- کل مدل داده (۱۷ جدول)
- الگوریتم حل مجوز
- قرارداد پل CRM↔WebDAV
- طراحی قفل و نسخه
- ۲۴۰ تست (نوشته شده، اجرا نشده)
- Threat Model
- استقرار

## ضمیمه ۳ — چه چیزهایی **تست نشده**؟ `NOT TESTED`

- هر ادعای ویندوز (۳۰ تست)
- هر ادعای کارایی (۱۲ سناریو)
- رفتار cPanel با آپلود بزرگ
- رفتار sabre/dav با کلاینت واقعی
- بازیابی بکاپ
- کارکرد لینک WebDAV با CRM

## ضمیمه ۴ — چه چیزهایی به **VPS/Windows واقعی** نیاز دارند؟ `BLOCKED`

- ۳۴ تست WebDAV
- ۳۰ تست ویندوز
- ۱۲ تست کارایی
- بخشی از تست‌های مجوز/قفل (که از WebDAV عبور می‌کنند)
- **جمع: ~۷۶ تست**

## ضمیمه ۵ — چه چیزهایی به **تصمیم کارفرما** نیاز دارند؟ `NEEDS APPROVAL`

۱۳ تصمیم مسدودکننده (OD-101، ۱۰۲، ۱۰۴، ۱۰۵، ۱۰۶، ۱۱۰، ۱۱۳، ۱۲۰، ۱۲۱، ۱۲۲، ۱۲۷، ۱۲۸، ۱۳۱، ۱۳۵).

## ضمیمه ۶ — ریسک‌های **باز**

R1…R10 در بخش R.

---

## ضمیمه ۷ — فهرست تحویل‌شدنی‌ها

| # | سند | وضعیت |
|---|-----|-------|
| 1 | `FILE_COLLABORATION_RESEARCH.md` | ✅ |
| 2 | `FILE_STORAGE_ARCHITECTURE.md` | ✅ |
| 3 | `FILE_WORKSPACE_REQUIREMENTS.md` | ✅ |
| 4 | `FILE_PERMISSION_MATRIX.md` | ✅ |
| 5 | `FILE_WORKSPACE_ERD.md` | ✅ |
| 6 | `FILE_VERSIONING_AND_LOCKING.md` | ✅ |
| 7 | `WINDOWS_WEBDAV_COMPATIBILITY.md` | ✅ |
| 8 | `WEBDAV_VPS_ARCHITECTURE.md` | ✅ |
| 9 | `WEBDAV_CRM_AUTHORIZATION_BRIDGE.md` | ✅ |
| 10 | `ONLINE_OFFICE_INTEGRATION.md` | ✅ |
| 11 | `FILE_SECURITY_THREAT_MODEL.md` | ✅ |
| 12 | `FILE_STORAGE_CAPACITY_PLAN.md` | ✅ |
| 13 | `FILE_WORKSPACE_TEST_PLAN.md` | ✅ |
| 14 | `FILE_WORKSPACE_DEPLOYMENT.md` | ✅ |
| 15 | `FILE_BACKUP_AND_RECOVERY.md` | ✅ |
| 16 | `FILE_WORKSPACE_OPEN_DECISIONS.md` | ✅ |
| 17 | `FILE_WORKSPACE_ACCEPTANCE_CRITERIA.md` | ✅ |
| 18 | `FILE_WORKSPACE_TRACEABILITY.md` | ✅ (تولید خودکار) |
| + | `adr/ADR-FILE-001-…md` | ✅ |
| + | ۱۰ نمودار SVG/PNG | ✅ |

---

## ضمیمه ۸ — آنچه انجام **نشد** (و چرا)

| ❌ | چرا |
|----|-----|
| هیچ Migration | دستور §25 — نیازمند تأیید |
| هیچ کد Production | F0 = مستندات |
| هیچ تغییر دیتابیس | دستور §26 |
| هیچ تست ویندوز | محیط E3 نیست |
| هیچ تست WebDAV | محیط E2 نیست |
| هیچ نصب ONLYOFFICE | تصمیم قطعی F3 |
| هیچ قیمت | ممنوعیت حدس |
| هیچ ادعای «امن» | روش‌شناسی |

---

## ضمیمه ۹ — حکم نهایی

معماری **قابل دفاع، قابل اجرا و قابل اثبات** است — **به شرط ۱۳ تصمیم کارفرما و راه‌اندازی محیط E2/E3**.

اگر این تصمیم‌ها گرفته شوند، F1 در ~۵۰ روز مهندسی قابل شروع است.
اگر گرفته نشوند، هیچ کدی نوشته نمی‌شود — و این درست است.
