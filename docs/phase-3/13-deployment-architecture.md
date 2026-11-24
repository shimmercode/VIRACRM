# 13 — معماری استقرار (Gate A)

**Vira CRM** · Gate A · ۲ آذر ۱۴۰۵

---

## ۱. واقعیت تلخ: هاست تأیید نشده است

از `docs/phase-1/01a-host-compliance-report.md`:

> «وضعیت کلی: **CHECK NOT COMPLETED** — نیازمند اجرای اسکریپت روی هاست توسط کارفرما»

| مورد | وضعیت |
|------|--------|
| H-01 DNS `crm.viraagency.co → 185.164.72.77` | ✅ **تأییدشده** |
| H-03 اشتراک IP با سایت اصلی | ✅ **تأییدشده** |
| H-04 پورت ۴۴۳ باز | ✅ **تأییدشده** |
| H-05 دست‌دادن TLS | ⚠️ از sandbox ناموفق (نتیجهٔ قطعی نیست) |
| H-10 نسخه PHP | ❌ **نامعلوم** |
| H-11 Extensionها | ❌ **نامعلوم** |
| H-12 نسخه MySQL/MariaDB | ❌ **نامعلوم** |
| H-13 Composer | ❌ **نامعلوم** |
| H-14 Artisan/CLI | ❌ **نامعلوم** |
| H-15 Cron | ❌ **نامعلوم** |
| H-16 Memory/execution limit | ❌ **نامعلوم** |
| H-17 Document Root اختصاصی | ❌ **نامعلوم** |
| H-18 SSL و HSTS | ❌ **نامعلوم** |
| H-19 نوشتن در storage | ❌ **نامعلوم** |
| H-20 Backup/Restore | ❌ **نامعلوم** |
| H-21 Queue | ❌ **نامعلوم** |

**۱۴ مورد از ۲۱ مورد هنوز نامعلوم است.**

**پیامد صریح:** هیچ ادعای «آمادهٔ استقرار» مجاز نیست. ما می‌توانیم بگوییم
«طراحی‌شده برای این محدودیت‌ها»، نه «تست‌شده روی هاست». این تفاوت مهم است و باید
در گزارش نهایی حفظ شود.

---

## ۲. توپولوژی هدف (از فاز ۰، بدون تغییر)

```
DNS
  viraagency.co       → 185.164.72.77   (وردپرس — دست‌نخورده)
  www.viraagency.co   → همان
  crm.viraagency.co   → 185.164.72.77   (CRM — مستقل)

فایل‌سیستم:
  /home/virauser/
    ├── vira-crm/                ← ریشهٔ برنامه (خارج از public_html)
    │   ├── app/ ... vendor/  .env (0600)
    └── public_html/
        └── crm/                 ← Document Root (فقط محتوای public/)
```

**این توپولوژی درست است** و دو الزام P0 را پاسخ می‌دهد:
`REQ-01.02` (ساب‌دامین مستقل) و `REQ-01.03` (فایل حساس خارج از Document Root).

**حالت جایگزین:** اگر Document Root مستقل ممکن نباشد (H-17 ❌)،
`public_html/crm/` با `.htaccess` مسدودکنندهٔ `storage/`, `vendor/`, `.env`.
**این حالت دوم امنیت کمتری دارد** و باید صریح در گزارش بماند.

---

## ۳. الزامات محیط اجرا (تخمین بر اساس کد)

| نیاز | نسخه/مقدار | چرا | وضعیت |
|------|-----------|-----|--------|
| PHP | **≥ ۸.۲** (هدف ۸.۳) | `composer.json` + ویژگی‌های استفاده‌شده | ❌ |
| `openssl` | — | AES-256-GCM (رمز دسترسی‌ها) | ❌ |
| `mbstring` | — | فارسی، نرمال‌سازی، PDF | ❌ |
| `pdo_mysql` | — | DB هدف | ❌ |
| `gd` | — | mPDF (تصویر/فونت) | ❌ |
| `zip` | — | Composer + بکاپ | ❌ |
| `dom`, `xml` | — | mPDF + تست | ❌ |
| `bcmath` | اختیاری | `Money` (احتمالاً از int استفاده می‌کند) | ❌ |
| `intl` | اختیاری | `morilog/jalali` به آن نیاز ندارد | ❌ |
| MySQL/MariaDB | **۵.۷+ / ۱۰.۳+** | `utf8mb4`, JSON, ایندکس ترکیبی | ❌ |
| `memory_limit` | **≥ ۲۵۶M** | mPDF برای قرارداد/فیش | ❌ |
| `max_execution_time` | **≥ ۶۰s** | تولید PDF + گزارش | ❌ |
| `upload_max_filesize` | ≥ ۱۰M | آپلود قرارداد/فاکتور | ❌ |
| Cron | حداقل ۱ بار در روز | یادآوری | ❌ |
| SSH / Artisan | برای migration و بازیابی رمز | **پیش‌نیاز عملیاتی** | ❌ |

