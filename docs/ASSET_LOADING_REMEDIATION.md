# ASSET_LOADING_REMEDIATION — رفع ایراد بارگذاری دارایی‌ها

> **وضعیت: رفع‌شده در محیط Preview — نیازمند تأیید روی هاست واقعی.**
> این سند **Production Verified نیست** و نباید چنین خوانده شود.
>
> تاریخ: ۲ آذر ۱۴۰۵ (2026-11-22) · پاسخ به درخواست بررسی نهایی، ۸ بند

---

## خلاصهٔ اجرایی

صفحهٔ ورود سه بار بدون استایل تحویل داده شد. هر بار یک شکل از «آدرس مطلق
اشتباه» اصلاح شد و مشکل برگشت. اصلاح ریشه‌ای: آدرس دارایی‌ها **نسبی به ریشه**
(root-relative) شد تا مرورگر آن‌ها را نسبت به همان origin که صفحه را از آن
گرفته حل کند.

**اما ادعای «درست است» محدود است.** این بررسی نشان داد `ASSET_URL=/` برای
**نصب روی ریشهٔ دامنه** درست است و برای **نصب در زیرمسیر خراب می‌شود**.
این نکته قبلاً مستند نشده بود و اکنون در `docs/DEPLOYMENT_GUIDE.md §4.1` هست.

| بند | موضوع | نتیجه |
|:-:|---|---|
| ۱ | ثبت خروجی واقعی `asset()` و Vite | ✅ انجام شد — **Vite وجود ندارد** |
| ۲ | نبود URL مطلق در HTML/CSS/JS/Build | ✅ صفر مورد در ۵ فایل |
| ۳ | Hard Reload با کش پاک | ✅ سه بار بارگذاری، هر دارایی ۳ درخواست شبکه |
| ۴ | ثبت Network (Status/Type/URL/Console) | ✅ ۱۵ درخواست، ۰ خطا |
| ۵ | سازگاری cPanel و Reverse Proxy | ⚠️ **فقط قاعدهٔ حل آدرس اثبات شد، نه cPanel** |
| ۶ | مستندسازی مسیر نصب و Base Path | ✅ بخش ۴.۱ اضافه شد |
| ۷ | بررسی پورت‌های ۴۱۷۳ و ۴۱۷۴ | ✅ خروجی خام در بند ۷ |
| ۸ | اجرای کامل Regression | ✅ ۳۱ سوئیت، ۰ Skipped |

---

## ۱. خروجی واقعی `asset()` و Vite

### Vite استفاده نمی‌شود

این اولین چیزی است که باید صریح گفته شود، چون درخواست شما فرض کرده بود Vite
هست:

```
$ ls package.json vite.config.*
package.json
  no package.json / no vite.config

$ grep -rn "@vite\|vite(" resources/views/
  (هیچ نتیجه‌ای)

$ grep -rn "Vite::\|hotFile\|manifest.json" app/ resources/
  (هیچ نتیجه‌ای)
```

`tools/build-assets.mjs` خودش توضیح می‌دهد چرا:

> «Vite نصب نیست... به‌جای آن زنجیرهٔ `@import` حل و CSS به‌ترتیب الحاق
> می‌شود، که تمام چیزی است که استایل‌شیت‌ها نیاز دارند.»

پس تمام دارایی‌ها از تابع `asset()` می‌آیند، نه از Vite. این یعنی بررسی
`ASSET_URL` کل مسیر را پوشش می‌دهد — هیچ مسیر دومی وجود ندارد که از آن
دور بزند.

### خروجی واقعی HTML

از سروری با تنظیمات دقیقاً مثل محیط Preview:

```html
<link rel="preload" href="/build/fonts/Vazirmatn-Regular.woff2" as="font" ...>
<link rel="stylesheet" href="/build/css/app.css?v=1795590068">
<script src="/build/js/app.js?v=1795518775" defer></script>
```

| سنجه | مقدار |
|---|---|
| کل URL دارایی در صفحه | ۳ |
| نسبت به ریشه (`/...`) | **۳ از ۳** |
| مطلق با هاست/پورت/پروتکل | **۰** |

---

## ۲. جست‌وجوی URL مطلق در خروجی و سورس

الگو: `(?:https?:)?//<host>[:port]/build/`

| فایل | حجم | نتیجه |
|---|--:|---|
| `public/build/css/app.css` | — | پاک |
| `public/build/js/app.js` | — | پاک |
| `resources/views/layouts/app.blade.php` | — | پاک |
| `resources/views/layouts/plain.blade.php` | — | پاک |
| `resources/views/auth/login.blade.php` | — | پاک |

