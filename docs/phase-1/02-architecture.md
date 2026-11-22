# VIRA CRM — سند معماری فنی (فاز ۱)

| مورد | مقدار |
|---|---|
| نسخه سند | 0.1-draft (فاز ۱) |
| تاریخ | 2026-11-22 |
| وضعیت | **در انتظار تأیید کارفرما (گیت فاز ۱)** |
| سند مرجع | `Vira_CRM_Requirements_FA.docx` — **در مخزن موجود نیست** (بند ۵ بریف) |
| مبنای تحلیل | بریف توسعه ۳۲ بخشی + کاتالوگ ۸۱ الزام |
| محیط اجرا | PHP 8.2+ / Laravel 12 / MySQL 8.0+ یا MariaDB 10.6+ |

> **هشدار شفافیت:** سند اصلی ۱۵ بخشی (`Vira_CRM_Requirements_FA.docx`) در این مخزن یا در محیط
> توسعه در دسترس تیم نیست. تمام الزامات این سند از متن بریف استخراج شده‌اند و بریف خودش خلاصه
> ۱۵ بخش سند را بازگو می‌کند. پس از دریافت سند اصلی، هر الزام با بند دقیق سند تطبیق
> (`verify_doc_ref`) داده می‌شود و مواردی که بریف پوشش نداده به کاتالوگ اضافه می‌شود.
> هیچ بندی از سند اصلی به‌خاطر این نبودن، حذف یا فرض نشده است.

---

## ۱. اصول معماری

| # | اصل | الزام مرتبط |
|---|---|---|
| A1 | **Standalone، نه افزونه وردپرس.** CRM یک فرآیند PHP مستقل با دیتابیس مستقل است؛ هیچ کد، جدول یا کاربری از وردپرس استفاده نمی‌شود. | REQ-01.01 |
| A2 | **مستقل از سایت اصلی.** قطعی/ارتقای `viraagency.co` روی CRM اثر صفر دارد. فقط اتصال API آینده مجاز است. | REQ-01.04 |
| A3 | **Modular Monolith.** هر دامنه یک ماژول با Service، Policy، FormRequest، Migration و Route جدا. هیچ Controller چاق. | REQ-13.01 |
| A4 | **قابلیت انتقال بدون بازنویسی.** تمام تغییرات محیطی از `.env`؛ هیچ رمز/کلید/آدرس هاردکد. | REQ-15.05 |
| A5 | **امنیت در لایه سرویس، نه UI.** هر عملیات حساس مجوز Backend و Audit دارد. | REQ-10.03, REQ-10.05 |
| A6 | **محرمانگی پیش‌فرض.** رمز دسترسی‌ها AES-256-GCM؛ کلید خارج از DB و خارج از Repository. | REQ-06.03 |
| A7 | **تاریخ شمسی در UI، استاندارد در DB.** محاسبه موعد بر پایه روز تقویمی Asia/Tehran. | REQ-11.04, REQ-12.08 |
| A8 | **بدون حذف بی‌ردپا.** Soft-delete فقط برای موجودیت‌های غیرمالی؛ مالی/حقوقی فقط اصلاحیه با نسخه. | REQ-04.07, REQ-07.04 |
| A9 | **همه چیز قابل تست.** هر ماژول Unit/Feature test دارد؛ SQLite for tests، MySQL for staging/prod. | REQ-14.01 |
| A10 | **کارایی در مقیاس واقعی.** Pagination اجباری، ایندکس‌های لازم، پرهیز از N+1 با Eager Loading. | REQ-09.01 |

---

## ۲. نمای کلان سیستم (C4 — Level 1/2)

