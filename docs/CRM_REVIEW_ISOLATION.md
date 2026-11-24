# CRM_REVIEW_ISOLATION — جداسازی از تولید، سند به سند

> ادعای «هیچ اتصالی به تولید وجود ندارد» فقط وقتی معنا دارد که هر لایه
> جداگانه اثبات شود. این سند همان اثبات است.

## ۱. لایه‌های جداسازی

| # | لایه | مکانیزم | اثبات |
|---|---|---|---|
| ۱ | فعال‌سازی | `VIRA_REVIEW_ENABLED` (پیش‌فرض `false`) | T1 |
| ۲ | مسیرها | وقتی خاموش است، `routes/review.php` ثبت **نمی‌شود** → ۴۰۴ | `review-integrity.mjs` |
| ۳ | محیط اجرا | `guard()` فقط `local`/`review`/`testing` را می‌پذیرد | کد کنترلر |
| ۴ | پایگاه داده | فایل جداگانه در `database/review/` | T3 |
| ۵ | فایل پایه | فقط خوانده می‌شود؛ هش قبل/بعد یکسان | T3b |
| ۶ | سیدر | مسیر خارج از `database/review/` را رد می‌کند | تست‌شده |
| ۷ | احراز هویت | هر مسیر پشت `auth`؛ ۰ پاسخ ۲۰۰ بی‌نشست | T2 |
| ۸ | مجوز | همان `hasPermission()` محصول | T11 |
| ۹ | نوشتن | فقط GET؛ هیچ POST روی `/review/*` | `review-integrity.mjs` |
| ۱۰ | ایمیل/SMS/پرداخت | هیچ کد ارسالی در این محیط وجود ندارد | جست‌وجوی کد |
| ۱۱ | ایندکس شدن | هدر + متا + `robots.txt` | T5 |
| ۱۲ | مخزن | فایل دیتابیس و رمزها در `.gitignore` | `git check-ignore` |

## ۲. سد راه‌های کد — با نقل مستقیم

### ۲.۱ کلید اصلی

```php
// config/review.php
'enabled' => env('VIRA_REVIEW_ENABLED', false),
```

پیش‌فرض `false` است. یک استقرار تولیدی که این متغیر را ست نکند،
هرگز `/review` را نمی‌بیند.

### ۲.۲ ثبت شرطی مسیرها

```php
// bootstrap/app.php
->withRouting(
    ...
    then: function () {
        if (config('review.enabled')) {
            Route::middleware('web')->group(base_path('routes/review.php'));
        }
    },
)
```

وقتی خاموش است، مسیر **وجود ندارد** — نه اینکه ۴۰۳ بدهد. تفاوت مهم است:
مسیر ناموجود سطح حمله ندارد.

### ۲.۳ نگهبان محیط

```php
private function guard(): void
{
    $allowed = (array) config('review.allowed_environments', ['local']);

    abort_unless(config('review.enabled') === true, 404);
    abort_unless(in_array(app()->environment(), $allowed, true), 404);
}
```

هر دو `abort` به ۴۰۴ می‌رسند، نه ۴۰۳ — تا وجود محیط بازبینی هم لو نرود.

### ۲.۴ رد کردن مسیر نادرست در سیدر

```
REFUSED: /home/user/VIRACRM/.arena/flows/manual/database.sqlite
         is outside the review data directory (/home/user/VIRACRM/database/review).
```

این پیام در حین کار واقعاً دیده شد: تست نقش‌ها مسیر اشتباهی پیشنهاد داد
و سیدر آن را رد کرد. محافظ کار می‌کند.

## ۳. جداسازی پایگاه داده — با عدد

| سنجه | مقدار |
|---|---|
| فایل بازبینی | `database/review/crm-review.sqlite` |
| فایل پایه | `database/database.sqlite` |
| هش پایه قبل از seed | ثبت‌شده |
| هش پایه بعد از ۳ بار seed + ۱ reset | **یکسان** |
| شمارش پایه | `users=2 customers=7 contracts=5 projects=3 work=9` |
| شمارش پس از seed | `users=6 customers=19 contracts=13 projects=11 work=68` |
| شمارش پس از reset | دقیقاً برابر پایه |

هیچ‌کدام از این اعداد دستی نوشته نشده‌اند؛ `review-seed-check.mjs` آن‌ها را
می‌خواند و در `docs/evidence/review/reset-check.json` می‌نویسد.

## ۴. داده ساختگی — چه چیزی تضمین می‌شود

| بررسی | روش | نتیجه |
|---|---|---|
| ایمیل غیرقابل‌ارسال | دامنه در `.example.com` / `.invalid` / `example.com` | ۵۶ assertion، CLEAN |
| صفر دامنه `.ir` | پیمایش `customer_websites` | ✔ |
| کد ملی نامعتبر | رقم کنترل عمداً غلط | ✔ (بعد از اصلاح نقص) |
| حساب‌های audit | فقط `admin`/`viewer1`/`review.*` | ✔ |

**نقصی که پیدا شد:** نسخهٔ اول سیدر کد ملی‌هایی می‌ساخت که رقم کنترل
واقعی ایران را پاس می‌کردند — یعنی از شناسهٔ یک شخص واقعی قابل تفکیک
نبودند. Audit آن را گرفت و تولیدکننده اصلاح شد.

## ۵. چه چیزی **هنوز** جداسازی نشده

| مورد | وضعیت | چرا مهم است |
|---|---|---|
| ذخیره‌سازی فایل | وجود ندارد | ماژول فایل نوشته نشده |
| صف (Queue) | استفاده نمی‌شود | هیچ job‌ای در این محیط اجرا نمی‌شود |
| Cache | مشترک با فرایند | اگر روی یک ماشین با تولید اجرا شود، کلیدها تفکیک نشده‌اند |
| Session | فایل‌محور، مشترک با فرایند | همان مشکل |
| کلید `APP_KEY` | از `.env` مشترک | اگر این محیط روی ماشین تولیدی اجرا شود، کوکی‌ها قابل تعویض نیستند |

**نتیجه صریح:** جداسازی در سطح *داده* کامل است. جداسازی در سطح *فرایند*
فقط وقتی برقرار می‌شود که این محیط روی یک ماشین جدا اجرا شود — و در این
فاز روی یک sandbox جدا اجرا شده است، ولی هیچ ادعایی دربارهٔ استقرار
چندنمونه‌ای (multi-tenant) نمی‌شود.

## ۶. چک‌لیست پیش از هر استقرار

```bash
node tools/checks/review-acceptance.mjs      # باید ۱۳/۱۳ باشد
node tools/checks/review-seed-check.mjs      # باید PASS
node docs/tools/php-cli.mjs tools/checks/review-data-audit.php \
     database/review/crm-review.sqlite       # باید CLEAN
```

اگر هر یک شکست خورد، محیط بازبینی نباید منتشر شود.
