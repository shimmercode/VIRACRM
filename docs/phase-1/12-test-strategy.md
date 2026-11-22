# VIRA CRM — استراتژی تست (فاز ۱)

> قاعده طلایی: **هیچ تستی بدون اجرای واقعی Pass نمی‌شود** (بخش ۲۳ و ۲۴ بریف).
> کاتالوگ کامل: `docs/requirements/tests.catalog.yaml` · خلاصه خودکار: `11-test-plan-counts.md`

## ۱. هرم تست

```text
                 ┌────────────────────┐
                 │  UI / Responsive   │  ← دستی + بررسی بصری (فاز ۲/۵)
                 │  (دستی + اسکرین)   │
                 ├────────────────────┤
                 │  Integration/E2E   │  ← HTTP Feature Tests (Laravel)
                 │  Scheduler, PDF,   │
                 │  Excel, Backup     │
                 ├────────────────────┤
                 │      Unit          │  ← Services: Money, Jalali, Crypto,
                 │  (سریع، SQLite)    │     Template engine, Policies
                 └────────────────────┘
```

| سطح | ابزار | محیط | چه چیزی |
|---|---|---|---|
| Unit | PHPUnit/Pest | SQLite in-memory | Money/bcmath، Jalali، Crypto، Placeholder Engine، Guards |
| Feature (HTTP) | Laravel TestClient | SQLite | CRUD، مجوزها، Validation، CSRF |
| Integration | MySQL staging | staging | Migration واقعی، Queryهای پیچیده، concurrency |
| Scheduler | `artisan schedule:test` + clock fake | staging | D-2/روز موعد/عقب‌افتاده/Idempotency |
| PDF | تولید واقعی + بازکردن + بررسی متن/تصویر | staging | فارسی/RTL/شکست صفحه |
| Excel | تولید XLSX + بازکردن | staging | نوع داده، صفر ابتدایی، Formula Injection |
| Security | تست‌های دستی + ابزار | staging | IDOR، XSS، SQLi، Rate limit، فایل |
| Backup/Restore | واقعی | staging | کیفیت بازیابی |
| Deployment | staging → host | staging | بدون تغییر کد |

## ۲. قواعد شواهد

| نوع تست | شاهد قابل قبول |
|---|---|
| Unit/Feature | خروجی PHPUnit (لاگ) + نام تست |
| PDF | فایل PDF واقعی + رندر PNG صفحه اول + بررسی متن |
| Excel | فایل XLSX + اسکرین‌شات باز شده در LibreOffice |
| UI | اسکرین‌شات قبل/بعد + بررسی RTL |
| Scheduler | خروجی `job_runs` + رکورد `scheduled_events` |
| Security | لاگ درخواست + پاسخ (کد وضعیت) |
| Backup | لاگ بازیابی + مقایسه تعداد رکوردها |

## ۳. وضعیت فعلی (چرا تقریباً همه not-tested)

| دلیل | توضیح |
|---|---|
| کد محصول وجود ندارد | گیت فاز ۱ باز است؛ توسعه اصلی شروع نشده |
| هاست در دسترس نیست | T-INF-01/03، T-SCH-01، T-PAY-02/03/05/07(مرتبط)، T-RPT-02 → `blocked` |
| فرمول حقوق تأیید نشده | T-PAY-02/03/05 → `blocked` |
| محیط sandbox بدون Composer/MySQL | Feature/Integration تا فاز ۳ امکان‌پذیر نیست |

**آنچه واقعاً اجرا شده (شاهد در `docs/phase-1/evidence/`):**

| اجرا | نتیجه | شاهد JSON |
|---|---|---|
| Jalali engine (۸ مورد مرجع + ۲۴۲۴ invariant + D-2 + Timezone) | PASS، ۰ خطا | `jalali-smoke-test.json` |
| PHP runtime capabilities (AES-GCM، bcmath، bcrypt، HMAC، Persian normalization، SQLite، JSON، random) | PASS، ۱۰/۱۰ | `php-runtime-smoke-test.json` |

> این دو اجرا **تست ماژول محصول نیستند**؛ اثبات امکان‌پذیری و درستی الگوریتم‌های زیربنایی‌اند
> و در کاتالوگ تست محصول (T-*) حساب نمی‌شوند.

## ۴. معیار خروج از فاز ۵ (تست)

| معیار | آستانه |
|---|---|
| تست‌های P0 | ۱۰۰٪ Pass با شاهد |
| تست‌های امنیتی | ۱۰۰٪ Pass |
| تست‌های PDF/Excel/Scheduler/Backup | ۱۰۰٪ Pass با شاهد فایل |
| تست‌های UI/Responsive | ≥۹۰٪ با اسکرین‌شات |
| Known Issues | مستند با شدت و برنامه رفع |

## ۵. نگاشت تست‌ها به نیازمندی

خودکار در `05-traceability-matrix.md`: هر الزام با شناسه تست و وضعیت آن. هیچ الزام بدون
تست (به‌جز موارد فرآیندی) وجود ندارد — بررسی سلامت ردیابی در همان سند.
