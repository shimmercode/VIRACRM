# FILE_WORKSPACE_ACCEPTANCE_CRITERIA — معیارهای پذیرش Vira Drive

**نسخه:** 1.0 · **تاریخ:** ۲ آذر ۱۴۰۵ (2026-11-23) · **فاز:** F0 · **وضعیت:** Proposed — نیازمند تأیید کارفرما

> این سند **قرارداد پذیرش** است، نه فهرست آرزو. هر بند یا «قابل اثبات با شاهد» است یا نوشته شده که نیست.
> **اصل:** تا وقتی شاهدی وجود ندارد، بند **نپذیرفته** است — حتی اگر کد نوشته شده باشد.

---

## ۱. تعریف «پذیرفته‌شده»

یک بند وقتی `Accepted` است که **هر سه** شرط برقرار باشد:

| # | شرط | شاهد |
|---|------|------|
| 1 | تست متناظر **اجرا شده** | خروجی تست با timestamp |
| 2 | تست **از مسیر واقعی** (HTTP/WebDAV/Explorer) | لاگ درخواست |
| 3 | نتیجه **قابل بازتولید** | اجرای دوم همان نتیجه |

**یک تست Skip‌شده، شاهد نیست.** یک Mock، شاهد نیست. یک اسکرین‌شات بدون لاگ، شاهد ضعیف است.

---

## ۲. معیارهای پذیرش F0 (امروز)

| # | معیار | شاهد | وضعیت |
|---|-------|------|-------|
| A-F0-01 | ۱۸ سند تحویلی موجود و سازگار | فایل‌ها + بازبینی متقابل | ✅ |
| A-F0-02 | ۱۰ نمودار رندر‌شده | `docs/diagrams/file-module/` | ✅ |
| A-F0-03 | مقایسهٔ A/B/C با امتیاز وزن‌دار | `FILE_STORAGE_ARCHITECTURE.md` §2 | ✅ |
| A-F0-04 | ADR ثبت‌شده | `docs/adr/ADR-FILE-001…` | ✅ |
| A-F0-05 | ۹۹ نیازمندی با شناسهٔ یکتا | `FILE_WORKSPACE_REQUIREMENTS.md` | ✅ |
| A-F0-06 | ردیابی ۹۹/۹۹ | `FILE_WORKSPACE_TRACEABILITY.md` | ✅ |
| A-F0-07 | ۳۶ تصمیم باز ثبت‌شده (۱۶ مسدودکننده) | `FILE_WORKSPACE_OPEN_DECISIONS.md` · `FILE_WORKSPACE_DECISION_REGISTER.md` | ✅ |
| A-F0-08 | هیچ کد Production تغییر نکرده | `git diff` فقط `docs/` | ✅ |
| A-F0-09 | هیچ Migration نوشته نشده | `git diff` روی `database/` خالی | ✅ |
| A-F0-10 | هیچ تغییری در دیتابیس | baseline دست‌نخورده | ✅ |
| A-F0-11 | Threat Model با ۲۹ سناریو | `FILE_SECURITY_THREAT_MODEL.md` | ✅ |
| A-F0-12 | تصمیم WebDAV مستند | §2.2 دستور + `WEBDAV_VPS_ARCHITECTURE.md` | ✅ |

**F0 «کامل» است — نه «F1 آماده».**

---

## ۳. معیارهای پذیرش F1 (آینده) — ۱۰ حوزه

