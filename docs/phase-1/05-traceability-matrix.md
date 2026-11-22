# VIRA CRM — Requirements Traceability Matrix (RTM) — نسخه اولیه فاز ۱

> تولید خودکار از `docs/requirements/requirements.catalog.yaml` + `tests.catalog.yaml` در **2026-11-22**.
> هر عدد/سلول این سند محاسبه‌شده است؛ دستی ویرایش نکنید. بازتولید: `python3 docs/tools/build_phase1_docs.py`

## راهنمای ستون‌ها

- **شناسه**: شناسه یکتای الزام (`REQ-xx.yy`)
- **نوع**: سند = الزام صریح سند مرجع ۱۵ بخشی · بریف = الزام صریح بریف توسعه · مشتق = الزام مهندسی مشتق‌شده · پیشنهادی = تصمیم تیم، نیازمند تأیید
- **اولویت**: P0 بلوکه‌کننده تحویل · P1 الزام نسخه ۱ · P2 ارزش بالا · P3 تکمیلی
- **وضعیت**: وضعیت واقعی در پایان فاز ۱ (هیچ الزامی `implemented` یا `tested` نیست، چون کد محصول هنوز نوشته نشده و گیت تأیید فاز ۱ هنوز باز است)
- **تست‌ها**: شناسه‌های سناریوی تست در `docs/requirements/tests.catalog.yaml`

## خلاصه پوشش

| گروه | عنوان | تعداد الزام | بدون تست | ماژول‌ها |
|---|---|---|---|---|
| REQ-01 | اهداف، اصول و معماری غیرقابل مذاکره | 4 | 1 | Deployment, Foundation |
| REQ-02 | داشبورد و کارتابل | 5 | 0 | Contracts, Dashboard, Reminders, Settings, UI |
| REQ-03 | مدیریت مشتریان | 8 | 0 | Contracts, Customers, Search |
| REQ-04 | مدیریت قراردادها و بانک قرارداد | 10 | 0 | ContractTemplates, Contracts, Customers, Search, Settings |
| REQ-05 | مدیریت پروژه‌ها | 5 | 0 | DesignProjects, Employees, Projects, SeoProjects |
| REQ-06 | دسترسی‌های پروژه و PDF محرمانه | 7 | 0 | Audit, Credentials, Pdf, Projects, Security, SeoProjects, Settings |
| REQ-07 | پرسنل و حقوق و دستمزد | 5 | 0 | Employees, Payroll, Pdf, Security, Settings |
| REQ-08 | یادداشت‌ها و یادآوری دستی | 2 | 0 | Dashboard, Notes |
| REQ-09 | جستجو، فیلتر و گزارش‌گیری | 7 | 0 | Contracts, Employees, Excel, Invoices, Pdf, Projects, Reports, Search, SeoReports |
| REQ-10 | کاربران، نقش‌ها و امنیت | 7 | 0 | Audit, Files, Permissions, Roles, Security, Users |
| REQ-11 | طراحی UI/UX و هویت بصری | 5 | 0 | Jalali, UI |
| REQ-12 | موتور یادآوری خودکار و گردش‌کارها | 9 | 0 | Files, Invoices, Reminders, Scheduler, SeoReports, Settings |
| REQ-13 | اولویت‌بندی و برنامه اجرا | 2 | 1 | Deployment, PM |
| REQ-14 | معیارهای پذیرش | 2 | 0 | PM, QA |
| REQ-15 | تنظیمات مدیریتی و قابلیت استقرار | 6 | 1 | Backup, DevOps, Docs, Installer, Settings |

| نوع | تعداد |
|---|---|
| سند | 61 |
| بریف | 20 |
| مشتق | 1 |
| پیشنهادی | 2 |

| اولویت | تعداد |
|---|---|
| P0 | 29 |
| P1 | 51 |
| P2 | 4 |

## ✅ سلامت ردیابی

