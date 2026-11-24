# FILE_WORKSPACE_TEST_PLAN — طرح آزمون Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — **هیچ تستی اجرا نشده است**

> **قواعد آزمون (غیرقابل مذاکره):**
> 1. تست Skip‌شده **Pass گزارش نمی‌شود**.
> 2. Mock جایگزین تست واقعی WebDAV/Windows نمی‌شود.
> 3. همهٔ تست‌های مهم **از مسیر HTTP واقعی** با **حداقل دو کاربر مستقل** اجرا می‌شوند.
> 4. هر تست به یک `Requirement ID` و یک `Evidence` نگاشت دارد.
> 5. تست‌های نوشتنی روی **نسخهٔ ایزولهٔ دیتابیس** اجرا می‌شوند؛ دیتابیس مشترک هرگز تغییر نمی‌کند.

---

## ۱. شمارش و وضعیت‌ها

| گروه | تعداد | اجرا شده | وضعیت امروز |
|------|------:|----------:|-------------|
| T-FILE (عملیاتی) | ۳۴ | ۰ | `NOT TESTED` |
| T-PERM (دسترسی) | ۳۰ | ۰ | `NOT TESTED` |
| T-VER (نسخه) | ۱۲ | ۰ | `NOT TESTED` |
| T-LOCK (قفل) | ۱۴ | ۰ | `NOT TESTED` |
| T-DAV (WebDAV) | ۳۴ | ۰ | `NOT TESTED` |
| T-SEC (امنیت) | ۲۸ | ۰ | `NOT TESTED` |
| T-UP (آپلود) | ۱۶ | ۰ | `NOT TESTED` |
| T-BAK (بکاپ) | ۱۰ | ۰ | `NOT TESTED` |
| T-PERF (کارایی) | ۱۲ | ۰ | `NOT TESTED` |
| T-UI (رابط) | ۲۰ | ۰ | `NOT TESTED` |
| T-WIN (ویندوز) | ۳۰ | ۰ | `NOT TESTED` |
| **جمع** | **۲۴۰** | **۰** | **همه `NOT TESTED`** |

**این جدول عمداً در ابتدا آمده است.** هر گزارشی که بدون پر شدن این عددها ادعای «آماده» کند، بی‌پایه است.

---

## ۲. محیط‌های آزمون (سه سطح)

| محیط | برای چه | در دسترس امروز؟ |
|------|---------|:---------------:|
| **E1 — توسعهٔ محلی** | تست واحد/یکپارچگی، SQLite ایزوله | ✅ |
| **E2 — VPS آزمایشی** | WebDAV واقعی، کارایی، Fail-Closed | ❌ **تهیه نشده** |
| **E3 — ویندوز واقعی** | T-WIN | ❌ **در دسترس نیست** |

> **E2 و E3 وجود ندارند.** هر تستی که به آن‌ها نیاز دارد در F0 با وضعیت `BLOCKED` ثبت می‌شود — نه Pass.

---

## ۳. ابزارها

| ابزار | برای | وضعیت |
|-------|------|-------|
| `tools/checks/run-all.mjs` (موجود) | اجرای سوییت‌های پروژه | ✅ |
| `curl` برای HTTP خام | تأیید هدرها، Range، وضعیت‌ها | ✅ |
| Playwright/Puppeteer (موجود) | UI و مسیر مرورگر | ✅ |
| `litmus` (WebDAV compliance suite) | انطباق RFC 4918 | ❌ **نصب نشده** |
| `k6` یا `locust` | کارایی | ❌ **نصب نشده** |
| `cadaver` یا `davfs2` | کلاینت WebDAV خط فرمان | ❌ **نصب نشده** |
| ویندوز ۱۰/۱۱ واقعی | T-WIN | ❌ **در دسترس نیست** |

**قاعده:** اگر ابزاری نیست، تست **اجرا نشده** است. در F0 تعداد تست‌های ممکن با ابزار موجود: **۱۴** (queue: تست‌های مسیر مرورگر و API که به فایل‌سیستم/DB نیاز دارند). بقیه `BLOCKED`.

---

## ۴. T-FILE — عملیاتی (۳۴)