### ۳.۱. فضای کاری و UI

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-UI-01 | ناحیه‌های فضای کاری کار می‌کنند | ۱۱ ناحیه | اسکرین + HTTP | `NOT TESTED` |
| A-UI-02 | Explorer با تمام عناصر | ۱۶ عنصر | اسکرین + تست | `NOT TESTED` |
| A-UI-03 | RTL و فارسی کامل | ۷ عرض | اسکرین | `NOT TESTED` |
| A-UI-04 | طراحی هم‌راستا با Design System | رنگ/فونت/شعاع | بازبینی + `design-system` check | `NOT TESTED` |
| A-UI-05 | موبایل قابل استفاده | ۳۹۰px | اسکرین + استفاده | `NOT TESTED` |
| A-UI-06 | هیچ کنترل نمایشی بدون عملکرد | ۰ مورد | بازبینی دستی | `NOT TESTED` |
| A-UI-07 | حالت‌های Empty/Error/Denied | هر سه | اسکرین | `NOT TESTED` |
| A-UI-08 | Drag & drop با پیشرفت و تلاش مجدد | کار کند | ویدیو + لاگ | `NOT TESTED` |

### ۳.۲. مجوز

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-PM-01 | ۱۸ قابلیت کار می‌کنند | ۱۸/۱۸ | تست | `NOT TESTED` |
| A-PM-02 | سناریوی A/B/C | همه | تست ۳ کاربر | `NOT TESTED` |
| A-PM-03 | Deny مقدم بر Allow | ۱۰۰٪ | تست | `NOT TESTED` |
| A-PM-04 | Default-deny | ۱۰۰٪ | تست | `NOT TESTED` |
| A-PM-05 | Break-inheritance | درست | تست | `NOT TESTED` |
| A-PM-06 | بدون تشدید امتیاز | ۶ سناریو | تست | `NOT TESTED` |
| A-PM-07 | Revocation فوری در UI/API | ≤۰s | تست | `NOT TESTED` |
| A-PM-08 | Revocation در WebDAV | ≤۳۰s | تست | `NOT TESTED` |
| A-PM-09 | هیچ مسیر جانبی | صفر | تست | `NOT TESTED` |
| A-PM-10 | حالت‌های مرزی E1…E10 | ۱۰/۱۰ | تست | `NOT TESTED` |

### ۳.۳. نسخه‌بندی

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-VR-01 | تاریخچهٔ کامل | ۱۰۰ نسخه | تست | `NOT TESTED` |
| A-VR-02 | Restore = نسخهٔ جدید | ۱۰۰٪ | تست | `NOT TESTED` |
| A-VR-03 | بدون Lost Update | ۰ | تست هم‌زمانی | `NOT TESTED` |
| A-VR-04 | Dedup | کار کند | تست | `NOT TESTED` |
| A-VR-05 | Retention فقط با dry-run-off | ۱۰۰٪ | تست | `NOT TESTED` |
| A-VR-06 | Legal hold | غیرقابل نقض | تست | `NOT TESTED` |
| A-VR-07 | Audit نسخه | همهٔ رخدادها | DB query | `NOT TESTED` |

### ۳.۴. قفل

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-LK-01 | قفل واقعی سرور | ۱۰۰٪ | تست HTTP | `NOT TESTED` |
| A-LK-02 | ۱۰۰ درخواست هم‌زمان → ۱ قفل | دقیقاً ۱ | تست | `NOT TESTED` |
| A-LK-03 | TTL آزاد می‌کند | ≤TTL+۱دقیقه | تست | `NOT TESTED` |
| A-LK-04 | Force-unlock Audit | ۱۰۰٪ | تست | `NOT TESTED` |
| A-LK-05 | قفل بین WebDAV و UI مشترک | یکسان | تست | `NOT TESTED` |

### ۳.۵. WebDAV

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-DV-01 | TLS معتبر | A رتبه | ssllabs | `BLOCKED` |
| A-DV-02 | ۱۲ Method | ۱۲/۱۲ | litmus | `BLOCKED` |
| A-DV-03 | Fail-Closed | ۱۰۰٪ | تست قطعی | `BLOCKED` |
| A-DV-04 | نگاشت Method→مجوز | ۱۰۰٪ | تست | `BLOCKED` |
| A-DV-05 | Audit WebDAV | همه | DB | `BLOCKED` |
| A-DV-06 | نام فارسی | درست | تست ویندوز | `BLOCKED` |
| A-DV-07 | حداکثر حجم مستند | سند + تست | — | `BLOCKED` |

