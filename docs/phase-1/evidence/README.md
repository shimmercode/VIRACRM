# Evidence — شواهد اجرایی فاز ۱

این پوشه فقط خروجی **اجراهای واقعی** را نگه می‌دارد. هیچ شاهد دست‌نویس پذیرفته نمی‌شود.

| فایل | تولید توسط | چه چیزی اثبات می‌کند |
|---|---|---|
| `jalali-smoke-test.json` | `node docs/tools/php-cli.mjs docs/src/jalali-smoke-test.php` | درستی تبدیل تاریخ شمسی↔میلادی، محاسبه D-2 بر مبنای روز تقویمی، مرز Timezone، و invariantهای ماه/سال |
| `php-runtime-smoke-test.json` | `node docs/tools/php-cli.mjs docs/src/php-runtime-smoke-test.php` | امکان‌پذیری AES-256-GCM با AAD و کلید مشتق‌شده، bcrypt، HMAC، محاسبات پولی `bcmath`، SQLite، نرمال‌سازی فارسی |

هر دو فایل شامل `generated_at`، `php_version`، آرایه بررسی‌ها با `status` و `verdict` نهایی است.
این شواهد مربوط به **امکان‌پذیری و الگوریتم‌های زیربنایی** است، نه تست ماژول محصول (که در
`docs/requirements/tests.catalog.yaml` با کد `T-*` و وضعیت `not-tested` ثبت شده است).
