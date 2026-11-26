# فهرست اقلام انتشار (Release Manifest)

> **تولیدشده.** با `node tools/generate-release-manifest.mjs` از درخت کاری ساخته
> می‌شود و `docs-integrity` هم‌خوانی‌اش را می‌سنجد. دستی ویرایش نکنید.
>
> عمداً **بدون مهر زمانی و بدون هش commit** است: آن‌ها باعث می‌شدند فایل در هر
> commit متفاوت شود، `--check` هرگز پاس نشود، و سند دوباره به تزئین تبدیل شود.
> اینکه انتشار از کدام commit بریده شده را خودِ git نگه می‌دارد و نیازی به نسخهٔ
> دومش نیست.

## موجودی

| مورد | تعداد |
|---|---:|
| فایل‌های انتشار (tracked + غیر‌ignore) | **1012** |
| فایل PHP | 300 |
| قالب Blade | 93 |
| مهاجرت پایگاه داده | 14 |
| سند Markdown زیر `docs/` | 150 |
| بررسی در `tools/checks/` | 79 |
| فایل آزمون | 17 |

### بر پایهٔ پسوند

| پسوند | تعداد |
|---|---:|
| `.php` | 300 |
| `.png` | 300 |
| `.md` | 168 |
| `.mjs` | 68 |
| `.csv` | 38 |
| `.json` | 37 |
| `.py` | 28 |
| `.(none)` | 18 |
| `.dot` | 12 |
| `.svg` | 12 |

## اقلام همراه انتشار

هش‌ها SHA-256 و ۱۶ رقم اول‌اند. اگر فایلی در این جدول `MISSING` شد، یعنی وجود
ندارد — یعنی منتشر نمی‌شود.

| فایل | اندازه | SHA-256 (۱۶ رقم) | چیست |
|---|---:|---|---|
| `composer.json` | 2.4 KB | `f99c377228f87537` | وابستگی‌های PHP |
| `.env.example` | 2.6 KB | `1fd058096069454d` | الگوی متغیرهای محیط |
| `tools/build-assets.mjs` | 2.1 KB | `9fa6bf24f117fea2` | سازندهٔ دارایی‌ها — در مقصد اجرا می‌شود |

### تعریف پایگاه داده — خودِ فایل منتشر نمی‌شود

`database/database.sqlite` در `database/.gitignore` است (`*.sqlite*`)، پس
**با مخزن سفر نمی‌کند** و در شمارش بالا هم نیست. در مقصد باید با
`migrate` و `db:seed` ساخته شود — طبق `docs/DEPLOYMENT_GUIDE.md`.

هش هم عمداً **ندارد**، و دو دلیل دارد: اول اینکه اصلاً در انتشار نیست؛ دوم اینکه
بایت‌هایش هر بار که برنامه لمسش کند عوض می‌شود، حتی وقتی تعداد ردیف‌ها یکسان
بماند. هشی که با یک ورود به سیستم تغییر کند انتشار را شناسایی نمی‌کند و فقط
gate را الکی قرمز می‌کند. آنچه طرح و دادهٔ نمونه را واقعاً تعریف می‌کند
مهاجرت‌ها و seederها هستند:

| مورد | مقدار |
|---|---|
| فایل مهاجرت + seeder | 27 |
| SHA-256 ترکیبی (۱۶ رقم) | `9312bc9218637196` |

اگر این هش با آنچه در مقصد می‌بینید فرق داشت، طرح یا دادهٔ نمونه فرق کرده است.

## ساخته‌شده در مقصد — در git نیست

`/public/build` در `.gitignore` است، پس این فایل‌ها **با مخزن سفر نمی‌کنند** و
جزء شمارش بالا نیستند. `docs/DEPLOYMENT_GUIDE.md` به‌جای آن
`node tools/build-assets.mjs` را روی مقصد اجرا می‌کند.

هش زیر مربوط به ساختِ همین ماشین است و برای **مقایسه** آمده: اگر خروجی مقصد این
هش را نداد، یعنی دارایی‌ها درست ساخته نشده‌اند.

| فایل | اندازه | SHA-256 (۱۶ رقم) |
|---|---:|---|
| `public/build/css/app.css` | 158.5 KB | `912bf8f3e034c617` |
| `public/build/js/app.js` | 73.7 KB | `133a55091eaad398` |

## وابستگی‌ها

PHP: `^8.2`

| بسته | قید نسخه |
|---|---|
| `laravel/framework` | `12.40.0` |
| `mpdf/mpdf` | `^8.2` |

`composer.lock` در مخزن commit نشده است (چون Packagist در محیط توسعه در دسترس
نبود). در عوض `tools/resolve-vendor.py` وابستگی‌ها را با هش ثابت حل می‌کند و
`--verify` تعداد بسته‌های نصب‌شده را تأیید می‌کند.

**این یک ریسک واقعی انتشار است:** بدون `composer.lock`، نسخهٔ دقیقِ
وابستگی‌های غیرمستقیم روی ماشین مقصد ممکن است با آنچه اینجا آزموده شد فرق کند.

## فهرست اسناد مورد انتظار

هر 20 سندِ مورد انتظار وجود دارد:

- `docs/ARCHITECTURE.md`
- `docs/DATABASE_SCHEMA.md`
- `docs/DATABASE_MAP.md`
- `docs/ERD.md`
- `docs/API_DOCUMENTATION.md`
- `docs/SECURITY_ARCHITECTURE.md`
- `docs/SECURITY_REVIEW.md`
- `docs/RBAC_PERMISSION_MATRIX.md`
- `docs/AUDIT_LOG_SPECIFICATION.md`
- `docs/BACKUP_RESTORE_GUIDE.md`
- `docs/BACKUP_RECOVERY.md`
- `docs/DEPLOYMENT_GUIDE.md`
- `docs/TEST_STRATEGY.md`
- `docs/REGRESSION_TEST_REPORT.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/DOCUMENTATION_INDEX.md`
- `docs/CHANGELOG.md`
- `docs/CREDENTIAL_MODULE.md`
- `docs/pdf/PDF_ENGINE.md`
- `docs/pdf/PDF_TEST_REPORT.md`

## آنچه در این انتشار نیست

صادقانه، چون فهرستِ بدون این بخش تصویر ناقصی می‌دهد:

| نبود | وضعیت |
|---|---|
| کد بکاپ | **صفر** — جدول `backup_runs` ساخته نشده، فرمان کنسولی نیست |
| API عمومی | **صفر** — `routes/api.php` نیست، Sanctum/Passport نصب نیست |
| رابط خواندن گزارش رویدادها | **نیست** — داده نوشته می‌شود ولی هیچ صفحه‌ای ندارد |
| `composer.lock` | commit نشده |
| Vira Drive (بستر فایل) | طراحی شده، **صفر کد** |
| آزمون روی MariaDB/MySQL | نیست — همهٔ آزمون‌ها روی SQLite |

## دروازه‌های کیفیت

پیش از انتشار باید همه سبز باشند:

```bash
node tools/checks/run-all.mjs          # ۳۶ سوئیت
```

جزئیات آخرین اجرا در [`REGRESSION_TEST_REPORT.md`](REGRESSION_TEST_REPORT.md).