### ۳.۶. ویندوز

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-WN-01 | Mount روی ویندوز | کار کند | ویدیو + نسخه | `BLOCKED` |
| A-WN-02 | عملیات پایه (۸ عمل) | ۸/۸ | تست | `BLOCKED` |
| A-WN-03 | فایل ۵۰MB پس از Registry | کار کند | تست + Registry | `BLOCKED` |
| A-WN-04 | مستند محدودیت ویندوز | کامل | سند | ✅ |
| A-WN-05 | راهنمای کاربر گام‌به‌گام | کامل | سند | `NOT TESTED` |
| A-WN-06 | **ادعای سازگاری فقط با شاهد** | ۱۰۰٪ | بازبینی متن | ✅ (سیاست) |

### ۳.۷. امنیت

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-SC-01 | ۲۸ تست امنیتی | ۲۸/۲۸ | گزارش | `NOT TESTED` |
| A-SC-02 | Path traversal | ۰ موفق | تست | `NOT TESTED` |
| A-SC-03 | IDOR | ۰ موفق | تست | `NOT TESTED` |
| A-SC-04 | XSS در preview | ۰ موفق | تست | `NOT TESTED` |
| A-SC-05 | Zip bomb | رد | تست | `NOT TESTED` |
| A-SC-06 | Secret در repo | ۰ | grep | `NOT TESTED` |
| A-SC-07 | Secret در log | ۰ | grep | `NOT TESTED` |
| A-SC-08 | بدون اسکن بدافزار | ریسک پذیرفته مکتوب | OD-127 | `NEEDS APPROVAL` |

### ۳.۸. آپلود و قابلیت اطمینان

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-UP-01 | آپلود ۶۴MB | کار کند | تست | `NOT TESTED` |
| A-UP-02 | Resume | کار کند | تست | `NOT TESTED` |
| A-UP-03 | هش نادرست → رد | ۴۲۲ | تست | `NOT TESTED` |
| A-UP-04 | نشست ناتمام پاک | TTL | تست | `NOT TESTED` |
| A-UP-05 | یکپارچگی متادیتا↔blob | ۰ یتیم | تست | `NOT TESTED` |

### ۳.۹. بکاپ

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-BK-01 | بکاپ خودکار روزانه | کار کند | لاگ | `BLOCKED` |
| A-BK-02 | بازیابی آزموده | ۱ بار | گزارش | `BLOCKED` |
| A-BK-03 | هش پس از بازیابی | ۱۰۰٪ نمونه | تست | `BLOCKED` |
| A-BK-04 | RPO/RTO مستند | عدد واقعی | سند | `BLOCKED` |

### ۳.۱۰. کارایی

| # | معیار | آستانه | شاهد | وضعیت |
|---|-------|--------|------|-------|
| A-PF-01 | ۲۵ کاربر هم‌زمان | پاس | k6 | `BLOCKED` |
| A-PF-02 | فهرست ۵۰۰ آیتم ≤۲s | پاس | k6 | `BLOCKED` |
| A-PF-03 | آپلود ۱۰۰MB ≤۳م | پاس | تست | `BLOCKED` |
| A-PF-04 | Benchmark روی محیط واقعی | مستند | گزارش | `BLOCKED` |

---

## ۴. نقشهٔ صفحه‌های UI (S01…S16)