| ID | سناریو | مسیر | معیار قبولی | نیاز | وضعیت |
|----|--------|------|-------------|------|-------|
| T-FILE-001 | رفتن به `/files` | GET | ۲۰۰ + فهرست | E1 | `NOT TESTED` |
| T-FILE-002 | مالکیت سازمانی | DB | `owner_org_id` پر، `created_by` متفاوت از `owner_user_id` | E1 | `NOT TESTED` |
| T-FILE-003 | ناحیه‌ها از داده | GET | ۱۱ ناحیه | E1 | `NOT TESTED` |
| T-FILE-004 | ساخت پوشه توسط غیرمجاز | POST | ۴۰۳ | E1 | `NOT TESTED` |
| T-FILE-005 | حذف → Trash → Restore | POST/DELETE | بازگشت به مسیر | E1 | `NOT TESTED` |
| T-FILE-006 | Hard delete بدون مجوز | DELETE | ۴۰۳ | E1 | `NOT TESTED` |
| T-FILE-007 | اتصال به مشتری | POST relations | رابطه ساخته شود | E1 | `NOT TESTED` |
| T-FILE-008 | حذف مشتری | — | رابطه `orphaned`، فایل مانده | E1 | `NOT TESTED` |
| T-FILE-009 | فایل پروژهٔ غیرقابل‌خواندن | GET | ۴۰۴ | E1 | `NOT TESTED` |
| T-FILE-010 | تب فایل در صفحهٔ پروژه | GET | فقط مجازها | E1 | `NOT TESTED` |
| T-FILE-011 | آپلود ۳۲MB | POST | ۲۰۱ + پیشرفت | E1 | `NOT TESTED` |
| T-FILE-012 | آپلود تکه‌ای ۱۰۰MB | POST/PUT | ۲۰۱ | E1 | `NOT TESTED` |
| T-FILE-013 | هش نادرست | POST | ۴۲۲ | E1 | `NOT TESTED` |
| T-FILE-014 | آپلود ناتمام | — | نسخه ساخته نشود | E1 | `NOT TESTED` |
| T-FILE-015 | MIME spoof | POST | ۴۲۲ | E1 | `NOT TESTED` |
| T-FILE-016 | نام با `\0` | POST | ۴۲۲ | E1 | `NOT TESTED` |
| T-FILE-017 | نام رزرو ویندوز `CON.txt` | POST | ۴۲۲ یا تغییر | E1 | `NOT TESTED` |
| T-FILE-018 | Range روی دانلود | GET | ۲۰۶ + `Content-Range` | E1 | `NOT TESTED` |
| T-FILE-019 | فایل غیرقابل‌پیش‌نمایش | GET | `attachment` | E1 | `NOT TESTED` |
| T-FILE-020 | سهمیهٔ پر | POST | ۵۰۷ | E1 | `NOT TESTED` |
| T-FILE-021 | جستجو | GET | نتایج درست | E1 | `NOT TESTED` |
| T-FILE-022 | جستجو از دید کاربر محدود | GET | فایل غیرمجاز نیاید | E1 | `NOT TESTED` |
| T-FILE-023 | جستجوی متن (F3) | — | خارج از دامنه | — | `N/A` |
| T-FILE-024 | پیش‌نمایش PDF | GET | ۲۰۰ + `inline` | E1 | `NOT TESTED` |
| T-FILE-025 | پیش‌نمایش SVG | GET | `attachment` | E1 | `NOT TESTED` |
| T-FILE-026 | iframe sandbox | GET | CSP header | E1 | `NOT TESTED` |
| T-FILE-027 | طبقه‌بندی | POST | ذخیره | E1 | `NOT TESTED` |
| T-FILE-028 | restricted از گروه | GET | ۴۰۳ | E1 | `NOT TESTED` |
| T-FILE-029 | سیاست نگهداری | Job | اعمال | E1 | `NOT TESTED` |
| T-FILE-030 | پاک‌سازی نسخه | Job | Audit + dry-run | E1 | `NOT TESTED` |
| T-FILE-031 | rename | PATCH | ۲۰۰ | E1 | `NOT TESTED` |
| T-FILE-032 | move | PATCH | ۲۰۰ + مجوز دو طرف | E1 | `NOT TESTED` |
| T-FILE-033 | copy | POST | کپی مستقل | E1 | `NOT TESTED` |
| T-FILE-034 | نام فارسی | POST | درست | E1 | `NOT TESTED` |

