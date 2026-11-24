# WEBDAV_VPS_ARCHITECTURE — معماری سرویس WebDAV روی VPS مستقل

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 (طراحی) / F1 (استقرار) · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> **تصمیم قطعی کارفرما (§2.2):** WebDAV روی **VPS مستقل** اجرا می‌شود، نه داخل Laravel روی cPanel. CRM مرجع مجوز می‌ماند. Fail-Closed. هیچ SMB عمومی.

---

## ۱. انتخاب نرم‌افزار WebDAV

### ۱.۱. گزینه‌های بررسی‌شده

| گزینه | مجوز | قفل واقعی | مدل مجوز خارجی | فعال؟ | نتیجه |
|-------|------|:---------:|:--------------:|:-----:|-------|
| **sabre/dav 4.7.1** | BSD-3-Clause | ✅ PDO backend (MySQL/SQLite) | ✅ `Auth\BackendInterface` سفارشی | ✅ push 2026-11 | **انتخاب شد** |
| Apache mod_dav | Apache-2.0 | ⚠️ فقط روی فایل سیستم محلی، نه object | ❌ | ✅ | رد — گره‌خوردگی به دیسک محلی، سخت‌سازی محدود |
| Nextcloud (فقط WebDAV) | AGPL-3.0 | ✅ | ❌ | ✅ | رد — برای WebDAV تنها، بار عملیاتی بی‌توجیه |
| rclone serve webdav | MIT | ❌ | ❌ (یک کاربر/رمز ثابت) | ✅ | رد — مجوز ریزدانه ندارد |
| nginx dav module | BSD | ❌ | ❌ | ✅ | رد — ماژول رسمی nginx از ۲۰۱۶ حذف شده |
| ساختن از صفر در PHP | — | دستی | ✅ | — | رد — ریسک پذیرش پروتکل |

### ۱.۲. چرا sabre/dav

| معیار | شاهد |
|-------|-------|
| استاندارد | RFC 4918 «Supported» + RFC 3744 (ACL) + RFC 4331 (Quota) + RFC 5397 |
| قفل | `Sabre\DAV\Locks\Plugin` با `...\Backend\PDO` — **ماندگار، نه در حافظه** |
| درگاه مجوز | پیاده‌سازی `Sabre\DAV\Auth\Backend\AbstractBasic` و/یا پلاگین `beforeMethod:*` |
| فعال بودن | آخرین انتشار 4.7.1 (۲۰۲۶-۰۷-۰۷)، آخرین push ۲۰۲۶-۱۱-۰۱ |
| مجوز | BSD-3-Clause — سازگار با محصول تجاری |
| وابستگی | **PHP خالص** — همان مهارت تیم، همان ابزار استقرار |

**ریسکی که پذیرفته شد:** sabre/dav یک «محصول» نیست، یک **کتابخانه** است؛ پیکربندی و سخت‌سازی روی ماست. این در Threat Model و Deployment پوشش داده شده.

---

## ۲. Component Diagram (متن)

```
┌─────────────────────────── VPS (Zone B) ───────────────────────────┐
│  nginx (TLS 1.2/1.3, HSTS, client_max_body_size, rate-limit)       │
│      │                                                             │
│      ▼                                                             │
│  PHP-FPM pool "dav" (dedicated user, open_basedir)                 │
│      │                                                             │
│      ├─▶ Sabre\DAV\Server                                          │
│      │      ├─ Auth\Plugin  ──▶ CrmAuthBackend ──┐                 │
│      │      ├─ Locks\Plugin ──▶ PDO (MySQL)      │                 │
│      │      ├─ Quota\Plugin                      │                 │
│      │      └─ Filesystem (custom tree)          │                 │
│      │                    │                      │                 │
│      └─▶ BridgeClient ────┼──────────────────────┼─▶ HTTPS ─▶ CRM   │
│                           │                      │   /api/dav/*    │
│                           ▼                      │                 │
│                  Object Storage (S3 API) ◀───────┘                 │
│                  credentials restricted to bucket/prefix           │
└────────────────────────────────────────────────────────────────────┘
```

> نمودار رسمی: `docs/diagrams/file-module/02-component.{svg,png}` و `03-deployment.{svg,png}` و `04-webdav-auth-sequence.{svg,png}`

