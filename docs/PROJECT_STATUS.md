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

## ۱. زیرساخت تست

| مورد | وضعیت | شاهد |
|------|-------|------|
| Regression Suite | `PASS` | ۲۴ سوییت · ۲ پاس · اجرای واقعی |
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
| RBAC (Policy) | `FAIL` | **هیچ `app/Policies/` وجود ندارد** |
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
