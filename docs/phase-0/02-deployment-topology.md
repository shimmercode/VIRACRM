# VIRA CRM — توپولوژی استقرار و انتقال (فاز ۰)

> این سند معماری **هدف** است؛ چون دسترسی به هاست تأیید نشده، هیچ‌کدام از این تنظیمات روی هاست
> اعمال نشده‌اند. `BLOCKED` = نیازمند اجرای کارفرما.

## ۱. توپولوژی هدف

```text
DNS
  viraagency.co        → 185.164.72.77  (سایت اصلی — وردپرس، دست‌نخورده)
  www.viraagency.co    → همان
  crm.viraagency.co    → 185.164.72.77  (CRM — مستقل)

فایل‌سیستم هدف (روی هاست):
  /home/virauser/
    ├── vira-crm/                 ← ریشه‌ی برنامه (خارج از public_html)
    │   ├── app/ bootstrap/ config/ database/ routes/ storage/ vendor/
    │   ├── resources/  (Blade, css, js, fonts)
    │   └── .env                  ← مجوز 600، خارج از دسترس وب
    └── public_html/
        └── crm/                  ← Document Root ساب‌دامین (فقط public/)
            ├── index.php
            ├── .htaccess
            ├── build/            ← Vite assets
            └── ...               ← هیچ فایل حساسی اینجا نیست
```

### حالت جایگزین (اگر Document Root مستقل ممکن نباشد)

```text
public_html/crm/
  ├── index.php                ← فقط فرانت‌کنترلر + مسیر ../vira-crm/bootstrap
  ├── .htaccess                ← deny برای storage/, vendor/, .env, *.sql, *.zip
  └── storage/ (لینک)          ← با دسترسی فقط-کنترلر
.env در /home/virauser/vira-crm/.env (خارج از public_html)
```
ریسک: حفاظت `.htaccess` ضعیف‌تر از «خارج از web root» است؛ در سند امنیت ثبت شده.

## ۲. فایل‌های استقرار (برنامه)

| فایل | نقش |
|---|---|
| `deploy/.env.example` | نمونه تنظیمات بدون مقدار محرمانه |
| `deploy/nginx-crm.conf.example` | نمونه کانفیگ Nginx اگر VPS انتخاب شد |
| `deploy/apache-vhost.conf.example` | نمونه VirtualHost |
| `deploy/cron.txt` | خط cron `schedule:run` |
| `deploy/DEPLOY.md` | گام‌به‌گام استقرار و انتقال |
| `scripts/compat-check.sh` | اجرای اسکریپت بررسی سازگاری روی هاست |

## ۳. متغیرهای محیطی مورد نیاز

| گروه | متغیرها |
|---|---|
| برنامه | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG=false`, `APP_URL=https://crm.viraagency.co` |
| دیتابیس | `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| رمزنگاری | `CREDENTIALS_KEYS=v1:<base64-32B>` (چرخش با `v2:` و `CREDENTIALS_ACTIVE_KEY=v2`) |
| نشست/کش | `SESSION_DRIVER`, `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=crm.viraagency.co` |
| صف | `QUEUE_CONNECTION=sync|database` |
| آرایه | `TIMEZONE=Asia/Tehran`, `APP_LOCALE=fa`, `APP_FAKER_LOCALE=fa_IR` |
| فایل | `FILESYSTEM_DISK=private`, مسیر `storage/app/private` |
| ایمیل (اختیاری) | `MAIL_*` |

> **هیچ‌کدام از این مقادیر در این مرحله دریافت/ذخیره نشده‌اند.** هیچ رمز واقعی در مخزن نیست.

## ۴. چک‌لیست انتقال (فاز ۶/۷)

- [ ] اجرای `host-compatibility-check.php` و تأیید PHP/Ext/DB/Cron
- [ ] ساخت دیتابیس + کاربر محدود
- [ ] تنظیم Document Root و SSL/HSTS
- [ ] آپلود بسته (ZIP) و استخراج خارج از `public_html`
- [ ] ساخت `.env` از نمونه + `php artisan key:generate`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=ProductionSeeder` (تنظیمات اولیه، بدون داده واقعی)
- [ ] `php artisan config:cache route:cache view:cache`
- [ ] تعیین `.env` و `storage/` مجوز درست
- [ ] cron `schedule:run`
- [ ] اجرای installer/ساخت مدیر اصلی
- [ ] قفل/حذف installer
- [ ] Backup اولیه + تست Restore
- [ ] تست ورود + ۵ سناریوی دود (smoke)
- [ ] ثبت گزارش انتقال

## ۵. گزینه‌های آینده (VPS)

اگر بعداً به VPS منتقل شد:
- همان کد، فقط `.env` و Document Root عوض می‌شود
- `QUEUE_CONNECTION=redis` و `schedule:work` با systemd
- Nginx + PHP-FPM + MySQL + Redis
- هیچ Migration یا تغییر کد لازم نیست (اصل A4)