---

## ۵. T-PERM — دسترسی (۳۰)

**سناریوی پایهٔ دسترسی (A/B/C):**

| نقش | تعریف |
|-----|-------|
| **A** | مالک فایل |
| **B** | دارای `view` (و شاید `download`) |
| **C** | بدون هیچ مجوز |

| ID | سناریو | معیار | نیاز | وضعیت |
|----|--------|-------|------|-------|
| T-PERM-001 | ۱۸ قابلیت تعریف‌شده | جدول پر | E1 | `NOT TESTED` |
| T-PERM-002 | افزودن قابلیت جدید بدون تغییر کد | migration + row | E1 | `NOT TESTED` |
| T-PERM-003 | هر ۸ دامنهٔ Grant | کار کند | E1 | `NOT TESTED` |
| T-PERM-004 | وراثت | B از والد می‌بیند | E1 | `NOT TESTED` |
| T-PERM-005 | Break | B نمی‌بیند | E1 | `NOT TESTED` |
| T-PERM-006 | Deny مقدم | حتی با Allow | E1 | `NOT TESTED` |
| T-PERM-007 | default-deny | C نمی‌بیند | E1 | `NOT TESTED` |
| T-PERM-008 | Deny جد ≠ Deny نوه | بررسی | E1 | `NOT TESTED` |
| T-PERM-009 | Grant گروهی ≤ گروه | رد | E1 | `NOT TESTED` |
| T-PERM-010 | share بدون view | ۴۰۳ | E1 | `NOT TESTED` |
| T-PERM-011 | Resolver واحد | همهٔ مسیرها | E1 | `NOT TESTED` |
| T-PERM-012 | Revocation → cache باطل | فوری | E1 | `NOT TESTED` |
| T-PERM-013 | «چه کسی دسترسی دارد» | درست | E1 | `NOT TESTED` |
| T-PERM-014 | اشتراک عمومی خاموش | ۴۰۴ | E1 | `NOT TESTED` |
| T-PERM-015 | (F3) | — | `N/A` |
| T-PERM-016 | کاربر غیرفعال | فوری قطع | E1 | `NOT TESTED` |
| T-PERM-017 | B دانلود (دارد) | ۲۰۰ | E1 | `NOT TESTED` |
| T-PERM-018 | B دانلود (ندارد) | ۴۰۳ | E1 | `NOT TESTED` |
| T-PERM-019 | C از UI | ۴۰۴ | E1 | `NOT TESTED` |
| T-PERM-020 | C از URL مستقیم | ۴۰۴ | E1 | `NOT TESTED` |
| T-PERM-021 | C از API | ۴۰۴ | E1 | `NOT TESTED` |
| T-PERM-022 | C از WebDAV | ۴۰۳ | **E2** | `BLOCKED` |
| T-PERM-023 | B با URL کامل نسخهٔ قدیمی | ۴۰۳ | E1 | `NOT TESTED` |
| T-PERM-024 | Department isolation | قطع | E1 | `NOT TESTED` |
| T-PERM-025 | Project isolation | قطع | E1 | `NOT TESTED` |
| T-PERM-026 | Customer isolation | قطع | E1 | `NOT TESTED` |
| T-PERM-027 | فایل‌های restricted نامرئی در جستجو | ۰ نتیجه | E1 | `NOT TESTED` |
| T-PERM-028 | شمارش نتایج | یکسان برای B/C | E1 | `NOT TESTED` |
| T-PERM-029 | Grant منقضی | بی‌اثر | E1 | `NOT TESTED` |
| T-PERM-030 | ۱۰۰۰ بررسی هم‌زمان | ثابت | E1 | `NOT TESTED` |

---

## ۶. T-VER — نسخه (۱۲)