---

## ۳. اجزای نرم‌افزاری (پیشنهاد)

| لایه | انتخاب | چرا |
|------|--------|-----|
| OS | Ubuntu Server LTS | وصله‌های امنیتی پیش‌بینی‌پذیر، مستندات فراوان |
| وب‌سرور | nginx | TLS ساده، rate-limit بومی |
| Runtime | PHP 8.2/8.3-FPM | همان نسخهٔ CRM → یک زبان در کل پروژه |
| کتابخانه | `sabre/dav ^4.7` | §1 |
| قفل | MySQL/MariaDB محلی VPS **یا** CRM — OD-113 | §6 |
| Storage | S3-compatible | §7 |
| Monitoring | journald + health endpoint + ۲ probe خارجی | §9 |
| Backup | snapshot VPS + replication اشیاء | `FILE_BACKUP_AND_RECOVERY.md` |

**تصمیم آگاهانه:** Node.js روی VPS **لازم نیست** — همان قاعدهٔ «بدون Node در Production» به‌طور یکنواخت رعایت می‌شود.

---

## ۴. درگاه احراز هویت (Design)

### ۴.۱. جریان اعتبارسنجی

```
1. کلاینت → nginx → PHP-FPM → Sabre\DAV\Auth\Plugin
2. Backend سفارشی: CrmAuthBackend::validateUserPass($user, $pass)
      ├─ cache hit (key = hash(user|pass|perm_version)) → OK
      └─ miss → POST https://crm/api/dav/verify  {user, pass_hash}
                ├─ 200 {ok, perm_version, user_id}
                └─ 401 → AuthFailure
3. Sabre\DAV\Auth\Plugin → principal = "principals/users/{id}"
4. beforeMethod — PermissionGuard:
      → POST /api/dav/authorize {user_id, method, path, lock_token, perm_version}
      → 200 {allow, node_id, caps[], quota}  | 403 | 503
```

### ۴.۲. همهٔ Methodها بررسی می‌شوند

| Method | مجوز CRM | توضیح |
|--------|----------|-------|
| `PROPFIND` | `view` | **هر** آیتم در پاسخ، جدا بررسی می‌شود |
| `PROPPATCH` | `edit` | فقط فیلدهای مجاز (displayname) |
| `GET` | `download` | — |
| `HEAD` | `view` | — |
| `PUT` | `upload` یا `edit` | قفل + `If-Match` |
| `MKCOL` | `create_folder` | قفل پوشهٔ والد |
| `MOVE` | `move` در مبدأ **و** مقصد | — |
| `COPY` | `copy` در مبدأ، `upload` در مقصد | — |
| `DELETE` | `delete` | به Trash، نه حذف فوری |
| `LOCK` | `lock` | — |
| `UNLOCK` | `unlock` | صاحب قفل یا ادمین |
| `OPTIONS` | هیچ (فقط نشان می‌دهد DAV هست) | پاسخ بدون داده |

### ۴.۳. `PROPFIND` — سخت‌ترین مورد

`PROPFIND` با `Depth: 1` می‌تواند صدها آیتم برگرداند. **یک درخواست authorize برای هر آیتم** هزینهٔ شبکه‌ای دارد.

**راه‌حل:** یک درخواست `authorize-batch` که لیست `node_ids` می‌گیرد و **آرایهٔ مجازها** برمی‌گرداند:

```json
POST /api/dav/authorize-batch
{ "user_id": 12, "perm_version": 7, "nodes": ["a1b2…", "c3d4…", "…"] }
→ 200 { "allowed": ["a1b2…"], "denied": ["c3d4…"], "truncated": false }
```

| محدودیت | مقدار |
|---------|-------|
| حداکثر node در یک batch | ۳۰۰ |
| فراتر از آن | سرور باطل‌های مجاز را کوتاه می‌کند (`truncated: true`) و نتیجه را **deny** می‌گذارد |
| هزینه | ۱ درخواست به‌جای N |

---

## ۵. Fail-Closed — طراحی صریح