```text
                         ┌───────────────────────────────────────────────────────────────────┐
                         │                    مرورگر کاربر (فارسی، RTL)                       │
                         │            Blade + Vite + Alpine.js + Vazirmatn (self-hosted)      │
                         └───────────────────────────────┬───────────────────────────────────┘
                                                         │ HTTPS (SSL اجباری، HSTS)
 ┌───────────────────────────────────────────────────────┴─────────────────────────────────┐
 │                     crm.viraagency.co — Document Root = /path/to/vira-crm/public          │
 │                                                                                           │
 │   ┌────────────────────────────────────────────────────────────────────────────────────┐  │
 │   │                          Laravel 12 — Modular Monolith                             │  │
 │   │  Routing → Middleware (auth, 2FA*, throttle, CSRF, no-store) → Controller →         │  │
 │   │  FormRequest (validation+authorize) → Service (business logic, transactions) →      │  │
 │   │  Repository/Query Objects → Eloquent Models → Events/Jobs (queue or sync)           │  │
 │   └───────┬──────────────────┬────────────────────┬────────────────────┬──────────────┘  │
 │           │                  │                    │                    │                 │
 │   ┌───────▼───────┐  ┌───────▼────────┐  ┌────────▼────────┐  ┌────────▼─────────────┐ │
 │   │ MySQL/MariaDB │  │ Storage خصوصی  │  │ Cache/Session   │  │ Audit Log (DB)      │ │
 │   │ دیتابیس مستقل │  │ (خارج از public)│  │ (file/redis)    │  │ append-only         │ │
 │   └───────────────┘  └────────────────┘  └─────────────────┘  └──────────────────────┘ │
 │           ▲                  ▲                    ▲                                     │
 │           │                  │                    │                                     │
 │   ┌───────┴──────────────────┴────────────────────┴──────────────────────────────────┐   │
 │   │      Scheduler (cron: schedule:run هر ۱ دقیقه)  →  ReminderEngine (Idempotent)   │   │
 │   └──────────────────────────────────────────────────────────────────────────────────┘   │
 └───────────────────────────────────────────────────────────────────────────────────────────┘
                                    │ API آینده (اختیاری، فقط با تأیید)
                          ┌─────────▼──────────┐
                          │ WordPress viraagency.co │
                          └────────────────────┘
```

### پروتکل/پورت‌ها و نقاط اعتماد

| مرز | پروتکل | کنترل |
|---|---|---|
| کاربر ↔ CRM | HTTPS | TLS 1.2+، HSTS، کوکی Secure/HttpOnly/SameSite=Lax |
| CRM ↔ MySQL | TCP 3306 (لوکال یا socket) | کاربر DB محدود به یک schema، حداقل امتیاز |
| CRM ↔ Storage | فایل‌سیستم | خارج از Document Root؛ دسترسی فقط با کنترلر |
| Cron → Application | CLI | php artisan schedule:run |
| CRM ↔ API وب‌سایت (آینده) | HTTPS + توکن | فقط با تأیید کارفرما؛ نامحدود فعلاً |

---

## ۳. ساختار ماژول‌ها (Modular Monolith)

```text
app/
├── Modules/
│   ├── Auth/                 # ورود، نشست، سیاست رمز، rate limit
│   ├── Users/                # کاربران، نقش‌ها، مجوزها (spatie)
│   ├── Audit/                # audit_logs + listeners + مشاهده
│   ├── Settings/             # تنظیمات کلید-مقدار نسخه‌دار + تاریخچه
│   ├── Customers/            # مشتری حقیقی/حقوقی، contacts, websites, dedupe
│   ├── Contracts/            # قرارداد، نسخه، فایل، تمدید، وضعیت
│   ├── ContractTemplates/    # قالب + placeholder engine + preview
│   ├── Projects/             # پروژه مشترک (base) + نوع‌ها
│   │   ├── SeoProjects/      # فیلدهای سئو + کلمات کلیدی
│   │   └── DesignProjects/   # فیلدهای طراحی سایت
│   ├── Credentials/          # credentials، types، requirements، reveal + audit
│   ├── Invoices/             # فاکتور دوره‌ای + وضعیت + فایل
│   ├── SeoReports/           # گزارش دوره‌ای سئو + فایل
│   ├── Reminders/            # موتور یادآوری، کارتابل، scheduled_events
│   ├── Notes/                # یادداشت دستی و اتصال‌ها
│   ├── Employees/            # پرسنل، قرارداد پرسنل، تخصیص
│   ├── Payroll/              # قواعد، دوره، اقلام، فیش، نسخه
│   ├── Dashboard/            # KPI + کارتابل تجمیعی
│   ├── Search/               # جستجوی سراسری نرمال‌شده فارسی
│   ├── Reports/              # گزارش‌های مدیریتی
│   ├── Pdf/                  # موتور مرکزی PDF + قالب‌ها + تحویل امن
│   ├── Excel/                # XLSX export + امنیت سلول
│   ├── Files/                # آپلود، اعتبارسنجی، storage خصوصی، دانلود کنترل‌شده
│   ├── Backup/               # backup/restore + تست بازیابی
│   └── Installer/            # نصب امن + قفل پس از نصب
├── Support/                  # Money، Jalali، PersianNormalizer، Crypto، Clock
├── Policies/                 # یا داخل هر ماژول
└── Providers/                # ModuleServiceProvider هر ماژول
```