| ID | سناریو | معیار | وضعیت |
|----|--------|-------|-------|
| T-VER-001 | دو نسخهٔ متوالی | ۱، ۲ | `NOT TESTED` |
| T-VER-002 | Restore → نسخهٔ ۳ | دست‌نخورده | `NOT TESTED` |
| T-VER-003 | **دو آپلود هم‌زمان** | ۲ و ۳، نه دو ۲ | `NOT TESTED` |
| T-VER-004 | Dedup | blob جدید نه، نسخه بله | `NOT TESTED` |
| T-VER-005 | `dry_run_only` | هیچ حذف | `NOT TESTED` |
| T-VER-006 | `legal_hold` | هیچ حذف | `NOT TESTED` |
| T-VER-007 | هش نسخه در بازیابی | تطابق | `NOT TESTED` |
| T-VER-008 | مقایسهٔ متادیتا | درست | `NOT TESTED` |
| T-VER-009 | دانلود نسخهٔ قدیمی | ۲۰۰ + Audit | `NOT TESTED` |
| T-VER-010 | نسخه‌بندی WebDAV (PUT) | نسخهٔ جدید | **BLOCKED (E2)** |
| T-VER-011 | نسخهٔ ۱۰۰ | کار کند | `NOT TESTED` |
| T-VER-012 | حذف فایل با نسخه‌ها | Trash، نسخه‌ها سالم | `NOT TESTED` |

---

## ۷. T-LOCK — قفل (۱۴)

| ID | سناریو | معیار | وضعیت |
|----|--------|-------|-------|
| T-LOCK-001 | دو قفل هم‌زمان | یکی موفق | `NOT TESTED` |
| T-LOCK-002 | TTL | آزاد خودکار | `NOT TESTED` |
| T-LOCK-003 | force-unlock | Audit + آزاد | `NOT TESTED` |
| T-LOCK-004 | PUT روی قفل دیگری | ۴۲۳ | `BLOCKED (E2)` |
| T-LOCK-005 | `If-Match` نادرست | ۴۱۲ | `BLOCKED (E2)` |
| T-LOCK-006 | ۱۰۰ درخواست هم‌زمان | ۱ قفل | `NOT TESTED` |
| T-LOCK-007 | قفل پوشه `Depth: infinity` | MKCOL رد | `BLOCKED (E2)` |
| T-LOCK-008 | refresh | TTL تمدید | `NOT TESTED` |
| T-LOCK-009 | قفل کاربر غیرفعال | آزاد | `NOT TESTED` |
| T-LOCK-010 | قفل + خروج از سیستم | آزاد (origin=app) | `NOT TESTED` |
| T-LOCK-011 | Lost Update: A نسخه ۳، B نسخه ۴ | ۴۰۹ | `NOT TESTED` |
| T-LOCK-012 | Unlock توسط غیرصاحب | ۴۰۳ | `NOT TESTED` |
| T-LOCK-013 | قفل فایل حذف‌شده | آزاد + Audit | `NOT TESTED` |
| T-LOCK-014 | قفل روی فایل restricted | فقط مجاز | `NOT TESTED` |

---

## ۸. T-DAV — WebDAV (۳۴)

> **همهٔ این‌ها `BLOCKED (E2)` یا `BLOCKED (E2+E3)` هستند تا VPS راه‌اندازی شود.**