همه الزام‌ها ماژول و تست دارند؛ هیچ تست یتیم و هیچ ارجاع نامعتبری وجود ندارد.

## ماتریس ردیابی

### REQ-01 — اهداف، اصول و معماری غیرقابل مذاکره

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-01.01` | بریف | P0 | استقلال کامل نرم‌افزار از وردپرس | Foundation | T-SEC-09 (NOT_RUN), T-OPS-07 (PASS), T-OPS-08 (PASS) | طراحی‌شده |
| `REQ-01.02` | بریف | P0 | دیتابیس مستقل و ساب‌دامین مستقل | Deployment | T-INF-04 (NOT_RUN) | طراحی‌شده |
| `REQ-01.03` | بریف | P0 | فایل‌های حساس خارج از Document Root | Deployment | T-SEC-10 (NOT_RUN) | طراحی‌شده |
| `REQ-01.04` | بریف | P1 | تاب‌آوری CRM در قطعی سایت اصلی | Foundation | — | طراحی‌شده |

### REQ-02 — داشبورد و کارتابل

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-02.01` | سند | P1 | شاخص‌های داشبورد | Dashboard | T-DB-01 (NOT_RUN), T-DB-02 (NOT_RUN), T-DB-03 (NOT_RUN) | در تحلیل |
| `REQ-02.02` | سند | P1 | کارتابل تفکیک‌شده | Dashboard, Reminders | T-DB-01 (NOT_RUN), T-DB-02 (NOT_RUN), T-DB-03 (NOT_RUN), T-DB-04 (NOT_RUN) | در تحلیل |
| `REQ-02.03` | سند | P1 | ناوبری مستقیم از اعلان به رکورد | Dashboard | T-DB-05 (NOT_RUN) | در تحلیل |
| `REQ-02.04` | سند | P1 | هشدار انقضای قرارداد با آستانه قابل تنظیم | Dashboard, Settings, Contracts | T-DB-02 (NOT_RUN) | در تحلیل |
| `REQ-02.05` | سند | P1 | داشبورد خلوت و کاربردی | Dashboard, UI | T-UI-05 (NOT_RUN) | در تحلیل |

### REQ-03 — مدیریت مشتریان

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-03.01` | سند | P1 | پرونده مشتری حقیقی | Customers | T-CUS-01 (PASS) | در تحلیل |
| `REQ-03.02` | سند | P1 | پرونده مشتری حقوقی | Customers | T-CUS-02 (PASS) | در تحلیل |
| `REQ-03.03` | سند | P1 | فرم پویا بر اساس نوع شخص | Customers | T-CUS-01 (PASS), T-CUS-02 (PASS) | در تحلیل |
| `REQ-03.04` | سند | P1 | چرخه عمر مشتری | Customers | T-CUS-01 (PASS), T-CUS-02 (PASS) | در تحلیل |
| `REQ-03.05` | سند | P0 | جلوگیری از ثبت مشتری تکراری | Customers | T-CUS-03 (PASS) | در تحلیل |
| `REQ-03.06` | سند | P1 | جستجوی مشتری بر اساس نام/تماس/شناسه/دامنه | Customers, Search | T-CUS-04 (PASS), T-CUS-05 (PASS), T-SRCH-01 (NOT_RUN) | در تحلیل |
| `REQ-03.07` | سند | P1 | پرونده مرکزی مشتری 360° | Customers | T-CUS-06 (NOT_RUN) | در تحلیل |
| `REQ-03.08` | سند | P0 | یک‌بار ثبت اطلاعات مشتری | Customers, Contracts | T-CTR-02 (NOT_RUN) | در تحلیل |

### REQ-04 — مدیریت قراردادها و بانک قرارداد

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-04.01` | سند | P1 | مدل داده قرارداد | Contracts | T-CTR-01 (NOT_RUN) | در تحلیل |
| `REQ-04.02` | سند | P1 | وضعیت‌های قرارداد | Contracts, Settings | T-CTR-01 (NOT_RUN) | در تحلیل |
| `REQ-04.03` | سند | P1 | چند قرارداد برای یک مشتری + اتصال به پرونده | Contracts, Customers | T-CTR-02 (NOT_RUN) | در تحلیل |
| `REQ-04.04` | سند | P1 | بانک قالب قرارداد | ContractTemplates | T-CTR-03 (NOT_RUN) | در تحلیل |
| `REQ-04.05` | سند | P1 | موتور Template قرارداد | ContractTemplates | T-CTR-03 (NOT_RUN) | در تحلیل |
| `REQ-04.06` | سند | P1 | ویرایش متن قرارداد پیش از نهایی‌سازی | Contracts | T-CTR-04 (NOT_RUN) | در تحلیل |
| `REQ-04.07` | سند | P0 | تثبیت نسخه نهایی قرارداد | Contracts | T-CTR-04 (NOT_RUN), T-CTR-08 (NOT_RUN) | در تحلیل |
| `REQ-04.08` | سند | P1 | تاریخچه و نسخه‌های قرارداد | Contracts | T-CTR-05 (NOT_RUN), T-CTR-06 (NOT_RUN), T-CTR-07 (NOT_RUN) | در تحلیل |
| `REQ-04.09` | سند | P1 | جستجو و فیلتر قرارداد | Contracts, Search | T-CTR-09 (NOT_RUN) | در تحلیل |
| `REQ-04.10` | سند | P1 | ثبت تمدید قرارداد | Contracts | T-CTR-07 (NOT_RUN) | در تحلیل |