قواعد:
- هر ماژول فقط از طریق **Service عمومی خودش** با ماژول دیگر حرف می‌زند؛ مدل‌ها cross-module کوئری مستقیم نمی‌شوند.
- ماژول `Credentials` تنها جایی است که رمزها رمزگشایی می‌شوند.
- `Audit` فقط از طریق Event Listenerها ثبت می‌کند (نه فراخوانی دستی پخش‌شده).

---

## ۴. جریان‌های اصلی (Data Flow)

### ۴.۱ جریان احراز هویت

```text
GET /login → ThrottleRequests (ip+email) → Fortify Login
  → password_verify (bcrypt cost 12) → session regenerate
  → event Login → Audit listener (user, ip, ua)
  → redirect /dashboard  (کوکی Secure/HttpOnly/SameSite=Lax)
```
- محدودیت: ۵ تلاش ناموفق/دقیقه به ازای email+IP، قفل ۱۵ دقیقه‌ای پس از ۱۰ خطا.
- خروج همه نشست‌ها: باطل‌سازی sessionها + چرخش remember token.
- 2FA اختیاری برای نقش مدیر (پیشنهادی، P2).

### ۴.۲ جریان قرارداد — از قالب تا PDF نهایی (Snapshot)

```text
قالب (contract_templates.body) + Placeholder Engine
   Placeholders: {{customer.name}} {{customer.company}} {{customer.national_id}}
                 {{customer.phone}} {{customer.address}} {{contract.amount}}
                 {{contract.start_date.jalali}} {{contract.end_date.jalali}}
                 {{project.type}} {{contract.title}}
   → رندر پیش‌نمایش (بدون ذخیره) → ویرایش دستی توسط مدیر
   → ذخیره نسخه: contract_versions{version_no, body_snapshot, data_snapshot(json)}
   → نهایی‌سازی: قفل نسخه (finalized_at) + تولید PDF فارسی
   → snapshot تضمین می‌کند تغییر مشتری در آینده، متن نهایی قبلی را تغییر ندهد (REQ-04.07)
```

### ۴.۳ موتور یادآوری (Reminder Engine)

```text
Invoices / SeoReports: هر دوره یک رکورد مستقل (period) با due_date.
Scheduler (هر دقیقه): reminder:dispatch
  1) قفل اجرا: cache lock با کلید ثابت (بدون همپوشانی اجرا)
  2) خواندن قواعد از Settings: invoice_lead_days=2, report_lead_days=2, timezone=Asia/Tehran
  3) for each open period:
       lead_day   = due_date − N days        (روز تقویمی، نه ۴۸ ساعت)
       status     = upcoming | due | overdue | done
  4) upsert در scheduled_events با کلید یکتای
       (event_type, invoice_id|seo_report_id, period_id, fire_date)
       → اجرای چندباره هیچ رکورد تکراری نمی‌سازد (Idempotent)
  5) ثبت در job_runs: started_at, finished_at, created_count, error
  6) کارتابل: today / upcoming / overdue / done  (overdue تا تعیین تکلیف باز می‌ماند)
```
استقلال فاکتور و گزارش با جداسازی کامل `event_type` و کلید یکتای جدا تضمین می‌شود (REQ-12.05).
تغییر تاریخ: دوره قبلی با `superseded_at` بسته می‌شود، سابقه می‌ماند و رکورد جدید با تاریخ جدید
ساخته می‌شود؛ کلید یکتای جدید مانع اعلان تکراری است (REQ-12.06).