**نکتهٔ حیاتی:** ستون «وضعیت» همه ❌ است. تا این اسکریپت روی هاست اجرا نشود،
هر ادعای آمادگی **بی‌پایه** است.

---

## ۴. اسکریپت بررسی (باید اجرا شود)

`docs/tools/host-compatibility-check.php` (موجود از فاز ۰). خروجی JSON می‌دهد:

```bash
php host-compatibility-check.php > host-check.json
```

موارد بررسی: نسخهٔ PHP، extensionها، MySQL، نوشتن storage، memory، time، cron، SSL.

**این تنها کار شما در Gate A است** (به‌جز تأیید تصمیم‌ها).

---

## ۵. استقرار — ترتیب گام‌ها

```bash
# 1. آپلود کد (خارج از public_html)
# 2. نصب وابستگی‌ها (اگر Composer موجود نباشد: آپلود vendor/)
composer install --no-dev --optimize-autoloader

# 3. محیط
cp .env.example .env && chmod 600 .env
php artisan key:generate
# VIRA_CREDENTIAL_KEY را جدا بسازید (نه از APP_KEY)

# 4. دیتابیس
php artisan migrate --force
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=PermissionSeeder

# 5. رمز اولیه — امن
php artisan vira:reset-admin-password --password-file=/run/secrets/admin_pw

# 6. کش تولید
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 7. زمان‌بند
# crontab: * * * * * php /home/virauser/vira-crm/artisan schedule:run

# 8. مجوز فایل
chmod -R 775 storage bootstrap/cache
```

**هیچ‌کدام از این گام‌ها روی هاست اجرا نشده است.**

---

## ۶. بکاپ و بازیابی — الزام P0 که وجود ندارد

`REQ-15.02` بکاپ و **تست بازیابی** می‌خواهد. امروز **هیچ کد بکاپ وجود ندارد**.

| جزء | چه چیزی |
|-----|---------|
| دیتابیس | `mysqldump` یا `php artisan` command |
| فایل‌ها | `storage/app/private/` (قراردادها، PDFها، اسناد) |
| **کلید رمزنگاری** | **جدا از بکاپ** — وگرنه رمزها بازگشت‌ناپذیرند |
| فرکانس | روزانه (تصمیم OD-09) |
| نگهداری | ۷ روزانه + ۴ هفتگی + ۳ ماهانه (تصمیم) |
| خارج از سایت | آپلود به فضای خارجی یا دانلود دستی |
| **تست بازیابی** | **الزامی** — بکاپ بدون تست، بکاپ نیست |

**قاعدهٔ سخت:** در گزارش نهایی هرگز نمی‌نویسیم «بکاپ کار می‌کند» مگر با **خروجی
واقعی یک restore**.

---

## ۷. امنیت استقرار

| مورد | قاعده |
|------|-------|
| `.env` | مجوز ۶۰۰، خارج از web root، در `.gitignore` ✅ |
| `APP_DEBUG` | **false** در تولید |
| `APP_ENV` | `production` |
| HTTPS | اجباری + HSTS |
| هدرها | CSP, X-Frame-Options, X-Content-Type-Options ✅ (middleware موجود) |
| لاگ | `storage/logs/` خارج از public ✅ + بدون مقدار رمز |
| DB user | حداقل مجوز (فقط SELECT/INSERT/UPDATE/DELETE روی همان DB) |
| فایل آپلود | خارج از public، نام تصادفی ✅ (طراحی) |

---

## ۸. آنچه برای Gate B لازم است (فقط ۳ اقدام از شما)

1. **اجرای `host-compatibility-check.php`** روی هاست و ارسال خروجی JSON.
2. **تأیید ۴ تصمیم کسب‌وکار** (فرمول حقوق، مرز روز، آستانه‌های انقضا، placeholderها).
3. **تأیید افزودن mPDF** به‌عنوان موتور PDF.

بدون این سه، Gate B می‌تواند در ماژول‌های **غیروابسته** (پروژه‌ها، پرسنل، تنظیمات)
شروع شود، ولی PDF و حقوق و زمان‌بند **متوقف** می‌مانند.