| ID | مورد | نیاز | وضعیت |
|----|------|------|-------|
| T-DAV-001 | TLS معتبر | E2 | `BLOCKED` |
| T-DAV-002 | ۱۲ Method | E2 | `BLOCKED` |
| T-DAV-003 | CRM تنها مرجع | E2 | `BLOCKED` |
| T-DAV-004 | نگاشت Method→مجوز | E2 | `BLOCKED` |
| T-DAV-005 | Fail-Closed (CRM down) | E2 | `BLOCKED` |
| T-DAV-006 | Basic روی TLS | E2 | `BLOCKED` |
| T-DAV-007 | App-Token | E2 | `BLOCKED` |
| T-DAV-008 | LOCK/UNLOCK | E2 | `BLOCKED` |
| T-DAV-009 | PUT → نسخهٔ جدید | E2 | `BLOCKED` |
| T-DAV-010 | Audit | E2 | `BLOCKED` |
| T-DAV-011 | حجم سرویس | E2 | `BLOCKED` |
| T-DAV-012 | پورت بسته | E2 | `BLOCKED` |
| T-DAV-013 | healthz | E2 | `BLOCKED` |
| T-DAV-014 | Windows mount | **E3** | `BLOCKED` |
| T-DAV-015 | مستند محدودیت ویندوز | سند | ✅ (این سند) |
| T-DAV-016 | `litmus` basic | E2 | `BLOCKED` |
| T-DAV-017 | `litmus` copymove | E2 | `BLOCKED` |
| T-DAV-018 | `litmus` locks | E2 | `BLOCKED` |
| T-DAV-019 | `litmus` props | E2 | `BLOCKED` |
| T-DAV-020 | `litmus` http | E2 | `BLOCKED` |
| T-DAV-021 | نام فارسی | E2 | `BLOCKED` |
| T-DAV-022 | نام طولانی | E2 | `BLOCKED` |
| T-DAV-023 | فاصل و کاراکتر خاص | E2 | `BLOCKED` |
| T-DAV-024 | پوشه ۱۰۰۰+ | E2 | `BLOCKED` |
| T-DAV-025 | Reconnect | E3 | `BLOCKED` |
| T-DAV-026 | Revocation حین استفاده | E2 | `BLOCKED` |
| T-DAV-027 | Token expiry | E2 | `BLOCKED` |
| T-DAV-028 | ۵۰MB بدون Registry | E3 | `BLOCKED` |
| T-DAV-029 | ۵۰MB با Registry | E3 | `BLOCKED` |
| T-DAV-030 | ۲GB با Registry | E3 | `BLOCKED` |
| T-DAV-031 | ۵GB (شکست انتظاری) | E3 | `BLOCKED` |
| T-DAV-032 | MOVE بین پوشه‌ها | E2 | `BLOCKED` |
| T-DAV-033 | COPY | E2 | `BLOCKED` |
| T-DAV-034 | DELETE → Trash | E2 | `BLOCKED` |

---

## ۹. T-SEC — امنیت (۲۸)

| ID | تست | معیار | وضعیت |
|----|-----|-------|-------|
| T-SEC-F01 | فایل در public | ۴۰۴ | `NOT TESTED` |
| T-SEC-F02 | IDOR | ۴۰۴ | `NOT TESTED` |
| T-SEC-F03 | ۶ الگوی path traversal | رد | `NOT TESTED` |
| T-SEC-F04 | لینک منقضی | ۴۰۳ | `NOT TESTED` |
| T-SEC-F05 | MIME spoof ×۵ | ۴۲۲ | `NOT TESTED` |
| T-SEC-F06 | XSS HTML/SVG | `attachment` | `NOT TESTED` |
| T-SEC-F07 | Zip bomb ×۳ | ۴۱۳ | `NOT TESTED` |
| T-SEC-F08 | Rate limit | ۴۲۹ | `NOT TESTED` |
| T-SEC-F09 | Audit بدون رمز | grep | `NOT TESTED` |
| T-SEC-F10 | Secret در repo | grep | `NOT TESTED` |
| T-SEC-F11 | Malware scan | — | `BLOCKED (OD-127)` |
| T-SEC-F12 | Timing leak | <۵۰ms تفاوت | `NOT TESTED` |
| T-SEC-F13 | CSRF | ۴۱۹ | `NOT TESTED` |
| T-SEC-F14 | SSTI در نام فایل | بدون اثر | `NOT TESTED` |
| T-SEC-F15 | SQLi در جستجو | بدون اثر | `NOT TESTED` |
| T-SEC-F16 | Header injection در نام | بدون CRLF | `NOT TESTED` |
| T-SEC-F17 | محتوای فایل در log | نه | `NOT TESTED` |
| T-SEC-F18 | Policy bypass با روش HTTP متفاوت | ۴۰۵ | `NOT TESTED` |
| T-SEC-F19 | Session fixation | بدون اثر | `NOT TESTED` |
| T-SEC-F20 | Token replay | ۴۰۱ | `NOT TESTED` |
| T-SEC-F21 | Nonce تکراری | رد | `NOT TESTED` |
| T-SEC-F22 | HMAC نامعتبر | ۵۰۳ | `NOT TESTED` |
| T-SEC-F23 | Slowloris روی WebDAV | timeout | `BLOCKED (E2)` |
| T-SEC-F24 | Enumeration با اندازهٔ پاسخ | یکسان | `NOT TESTED` |
| T-SEC-F25 | Backup بدون رمز | نه | `BLOCKED (E2)` |
| T-SEC-F26 | دسترسی مستقیم به S3 bucket | ۴۰۳ | `BLOCKED (E2)` |
| T-SEC-F27 | Privilege escalation با تغییر role | کش باطل | `NOT TESTED` |
| T-SEC-F28 | Mass download (۱۰۰۰/دقیقه) | ۴۲۹ | `NOT TESTED` |

