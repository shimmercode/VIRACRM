# VIRA CRM — وضعیت پروژه

**آخرین به‌روزرسانی:** ۲ آذر ۱۴۰۵ · **شاخه:** `arena/3b43de84-viracrm`

> این سند **وضعیت واقعی** است، نه اهداف. هر عدد با دستور بازتولید می‌شود.
> اگر چیزی تست نشده، `NOT TESTED` نوشته شده — نه «احتمالاً کار می‌کند».

## راهنمای وضعیت

| کد | معنا |
|----|------|
| `PASS` | با شاهد اجرایی اثبات شد |
| `FAIL` | نقص اثبات‌شده |
| `BLOCKED` | نیازمند دسترسی/تصمیم خارجی |
| `NOT TESTED` | کد هست، آزمون معتبر نیست |
| `NOT IMPLEMENTED` | کد کاربردی نیست |

---

## ۰. وضعیت فاز جاری — F0 ماژول فایل (Vira Drive)

| مورد | وضعیت | شاهد |
|------|-------|------|
| اسناد F0 (۱۸ سند) | `PASS` | `docs/FILE_*.md`, `docs/WEBDAV_*.md`, `docs/WINDOWS_*.md`, `docs/ONLINE_*.md` |
| نمودارها (۱۰×۳) | `PASS` | `docs/diagrams/file-module/` — `.dot`+`.svg`+`.png` |
| یکپارچگی مستندات | `PASS` | `docs-integrity` — **۴۴/۴۴** (+۵: ۴ سند جدید و گارد مسیرهای ارجاع‌شده) |
| ردیابی نیازمندی‌های فایل | `PASS` | ۹۹/۹۹ · `generate-file-traceability.mjs --check` |
| کاتالوگ نیازمندی‌ها | `PASS` | **۹۶** نیازمندی (۸۴ + ۱۲ مورد `REQ-16`) |
| تصمیم‌های باز فایل | **`NEEDS APPROVAL`** | ۳۶ مورد OD-101…OD-136 · **۱۶** مسدودکننده |
| کد ماژول فایل | `NOT IMPLEMENTED` | صفر خط — طبق دستور F0 |
| Migration ماژول فایل | `NOT IMPLEMENTED` | صفر فایل — طبق دستور §25 |
| تست‌های ماژول فایل | `NOT TESTED` | ۰ از ۲۴۰ |
| سازگاری Windows | `NOT TESTED` | ۰ از ۳۰ — محیط ویندوز در دسترس نیست |
| WebDAV روی VPS | `BLOCKED` | VPS آزمایشی تهیه نشده |
| بکاپ/بازیابی فایل | `BLOCKED` | صفر کد بکاپ در پروژه |

> **دامنهٔ F0 فقط مستندات بود.** هیچ کد Production، Migration، تغییر DB یا Storage انجام نشد.

---

## ۰.۱. فاز جاری — Decision Closure (بعد از F0)

> **محدودیت فاز:** بدون کد عملیاتی، بدون Migration، بدون تغییر Schema، بدون خرید زیرساخت.

| مورد | وضعیت | شاهد |
|------|-------|------|
| تفکیک ۳۶ تصمیم با ۱۵ فیلد | `PASS` | `FILE_WORKSPACE_DECISION_REGISTER.md` — ۳۶/۳۶ |
| بازمحاسبهٔ مسدودکننده‌ها | `PASS` | **۱۶** بر قاعدهٔ ثبت‌شده (§۰.۱ همان سند) — عدد قبلی «۱۳» بی‌مبنا بود |
| ممیزی سازمانی (OD-104/105) | `PASS` | `FILE_ORGANIZATIONAL_MODEL.md` — ممیزی از کد و DB، نه حافظه |
| دو مدل سازمانی + مقایسه | `PASS` | مدل A = ۸.۴ · مدل B = ۵.۹ → پیشنهاد A |
| سطح‌بندی شواهد ADR | `PASS` | `FILE_WORKSPACE_ADR_EVIDENCE.md` — ۴ `PROVEN` · **۶** `DESIGN-ONLY` · **۱** `NEEDS PoC` |
| تفکیک ادعاهای پژوهشی | `PASS` | `FILE_COLLABORATION_RESEARCH.md` §۲.۷ — MinIO/Seafile/Windows جدا |
| بستهٔ تصمیم کارفرما | `PASS` | `FILE_WORKSPACE_CLIENT_DECISION_PACK.md` — **۹** تصمیم + **۶** زیرساخت |
| بازآرایی شش‌دسته‌ای | `PASS` | `OPEN_DECISIONS.md` — **۹ کارفرما · ۲۱ Arena · ۶ زیرساخت** · ۰ بلوکه · ۰ Approved · ۰ Closed |
| تصمیم تصویب‌شده | **صفر** | هیچ ID به `Approved` یا `Closed` نرفت — نه با نوشتن پیشنهاد |
| نیازمندی‌های فایل | `Proposed` | ۹۹/۹۹ — هیچ‌کدام `Implemented` نیست |
| آمادگی شروع F1 | **`BLOCKED`** | منتظر **۹** تصمیم کارفرما + VPS + **۷** تصمیم فنی Arena |

