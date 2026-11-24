# راهنمای استقرار — VIRA CRM

**وضعیت:** ✅ مراحل مستند · ❌ روی هاست اجرا نشده (`BLOCKED`، سند ۱۳)

---

## ۱. پیش‌نیازها

| مورد | حداقل | بررسی |
|------|-------|-------|
| PHP | ۸.۲ | `php -v` |
| افزونه‌ها | openssl, mbstring, pdo_mysql, gd, zip, dom, xml | `php -m` |
| MySQL | ۵.۷ / MariaDB ۱۰.۳ | `mysql --version` |
| Composer | ۲ | `composer -V` |
| Node | ۱۸+ (فقط build) | `node -v` |
| Memory | ۲۵۶M | `php -i \| grep memory_limit` |
| `max_execution_time` | ۶۰s | `php -i` |
| Cron | الزامی | cPanel |
| SSL | الزامی | — |

---

## ۲. مراحل

```bash
# ۱. کد
git clone <repo> && cd VIRACRM
composer install --no-dev --optimize-autoloader

# ۲. محیط
cp .env.example .env
php artisan key:generate

# ۳. دیتابیس — .env را پر کنید، سپس:
php artisan migrate --force

# ۴. دارایی‌ها
node tools/build-js.mjs
node tools/build-assets.mjs

# ۵. کش تولید
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ۶. دسترسی
chmod -R 775 storage bootstrap/cache
```

**سپس:** کاربر مدیر اولیه با `php artisan vira:create-admin` (پیشنهادی) — چون
reset رمز وب وجود ندارد.

---

## ۳. Cron

```cron
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

> **هشدار:** `routes/console.php` فقط `inspire` دارد. تا M5 هیچ یادآوری
> واقعی اجرا نمی‌شود.

---

## ۴. متغیرهای محیطی حساس

| متغیر | نقش |
|-------|-----|
| `APP_KEY` | رمزنگاری عمومی |
| `VIRA_CREDENTIAL_KEY` | **جدا** — اعتبارنامهٔ مشتریان |
| `DB_*` | اتصال دیتابیس |
| `APP_DEBUG` | **باید `false`** |

**هیچ‌کدام در git نیستند.**

---

## ۵. پس از استقرار — چک‌لیست

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] SSL فعال + redirect
- [ ] `robots.txt` با `Disallow: /`
- [ ] `X-Robots-Tag: noindex` روی صفحات داخلی
- [ ] ورود با کاربر تست + تغییر رمز
- [ ] `storage/` خارج از دسترس وب
- [ ] بکاپ راه‌اندازی شد (M8)
- [ ] Regression Suite روی هاست

**تا تیک نخورده، استقرار تمام نشده.**

---

## ۶. وضعیت واقعی

| مرحله | وضعیت |
|-------|-------|
| مستندسازی | ✅ کامل |
| اجرا روی هاست | **`BLOCKED`** |
| بررسی افزونه‌ها | **`BLOCKED`** |
| Cron | **`BLOCKED`** |
| بکاپ | `NOT IMPLEMENTED` |
