# VIRA CRM — پیکربندی محیط‌ها و جداسازی Dev/Staging/Production

| مورد | مقدار |
|---|---|
| نسخه | 1.0 |
| تاریخ | 2026-11-22 |
| شناسه سند | OPS-ENV-01 |
| الزامات مرتبط | REQ-15.01 (استقرار مستقل)، REQ-15.04 (امنیت محیط) |
| وضعیت | طراحی‌شده — تصمیم‌های میزبانی نیازمند **تأیید کارفرما** (OD-18) |

> **چرا این سند لازم است؟** چون تنها محیطی که امروز وجود دارد «production روی هاست
> اشتراکی» است. اگر توسعه و تست روی همان محیط انجام شود، یک اشتباه کوچک داده واقعی
> مشتری را از بین می‌برد و راه بازگشت وجود ندارد.

---

## ۱. سه محیط، سه هدف

| محیط | هدف | داده | دسترسی عمومی | `APP_ENV` | `APP_DEBUG` |
|---|---|---|---|---|---|
| **Local** | توسعه روی سیستم برنامه‌نویس | داده ساختگی (fake) | ندارد | `local` | `true` |
| **Staging** | تست پیش از انتشار، تست بازیابی بکاپ | داده ساختگی یا نسخه بی‌نام‌شده | محافظت‌شده (Basic Auth + IP) | `staging` | `false` |
| **Production** | استفاده واقعی کارفرما | داده واقعی | عمومی روی `crm.viraagency.co` | `production` | `false` |

**قاعده آهنین:** هیچ‌گاه داده واقعی مشتری روی Local یا Staging کپی نمی‌شود.
اگر برای بازتولید یک باگ به داده واقعی نیاز بود، ابتدا نام‌ها/تلفن‌ها/شناسه‌ها
بی‌نام می‌شوند و سپس کپی می‌شوند.

---

## ۲. وضعیت واقعی امروز (بدون تعارف)

| محیط | وجود دارد؟ | شاهد |
|---|---|---|
| Local | **خیر** — در این sandbox فقط PHP-WASM هست، نه MySQL/Composer | `docs/phase-1/00-dev-environment-validation.md` |
| Staging | **خیر** | هیچ زیرساختی برای آن تعریف نشده |
| Production | **فقط یک ساب‌دامین که پاسخ نمی‌دهد** | DNS: `185.164.72.77`؛ TCP:443 باز ولی TLS شکست می‌خورد |

> بنابراین: «جداسازی محیط‌ها» در این مرحله یک **طرح** است، نه واقعیت. تا زمانی که
> staging ساخته نشود، تست‌های T-OPS-03 و T-SEC-12 قابل اجرا نیستند و وضعیتشان
> **NOT_RUN** می‌ماند.

---

## ۳. تفاوت مقادیر `.env` در هر محیط

| متغیر | Local | Staging | Production |
|---|---|---|---|
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `APP_URL` | `http://localhost:8000` | `https://staging.crm...` | `https://crm.viraagency.co` |
| `DB_CONNECTION` | `sqlite` یا `mysql` | `mysql` | `mysql` |
| `DB_DATABASE` | `vira_local` | `vira_staging` | `vira_crm` |
| `CACHE_STORE` | `file` | `file` | `file` |
| `SESSION_DRIVER` | `file` | `file` | `file` |
| `SESSION_SECURE_COOKIE` | `false` | `true` | `true` |
| `QUEUE_CONNECTION` | `sync` | `sync` | `sync` |
| `MAIL_MAILER` | `log` | `log` یا SMTP تست | SMTP واقعی (OD-19) |
| `CREDENTIALS_KEYS` | کلید آزمایشی | کلید staging **جدا** | کلید production **جدا** |
| `BACKUP_ENCRYPTION_KEY` | کلید آزمایشی | کلید staging جدا | کلید production جدا (escrow) |
| `VIRA_DIAG_TOKEN` | — | — | فقط هنگام اجرای اسکریپت تشخیص، سپس حذف |

> هیچ‌کدام از این مقادیر در Git نیست. فقط `.env.example` با مقادیر خالی نگهداری می‌شود.

---

## ۴. `.env.example` — فهرست کامل متغیرها

`docs/tools/env.example` (پیش‌نویس) شامل این گروه‌هاست:

```
# ── Application ─────────────────────────────
APP_NAME="VIRA CRM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Tehran
APP_URL=https://crm.viraagency.co

# ── Localization (Persian-first) ────────────
APP_LOCALE=fa
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=fa_IR

# ── Database ────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# ── Session / Security ──────────────────────
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
# نام کوکی باید اختصاصی VIRA باشد تا با وردپرس viraagency.co تداخل نکند
SESSION_COOKIE=vira_crm_session
XSRF_COOKIE_NAME=vira_crm_xsrf

# ── Credential encryption (KEY MANAGEMENT §2) ──
CREDENTIALS_KEYS=

# ── Backup (separate key! §5 of backup policy) ──
BACKUP_ENCRYPTION_KEY=
BACKUP_DISK=local

# ── Reminders (D-2) ─────────────────────────
REMINDER_TIMEZONE=Asia/Tehran
REMINDER_LEAD_DAYS_INVOICE=2
REMINDER_LEAD_DAYS_SEO_REPORT=2

# ── Mail (owner decision pending) ───────────
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="VIRA CRM"

# ── Diagnostics (temporary only) ────────────
VIRA_DIAG_TOKEN=
```

---

## ۵. استقلال از وردپرس — کنترل‌های مشخص

| ریسک تداخل | کنترل در CRM | چرا کافی است |
|---|---|---|
| کوکی مشترک | `SESSION_COOKIE=vira_crm_session` (نام یکتا) | وردپرس از `wordpress_logged_in_*` استفاده می‌کند؛ هیچ هم‌پوشانی نیست |
| Session در همان دامنه | ساب‌دامین مستقل `crm.viraagency.co` | کوکی‌های وردپرس روی `viraagency.co` با `domain=viraagency.co` تنظیم می‌شوند؛ CRM آن‌ها را نمی‌خواند |
| فایل `.htaccess` مشترک | Document Root مستقل | هر ساب‌دامین روت خودش را دارد |
| DB مشترک | پایگاه‌داده جدا `vira_crm` با کاربر جدا | کاربر DB وردپرس هیچ دسترسی‌ای به schema CRM ندارد |
| کد مشترک | هیچ `require`/`include` از وردپرس؛ هیچ استفاده از جدول `wp_*` | Modular Monolith مستقل است |
| ورود یکپارچه (SSO) | **وجود ندارد و در محدوده نیست** | اتصال به سایت اصلی تصمیم کارفرما است (OD-07) — و فعلاً رد شده |
| لینک از سایت به CRM | فقط لینک ساده `<a href>` | هیچ جریان احراز هویتی بین دو سیستم نیست |

> **آزمون پذیرش:** اگر کل وردپرس حذف شود، CRM باید بدون هیچ خطایی کار کند. این تست
> (T-OPS-06) طراحی شده ولی اجرا نشده، چون CRM هنوز ساخته نشده است.

---

## ۶. انتقال تنظیمات بین محیط‌ها

| چه چیزی | چگونه منتقل می‌شود | چه چیزی منتقل **نمی‌شود** |
|---|---|---|
| کد | Git (بدون `.env`) | — |
| کلیدها | دستی، از escrow، فقط توسط کارفرما | هرگز در Git یا چت |
| Schema DB | `php artisan migrate` | داده واقعی |
| داده اولیه (نقش‌ها، انواع قرارداد، سرویس‌ها) | Seeder | داده مشتری |
| تنظیمات برنامه | صفحه تنظیمات | — |

---

## ۷. چک‌لیست پیش از انتشار روی Production

- [ ] `APP_DEBUG=false` و `APP_ENV=production`
- [ ] `APP_KEY` تولید شده و در escrow ثبت شده
- [ ] `CREDENTIALS_KEYS` با کلید production (نه کلید آزمایشی)
- [ ] `BACKUP_ENCRYPTION_KEY` جدا از `CREDENTIALS_KEYS` و escrow شده
- [ ] `SESSION_SECURE_COOKIE=true` و HTTPS فعال
- [ ] `SESSION_COOKIE` با نام اختصاصی VIRA
- [ ] `VIRA_DIAG_TOKEN` پاک شده و `host-compatibility-check.php` از سرور حذف شده
- [ ] Installer قفل شده (`install.lock` موجود)
- [ ] مجوز `.env` = `600`، مجوز `storage/` قابل نوشتن برای کاربر وب
- [ ] cron برای scheduler تنظیم شده (بدون آن یادآور D-2 کار نمی‌کند)
- [ ] یک بکاپ دستی گرفته و **بازیابی آن روی staging تست شده**
- [ ] `php artisan about` و `php artisan migrate:status` بررسی شده (روی هاست واقعی)

> تا وقتی همه این موارد در یک گزارش اجرای واقعی تیک نخورند، «آماده انتشار» اعلام
> نمی‌شود. این چک‌لیست در فاز ۲/۳ اجرا می‌شود، نه حالا.
