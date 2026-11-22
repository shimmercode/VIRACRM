# docs/tools — ابزارهای مهندسی فاز ۰/۱

> این ابزارها برای **توسعه و اعتبارسنجی** هستند، نه بخشی از محصول مستقر.

| ابزار | نقش | نیازمندی | مثال |
|---|---|---|---|
| `host-compatibility-check.php` | بررسی نسخه PHP، Extensionها، Cron/FS، اتصال DB روی هاست کارفرما | PHP ≥ 8.0 | `php host-compatibility-check.php --text` |
| `php-cli.mjs` | اجرای یک اسکریپت PHP واقعی (8.3) در Node با PHP-WASM | Node 18+، `@php-wasm/node@3.1.65` | `node docs/tools/php-cli.mjs docs/src/jalali-smoke-test.php` |
| `build_phase1_docs.py` | تولید RTM/داشبورد/شمارش تست از کاتالوگ‌های YAML | Python 3.9+، PyYAML | `python3 docs/tools/build_phase1_docs.py` |

## قواعد امنیتی

- `host-compatibility-check.php` بعد از استفاده **حذف** شود؛ در حالت وب فقط با توکن کار می‌کند.
- هیچ‌کدام از این ابزارها نباید داخل Document Root محصول باقی بمانند.
- `php-cli.mjs` هرگز در محیط Production اجرا نمی‌شود.

## نحوه به‌روزرسانی شمارش‌ها

هر تغییری در `docs/requirements/*.yaml` باید با اجرای `build_phase1_docs.py` همراه باشد؛
اعداد مستندات از همین مسیر بازتولید می‌شوند و هرگز دستی ویرایش نمی‌شوند.