| کد | صفحه | نقش | مجوز |
|----|------|-----|------|
| **S01** | File Explorer | درخت + فهرست + نوار مسیر | `view` |
| **S02** | Shared With Me | فایل‌های شریک‌شده | `view` |
| **S03** | Workspace (Department/Project) | ناحیهٔ سازمانی | `view` |
| **S04** | Search Results | جستجو | `view` |
| **S05** | Permissions Dialog | «چه کسی دسترسی دارد» | `manage_permissions` |
| **S06** | Version History | تاریخچه + Restore | `view_versions` |
| **S07** | Preview | PDF/تصویر/متن در iframe sandbox | `view` |
| **S08** | Share Dialog | اشتراک داخلی (خارجی خاموش) | `share` |
| **S09** | Retention Settings | ادمین | `manage_retention` |
| **S10** | Folder Management | ساخت/تغییر پوشه | `create_folder` |
| **S11** | Trash | بازیافت + حذف قطعی | `restore` |
| **S12** | File Details | متادیتا + ارتباط CRM | `view` |
| **S13** | Upload Panel | صف آپلود | `upload` |
| **S14** | Entity Files Tab | فایل‌های پروژه/مشتری | `view` + Policy |
| **S15** | Activity Timeline | Audit فایل | `view_audit` |
| **S16** | WebDAV Setup Guide | راهنمای ویندوز | — |

**قاعدهٔ طراحی:**

| مورد | مقدار |
|------|-------|
| رنگ اصلی | `#450073` |
| ثانویه | `#D2AB67` (هرگز متن بدنه؛ متن روی طلایی `#7A5A1D`) |
| تیره | `#211B2B` |
| پس‌زمینه | `#F6F5F8` |
| سطح | `#FFFFFF` |
| فونت | Vazirmatn |
| جهت | RTL واقعی |
| شعاع | ۱۰px |
| سایدبار | ۲۶۴px / ۶۸px |
| حداقل متن | ۱۲px |

---

## ۵. ماتریس «چه چیزی اثبات شده»

| ادعا | وضعیت | شاهد |
|------|--------|------|
| Laravel 12 روی `local` با `serve=true` **temporaryUrl** می‌دهد | `VERIFIED` | اجرا در مخزن |
| امضای نامعتبر رد می‌شود | `VERIFIED` | curl ۳۰۲ به login |
| `storage.local` **Range نمی‌دهد** | `VERIFIED` | curl + کد |
| Content-Type از **محتوا** می‌آید | `VERIFIED` | `photo.png` با HTML → `text/html` |
| CSP `sandbox` روی مسیر storage | `VERIFIED` | هدر پاسخ |
| Flysystem مسیر `..` را رد می‌کند | `VERIFIED` | ۳ الگو |
| SHA-256 جریانی ۱۰۷MB/s در ۲۰MB RAM | `VERIFIED` (سندباکس) | benchmark |
| sabre/dav 4.7.1 فعال، BSD-3 | `VERIFIED` | GitHub API |
| sabre/dav قفل PDO دارد | `VERIFIED` | مستند رسمی |
| WebClient از ۲۰۲۳ Deprecated | `VERIFIED` | Microsoft Learn |
| `FileSizeLimitInBytes` = 50MB · سقف ۴GB | `VERIFIED` | مستندات + منابع |
| MinIO CE بایگانی شده | `VERIFIED` | مخزن رسمی |
| Seafile CE قفل/Audit ندارد | `VERIFIED` | مقایسهٔ رسمی |
| ONLYOFFICE CE سقف ۲۰ اتصال | `VERIFIED` | مستندات |
| هیچ ادعای ویندوز | `NOT TESTED` | — |
| هیچ ادعای Production | `NOT TESTED` | — |

---

## ۶. شرایطی که پذیرش را باطل می‌کند

| # | شرط | چرا |
|---|------|-----|
| 1 | تست Skip‌شده به‌عنوان Pass | دستور §33 |
| 2 | Mock به‌جای تست WebDAV/Windows | دستور §34 |
| 3 | ادعای «بدون تغییر Registry» | خلاف واقع |
| 4 | ادعای «ویرایش آنلاین» در F1 | خلاف دامنه |
| 5 | نبود شاهد برای «امن است» | خلاف روش |
| 6 | تغییر دیتابیس بدون بکاپ | دستور §26 |

---

## ۷. امضا و تأیید

| نقش | نام | تاریخ | امضا |
|------|-----|-------|------|
| کارفرما | — | — | — |
| مدیر فنی | — | — | — |
| مسئول امنیت | — | — | — |

**تا امضای ردیف‌های بالا، این سند `Proposed` است.**