### ۴.۴ جریان دسترسی‌های پروژه و PDF محرمانه

```text
ورود دسترسی → Fields: service_type, login_url, username, password(enc), notes
password_enc = v1.<b64 nonce>.<b64 tag>.<b64 ciphertext>   (AES-256-GCM, AAD=credential:{id})
کلید: CREDENTIALS_KEYS = v1:<base64 32B>   (چند کلید برای چرخش؛ active key id در .env)

مشاهده رمز (فقط با مجوز credentials.reveal):
   → Policy check در Backend → decrypt → Audit("credential.revealed", id)
   → پاسخ با Cache-Control: no-store
PDF محرمانه (مجوز credentials.pdf):
   → تولید در حافظه → دانلود از مسیر کنترل‌شده با توکن HMAC کوتاه‌عمر (≤۲ دقیقه)
   → Audit("credential.pdf.downloaded") → no-store → بدون URL قابل حدس
```

### ۴.۵ جریان حقوق و دستمزد (بدون فرمول خودساخته)

```text
payroll_rules (نسخه‌دار، مؤثر از تاریخ):
   base_salary, per_project_fixed, project_count_multiplier, bonus, overtime, deduction, advance
payroll_periods (ماه/سال شمسی) → محاسبه با bcmath (سنتی/هزارم) → payroll_items
→ payslip (Draft) → نهایی‌سازی (Final) → نسخه (payslip_versions)
→ اصلاحیه فقط با نسخه جدید + دلیل + Audit (فیش نهایی بدون اصلاحیه تغییر نمی‌کند)
```
**فرمول دقیق و تفسیر «ضریب بر اساس تعداد پروژه‌ها» نیازمند تأیید کارفرما است (Q-08).** تا قبل از
تأیید، موتور محاسبه فقط به‌صورت «قاعده‌محور با پارامتر» ساخته می‌شود و هیچ پیش‌فرض پنهانی ندارد.

### ۴.۶ جریان پرونده ۳۶۰° مشتری

```text
GET /customers/{id}  (Policy: customers.view)
  → Customer header + tabs:
     Contracts (فیلتر وضعیت) | Projects (نوع/وضعیت) | Invoices (موعد/انجام/عقب‌افتاده)
     | Notes | Files | Activity (audit رکورد و مشتری) | Reminders
  → Eager Loading + count subqueries برای جلوگیری از N+1
```

### ۴.۷ جریان فایل‌ها

```text
Upload → FormRequest (mime، پسوند، حداکثر ۱۰MB، نام امن uuid+ext)
  → Storage disk "private" (خارج از public) با مسیر سال/ماه
Download → Policy (بر اساس نوع رکورد) → StreamedResponse → Audit برای اسناد حساس
حذف رکورد → سیاست نگهداری: فایل به «سطل» منتقل می‌شود؛ حذف فیزیکی فقط با retention job پس از N روز
```

---

## ۵. امنیت (خلاصه — سند کامل: `08-security-plan.md`)

| حوزه | تصمیم |
|---|---|
| احراز هویت | Laravel Fortify + bcrypt(12) یا Argon2id در صورت وجود؛ session-based |
| مجوز | spatie/laravel-permission + Policies؛ مجوز در FormRequest و Service، نه فقط Blade |
| رمز پروژه | AES-256-GCM با AAD و کلید HKDF شده از master key؛ چرخش کلید با key id |
| رمز کاربر | فقط hash؛ هرگز reversible |
| فایل | Storage خصوصی، MIME/پسوند/حجم، نام uuid، تحویل با کنترلر |
| CSRF/XSS/SQLi | middleware، Blade escaping، Eloquent/Query Builder پارامتریزه |
| Rate limit | ورود ۵/دقیقه، مسیرهای حساس throttle اختصاصی |
| لاگ | هیچ رمز/توکن/کلید در log؛ redaction در exception handler |
| Headers | HSTS، X-Frame-Options، X-Content-Type-Options، Referrer-Policy، CSP پایه |
| Audit | append-only، کاربر/زمان/نوع/مرجع/IP/UA بدون داده حساس |
| Backup کلید | مستندسازی الزامی؛ از دست رفتن کلید = غیرقابل بازیابی بودن رمزها |

