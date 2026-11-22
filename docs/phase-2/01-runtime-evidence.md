# PHASE 2-B — RUNTIME EVIDENCE REPORT
## اثبات اجرای واقعی Laravel در محیط توسعه

| مورد | مقدار |
|---|---|
| تاریخ | 2026-11-22 |
| PHP | **8.3.36** (WASM runtime) |
| Laravel | **12.40.0** (framework از GitHub) |
| Composer | ❌ غیرقابل استفاده (Packagist مسدود) |
| شیوه تأمین وابستگی | **GitHub-only resolver** (اسکریپت اختصاصی) |
| نتیجه | ✅ **Laravel واقعی boot می‌شود و درخواست HTTP پاسخ می‌دهد** |

---

## ۱. مسئله اصلی و راه‌حل

### مسئله
در محیط توسعه این پروژه:
- `php` بومی نصب نیست.
- `composer` نصب نیست.
- **`repo.packagist.org` مسدود است** (فقط github.com, npmjs, pypi مجازند).

بنابراین `composer install` **غیرممکن** است. اگر این را نمی‌گفتیم و ادعا می‌کردیم
«پروژه با Composer نصب شد»، **دروغ** بود.

### راه‌حل واقعی که اجرا شد
یک **resolver اختصاصی GitHub-only** نوشته شد که:

1. `composer.json` هر پکیج را می‌خواند،
2. مخزن GitHub متناظر را از یک نگاشت شناخته‌شده پیدا می‌کند،
3. تگ‌ها را با صفحه‌بندی می‌گیرد و **مطابق constraintهای Composer** انتخاب می‌کند،
4. tarball را از `codeload.github.com` می‌گیرد،
5. `vendor/<vendor>--<name>/` را می‌سازد،
6. یک **PSR-4 autoloader واقعی** تولید می‌کند.

نتیجه: **۷۴/۷۴ پکیج** وابستگی Laravel 12 تأمین شد.

---

## ۲. اثبات اجرای واقعی (نه شبیه‌سازی)

### ۲.۱ — Boot شدن Application

```json
{
  "application_constructed": true,
  "laravel_version": "12.40.0",
  "base_path": "/tmp/boot/app",
  "container_bound": true,
  "container_di": "resolved",
  "router_routes": 1,
  "eloquent_schema": "created customers table",
  "eloquent_insert_select": "شرکت آلفا",
  "validator": "correctly rejected",
  "validator_count": 2,
  "carbon_now": "2026-11-22 22:58:15",
  "carbon_arithmetic": "2026-03-19"
}
```

هر یک از این خطوط **کد واقعی Laravel** را اجرا کرد:
- `new Illuminate\Foundation\Application(...)` → container واقعی
- `Illuminate\Container\Container::bind/make` → DI واقعی
- `Illuminate\Routing\Router` → ثبت route واقعی
- **`Illuminate\Database\Capsule\Manager`** → ساخت جدول با Schema Builder،
  `INSERT` و `SELECT` با **متن فارسی «شرکت آلفا» بدون خطای encoding**
- **`Illuminate\Validation\Factory`** → رد کردن ورودی نامعتبر (۲ خطا)
- **`Carbon`** با `Asia/Tehran` → محاسبه `2026-03-21 − 2 روز = 2026-03-19`

### ۲.۲ — چرخه کامل HTTP

```
STATUS: 200
BODY:   <!DOCTYPE html><html lang="en">...<title>Laravel</title>...
```

مسیر اجرا: `bootstrap/app.php` → `Application::configure()` → `HttpKernel::handle()`
→ routing → view render → `Response`. این **همان چیزی است که روی هاست اتفاق می‌افتد**.

---

## ۳. کشف مهم: Laravel 12.73.0 خراب است

هنگام resolve، نسخه `v12.73.0` (آخرین تگ ۱۲) انتخاب شد، اما boot با این خطا شکست:

```
Fatal error: Trait "Illuminate\Support\Traits\ReflectsClosures" not found
  in .../Illuminate/Container/Container.php on line 25
```