### REQ-05 — مدیریت پروژه‌ها

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-05.01` | سند | P1 | اتصال پروژه به مشتری و قرارداد + فرم نوع‌محور | Projects | T-PRJ-03 (NOT_RUN), T-PRJ-04 (NOT_RUN) | در تحلیل |
| `REQ-05.02` | سند | P1 | فیلدهای پروژه سئو | SeoProjects | T-PRJ-01 (NOT_RUN), T-PRJ-03 (NOT_RUN) | در تحلیل |
| `REQ-05.03` | سند | P1 | مدیریت ساختارمند کلمات کلیدی | SeoProjects | T-PRJ-05 (NOT_RUN) | در تحلیل |
| `REQ-05.04` | سند | P1 | فیلدهای پروژه طراحی سایت | DesignProjects | T-PRJ-02 (NOT_RUN), T-PRJ-04 (NOT_RUN) | در تحلیل |
| `REQ-05.05` | سند | P1 | تخصیص مسئول/کارشناس به پروژه | Projects, Employees | T-PRJ-06 (NOT_RUN) | در تحلیل |

### REQ-06 — دسترسی‌های پروژه و PDF محرمانه

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-06.01` | سند | P1 | مدیریت دسترسی‌های پروژه | Credentials | T-CRD-01 (NOT_RUN) | در تحلیل |
| `REQ-06.02` | سند | P0 | فهرست دسترسی‌های اجباری سئو | Credentials, SeoProjects, Settings | T-CRD-02 (NOT_RUN), T-PRJ-07 (NOT_RUN) | در تحلیل |
| `REQ-06.03` | بریف | P0 | رمزنگاری رمزها (AES-256-GCM) | Credentials, Security | T-CRD-03 (PASS), T-SEC-01 (PASS), T-KEY-01 (NOT_RUN), T-KEY-02 (PASS) | در تحلیل |
| `REQ-06.04` | بریف | P0 | Audit مشاهده و دریافت خروجی محرمانه | Credentials, Audit | T-CRD-04 (NOT_RUN), T-CRD-05 (NOT_RUN) | در تحلیل |
| `REQ-06.05` | بریف | P0 | PDF محرمانه دسترسی‌ها | Credentials, Pdf | T-CRD-05 (NOT_RUN), T-CRD-06 (NOT_RUN), T-PDF-09 (NOT_RUN), T-SEC-11 (NOT_RUN) | در تحلیل |
| `REQ-06.06` | بریف | P1 | جداسازی اطلاعات عمومی و محرمانه پروژه | Projects, Credentials, Pdf | T-PDF-08 (NOT_RUN), T-PDF-09 (NOT_RUN) | در تحلیل |
| `REQ-06.07` | سند | P1 | هشدار محرمانگی روی فایل حاوی رمز | Credentials, Pdf | T-CRD-07 (NOT_RUN) | در تحلیل |