| حالت | پاسخ سرویس WebDAV | کد HTTP |
|------|-------------------|---------|
| CRM در دسترس نیست (timeout >۲s) | DENY همه | `503` + `Retry-After: 30` |
| CRM 5xx | DENY | `503` |
| امضای HMAC نامعتبر | DENY | `503` (نه 401 — نشانهٔ خطای پیکربندی است) |
| پاسخ JSON نامعتبر | DENY | `503` |
| `perm_version` قدیمی | کش باطل، درخواست مجدد؛ اگر باز نشد | `503` |
| کاربر `is_active=0` | DENY | `401` |
| کاربر قفل‌شده (`locked_until`) | DENY | `401` |
| Token منقضی/ابطال‌شده | DENY | `401` |
| وضعیت فایل نامشخص (DB CRM خطا) | DENY | `503` |
| Object Storage خطا | DENY نوشتن، صف retry | `507` / `503` |

**قاعدهٔ کد:** `CrmBridge::authorize()` هیچ‌گاه `null`/`true` برنمی‌گرداند. فقط `Allow` یا استثنا. **هیچ `catch` بی‌صدا که مقدار پیش‌فرض «مجاز» بدهد مجاز نیست** — این باید در Code Review چک‌لیست باشد.

### ۵.۱. کش مجوز

| ویژگی | مقدار | چرا |
|-------|-------|-----|
| مدت | **۳۰ ثانیه** | توازن بین بار شبکه و تأخیر ابطال |
| کلید | `user:{id}:node:{id}:perm:{perm_version}` | تغییر `perm_version` → همهٔ کلیدها بی‌اثر |
| محل | APCu در PHP-FPM **یا** DB محلی VPS | اگر APCu نبود، DB |
| پاک‌سازی | با `perm_version` نه با TTL تنها | ابطال فوری حتی داخل پنجرهٔ کش |
| اندازه | حداکثر ۵۰٬۰۰۰ کلید | جلوگیری از مصرف حافظه |

**توجیه ۳۰ ثانیه:** `FILE_PERMISSION_MATRIX.md` §7 حداکثر تأخیر ابطال WebDAV را ۳۰ ثانیه اعلام کرده. اگر کارفرما صفر می‌خواهد، کش خاموش می‌شود و بار شبکه بالا می‌رود. **تصمیم: OD-107.**

---

## ۶. قفل — کجا زندگی می‌کند؟

| گزینه | مزیت | عیب |
|-------|------|-----|
| **الف) همان `file_locks` در CRM** | یک منبع حقیقت؛ UI و WebDAV همدیگر را می‌بینند | یک درخواست شبکه در هر LOCK |
| ب) جدول محلی VPS + گزارش به CRM | سریع | **خطر F3**: دو منبع قفل → UI نمی‌بیند |
| ج) Redis روی VPS | سریع | نیازمند Redis (مصداق «قفل UI نیست ولی منبع دوم است») |

**پیشنهاد: الف.** دلیل: هدف اصلی قفل، جلوگیری از Lost Update بین **مرورگر و Explorer** است. اگر دو منبع باشد، این هدف از دست می‌رود.

**هزینه:** یک `authorize` اضافی در هر LOCK/UNLOCK/refresh. با کش ۳۰ ثانیه‌ای، هزینهٔ واقعی کم است.

---

## ۷. اتصال به Storage

### ۷.۱. قاعدهٔ امنیتی

| ممنوع | مجاز |
|-------|------|
| WebDAV → مسیر عمومی فایل | WebDAV → S3 API با اعتبارنامهٔ محدود |
| WebDAV → MySQL مستقیم CRM | WebDAV → API CRM (HTTPS) |
| URL عمومی فایل | Presigned URL کوتاه‌عمر **یا** پروکسی جریانی |

### ۷.۲. مدل اعتماد

```
Storage credentials روی VPS:
  - فقط دسترسی به bucket/prefix مخصوص
  - فقط PutObject/GetObject/DeleteObject/ListBucket
  - نه admin، نه bucket policy
  - چرخش هر ۹۰ روز (OD-118)
```

### ۷.۳. جریان PUT (جزئیات)

```
1. authorize() → allow=upload, node_id, quota_left
2. sabre writes body → temp key: /tmp/{uuid}.part
   (اگر > 4GB در object storage، multipart upload)
3. محاسبه sha256 در حین استریم
4. POST /api/dav/commit {node_id, sha256, size, temp_key}
5. CRM در تراکنش:
     - بررسی دوباره authorize (TOCTOU defence)
     - بررسی قفل
     - ساخت file_version + file_blob
6. CRM → VPS: «promote {temp_key} → blobs/{sha[0:2]}/{sha}»
7. VPS انجام می‌دهد، 201 برمی‌گرداند
```

