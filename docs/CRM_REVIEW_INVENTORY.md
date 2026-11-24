# CRM_REVIEW_INVENTORY — فهرست کامل ۲۷ صفحه

> همهٔ اعداد این سند از `config/review.php` محاسبه شده‌اند، نه دستی نوشته شده.
> بازبینی: `node tools/checks/review-integrity.mjs`

## خلاصه

| وضعیت | تعداد | معنا |
|---|---|---|
| Functional | 7 | مسیر واقعی، کوئری واقعی، تعامل واقعی |
| Partial | 1 | داده واقعی هست، ولی منطق مجوز/کارکرد کامل نیست |
| UI Preview | 4 | فقط طراحی؛ هیچ بک‌اندی ندارد |
| Not Implemented | 15 | هیچ صفحه‌ای وجود ندارد |
| **جمع** | **27** | |


## Functional — 7 صفحه

| # | ماژول | صفحه | مسیر | URL |
|---|---|---|---|---|
| 1 | داشبورد | داشبورد | `dashboard` | `/` |
| 2 | مشتریان | فهرست مشتریان | `customers` | `/customers` |
| 3 | مشتریان | افزودن مشتری | `customers-create` | `/customers/create` |
| 4 | قراردادها | فهرست قراردادها | `contracts` | `/contracts` |
| 5 | قراردادها | افزودن قرارداد | `contracts-create` | `/contracts/create` |
| 6 | پروژه‌ها | فهرست پروژه‌ها | `projects` | `/projects` |
| 7 | پروژه‌ها | افزودن پروژه | `projects-create` | `/projects/create` |

## Partial — 1 صفحه

| # | ماژول | صفحه | مسیر | URL |
|---|---|---|---|---|
| 1 | ساختار سازمانی | تیم پروژه | `project-team` | `/review/preview/project-team` |

## UI Preview — 4 صفحه

| # | ماژول | صفحه | مسیر | URL |
|---|---|---|---|---|
| 1 | Vira Drive | فضای فایل (Vira Drive) | `drive` | `/review/preview/drive` |
| 2 | Vira Drive | مجوزهای فایل | `drive-permissions` | `/review/preview/drive-permissions` |
| 3 | Vira Drive | نسخه‌ها و قفل‌ها | `drive-versions` | `/review/preview/drive-versions` |
| 4 | ساختار سازمانی | ساختار سازمانی و کارمندان | `org` | `/review/preview/org` |

## Not Implemented — 15 صفحه

| # | ماژول | صفحه | مسیر | URL |
|---|---|---|---|---|
| 1 | پرسنل | فهرست پرسنل | `employees` | `—` |
| 2 | حقوق و دستمزد | دوره‌های حقوق | `payroll` | `—` |
| 3 | یادداشت و یادآوری | یادداشت‌ها | `notes` | `—` |
| 4 | جست‌وجوی سراسری | نتایج جست‌وجو | `search` | `—` |
| 5 | گزارش‌ها | گزارش‌های مدیریتی | `reports` | `—` |
| 6 | کاربران | مدیریت کاربران | `users` | `—` |
| 7 | نقش‌ها و مجوزها | نقش‌ها و مجوزها | `roles` | `—` |
| 8 | حسابداری | داشبورد حسابداری | `accounting` | `—` |
| 9 | حسابداری | درآمد و هزینه | `accounting-income` | `—` |
| 10 | حسابداری | نقد و بانک | `accounting-cash` | `—` |
| 11 | حسابداری | مطالبات و بدهی‌ها | `accounting-receivables` | `—` |
| 12 | حسابداری | گزارش‌های مالی | `accounting-reports` | `—` |
| 13 | تنظیمات | تنظیمات سیستم | `settings` | `—` |
| 14 | دسترسی‌های پروژه | دسترسی‌های پروژه | `project-credentials` | `—` |
| 15 | مدیریت سیستم | مدیریت سیستم | `admin` | `—` |

## قاعده‌ای که این جدول را قابل‌اتکا می‌کند

هر ردیف `Functional` **باید** به یک route واقعی اشاره کند. اگر روزی صفحه‌ای
حذف شود و برچسبش Functional بماند، `review-integrity.mjs` شکست می‌خورد.
هیچ ردیفی با «طراحی شده» به Approved ارتقا پیدا نمی‌کند — واژگان وضعیت
جدا از واژگان تصمیم است.