**صفر مورد.** جزئیات در `docs/evidence/review/asset-origin-audit.json`
(کلید `absoluteUrlScan`).

> **نکتهٔ مهم:** در CSS، آدرس فونت‌ها `url('../fonts/...')` است — یعنی نسبی به
> خود فایل CSS، نه به دامنه. این عمداً است و مستقل از `ASSET_URL` کار می‌کند.

---

## ۳. Hard Reload با کش پاک

سه بارگذاری پشت سر هم، با پاک‌کردن cookie، localStorage، sessionStorage و
`caches` بین آن‌ها:

| بارگذاری | درخواست دارایی |
|---|---|
| ۱. بارگذاری اول | ۷ |
| ۲. reload پس از پاک‌کردن کش | ۷ |
| ۳. `?cachebust=<timestamp>` | ۷ |

هر دارایی **دقیقاً ۳ بار** از شبکه آمده — یعنی هیچ‌کدام از کش سرو نشد:

```
3x  /build/css/app.css
3x  /build/js/app.js
3x  /build/fonts/Vazirmatn-Regular.woff2
3x  /build/fonts/Vazirmatn-Medium.woff2
3x  /build/fonts/Vazirmatn-Bold.woff2
```

هدر کش خود CSS: `cache-control: public, max-age=3600` — یعنی مرورگر
**می‌تواند** کش کند، و همین است که تکرار با کش پاک ارزش دارد.

---

## ۴. ثبت Network

| # | Status | Type | Content-Type | مسیر | Same-Origin |
|:-:|:-:|---|---|---|:-:|
| ۱ | 200 | stylesheet | `text/css; charset=utf-8` | `/build/css/app.css` | ✅ |
| ۲ | 200 | script | `text/javascript; charset=utf-8` | `/build/js/app.js` | ✅ |
| ۳ | 200 | font | `font/woff2` | `/build/fonts/Vazirmatn-Regular.woff2` | ✅ |
| ۴ | 200 | font | `font/woff2` | `/build/fonts/Vazirmatn-Medium.woff2` | ✅ |
| ۵ | 200 | font | `font/woff2` | `/build/fonts/Vazirmatn-Bold.woff2` | ✅ |

هیچ redirect، هیچ ۴۰۴، هیچ خطای console.

### اندازهٔ واقعی هر پاسخ

| مسیر | حجم |
|---|--:|
| `/build/css/app.css` | 144,300 B |
| `/build/js/app.js` | 67,159 B |
| `/build/fonts/Vazirmatn-Regular.woff2` | 50,684 B |

### نتیجهٔ رندر در مرورگر

```
font-family → Vazirmatn      (فونت سفارشی اعمال شد، نه فونت پیش‌فرض)
background  → rgb(246,245,248)
sheetsLoaded → true
```

---

## ۵. cPanel و Reverse Proxy — مرز اثبات

**این بخش مهم‌ترین محدودیت این سند است.**

### آنچه واقعاً اندازه‌گیری شد

ماتریسی از سه سناریو با یک reverse proxy واقعی (Node) روی PHP-WASM:

| سناریو | نصب | `ASSET_URL` | CSS href | نتیجه |
|:-:|---|---|---|---|
| A | ریشهٔ دامنه | `/` | `/build/css/app.css` | ✅ استایل |
| B | زیرمسیر `/crm` | `/` | `/build/css/app.css` | ❌ **بدون استایل** |
| C | زیرمسیر `/crm` | `/crm` | `/crm/build/css/app.css` | ✅ استایل |

شواهد: `docs/evidence/review/asset-basepath-matrix.json`

**سناریو B یک یافتهٔ واقعی است، نه خطای تست.** اولین نسخهٔ ماتریس فرض کرده
بود root-relative «همیشه کار می‌کند»؛ اندازه‌گیری خلافش را نشان داد:
مرورگر `/build/app.css` را نسبت به **ریشهٔ دامنه** حل می‌کند، نه پوشهٔ برنامه.

### آنچه اندازه‌گیری **نشد**

| مورد | چرا |
|---|---|
| Apache / mod_rewrite | محیط Apache در دسترس نیست |
| cPanel / LSAPI / PHP-FPM | همان |
| `SCRIPT_NAME` و رفتار زیرمسیر | PHP-WASM مقدار متفاوتی می‌دهد |
| `.htaccess` واقعی | فقط وجودش تأیید شد، اجرا نشد |
| HTTPS واقعی + HSTS | پراکسی محلی http بود |