---

## ۱۰. T-UP — قابلیت اطمینان آپلود (۱۶)

| ID | سناریو | معیار | وضعیت |
|----|--------|-------|-------|
| T-UP-001 | قطع اتصال وسط آپلود | نشست باز می‌ماند | `NOT TESTED` |
| T-UP-002 | Resume | ادامه از تکهٔ n | `NOT TESTED` |
| T-UP-003 | تکهٔ تکراری | idempotent | `NOT TESTED` |
| T-UP-004 | تکهٔ خارج از ترتیب | پذیرش | `NOT TESTED` |
| T-UP-005 | هش نهایی غلط | ۴۲۲ | `NOT TESTED` |
| T-UP-006 | آپلود تکراری (نام یکسان) | نسخهٔ جدید یا warning | `NOT TESTED` |
| T-UP-007 | آپلود هم‌زمان دو کاربر | یکی بعد از دیگری | `NOT TESTED` |
| T-UP-008 | DB down حین commit | rollback | `NOT TESTED` |
| T-UP-009 | Storage down حین commit | rollback | `NOT TESTED` |
| T-UP-010 | پاک‌سازی نشست منقضی | temp حذف | `NOT TESTED` |
| T-UP-011 | فایل ۰ بایت | پذیرش یا رد صریح | `NOT TESTED` |
| T-UP-012 | فایل ۴GB | (وابسته به Backend) | `BLOCKED` |
| T-UP-013 | سهمیه در وسط آپلود | رد در end | `NOT TESTED` |
| T-UP-014 | حذف فایل حین آپلود | ۴۰۹ | `NOT TESTED` |
| T-UP-015 | آپلود هم‌زمان + قفل | ۴۲۳ | `NOT TESTED` |
| T-UP-016 | اسم با ۲۵۵ کاراکتر | پذیرش/رد صریح | `NOT TESTED` |

---

## ۱۱. T-BAK — بکاپ و بازیابی (۱۰)

> **وضعیت پایه:** CRM **هیچ کد بکاپی ندارد** (`SECURITY_REVIEW` فاز ۳). این تست‌ها تا وجود کد، `BLOCKED` هستند.

| ID | سناریو | معیار | وضعیت |
|----|--------|-------|-------|
| T-BAK-001 | بکاپ متادیتا | فایل سالم | `BLOCKED` |
| T-BAK-002 | بکاپ بایت‌ها | مطابقت هش | `BLOCKED` |
| T-BAK-003 | بازیابی متادیتا | row count | `BLOCKED` |
| T-BAK-004 | بازیابی بایت‌ها | هش منطبق | `BLOCKED` |
| T-BAK-005 | بررسی یکپارچگی رابطهٔ نسخه↔blob | ۰ orphan | `BLOCKED` |
| T-BAK-006 | ثبات مجوز پس از بازیابی | نمونه | `BLOCKED` |
| T-BAK-007 | WebDAV پس از بازیابی | وصل شود | `BLOCKED` |
| T-BAK-008 | بکاپ رمزنگاری‌شده | قابل خواندن بدون کلید نه | `BLOCKED` |
| T-BAK-009 | بکاپ در مکان جدا | تأیید | `BLOCKED` |
| T-BAK-010 | RTO/RPO اندازه‌گیری | مستند | `BLOCKED` |

---

## ۱۲. T-PERF — کارایی (۱۲)

| ID | سناریو | معیار | وضعیت |
|----|--------|-------|-------|
| T-PERF-001 | P1 | P95 ≤ ۸۰۰ms | `BLOCKED` |
| T-PERF-002 | P2 | P95 ≤ ۱.۵s | `BLOCKED` |
| T-PERF-003 | P3 | P95 ≤ ۳s | `BLOCKED` |
| T-PERF-004 | P4 | بدون خطا | `BLOCKED` |
| T-PERF-005 | P5 | همه موفق | `BLOCKED` |
| T-PERF-006 | P6 | همه موفق | `BLOCKED` |
| T-PERF-007 | P7 | موفق | `BLOCKED` |
| T-PERF-008 | P8 | بدون timeout | `BLOCKED` |
| T-PERF-009 | P9 | ≤۵s | `BLOCKED` |
| T-PERF-010 | P10 | ≤۵۰ms | `BLOCKED` |
| T-PERF-011 | P11 | ≤۵۰۰ms | `BLOCKED` |
| T-PERF-012 | P12 | fail-closed ≤۲s | `BLOCKED` |

