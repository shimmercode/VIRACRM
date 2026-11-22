# VIRA CRM — طرح امنیت (فاز ۱)

| مورد | مقدار |
|---|---|
| نسخه | 0.2 |
| مبنای الزام | REQ-06.03, REQ-06.04, REQ-06.05, REQ-10.03, REQ-10.05, REQ-10.06, REQ-10.07 |
| وضعیت | طراحی‌شده — اجرا در فاز ۳ به بعد |
| اسناد وابسته | `16-key-management-policy.md` (کلیدها)، `17-backup-restore-policy.md` (بکاپ)، `18-environment-configuration.md` (محیط‌ها) |

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

---

## ۹. محدودیت نرخ درخواست (Rate Limiting)

Rate limit فقط روی ورود نیست؛ هر مسیر حساس سقف دارد. بدون این، حتی یک اسکریپت ساده
می‌تواند رمزها را امتحان کند یا منابع هاست اشتراکی را مصرف کند.

| مسیر | سقف | کلید | پاسخ در عبور از سقف |
|---|---|---|---|
| `POST /login` | ۵ در دقیقه | `email + IP` | `429` + قفل تدریجی (۱، ۵، ۱۵ دقیقه) |
| `POST /login` (global) | ۲۰ در دقیقه | `IP` | `429` |
| `POST /password/email` (بازیابی رمز) | ۳ در ۱۰ دقیقه | `email + IP` | `429`، پاسخ همیشه یکسان |
| `POST /credentials/{id}/reveal` | ۱۰ در دقیقه | `user_id` | `429` + Audit `throttled` |
| `GET /credentials/{id}/pdf` | ۵ در ۱۰ دقیقه | `user_id` | `429` + Audit |
| `POST /payroll/calculate` | ۳ در ۵ دقیقه | `user_id` | `429` |
| `GET /reports/export/*` | ۱۰ در ساعت | `user_id` | `429` + هشدار در UI |
| API عمومی (`/api/*`) | ۶۰ در دقیقه | `user_id` یا `IP` | `429` با هدر `Retry-After` |

**قواعد:**
- شمارنده در `cache` (درایور `file`) نگهداری می‌شود؛ Redis لازم نیست.
- خطای `429` هیچ اطلاعاتی از وجود/عدم وجود حساب نمی‌دهد.
- Rate limit روی مسیر ورود **حتی برای IPهای داخلی** اعمال می‌شود (هاست اشتراکی است).
- در Audit فقط رویداد «throttled» ثبت می‌شود، نه محتوای درخواست.

> **آزمون:** T-SEC-08 (ورود تکراری → قفل)، T-SEC-09 (بازیابی رمز → سقف)،
> T-SEC-10 (reveal تکراری → سقف). همه `not-tested` تا اجرای کد.

---

## ۱۰. بازیابی رمز عبور (Password Reset)

| مورد | تصمیم |
|---|---|
| مکانیزم | توکن یک‌بارمصرف Laravel (`password_reset_tokens`، توکن **هش‌شده** ذخیره می‌شود) |
| عمر توکن | ۶۰ دقیقه |
| استفاده مجدد | پس از تغییر رمز، همه توکن‌های قبلی باطل می‌شوند |
| پاسخ یکسان | چه ایمیل وجود داشته باشد چه نه، پاسخ همیشه «اگر حسابی با این ایمیل باشد، لینک ارسال شد» |
| اثر تغییر رمز | همه نشست‌های فعال باطل + `remember_token` پاک می‌شود + Audit `password.changed` |
| ایمیل | SMTP واقعی — **پارامترهای SMTP تصمیم کارفرما است (OD-19)**. تا تعیین نشدن، تحویل ایمیل تست نشده است |
| در نسخه ۱ | چون فقط مدیر کل فعال است، مسیر بازیابی ضروری است (اگر مدیر رمز را فراموش کند، چاره‌ای نباشد) |

> ⚠️ **ریسک واقعی:** اگر SMTP به‌درستی تنظیم نشود، «بازیابی رمز» عملاً کار نمی‌کند و
> مدیر برای ورود به سیستم قفل می‌شود. به همین دلیل باید پیش از تحویل، **یک ایمیل
> بازیابی واقعی ارسال و دریافت شود** — و این کار تا امروز انجام نشده (T-SEC-11: NOT_RUN).