**تصحیح مهم ثبت‌شده در این فاز:** دو عدد نادرست در F0 یافت و اصلاح شد —
(۱) «۱۳ مسدودکننده» → **۱۶**؛ (۲) ارجاع ERD به یک فایل `.dot` که هرگز ساخته نشد.
برای دومی یک گارد خودکار اضافه شد (`T-DOC-referenced-paths`) تا این نوع خطا تکرار نشود.
> هیچ ادعای «Production Ready» یا «سازگار با Windows» در هیچ سندی مطرح نشده است.

---

## ۱. زیرساخت تست

| مورد | وضعیت | شاهد |
|------|-------|------|
| Regression Suite | `PASS` | ۲۵ سوییت · **۹۰۸ اَسِرشِن** · ۰ شکست · exit 0 |
| **Execution Lock** | **`PASS`** | `lock-concurrency.mjs` — ۱۵ اَسِرشِن، شامل SIGKILL |
| Exit Code Contract | `PASS` | ۰/۱/۲/۳ — `runner-contract.mjs` ۱۱ اَسِرشِن |
| Repeatability | `PASS` | ۵ بُعد مقایسه + artifact در `.arena/` |
| Per-pass Isolation | `PASS` | اندازه‌گیری حول هر pass |
| ۷ عرض واکنش‌گرا | `PASS` | ۳۹۰/۴۳۰/**۵۷۶**/۷۶۸/۱۰۲۴/۱۲۸۰/۱۴۴۰ |
| اسناد اجباری (§20) | `PASS` | ۱۵ سند + بررسی خودکار گام‌به‌گام |
| ماتریس ردیابی | `PASS` | تولیدشده از کاتالوگ، ۸۴ نیازمندی، drift-guarded |
| PHPUnit واقعی | `NOT TESTED` | تست‌ها با shim اجرا می‌شوند (Composer نیست) |

---

## ۲. ماژول‌های محصول

| ماژول | صفحه | Route | مجوز | تست | وضعیت |
|-------|------|-------|------|-----|-------|
| احراز هویت | ✓ | ✓ | ✓ | ✓ | **PASS** |
| داشبورد | ✓ | ✓ | ✓ | ✓ | `PASS` (نیمه — بدون گروه انجام‌شده) |
| مشتریان | ۴ صفحه | ✓ | ✓ | ✓ | **PASS** |
| قراردادها | ۴ صفحه | ✓ | ✓ | ✓ | `PASS` (بدون قالب/PDF/نسخه) |
| پروژه‌ها | ۴ صفحه | ✓ | ✓ | ✓ (۲۸ اَسِرشِن) | **`PASS`** |
| دسترسی‌ها | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` |
| پرسنل | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` |
| حقوق | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` |
| کارتل/یادآوری | جزئی | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` (موتور آماده) |
| گزارش‌ها | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` |
| تنظیمات | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` (خواندن بله) |
| Audit (مشاهده) | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` (نوشتن بله) |
| حسابداری | ✗ | ✗ | ✗ | ✗ | **طراحی فقط** |
| بکاپ | ✗ | ✗ | ✗ | ✗ | `NOT IMPLEMENTED` |

---

## ۳. امنیت

| مورد | وضعیت | یادداشت |
|------|-------|---------|
| Authentication | `PASS` | bcrypt، throttle، session regenerate |
| RBAC (middleware) | `PASS` | ۳۹ مجوز، روی همهٔ مسیرها |
| RBAC (Policy) | `PASS` | `ContractPolicy` + `ProjectPolicy` · ۱۴ اَسِرشِن |
| Audit (نوشتن) | `PASS` | ۲۳۲ ردیف، شامل ۲۷ `denied` |
| Audit (مشاهده) | `NOT IMPLEMENTED` | — |
| `EnvelopeEncrypter` (واحد) | `PASS` | ۲۶ اَسِرشِن |
| **Encryption در مسیر واقعی** | **`FAIL`** | **هیچ‌جا فراخوانی نمی‌شود** |
| Key Management | `NOT IMPLEMENTED` | `VIRA_CREDENTIAL_KEY` وجود ندارد |
| Backup/Restore | `NOT IMPLEMENTED` | — |

---

## ۴. زیرساخت «آماده ولی خاموش»

| سرویس | تست | در برنامه؟ |
|-------|-----|-----------|
| `EnvelopeEncrypter` | ۲۶ | ❌ |
| `ReminderSchedule` | ۴۳ | ❌ |
| Scheduler (`routes/console.php`) | — | ❌ فقط `inspire` |
| PDF engine | — | ❌ نصب نیست |

---

## ۵. هاست

| مورد | وضعیت |
|------|-------|
| DNS ساب‌دامین | `PASS` |
| PHP/Extensions/MySQL/Cron/Memory | **`BLOCKED`** (۱۴ از ۲۱ نامعلوم) |
| PDF روی هاست | `BLOCKED` |
| Backup/Restore روی هاست | `BLOCKED` |

**هیچ ادعای Production Readiness وجود ندارد و نخواهد داشت تا این‌ها `PASS` شوند.**

---

## ۶. اولین کار بعدی

1. **رفع `FAIL` امنیتی:** لایهٔ Policy — بالاترین اولویت چون یک نقص واقعی است
2. M6 — فعال‌سازی واقعی encryption + scheduler (نیازمند H-15)
3. M3 ادامه — CRUD کلمات کلیدی، صفحهٔ ۵۰۰، اجزای DS #۲۴–۲۶