**بنابراین: قاعدهٔ حل آدرس اثبات شد، سازگاری cPanel نه.**

### رفتار روی هاست واقعی (استدلال، نه اندازه‌گیری)

اگر `ASSET_URL` تعریف **نشود**، آدرس‌ها مطلق و بر اساس `APP_URL` ساخته
می‌شوند. تأیید شد:

```
$ curl -H "Host: crm.example.com" http://127.0.0.1:4199/login | grep app.css
http://crm.example.com/build/css/app.css
```

یعنی روی هاست واقعی که `APP_URL` را صریح تنظیم می‌کنید، رفتار پیش‌فرض درست
است و `ASSET_URL=/` اصلاً لازم نیست. **این استدلال است، نه اثبات روی هاست.**

---

## ۶. مستندسازی

| فایل | تغییر |
|---|---|
| `docs/DEPLOYMENT_GUIDE.md` | بخش **۴.۱** اضافه شد: قاعده، جدول سه حالت، روش تشخیص، و توضیح چرا Vite نیست |
| `docs/DEPLOYMENT_GUIDE.md §5` | سه آیتم چک‌لیست دربارهٔ `ASSET_URL` و استایل صفحهٔ ورود |
| `.env.example` | بلوک توضیح `ASSET_URL` با سه حالت و ارجاع به بند ۴.۱ |

مسیر نصب فعلی (این محیط):

```
/home/user/VIRACRM          ← ریشهٔ مخزن
/home/user/VIRACRM/public   ← document root
```

---

## ۷. پورت‌های ۴۱۷۳ و ۴۱۷۴ — خروجی خام

```
$ ss -ltnp | grep -E ':(4173|4174)\b'
LISTEN 0      511          0.0.0.0:4173       0.0.0.0:*    users:(("node",pid=851979,fd=24))

$ pgrep -af 'tools/dev-server.mjs'
851979 node tools/dev-server.mjs --port 4173

$ curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:4174/login
000        ← connection refused، هیچ listener
$ curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:4173/login
200
```

| پورت | وضعیت | Listener |
|:-:|---|---|
| 4173 | ✅ فعال — محیط بازبینی | PID 851979 |
| 4174 | ✅ آزاد | هیچ‌کس |

### چرا قبلاً ۴۱۷۴ مشکل بود

سرور تستی که دستی روی ۴۱۷۴ بالا آورده بودم، پس از پایان کار متوقف نشد و
پراکسی پیش‌نمایش آن را هم نمایش می‌داد. شما آن را باز کردید و صفحهٔ بدون
استایل دیدید. اکنون:

- هر دو سوئیت جدید (`review-authz`، `review-embed`) سرور خودشان را روی پورت
  آزاد می‌سازند و در پایان می‌بندند.
- دو اسکریپت حسابرسی این سند هم سرورهایشان را در `finally` می‌بندند — حتی
  اگر یک سناریو خطا بدهد.

**این باگ در جریان همین بررسی پیدا و رفع شد:** اجرای اول ماتریس، دو listener
رهاشده روی ۳۵۹۰۷ و ۳۵۳۳۱ گذاشت. پس از افزودن `finally`، اجرای مجدد **صفر**
نشتی داشت.

---

## ۸. Regression

```
══════════════════════════════════════════
suites     33 total · 33 executed · 33 passed · 0 failed · 0 skipped
assertions 1008 executed · 1008 passed · 0 failed · 0 skipped
isolation  shared database untouched (every pass)
REGRESSION SUITE PASSED
```

### تفکیک

| سنجه | مقدار |
|---|--:|
| سوئیت کل | 33 |
| اجراشده | 33 |
| Passed | 33 |
| Failed | 0 |
| **Skipped** | **0** |
| Assertion اجراشده | 1008 |
| Assertion Passed | 1008 |
| Assertion Failed | 0 |
| Assertion Skipped | 0 |

### دو سوئیت جدید این بررسی

| سوئیت | Assertions | چه چیزی را می‌سنجد |
|---|--:|---|
| `asset-origin` | 7 | URLهای تولیدشده، اسکن مطلق، Network، فونت، console |
| `asset-basepath` | 3 | ماتریس سه‌سناریویی نصب |

