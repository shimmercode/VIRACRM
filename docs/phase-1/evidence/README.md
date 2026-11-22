# Evidence — شواهد اجرایی فاز ۱

این پوشه فقط خروجی **اجراهای واقعی** را نگه می‌دارد. هیچ شاهد دست‌نویس پذیرفته نمی‌شود.
هر فایل شامل `generated_at`، `php_version`، آرایه بررسی‌ها با `status` و `verdict` نهایی است.

## شواهد معتبر (مبنای استناد)

| فایل | تولید توسط | چه چیزی اثبات می‌کند | وضعیت |
|---|---|---|---|
| `jalali-golden-verify.json` | `node docs/tools/php-cli.mjs docs/src/jalali-golden-verify.php` | الگوریتم `morilog/jalali` v3.5.0 روی ۴۱ مورد Golden Set (۷۵٪ با ۳ مرجع مستقل)، رفت‌وبرگشت روی ۱۱٬۳۲۳ روز، الگوی کبیسه، رد تاریخ نامعتبر، و کنترل منفی ۲۸۲۰ | **PASS** — PHP 8.3.36 و 8.2.35 |
| `d2-daymath-verify.json` | `node docs/tools/php-cli.mjs docs/src/d2-daymath-verify.php` | D-2 = دو روز تقویمی (نه ۴۸ ساعت)، مرز نیمه‌شب تهران/UTC، آفست ثابت +۰۳:۳۰، پایداری `unique_key`، استقلال کامل از تبدیل شمسی | **PASS** — ۲۲ بررسی، PHP 8.3.36 و 8.2.35 |
| `php-runtime-smoke-test.json` | `node docs/tools/php-cli.mjs docs/src/php-runtime-smoke-test.php` | امکان‌پذیری AES-256-GCM با AAD، bcrypt، HMAC، محاسبات پولی `bcmath`، SQLite، نرمال‌سازی فارسی | **PASS** — 10/10 |

## شواهد منسوخ (نگهداری فقط برای شفافیت — استناد نکنید)

| فایل | چرا منسوخ است |
|---|---|
| `jalali-smoke-test.json` | توسط موتور **حسابی ۲۸۲۰ ساله** تولید شده که با تقویم رسمی ایران در **۱۴۰۴** و **۱۴۳۷** اختلاف دارد. این موتور از مسیر محصول **حذف** شد. فایل فقط به‌عنوان مدرک کشف و تصحیح خطا نگه داشته شده است. |

جزئیات تصحیح: `docs/phase-1/19-calendar-date-policy.md` و
`docs/phase-1/01-architecture-decision-record.md` §8 (Addendum A).

## آنچه این شواهد اثبات *نمی‌کنند*

این‌ها شواهد **امکان‌پذیری و الگوریتم** هستند، نه تست ماژول محصول:

- هیچ ماژول CRM تست نشده است (کد محصول وجود ندارد).
- Composer/Artisan/MySQL/cron هیچ‌کدام در این محیط وجود ندارند؛ نصب واقعی `morilog/jalali`
  با Composer انجام نشده و `T-INF-02` در وضعیت **BLOCKED** است.
- اجرا روی **PHP-WASM** بوده، نه روی PHP نصب‌شده روی `crm.viraagency.co`.
  این تفکیک در `00-dev-environment-validation.md` (سطوح شواهد E1–E4) آمده است.
- تست‌های محصول در `docs/requirements/tests.catalog.yaml` با کد `T-*` و وضعیت واقعی
  (`not-tested` / `blocked`) ثبت شده‌اند.

## بازتولید

```bash
npm i @php-wasm/node@3.1.65          # یک‌بار
node docs/tools/php-cli.mjs docs/src/jalali-golden-verify.php
node docs/tools/php-cli.mjs docs/src/d2-daymath-verify.php
node docs/tools/php-cli.mjs docs/src/php-runtime-smoke-test.php
```