### REQ-07 — پرسنل و حقوق و دستمزد

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-07.01` | سند | P1 | پرونده پرسنل | Employees | T-EMP-01 (NOT_RUN) | در تحلیل |
| `REQ-07.02` | سند | P0 | قواعد قابل تنظیم حقوق (بدون فرمول خودساخته) | Payroll, Settings | T-PAY-02 (BLOCKED), T-PAY-03 (BLOCKED) | در تحلیل |
| `REQ-07.03` | سند | P1 | فیش حقوقی ماهانه (PDF فارسی RTL) | Payroll, Pdf | T-PAY-04 (NOT_RUN), T-PAY-05 (BLOCKED), T-PDF-10 (NOT_RUN) | در تحلیل |
| `REQ-07.04` | سند | P0 | تاریخچه و تثبیت فیش‌ها | Payroll | T-PAY-06 (NOT_RUN), T-PAY-07 (NOT_RUN) | در تحلیل |
| `REQ-07.05` | سند | P0 | دسترسی مستقل اطلاعات حقوقی | Payroll, Security | T-PAY-08 (NOT_RUN), T-SEC-03 (NOT_RUN) | در تحلیل |

### REQ-08 — یادداشت‌ها و یادآوری دستی

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-08.01` | سند | P1 | مدل یادداشت | Notes | T-NOT-01 (NOT_RUN) | در تحلیل |
| `REQ-08.02` | سند | P1 | چرخه عمر یادداشت | Notes, Dashboard | T-NOT-02 (NOT_RUN), T-NOT-03 (NOT_RUN), T-DB-04 (NOT_RUN) | در تحلیل |

### REQ-09 — جستجو، فیلتر و گزارش‌گیری

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-09.01` | سند | P1 | جستجوی سراسری | Search | T-SRCH-01 (NOT_RUN), T-SRCH-02 (NOT_RUN) | در تحلیل |
| `REQ-09.02` | سند | P1 | فیلترهای قرارداد و پروژه | Contracts, Projects, Search | T-CTR-09 (NOT_RUN), T-PRJ-08 (NOT_RUN) | در تحلیل |
| `REQ-09.03` | سند | P1 | گزارش فاکتور و گزارش | Invoices, SeoReports | T-INV-06 (NOT_RUN), T-SEO-06 (NOT_RUN) | در تحلیل |
| `REQ-09.04` | سند | P1 | گزارش قراردادها و پرسنل | Reports | T-RPT-01 (NOT_RUN), T-RPT-02 (BLOCKED) | در تحلیل |
| `REQ-09.05` | سند | P1 | خروجی PDF الزامی | Pdf | T-PDF-01 (NOT_RUN), T-PDF-08 (NOT_RUN), T-PDF-09 (NOT_RUN), T-PDF-10 (NOT_RUN) | در تحلیل |
| `REQ-09.06` | پیشنهادی | P2 | خروجی Excel تکمیلی | Excel | T-XLS-01 (NOT_RUN), T-XLS-02 (NOT_RUN), T-XLS-03 (NOT_RUN) | شروع‌نشده |
| `REQ-09.07` | سند | P1 | گزارش تعداد پروژه‌های فعال هر کارشناس | Reports, Employees | T-RPT-02 (BLOCKED) | در تحلیل |

### REQ-10 — کاربران، نقش‌ها و امنیت

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-10.01` | سند | P1 | نسخه اول: فقط مدیر اصلی | Users, Security | T-SEC-02 (NOT_RUN) | در تحلیل |
| `REQ-10.02` | پیشنهادی | P2 | نقش‌های نمونه آینده (فعال‌سازی با تأیید کارفرما) | Roles, Security | T-SEC-03 (NOT_RUN) | در تحلیل |
| `REQ-10.03` | سند | P0 | مجوزهای مستقل به تفکیک حوزه | Permissions | T-SEC-03 (NOT_RUN), T-SEC-04 (NOT_RUN) | در تحلیل |
| `REQ-10.04` | سند | P0 | Audit Log رویدادهای مهم | Audit | T-AUD-01 (NOT_RUN), T-AUD-02 (NOT_RUN), T-CRD-04 (NOT_RUN) | در تحلیل |
| `REQ-10.05` | بریف | P0 | الزامات امنیتی پایه | Security | T-SEC-05 (NOT_RUN), T-SEC-06 (NOT_RUN), T-SEC-07 (NOT_RUN), T-SEC-08 (NOT_RUN), T-SEC-14 (NOT_RUN), T-SEC-15 (NOT_RUN), T-SEC-16 (NOT_RUN) | در تحلیل |
| `REQ-10.06` | بریف | P0 | امنیت فایل‌های آپلودی و IDOR | Files, Security | T-SEC-08 (NOT_RUN), T-SEC-09 (NOT_RUN), T-SEC-10 (NOT_RUN) | در تحلیل |
| `REQ-10.07` | بریف | P1 | سیاست رمز و مدیریت نشست | Security | T-SEC-12 (NOT_RUN), T-SEC-13 (NOT_RUN) | در تحلیل |

