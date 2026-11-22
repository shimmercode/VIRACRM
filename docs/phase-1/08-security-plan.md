# VIRA CRM — طرح امنیت (فاز ۱)

| مورد | مقدار |
|---|---|
| نسخه | 0.1-draft |
| مبنای الزام | REQ-06.03, REQ-06.04, REQ-06.05, REQ-10.03, REQ-10.05, REQ-10.06, REQ-10.07 |
| وضعیت | طراحی‌شده — اجرا در فاز ۳ به بعد |

## ۱. مدل تهدید (خلاصه)

| دارایی | تهدید | کنترل |
|---|---|---|
| رمز دسترسی مشتریان | نشت از DB/Log/URL/Backup | AES-256-GCM + AAD، کلید خارج از DB، redaction، no-store، signed URL |
| اطلاعات مالی و حقوقی | دسترسی غیرمجاز کارکنان | مجوز مستقل، Backend policy، Audit |
| پرونده مشتریان (PII) | IDOR، XSS، SQLi | Policy + scoping، escaping، query پارامتریزه |
| PDF محرمانه | حدس URL / کش / اشتراک‌گذاری لینک | توکن کوتاه‌عمر یک‌بارمصرف + Audit + no-store |
| نشست مدیر | سرقت کوکی/CSRF | Secure+HttpOnly+SameSite، regenerate، CSRF token، خروج همه نشست‌ها |
| نصب اولیه | دسترسی به Installer | قفل پس از نصب، حذف/مسدودسازی مسیر، رمز تصادفی اجباری |
| فایل آپلودی | RCE/آپلود مخرب | اعتبارسنجی MIME/پسوند/حجم، نام uuid، Storage خصوصی، عدم اجرای اسکریپت |
| Backup | نشت آرشیو | خارج از web root، رمزنگاری آرشیو، دسترسی محدود |

## ۲. رمزنگاری رمز پروژه

| مورد | تصمیم |
|---|---|
| الگوریتم | AES-256-GCM (احراز اصالت‌شده) |
| کلید | ۳۲ بایت تصادفی، در `.env` (`CREDENTIALS_KEYS=v1:<base64>[,v2:...]`) — خارج از DB/Repository |
| AAD | `credential:{id}` — جلوگیری از جابه‌جایی ciphertext بین رکوردها |
| Nonce | ۱۲ بایت تصادفی برای هر رمزنگاری، ذخیره در payload |
| قالب ذخیره | `v1.<b64 nonce>.<b64 tag>.<b64 cipher>` |
| چرخش کلید | پارامتر `key_id`؛ رمزگشایی با کلید متناظر؛ re-encrypt تدریجی |
| Backup کلید | خارج از سرور، در اختیار مدیر، مستند؛ **از دست رفتن کلید = غیرقابل بازیابی بودن رمزها** (در مستندات و UI هشدار داده می‌شود) |
| لاگ | هیچ مقدار رمز در log/exception/audit؛ در صورت خطا فقط شناسه رکورد |

شاهد اجرایی: `docs/phase-1/evidence/php-runtime-smoke-test.json` (RT-01، RT-02).

## ۳. احراز هویت و نشست

- bcrypt cost 12 (یا Argon2id در صورت پشتیبانی هاست) — `password_hash`/`password_verify`.
- Rate limit ورود: ۵ در دقیقه به‌ازای email+IP؛ قفل تدریجی تا ۱۵ دقیقه.
- Session: `Secure`, `HttpOnly`, `SameSite=Lax`, `session_regenerate_id` پس از ورود.
- خروج همه نشست‌ها + باطل‌سازی remember token.
- سیاست رمز: حداقل ۱۲ کاراکتر، عدم تطابق با نام/ایمیل، بررسی در لیست رمزهای رایج.
- احراز مجدد (re-auth) برای مشاهده رمز و دریافت PDF محرمانه.
- احراز دو مرحله‌ای (TOTP) به‌عنوان قابلیت اختیاری P2 برای مدیر.