---

## ۱۳. T-UI — رابط (۲۰)

| ID | مورد | معیار | وضعیت |
|----|------|-------|-------|
| T-UI-001 | Folder tree | نمایش | `NOT TESTED` |
| T-UI-002 | Breadcrumb | ناوبری | `NOT TESTED` |
| T-UI-003 | Grid/List toggle | کار کند | `NOT TESTED` |
| T-UI-004 | Sort | ۴ ستون | `NOT TESTED` |
| T-UI-005 | Filter | کار کند | `NOT TESTED` |
| T-UI-006 | Multi-select | Shift/Ctrl | `NOT TESTED` |
| T-UI-007 | Drag & drop | آپلود | `NOT TESTED` |
| T-UI-008 | Progress | واقعی | `NOT TESTED` |
| T-UI-009 | Retry | کار کند | `NOT TESTED` |
| T-UI-010 | Context menu | آیتم‌های مجاز | `NOT TESTED` |
| T-UI-011 | Bulk actions | فقط مجاز | `NOT TESTED` |
| T-UI-012 | Details panel | متادیتا | `NOT TESTED` |
| T-UI-013 | Version history | فهرست | `NOT TESTED` |
| T-UI-014 | Permission dialog | کار کند | `NOT TESTED` |
| T-UI-015 | Empty state | فارسی | `NOT TESTED` |
| T-UI-016 | Error state | فارسی | `NOT TESTED` |
| T-UI-017 | Permission denied state | پیام روشن | `NOT TESTED` |
| T-UI-018 | RTL | درست | `NOT TESTED` |
| T-UI-019 | ۷ عرض | بدون شکست | `NOT TESTED` |
| T-UI-020 | موبایل ۳۹۰px | قابل استفاده | `NOT TESTED` |

---

## ۱۴. T-WIN — ویندوز (۳۰)

همان جدول `WINDOWS_WEBDAV_COMPATIBILITY.md` §5. **همه `BLOCKED (E3)`**.

---

## ۱۵. تعریف «انجام‌شده»

| سطح | شرط |
|------|-----|
| **Implemented** | کد روی شاخه |
| **Unit Tested** | تست واحد پاس |
| **Integration Tested** | ≥۲ کاربر از HTTP واقعی پاس |
| **Environment Tested** | روی E2/E3 پاس |
| **Accepted** | کارفرما پذیرفته |

**قاعده:** هیچ ویژگی با وضعیت `Implemented` به‌عنوان «تمام» گزارش نمی‌شود.

---

## ۱۶. بلوکه‌کننده‌های امروز

| # | چه چیزی | چه تست‌هایی را بلوکه می‌کند |
|---|---------|------------------------------|
| B1 | **VPS آزمایشی وجود ندارد** | T-DAV (۳۴) · T-PERF (۱۲) · بخشی از T-PERM/T-LOCK/T-SEC (~۴۰) |
| B2 | **ویندوز واقعی در دسترس نیست** | T-WIN (۳۰) · T-DAV-014/025/028-031 |
| B3 | **هیچ کد ماژول فایل وجود ندارد** | همهٔ T-FILE/T-PERM/T-VER/T-LOCK/T-UP/T-UI (۱۴۶) |
| B4 | **هیچ کد بکاپ وجود ندارد** | T-BAK (۱۰) |
| B5 | **`litmus` نصب نشده** | T-DAV-016…020 |
| B6 | **k6/locust نصب نشده** | T-PERF (۱۲) |
| B7 | **تصمیم اسکن بدافزار** | T-SEC-F11 |
| B8 | **تصمیم دپارتمان/گروه** | T-PERM-024…026 |

**قابل اجرا در F0 امروز:** فقط تست‌های سبک که به کد جدید نیاز ندارند — یعنی **۰ تست از این ۲۴۰**. همهٔ آن‌ها F1 هستند.