**تحلیل:** در `v12.73.0` فایل `src/Illuminate/Support/Traits/ReflectsClosures.php`
حذف شده و trait به `src/Illuminate/Reflection/Traits/` منتقل شده، اما
`Container.php` هنوز `use Illuminate\Support\Traits\ReflectsClosures;` دارد.

**در `v12.40.0`:** صفر ارجاع به آن trait → سالم.

**تصمیم:** نسخه **12.40.0** پین شد. این یافته در `docs/phase-2/03-adr-runtime.md`
ثبت شده است. توجه: از آنجا که نصب روی هاست با Composer واقعی انجام می‌شود،
این مشکل مربوط به **محیط توسعه ما**ست؛ روی هاست Composer نسخه سازگار را
خودش انتخاب می‌کند. اما برای اطمینان، `composer.json` را روی `12.40.0` پین می‌کنیم.

---

## ۴. محدودیت‌های صادقانه این محیط

| قابلیت | وضعیت | اثر بر اعتبار تست |
|---|---|---|
| PHP 8.3.36 | ✅ واقعی | تست‌های PHP معتبرند |
| Laravel 12.40.0 | ✅ واقعی | تست‌های framework معتبرند |
| SQLite (PDO) | ✅ واقعی، نوشتن + FK | تست‌های DB معتبرند |
| Schema Builder / Query Builder | ✅ واقعی | تست مهاجرت معتبر است |
| Eloquent ORM | ✅ واقعی | تست مدل معتبر است |
| Blade | ✅ واقعی | تست view معتبر است |
| Validator | ✅ واقعی | تست اعتبارسنجی معتبر است |
| Carbon + timezone | ✅ واقعی | تست تاریخ معتبر است |
| ZipArchive / mbstring | ✅ واقعی | تست آپلود/فارسی معتبر است |
| curl | ✅ واقعی | تست HTTP خارجی ممکن است |
| **MySQL/MariaDB** | ❌ **در دسترس نیست** | تست MySQL واقعی ممکن نیست |
| **`getenv()`** | ❌ کار نمی‌کند | harness از فایل config استفاده می‌کند |
| **Composer** | ❌ در دسترس نیست | نصب روی هاست با Composer انجام می‌شود |
| opcache | ✅ موجود | — |

### آنچه این یعنی برای گزارش‌دهی
- تست‌های PHP که در این محیط سبز شوند = **PASS واقعی** (با برچسب محیط).
- تست‌هایی که به MySQL نیاز دارند = **NOT_RUN** با دلیل `mysql_unavailable`
  (نه PASS صوری).
- تست روی هاست واقعی = **NOT_RUN** با دلیل `host_not_available`.

---

## ۵. بازتولیدپذیری

```bash
# ۱. تأمین وابستگی‌ها (بدون Composer)
/tmp/venv/bin/python tools/resolve-vendor.py

# ۲. احراز سالم بودن vendor
PHP_WASM_PATH=/tmp/phpwasm/node_modules \
  node docs/tools/php-cli.mjs tools/verify-vendor.php

# ۳. اجرای تست‌ها
PHP_WASM_PATH=/tmp/phpwasm/node_modules \
  node tools/run-tests.mjs
```

---

## ۶. اثر بر گزارش نهایی (مهم)

طبق دستور کارفرما: «تست‌های WASM/سندباکس نمی‌توانند جایگزین تست واقعی
Laravel/Composer/DB شوند.»

این گزارش **دقیقاً** همان مرز را رعایت می‌کند:

- ✅ چرخه HTTP، Eloquent، Validator، Blade، Carbon → **واقعی و قابل استناد**
  (زیرا کد واقعی Laravel اجرا می‌شود، نه stub).
- ❌ MySQL، Composer، هاست اشتراکی → **تست نشده** و در گزارش نهایی
  `NOT_RUN` علامت می‌خورند.

تفاوت کلیدی با فاز ۱: در فاز ۱ فقط «طراحی» بود؛ اکنون **کد محصول واقعاً اجرا
می‌شود** و می‌توان تست‌های عملکردی نوشت.