### REQ-11 — طراحی UI/UX و هویت بصری

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-11.01` | سند | P1 | UI فارسی RTL اختصاصی | UI | T-UI-01 (NOT_RUN), T-UI-02 (NOT_RUN) | در تحلیل |
| `REQ-11.02` | سند | P0 | Design System مشترک | UI | T-UI-03 (NOT_RUN) | در تحلیل |
| `REQ-11.03` | بریف | P1 | فونت فارسی پایدار و قانونی | UI | T-UI-04 (NOT_RUN) | طراحی‌شده |
| `REQ-11.04` | سند | P0 | تاریخ شمسی ورود/نمایش + ذخیره استاندارد | Jalali, UI | T-DATE-01 (NOT_RUN), T-DATE-02 (NOT_RUN), T-DATE-03 (NOT_RUN), T-CAL-01 (PASS), T-CAL-02 (PASS), T-CAL-03 (PASS), T-CAL-05 (PASS), T-CAL-06 (PASS), T-CAL-09 (BLOCKED), T-CAL-10 (PASS) | در تحلیل |
| `REQ-11.05` | سند | P1 | حالت‌های Loading/Empty/Success/Error و اعتبارسنجی | UI | T-UI-03 (NOT_RUN), T-UI-06 (NOT_RUN) | در تحلیل |

### REQ-12 — موتور یادآوری خودکار و گردش‌کارها

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-12.01` | سند | P0 | یادآوری فاکتور دو روز تقویمی قبل | Invoices, Reminders | T-INV-01 (NOT_RUN), T-INV-02 (NOT_RUN) | در تحلیل |
| `REQ-12.02` | سند | P0 | یادآوری روز سررسید فاکتور و عقب‌افتادگی | Invoices, Reminders | T-INV-02 (NOT_RUN), T-INV-03 (NOT_RUN) | در تحلیل |
| `REQ-12.03` | سند | P1 | ثبت انجام فاکتور | Invoices | T-INV-04 (NOT_RUN) | در تحلیل |
| `REQ-12.04` | سند | P0 | یادآوری گزارش سئو (۲ روز قبل + روز موعد + عقب‌افتادگی) | SeoReports, Reminders | T-SEO-01 (NOT_RUN), T-SEO-02 (NOT_RUN), T-SEO-03 (NOT_RUN) | در تحلیل |
| `REQ-12.05` | سند | P0 | استقلال یادآوری فاکتور و گزارش + چند دوره | Reminders | T-INV-05 (NOT_RUN), T-SEO-04 (NOT_RUN), T-SEO-05 (NOT_RUN) | در تحلیل |
| `REQ-12.06` | سند | P0 | تغییر تاریخ: بازمحاسبه بدون تکرار، حفظ سوابق | Reminders, Invoices, SeoReports | T-INV-07 (NOT_RUN), T-INV-08 (NOT_RUN), T-SEO-07 (NOT_RUN) | در تحلیل |
| `REQ-12.07` | سند | P0 | زمان‌بندی واقعی، Idempotent و قابل مشاهده | Scheduler | T-SCH-01 (BLOCKED), T-SCH-02 (NOT_RUN), T-SCH-03 (NOT_RUN), T-SCH-04 (NOT_RUN), T-INF-03 (BLOCKED) | در تحلیل |
| `REQ-12.08` | سند | P0 | تعریف روز تقویمی و Timezone | Scheduler, Settings | T-SCH-05 (NOT_RUN), T-DATE-02 (NOT_RUN), T-CAL-04 (PASS), T-CAL-07 (PASS), T-CAL-08 (PASS) | در تحلیل |
| `REQ-12.09` | سند | P1 | ثبت مشخصات و فایل فاکتور پس از ارسال | Invoices, Files | T-INV-06 (NOT_RUN) | در تحلیل |