## ۶. کارایی

- Pagination سرورساید در همه لیست‌ها (پیش‌فرض ۲۵).
- ایندکس روی `customers(national_id)`, `customers(phone)`, `contracts(customer_id,status,end_date)`,
  `invoices(project_id,due_date,status)`, `scheduled_events(unique_key)`, `audit_logs(created_at)`.
- Eager Loading اجباری در پرونده مشتری؛ `Model::preventLazyLoading()` در tests/staging.
- Queryهای گزارش با aggregate در DB، نه حلقه PHP.
- Cache فقط برای تنظیمات و دیکشنری‌ها؛ اطلاعات محرمانه هرگز cache نمی‌شود.

## ۷. استقرار (خلاصه — سند کامل: `phase-0/02-deployment-topology.md`)

```text
/home/virauser/vira-crm/          ← خارج از public_html (کد، vendor، storage، .env)
   ├── app/ bootstrap/ config/ database/ routes/ storage/ vendor/ .env
   └── public/                     ← Document Root ساب‌دامین crm.*
        ├── index.php  .htaccess   (فقط این پوشه از وب قابل دسترس)
        ├── build/                 (assetهای Vite)
        └── fonts/ (اختیاری، public)
Cron: * * * * * cd /home/virauser/vira-crm && php artisan schedule:run >> storage/logs/cron.log 2>&1
```
- فایل ZIP قابل انتقال + `deploy.sh` + راهنمای نصب و انتقال.
- حالت‌های جایگزین در صورت محدودیت هاست (بدون SSH/Cron) در سند استقرار آمده است.

## ۸. تصمیمات باز و اثر مستقیم بر معماری

| # | تصمیم | اثر |
|---|---|---|
| Q-01 | نسخه PHP هاست (8.2؟ 8.3؟) | تعیین Laravel 12 vs 13 |
| Q-02 | وجود Composer/CLI | نحوه استقرار (vendored vs composer install) |
| Q-03 | وجود Cron و حداقل فاصله | SLA یادآوری‌ها |
| Q-04 | امکان Document Root مستقل | امنیت فایل‌ها |
| Q-05 | ساعت مرز روز و اجرای یادآوری | محاسبه D-2 |
| Q-06 | مبنا: تقویم نجومی یا حسابي ۲۸۲۰ ساله برای نوروز | جابه‌جایی یک‌روزه در برخی سال‌ها |
| Q-07 | مشتری حقوقی و شخص حقیقی: آیا مشتری «شخص» یا «شرکت» است یا هر دو؟ | مدل داده مشتری |
| Q-08 | فرمول دقیق حقوق (ضریب پروژه‌ها) | موتور پرداخت |
| Q-09 | نیاز به رمز روی PDF محرمانه؟ | لایه حفاظت اضافه |
| Q-10 | فعال‌سازی نقش‌های نمونه در نسخه ۱؟ | مجوزها |

## ۹. گام‌های بعدی (پس از تأیید)

1. پیاده‌سازی اسکلت Laravel + ماژول‌های پایه (Auth, Users, Audit, Settings) + Design System.
2. اجرای Migrationها روی SQLite (تست) و MySQL (staging).
3. ماژول مشتری → قرارداد → قالب/PDF → داشبورد → پروژه‌ها → یادآوری → دسترسی‌ها → پرسنل/حقوق → Excel.
4. اجرای ۱۱۹ سناریوی تست و ثبت شواهد واقعی.
5. آماده‌سازی استقرار و انتقال با گزارش هاست.