## ۴. مجوزها

| مجوز | توضیح |
|---|---|
| `customers.view/create/update/archive` | مشتری |
| `contracts.view/create/update/finalize` | قرارداد |
| `projects.view/create/update/finalize` | پروژه |
| `credentials.view` | دیدن لیست دسترسی‌ها (بدون رمز) |
| `credentials.reveal` | دیدن رمز (Audit الزامی) |
| `credentials.pdf` | PDF محرمانه (Audit + re-auth) |
| `employees.view/update` | پرسنل |
| `payroll.view/calculate/finalize` | حقوق |
| `reports.view` | گزارش‌ها |
| `exports.pdf/excel` | خروجی‌ها |
| `settings.manage` | تنظیمات |
| `audit.view` | Audit Log |

> در نسخه ۱ فقط نقش «مدیر کل» فعال است. نقش‌های دیگر (مدیر پروژه، کارشناس، مالی، محدود)
> در داده اولیه تعریف می‌شوند اما **فعال‌سازی آن‌ها نیازمند تأیید کارفرما است** (Q-10).

## ۵. کنترل‌های ورودی/خروجی

- Validation سمت سرور در FormRequest برای همه فرم‌ها (هیچ اتکا به HTML5).
- Blade escaping پیش‌فرض؛ محتوای غنی قرارداد با HTML sanitizer allowlist.
- Query Builder/Eloquent پارامتریزه؛ هیچ `whereRaw` با ورودی کاربر.
- فایل: allowlist پسوند+MIME (pdf, jpg, png, docx, xlsx, zip)، حداکثر ۱۰MB، نام uuid، اسکن نام فایل.
- XLSX: ضد Formula Injection (پیشوند `'` برای `= + - @`).
- PDF: بدون اجرای JS، خروجی در حافظه، فایل موقت پاک می‌شود.
- هدرها: `Strict-Transport-Security`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: no-referrer`, CSP پایه.
- `Cache-Control: no-store` برای صفحات دسترسی/PDF/حقوق.

## ۶. Audit Log

| رویداد | داده ثبت‌شده (بدون داده حساس) |
|---|---|
| login / logout / failed_login | user, ip, ua |
| create/update/archive (همه موجودیت‌ها) | entity, id, تغییرات کلیدی (before/after غیرحساس) |
| credential.revealed | user, credential_id, project_id, ip |
| credential.pdf.downloaded | user, project_id, token_id, ip |
| payroll.calculated / finalized / amended | period, employee, reason |
| settings.changed | key, old→new |
| role/permission.changed | target_user, role |
| backup.created / restore.executed | file, size |

پیاده‌سازی: Event Listener مرکزی + جدول append-only؛ هیچ مسیر «حذف Audit» وجود ندارد.

## ۷. استقرار امن

- `.env` خارج از web root، مجوز فایل `600`.
- `APP_DEBUG=false` در Production؛ صفحه خطای کاربرپسند + ثبت داخلی.
- فقط `public/` در Document Root؛ `.htaccess` برای deny مسیرهای حساس در صورت استقرار غیراستاندارد.
- Installer پس از نصب قفل و مسدود می‌شود.
- HTTPS اجباری؛ بدون SSL ماژول دسترسی‌ها فعال نمی‌شود.
- بکاپ رمزنگاری‌شده، خارج از web root.

## ۸. تست امنیت

سناریوهای امنیتی: T-SEC-01 تا T-SEC-17 در `docs/requirements/tests.catalog.yaml` — وضعیت فعلی:
`not-tested` (کد محصول وجود ندارد). تست‌های زیرساختی مرتبط با رمزنگاری **واقعاً اجرا شده‌اند**
و در `docs/phase-1/evidence/` شاهد دارند، اما این‌ها تست ماژول محصول نیستند.
