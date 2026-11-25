# VIRA CRM — بستهٔ تحویل

**شاخه:** `arena/3b43de84-viracrm`

---

## ۱. این بسته چیست — و چه چیزی **نیست**

این بسته **کد کامل پروژه** است.

> **`vendor/` فقط در بستهٔ production هست.** بستهٔ منبع ۱۷ MB است و برای
> اجرا به `composer install` نیاز دارد (بخش ۳). بستهٔ production ۲۵ MB است و
> `vendor/` را همراه دارد، برای هاستی که Composer ندارد.

> **این بسته «آمادهٔ تولید» نیست.** هیچ تستی روی هاست واقعی اجرا نشده است.
> محدودیت‌ها در بخش ۶.

---

## ۲. محتوای بسته

| مسیر | چه چیزی |
|------|---------|
| `app/` | ۶۴ فایل PHP: کنترلرها، مدل‌ها (۲۶)، سرویس‌ها، Policy |
| `resources/views/` | ۶۳ فایل Blade (همه کامپایل می‌شوند) |
| `resources/css/` | ۵ فایل CSS — **بدون Tailwind**، توکن‌محور |
| `public/build/` | دارایی ساخته‌شده: CSS، JS، فونت Vazirmatn (۴۵۲ KB) |
| `database/migrations/` | ۹ مهاجرت |
| `database/seeders/` | ۸ seeder |
| `tests/` | ۸ سوییت PHP |
| `tools/checks/` | ۶۴ فایل بررسی رگرسیون |
| `docs/` | ۱۲۲ سند + شواهد |
| `.env.example` | الگوی تنظیمات — همهٔ کلیدها خالی |

### عمداً حذف شده

`.env` · `database/*.sqlite` · `storage/logs/*` · `node_modules/` · `.git/`
و در بستهٔ منبع: `vendor/`

---

## ۳. نصب

پیش‌نیاز: PHP **8.2/8.3** با `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
`ctype`, `tokenizer`, `xml` · MySQL 8 / MariaDB 10.6+ · **Node لازم نیست**

```bash
# ۱) کد بیرون از public_html
unzip viracrm-*.zip -d ~/viracrm && cd ~/viracrm

# ۲) وابستگی‌ها (در بستهٔ production این پوشه از قبل هست)
composer install --no-dev --optimize-autoloader

# ۳) محیط
cp .env.example .env
php artisan key:generate

# ۴) کلید رمزنگاری دسترسی‌ها — جدا از APP_KEY
php -r 'echo "VIRA_CREDENTIAL_KEY=", base64_encode(random_bytes(32)), PHP_EOL;' >> .env

# ۵) دیتابیس در .env، سپس
php artisan migrate --force && php artisan db:seed --force

# ۶) مجوزها
chmod -R 755 storage bootstrap/cache
```

ساختار cPanel: کد در `/home/USER/viracrm/`، و فقط `public/` در
`public_html/`. راهنمای کامل: `docs/DEPLOYMENT_GUIDE.md`.

---

## ۴. بررسی سلامت پس از نصب

```bash
php tools/checks/routes.php    # مسیرها + میان‌افزار
php tools/checks/views.php     # کامپایل ۶۳ ویو
php tests/run.php              # ۸ سوییت
```

در مرورگر: `/login` باید با فونت وزیرمتن و رنگ `#450073` بیاید.
صفحهٔ پروژه → «دسترسی‌ها» → اگر بنر قرمز دیدید، `VIRA_CREDENTIAL_KEY` تنظیم نشده.

---

## ۵. آزمون‌ها

```bash
php tests/run.php                    # PHP — بدون مرورگر
node tools/checks/run-all.mjs        # کامل: ۳۵ سوییت، نیازمند Node + Playwright
```

نتیجهٔ اجرای کامل روی کامیت نهایی: **۳۵/۳۵ سوییت · ۱۰۳۳/۱۰۳۳ اَسِرشن ·
۰ ناموفق · ۰ ردشده** — لاگ در `docs/evidence/regression-final-*.log`.

---

## ۶. محدودیت‌های شناخته‌شده

| مورد | وضعیت واقعی |
|------|-------------|
| **حسابداری** | ❌ صفر کد. منتظر تأیید OD-41 (کدینگ حساب‌ها) |
| **ماژول فایل (Vira Drive)** | ❌ صفر کد. طراحی کامل هست |
| **خروجی PDF** | ❌ mPDF نصب نیست |
| **حقوق و دستمزد** | ❌ صفر کد. فرمول منتظر OD-16 |
| **یادآوری/زمان‌بندی** | ❌ هیچ cron تنظیم نشده |
| **فاکتور، گزارش، جست‌وجوی سراسری** | ❌ وجود ندارد |
| **نصب روی هاست واقعی** | ⏸ انجام نشده — دسترسی نبود |
| **Windows / WebDAV** | ⏸ تست نشده |
| **Google API** | ⏸ متوقف به دستور کارفرما |

### آنچه اثبات‌شده کار می‌کند

ورود/خروج · تغییر رمز · مشتریان · قراردادها · پروژه‌ها (سئو و طراحی) ·
**دسترسی‌های پروژه با AES-256-GCM** · RBAC روی همهٔ مسیرها · audit شامل رد
دسترسی · داشبورد · واکنش‌گرایی ۷ عرض · تقویم جلالی.

---

## ۷. حساب مدیر اولیه

`UserSeeder` نام کاربری را از `VIRA_ADMIN_USERNAME` و رمز را از
`VIRA_ADMIN_PASSWORD` می‌خواند. اگر تنظیم نشده باشند، seeder اجرا نمی‌شود.
رمز پیش‌فرض در کد یعنی رمز پیش‌فرض در Git — عمداً وجود ندارد.

---

## ۸. کجا را بخوانید

| پرسش | سند |
|------|-----|
| چه چیزی واقعاً کار می‌کند؟ | `docs/PROJECT_STATUS.md` |
| هر نیازمندی چه وضعی دارد؟ | `docs/REQUIREMENTS_TRACEABILITY.md` |
| چه تصمیمی منتظر ماست؟ | `docs/OPEN_DECISIONS.md` |
| سند مورد نظرم کجاست؟ | `docs/DOCUMENTATION_INDEX.md` |
| این چرخه چه تغییر کرد؟ | `docs/CHANGELOG.md` |