---

## ۱۱. استقلال کوکی و نشست از وردپرس

| کنترل | مقدار | چرا |
|---|---|---|
| نام کوکی نشست | `vira_crm_session` | وردپرس از `wordpress_logged_in_<hash>` استفاده می‌کند؛ هم‌پوشانی صفر |
| نام کوکی CSRF | `vira_crm_xsrf` | جلوگیری از تداخل |
| دامنه کوکی | `crm.viraagency.co` (بدون `.viraagency.co`) | کوکی CRM به سایت اصلی ارسال نمی‌شود |
| `SameSite` | `Lax` | ارسال نشدن در درخواست‌های cross-site |
| `HttpOnly` | `true` | عدم دسترسی JavaScript |
| `Secure` | `true` در Production | فقط HTTPS |
| `SESSION_ENCRYPT` | `true` | payload سشن روی سرور رمز می‌شود |
| درایور سشن | `file` در مسیر `storage/framework/sessions` | جدول `sessions` فقط برای «خروج همه دستگاه‌ها» اختیاری است |
| `session.cookie_path` | `/` روی ساب‌دامین | محدود به CRM |

**آزمون پذیرش استقلال:**
1. ورود به وردپرس → بررسی کنید هیچ کوکی `vira_crm_*` ساخته نشده باشد.
2. ورود به CRM → در DevTools هیچ کوکی `wordpress_*` ارسال نشود.
3. حذف کامل وردپرس → CRM بدون خطا بالا بیاید (T-OPS-06).
4. `Set-Cookie` در پاسخ CRM نباید `Domain=.viraagency.co` داشته باشد.

> این چهار مورد **طراحی شده‌اند ولی اجرا نشده‌اند**، چون CRM وجود ندارد. در جدول
> تست‌ها به‌عنوان `not-tested` ثبت شده‌اند و نباید به‌عنوان «تأییدشده» گزارش شوند.

---

## ۱۲. جداسازی محیط‌ها از منظر امنیت

| خطر | کنترل | سند مرجع |
|---|---|---|
| فعال بودن `APP_DEBUG` در production → نشت stack trace و `.env` | `APP_DEBUG=false` اجباری + بررسی در چک‌لیست انتشار | `18-environment-configuration.md` §7 |
| استفاده از کلید production در staging | کلید جداگانه per environment | `16-key-management-policy.md` §5 |
| داده واقعی مشتری در محیط توسعه | ممنوعیت صریح + داده ساختگی | `18-environment-configuration.md` §1 |
| فراموش شدن حذف اسکریپت تشخیص | حذف اجباری در چک‌لیست + توکن `CHANGE_ME` که بدون تغییر کار نمی‌کند | `host-compatibility-check.php` |
| Installer باز بماند | قفل پس از نصب + حذف مسیر | §۷ همین سند |

---

## ۱۳. جمع‌بندی: چه چیزی طراحی شده و چه چیزی اجرا شده

| حوزه | طراحی | اجرا | شاهد |
|---|---|---|---|
| AES-256-GCM + AAD | ✅ | ✅ (الگوریتم، روی PHP واقعی) | `evidence/php-runtime-smoke-test.json` |
| سیاست کلید و چرخش | ✅ | ❌ (نیازمند کد و تأیید کارفرما) | `16-key-management-policy.md` |
| بکاپ/بازیابی | ✅ | ❌ (نیازمند هاست واقعی) | `17-backup-restore-policy.md` |
| Rate limit | ✅ | ❌ | همین سند §۹ |
| بازیابی رمز | ✅ | ❌ (نیازمند SMTP واقعی) | همین سند §۱۰ |
| استقلال از وردپرس | ✅ | ❌ (نیازمند نصب CRM) | همین سند §۱۱ |
| جداسازی محیط | ✅ | ❌ (staging وجود ندارد) | `18-environment-configuration.md` |
| ۱۷ تست امنیتی `T-SEC-*` | ✅ | ❌ همه `not-tested` | `tests.catalog.yaml` |

> **این جدول باید در گزارش نهایی عیناً تکرار شود.** هیچ سطر «❌» نباید به‌عنوان
> «انجام‌شده» خوانده شود.
