# VIRA CRM — گزارش اعتبارسنجی محیط توسعه (فاز ۰)

| مورد | مقدار |
|---|---|
| تاریخ اجرا | 2026-11-22 |
| مکان اجرا | sandbox توسعه (Debian 12, Linux x86_64) |
| نسخه PHP استفاده‌شده | **8.3.36** (build WebAssembly رسمی php-src، بسته `@php-wasm/node@3.1.65`) |
| Node.js | v22.22.3 |
| Python | 3.11.2 (ابزار تولید مستند) |
| دسترسی به هاست تولید | **ندارد** |

> این گزارش فقط دربارهٔ **محیط توسعه** است. بررسی هاست تولید (`crm.viraagency.co`) انجام
> **نشده** و در سند `01a-host-compliance-report.md` صریحاً «مسدود» ثبت شده است.

## ۱) وضعیت ابزارهای محیط توسعه (اجراشده، با شاهد)

| # | بررسی | نتیجه واقعی | شاهد |
|---|---|---|---|
| E-01 | نصب PHP محلی (`php -v`) | **موجود نیست** — مخازن Debian در این شبکه در دسترس نیستند | `php: command not found`, `apt-get update` با خطای دسترسی به deb.debian.org |
| E-02 | نصب Composer | **موجود نیست** | `composer: command not found` |
| E-03 | کلاینت/سرور MySQL | **موجود نیست** | `mysql`/`mariadb: command not found` |
| E-04 | اجرای PHP واقعی از طریق WASM | **موفق** — PHP 8.3.36، SAPI=cli | `docs/tools/php-cli.mjs` + `docs/src/php-runtime-smoke-test.php` |
| E-05 | Extensionهای موجود در همین runtime | `mbstring, openssl, gd, zip, curl, pdo_mysql, pdo_sqlite, bcmath, dom, xml, fileinfo, intl:NO` | خروجی اسکریپت host-check اجراشده روی wasm |
| E-06 | رمزنگاری AES-256-GCM (AAD، tamper، کلید اشتباه) | **PASS** (۱۰/۱۰ بررسی) | `docs/phase-1/evidence/php-runtime-smoke-test.json` |
| E-07 | محاسبات پولی `bcmath` (بدون float) | **PASS** | همان فایل، RT-05 |
| E-08 | `password_hash` (bcrypt) و verify | **PASS** | همان فایل، RT-03 |
| E-09 | PDO SQLite برای تست‌های خودکار + FK | **PASS** | همان فایل، RT-07 |
| E-10 | موتور تاریخ شمسی (J↔G، D-2، مرز روز) | **PASS** — ۰ خطا از ۸ مورد مرجع + ۲۴۲۴ بررسی invariant | `docs/phase-1/evidence/jalali-smoke-test.json` |
| E-11 | Timezone `Asia/Tehran` بدون DST (بعد از ۲۰۲۲) | **PASS** — offset ثابت +12600 ثانیه | همان فایل |
| E-12 | تولید دیاگرام Graphviz (نصب رسمی از npm) | **PASS** — SVG/PNG با فونت فارسی Vazirmatn | `docs/diagrams/*.svg` |
| E-13 | اعتبارسنجی syntax سه اسکریپت PHP با `token_get_all(TOKEN_PARSE)` | **PASS** — ALL SYNTAX OK | خروجی اجرا در همین نشست |
| E-14 | صحت لینک‌های داخلی ۲۰ سند Markdown | **PASS** — ۰ لینک شکسته | بررسی اسکریپتی در همین نشست |

## ۲) نتیجه‌گیری صریح

1. **ادعای اجرای واقعی PHP، رمزنگاری، محاسبات مالی، تاریخ شمسی و SQLite در این محیط، با شاهد اجرایی است.**
2. **ادعای نصب Laravel یا اجرای Artisan در این محیط وجود ندارد.** Composer در sandbox اجرا نشد؛
   چون مخازن Debian در دسترس نیستند و packagist نیز در فهرست دسترسی شبکه این محیط نیست.
   راه‌اندازی Laravel و اجرای تست‌های Feature/Integration در اولین مرحله فاز ۳ (پس از تأیید)
   روی محیطی با Composer انجام می‌شود.
3. **ادعای بررسی هاست وجود ندارد.** هیچ بررسی موفق روی `crm.viraagency.co` انجام نشد؛
   فقط DNS و باز بودن پورت ۴۴۳ تأیید شد و TLS از این شبکه بسته می‌شود.
4. **ادعای اجرای Cron/Backup/Restore در این محیط وجود ندارد**؛ این موارد در کاتالوگ تست با
   وضعیت `blocked` ثبت شده‌اند.

## ۳) محدودیت‌ها و اثر آن‌ها

| محدودیت | اثر | راه‌کار |
|---|---|---|
| نبود Composer/PHP محلی | نمی‌توان اسکلت Laravel را در این sandbox نصب کرد | فاز ۳ در محیط دارای Composer (دسترسی شما یا VPS)؛ کد از ابتدا با ابزارهای آزمون‌پذیر ساخته می‌شود |
| نبود MySQL | تست‌ها روی SQLite در حافظه اجرا می‌شوند | Migrationها سازگار با هر دو SQLite/MySQL نوشته می‌شوند؛ تفاوت‌ها در CI تست می‌شود |
| عدم دسترسی TLS به هاست | بررسی نسخه PHP و Extensionها ممکن نیست | اجرای `docs/tools/host-compatibility-check.php` روی هاست توسط کارفرما |
| runtime WASM بدون spawn | `shell_exec` در ابزار اعتبارسنجی قابل استفاده نیست | از فراخوانی `shell_exec` در اسکریپت بررسی هاست صرف‌نظر شد؛ در نسخه نهایی هاست قابل اجراست |