**TOCTOU (Time-of-check to time-of-use):** بین authorize و commit، مجوز ممکن است عوض شود. commit **دوباره** authorize می‌کند. این برای دقت است، نه زیاده‌روی: در فاصلهٔ آپلود ۱۰۰MB، کاربر ممکن است اخراج شده باشد.

---

## ۸. استقرار (خلاصه — جزئیات در `FILE_WORKSPACE_DEPLOYMENT.md`)

| مورد | مقدار پیشنهادی |
|------|----------------|
| دامنه | `dav.<domain>` با گواهی Let's Encrypt (auto-renew) |
| TLS | 1.2+ فقط، HSTS با preload |
| Firewall | `ufw`: ۲۲ (کلید محدود به IP مدیر)، ۴۴۳ باز، بقیه بسته |
| PHP-FPM | pool جدا `dav`, `open_basedir`، `disable_functions` |
| nginx | `client_max_body_size 0` (چون chunked)، `proxy_request_buffering off` |
| Log | journald + rotate ۱۴ روز؛ **بدون** ثبت Authorization header |
| Health | `GET /healthz` → DB + CRM reachability + storage reachability |
| Updates | unattended-upgrades برای امنیتی، پنجرهٔ ماهانه برای major |

---

## ۹. Monitoring و Alerting

| Probe | بازه | آستانه هشدار |
|-------|------|--------------|
| `GET /healthz` | ۶۰s | ۲ خطای متوالی |
| `OPTIONS /dav/` از بیرون | ۳۰۰s | ۲ خطای متوالی |
| CRM API latency | ۶۰s | p95 > ۵۰۰ms |
| Disk usage (اگر local) | ۳۰۰s | > ۸۰٪ |
| Certificate expiry | روزانه | < ۱۴ روز |
| Failed auth spike | ۶۰s | > ۵۰ در ۵ دقیقه (نشانهٔ brute force) |

---

## ۱۰. هزینهٔ زیرساخت (تخمین، با فرض صریح)

> **هیچ عددی «قیمت واقعی» نیست.** این‌ها **برآورد مهندسی بر اساس منابع عمومی** است. قیمت نهایی از فروشندهٔ انتخاب‌شده استعلام شود.
> **تصمیم ارائه‌دهنده و منطقهٔ جغرافیایی: OD-119.**

| منبع | حداقل | پیشنهادی | چرا |
|------|-------|----------|-----|
| vCPU | ۲ | ۴ | ۲۵ کاربر هم‌زمان فهرست‌گیری |
| RAM | ۲GB | ۸GB | PHP-FPM workers + cache |
| Disk OS | ۲۰GB | ۴۰GB | logs + OS + temp multipart |
| Bandwidth | ۱TB/ماه | ۴TB/ماه | آپلود/دانلود سازمانی |
| Storage (objects) | ۵۰۰GB | ۲TB | رشد ۱۲ ماه |
| Backup | ۲۰٪ فعال | ۵۰٪ | سنگین‌تر |

**فرض‌های پنهان که باید نوشته شوند:**
- ۲۵ کاربر هم‌زمان، نه ۲۵۰.
- میانگین فایل ۲MB، ۱۰٪ فایل‌ها > ۵۰MB.
- رشد ۱۵٪ ماهانه در سال اول.
- بدون ویدیو.

---

## ۱۱. آنچه هنوز اثبات نشده

| مورد | وضعیت |
|------|-------|
| کارکرد sabre/dav با Windows Explorer | `NOT TESTED` |
| کارایی ۲۵ کاربر هم‌زمان | `NOT TESTED` |
| کارکرد قفل مشترک با CRM از طریق Bridge | `NOT TESTED` (طراحی شده) |
| رفتار ذخیره‌سازی S3 با فایل ۴GB | `NOT TESTED` |
| هزینهٔ واقعی VPS | `NEEDS APPROVAL` |
| انتخاب ارائه‌دهنده و منطقه | `NEEDS APPROVAL` |