### REQ-13 — اولویت‌بندی و برنامه اجرا

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-13.01` | بریف | P1 | مرحله‌بندی و گیت تأیید | PM | — | طراحی‌شده |
| `REQ-13.02` | بریف | P1 | بازبینی و تأیید هاست مقصد | Deployment | T-INF-01 (BLOCKED), T-INF-02 (NOT_RUN) | در تحلیل |

### REQ-14 — معیارهای پذیرش

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-14.01` | بریف | P0 | تست واقعی، بدون Pass صوری | QA | T-QA-01 (PASS) | در تحلیل |
| `REQ-14.02` | مشتق | P0 | Requirements Traceability Matrix | PM | T-QA-02 (PASS) | طراحی‌شده |

### REQ-15 — تنظیمات مدیریتی و قابلیت استقرار

| شناسه | نوع | اولویت | عنوان | ماژول | تست‌ها (وضعیت) | وضعیت پیاده‌سازی |
|---|---|---|---|---|---|---|
| `REQ-15.01` | سند | P1 | تنظیمات مدیریتی سامانه | Settings | T-SET-01 (NOT_RUN), T-SET-02 (NOT_RUN), T-PAY-01 (NOT_RUN), T-OPS-05 (NOT_RUN), T-OPS-06 (NOT_RUN) | در تحلیل |
| `REQ-15.02` | بریف | P0 | Backup و Restore تست‌شده | Backup | T-BKP-01 (NOT_RUN), T-BKP-02 (NOT_RUN), T-BKP-03 (NOT_RUN), T-OPS-01 (BLOCKED), T-OPS-02 (NOT_RUN), T-OPS-03 (BLOCKED), T-OPS-04 (NOT_RUN) | در تحلیل |
| `REQ-15.03` | بریف | P1 | Installer امن | Installer | T-INS-01 (NOT_RUN), T-INS-02 (NOT_RUN) | در تحلیل |
| `REQ-15.04` | بریف | P1 | مهاجرت و نسخه‌بندی | DevOps | T-INF-05 (NOT_RUN) | طراحی‌شده |
| `REQ-15.05` | بریف | P2 | تنظیمات محیطی (بدون Hardcode) | DevOps | T-SEC-17 (NOT_RUN), T-KEY-03 (NOT_RUN) | طراحی‌شده |
| `REQ-15.06` | بریف | P2 | بسته مستندات فنی و کاربری | Docs | — | در تحلیل |

