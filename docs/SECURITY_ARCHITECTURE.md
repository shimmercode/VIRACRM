# معماری امنیت

هر عدد، نام هدر و نام ثابت در این سند از کد خوانده شده است، نه از حافظه. جایی که
چیزی **نیست**، صریح نوشته شده — چون «سکوت در سند» با «سکوت در کد» فرق دارد و
اولی خطرناک‌تر است.

## لایه‌ها

درخواست از این ترتیب عبور می‌کند (`bootstrap/app.php`):

```
TrustPreviewProxy → SecurityHeaders → session → auth
  → password.settled (EnsurePasswordChanged)
  → permission:<code> (EnsurePermission)
  → controller
```

ترتیب عمدی است و در کامنت `bootstrap/app.php` قید شده: `TrustPreviewProxy` باید
**قبل** از `SecurityHeaders` اجرا شود، چون ریشهٔ URL را بازنویسی می‌کند و CSP باید
بداند آیا این استقرار اجازهٔ frame شدن دارد.

## کنترل دسترسی

### سه سطح، هر سه لازم

1. **مسیر** — `permission:<code>` روی هر مسیر محافظت‌شده.
2. **رکورد** — مالکیت و سطح ردیف، در کوئری و در Policy.
3. **ابردمدیر** — `users.is_superadmin` از همهٔ بررسی‌ها عبور می‌کند.

ماتریس کامل در [`RBAC_PERMISSION_MATRIX.md`](RBAC_PERMISSION_MATRIX.md) است و از
پایگاه داده تولید می‌شود.

### «هر مسیر مجوز دارد» یک ادعا بود، حالا یک کنترل است

`routes/web.php` ادعا می‌کرد هر مسیر احراز هویت‌شده مجوزش را اعلام می‌کند. هیچ
بررسی‌ای این را نمی‌سنجید، پس ادعا بود نه کنترل: مسیری که بعداً بدون
`permission:` اضافه می‌شد، فقط پشت درِ احراز هویت می‌ماند و برای **هر** کاربر
واردشده باز بود.

`tools/checks/route-permissions.php` حالا این را می‌سنجد:

| اندازه | مقدار |
|---|---|
| کل مسیرها | 105 |
| مسیر احراز هویت‌شده | 101 |
| بدون مجوز | **0** |
| مجوز اعلام‌شده ولی ناموجود در جدول | **0** |
| استثنا با دلیل | 7 |

هفت استثنا (هر کدام با دلیل در همان فایل): `logout`، `password.change`،
`password.update`، `dashboard`، `review.index`، `review.health`،
`review.preview`. سه مسیر review مجوز ندارند چون سه کنترل قوی‌تر دارند:
`routes/review.php` فقط وقتی `config('review.enabled')` راست است ثبت می‌شود،
`ReviewController::index()` همان را به‌همراه فهرست مجاز محیط‌ها دوباره بررسی و در
غیر این صورت 404 می‌دهد، و هر صفحه با `authorizePage()` علیه مجوزِ اعلام‌شدهٔ خودش
سنجیده می‌شود. در استقرار پروداکشن اصلاً سطح `/review` وجود ندارد.

**این بررسی با یک مسیر ساختگی امتحان شد:** تزریق یک مسیر بدون مجوز خروجی را
`FAIL` و کد خروج را ۱ کرد؛ بعد از حذف، دوباره ۰. بررسی‌ای که نتواند شکست بخورد،
کنترل نیست.

### چند مجوز یعنی «هرکدام»

`EnsurePermission::handle()` روی هر مجوز اعلام‌شده حلقه می‌زند و با **اولین**
مجوزی که کاربر دارد عبور می‌کند. پس `permission:a,b` یعنی «a یا b». این را
`authz.php` و `authz-flow.mjs` می‌سنجند.

### نبودِ مجوز ۴۰۳ می‌دهد، و ثبت می‌شود

`EnsurePermission` قبل از `abort(403, 'شما به این بخش دسترسی ندارید.')`
`AuditRecorder::denied()` را صدا می‌زند. در پایگاه نمونه ۲۷ رکورد `denied` هست —
یعنی این مسیر واقعاً اجرا شده، نه اینکه نوشته شده باشد.

## سرصفحه‌های امنیتی

`app/Http/Middleware/SecurityHeaders.php` روی **هر** پاسخ:

| هدر | مقدار |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `same-origin` |
| `X-Permitted-Cross-Domain-Policies` | `none` |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=(), payment=()` |
| `Cache-Control` | `no-store, no-cache, must-revalidate, private` |
| `X-Robots-Tag` | `noindex, nofollow, noarchive, nosnippet` (فقط وقتی review فعال است) |

`Cache-Control` فقط وقتی ست می‌شود که کنترلر خودش چیزی نگذاشته باشد؛ صفحه‌ها
دادهٔ مشتری و فرادادهٔ اعتبارنامه دارند و هرگز نباید کش شوند.

### CSP

```
default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline';
script-src 'self'; font-src 'self'; form-action 'self';
frame-ancestors 'none'; base-uri 'self'; object-src 'none'
```

`'unsafe-inline'` روی `style-src` **عمدی** است و در کد کامنت دارد: attributeهای
کامپوننت Blade استایل درون‌خطی تولید می‌کنند. `script-src` بدون `unsafe-inline`
است، یعنی هیچ اسکریپت درون‌خطی اجرا نمی‌شود — این همان چیزی است که XSS را گران
می‌کند. `frame-ancestors 'none'` و `X-Frame-Options: DENY` هر دو هستند؛ یکی برای
مرورگرهای جدید، یکی برای قدیمی.

## احراز هویت

### رمز عبور

`PasswordController` — `Password::min(12)->letters()->mixedCase()->numbers()`،
`confirmed`، و `different:current_password`. تغییر رمز `remember_token` را می‌چرخاند،
پس نشست‌های دیگر باطل می‌شوند.

### نرخ و قفل

| مکانیزم | مقدار | منبع |
|---|---|---|
| تلاش مجاز | 5 | `LoginController::MAX_ATTEMPTS` |
| پنجرهٔ فروپاشی | 300 ثانیه | `LoginController::DECAY_SECONDS` |
| کلید محدودسازی | نام کاربری + IP | `throttleKey()` |
| محدودسازی سطح مسیر | `throttle:10,1` روی `POST /login` | `routes/web.php` |
| قفل حساب | `users.locked_until` | ستون در جدول |

هر دو شکست (`login_failed`) ثبت می‌شود؛ در پایگاه نمونه ۱۱ رکورد هست.

### نشست

`config/session.php`: عمر ۱۲۰ دقیقه، `http_only` راست، `same_site` = `lax`،
`secure` از محیط. `password.settled` کاربر را تا تعیین رمز عبور از بقیهٔ بخش‌ها
بیرون نگه می‌دارد، اما عمداً **بالای** گروه `password.settled` دو مسیر تغییر رمز
مانده‌اند تا کسی که مجبور به تغییر رمز است بتواند همان کار را بکند.

## رمزنگاری اعتبارنامه‌ها

`app/Services/Security/EnvelopeEncrypter.php` و `CredentialVault.php`:

| مورد | مقدار |
|---|---|
| الگوریتم | AES-256-GCM (رمزنگاری اصالت‌دار) |
| طول کلید | 32 بایت |
| nonce | 12 بایت، تصادفی برای **هر** رکورد |
| tag | 16 بایت |
| قالب خروجی | `"v1:" . base64(nonce ‖ tag ‖ ciphertext)` |

GCM به‌جای CBC انتخاب شده چون اصالت را بررسی می‌کند: ردیفِ دست‌کاری‌شده با خطا
شکست می‌خورد، نه اینکه متنِ بی‌معنی برگرداند.

**کلید از `VIRA_CREDENTIAL_KEY` می‌آید و اگر نباشد، `store()` قبل از نوشتن هر
چیزی استثنا می‌دهد.** این یعنی یک استقرار بدون کلید، اعتبارنامه را رمزنگاری‌نشده
ذخیره نمی‌کند — ذخیره نمی‌کند، نقطه. جایگزینی یک مقدار، اول ردیف قدیمی را حذف
می‌کند تا متن رمزشدهٔ کهنه نماند.

جدول‌ها جدا هستند: فراداده در `project_credentials`، مقدارها در
`credential_values.ciphertext`. جدا بودن یعنی یک `SELECT *` ساده روی جدول اصلی،
رازها را بیرون نمی‌دهد.

## گزارش رویدادها

جزئیات کامل در [`AUDIT_LOG_SPECIFICATION.md`](AUDIT_LOG_SPECIFICATION.md). نکتهٔ
امنیتی‌اش این است: **پوشش از یک نقطه عبور می‌کند.** هر رویداد از
`AuditRecorder` رد می‌شود و `AuditLog::redact()` روی مجموعهٔ تغییرات اجرا می‌شود،
پس فراموش کردنِ پوشاندن در یک کنترلر ممکن نیست.

## تهدیدهایی که این معماری پوشش نمی‌دهد

صادقانه، چون سندِ بدون این بخش توهم امنیت می‌سازد:

1. **SQL Injection** — کوئری‌ها با Eloquent و پارامتر بند شده‌اند، اما هیچ
   بررسی خودکاری وجود ندارد که یک کوئری خامِ تازه اضافهشده را بگیرد.
2. **CSRF** — به میدل‌ور `web` لاراول سپرده شده و آزمون اختصاصی CSRF در
   `tools/checks/` نیست.
3. **آپلود فایل** — در `FILE_SECURITY_THREAT_MODEL.md` آمده، نه اینجا.
4. **نشت از راه مرورگر** — `credential-shots` اثبات می‌کند راز در DOM نیست، اما
   این یک آزمون روی صفحاتِ شناخته‌شده است، نه اثبات کلی.
5. **کلیدها در محیط** — `VIRA_CREDENTIAL_KEY` و `APP_KEY` چرخش ندارند؛ سازوکار
   چرخش کلید پیاده‌سازی نشده است.

## آزمون‌ها

| ادعا | کجا سنجیده می‌شود |
|---|---|
| هر مسیر محافظت‌شده مجوز دارد | `tools/checks/route-permissions.php` (4/4) |
| مجوز اعلام‌شده در جدول وجود دارد | همان فایل |
| نبودِ مجوز → ۴۰۳ | `tools/checks/authz.php`، `tools/checks/authz-flow.mjs` (11/11) |
| سیاست‌های سطح رکورد | `tools/checks/policies.php` (14/14) |
| سرصفحه‌ها و CSP | `tools/checks/asset-origin-audit.mjs` (7/7) |
| راز در DOM نیست | `tools/checks/credential-shots.mjs` |
| صفحهٔ review فقط برای مجازها | `tools/checks/review-authz.mjs` (9/9) |
| ماتریس با پایگاه هم‌خوان است | `tools/checks/docs-integrity.mjs` |