**حساب می‌خواند:** ۹۹۸ + ۷ + ۳ = ۱۰۰۸. یعنی هیچ سوئیتی صفر assertion
اضافه نکرده — همان «pass بی‌صدا» که قبلاً یک بار رخ داده بود.

### تفکیک کامل سوئیت‌ها

| سوئیت | نتیجه |
|---|---|
| unit | 7/7 |
| views | 60/60 |
| authorization | OK |
| policies | 14/14 |
| docs-integrity | 49/49 |
| flow-login | 8/8 |
| flow-authed | 20/20 |
| flow-authz | 11/11 |
| flow-crud | 18/18 |
| flow-projects | 28/28 |
| css-audit | 42/42 |
| contrast | OK |
| unstyled | OK |
| tokens | OK |
| calendar | OK |
| a11y | 10/10 |
| login-shots | 24/24 |
| ui-shots | 290/290 |
| table-cards | 21/21 |
| overflow-1024 | 44/44 |
| design-system | 21/21 |
| filter-chips | 22/22 |
| regression-matrix | 218/218 |
| runner-contract | 11/11 |
| lock-concurrency | 15/15 |
| review-integrity | 9/9 |
| review-authz | 9/9 |
| review-embed | 9/9 |
| **asset-origin** | **7/7** |
| **asset-basepath** | **3/3** |
| decision-audit | 19/19 |
| decision-pack | 2/2 |
| review-report | 17/17 |

**هیچ Skipped وجود ندارد؛ بنابراین هیچ چیزی به‌عنوان Passed گزارش نشده.**
سوئیت‌های `required: true` که اجرا نشوند، `run-all` را با کد ۲ fail می‌کنند،
نه skip. `runner-contract` این را می‌آزماید و اگر سوئیتی `required` نباشد،
شکست می‌خورد — که دقیقاً زمانی رخ داد که `review-embed` را ابتدا optional
گذاشته بودم.

### وضعیت پورت‌ها پس از اجرای کامل

```
$ ss -ltnp | grep -E ':(4173|4174)\b'
LISTEN 0  511  0.0.0.0:4173  0.0.0.0:*  users:(("node",pid=851979,fd=24))

$ pgrep -af tools/dev-server.mjs
851979 node tools/dev-server.mjs --port 4173
```

پس از ۳۳ سوئیت، **صفر listener رهاشده**. تمام سوئیت‌های مرورگری سرور خودشان
را می‌سازند و می‌بندند.

---

## فایل‌های شواهد

| فایل | محتوا |
|---|---|
| `docs/evidence/review/asset-origin-audit.json` | URLهای تولیدشده، اسکن مطلق، ۲۱ رکورد شبکه، رندر DOM |
| `docs/evidence/review/asset-basepath-matrix.json` | ماتریس سه‌سناریویی Base Path |
| `docs/evidence/review/asset-audit-login.png` | اسکرین‌شات صفحهٔ ورود در زمان حسابرسی |
| `docs/evidence/review/login-real-host.png` | صفحهٔ ورود از طریق هاست پیش‌نمایش واقعی |
| `/tmp/final-regression.log` | خروجی کامل ۳۳ سوئیت (موقت — در repo نیست) |

ابزار تولید:

```bash
node tools/checks/asset-origin-audit.mjs
node tools/checks/asset-basepath-matrix.mjs
```

---

## وضعیت نهایی

| ادعا | وضعیت |
|---|---|
| دارایی‌ها در Preview از همان Origin می‌آیند | ✅ **اثبات‌شده** (۱۵ درخواست، ۰ خطا، ۳ بارگذاری) |
| هیچ URL مطلق ثابتی باقی نمانده | ✅ **اثبات‌شده** (۵ فایل اسکن شد) |
| قاعدهٔ حل آدرس در سه حالت نصب | ✅ **اثبات‌شده** (ماتریس A/B/C) |
| سازگاری با Apache/cPanel واقعی | ❌ **تأیید نشده — Blocked** |
| سازگاری با reverse proxy واقعی (nginx/Cloudflare) | ❌ **تأیید نشده — Blocked** |
| Production Verified | ❌ **نیست** |

**پیشنهاد برای بستن کامل:** روی هاست واقعی، پس از استقرار، این سه دستور:

```bash
curl -s https://<دامنه>/login | grep -E 'app\.(css|js)'
curl -sI https://<دامنه>/build/css/app.css
```

اگر `href` مسیر درست را نشان داد و CSS با `200` و `text/css` برگشت،
می‌توان این مورد را از «Preview» به «Production Verified» ارتقا داد.
