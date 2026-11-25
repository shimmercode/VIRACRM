# ماتریس نقش و مجوز

> **این فایل تولید می‌شود.** با `node tools/generate-rbac-doc.mjs` از پایگاه
> داده بازتولید می‌شود و `docs-integrity` هم‌خوانی‌اش را بررسی می‌کند.
> دستی ویرایش نکنید.

4 نقش، **55** مجوز، 2 کاربر (1 ابرمدیر).

## مدل

- **ابرمدیر** (`users.is_superadmin`) از همهٔ بررسی‌ها عبور می‌کند و وجودش در
  ماتریس معنا ندارد. عمداً ستون جدا دارد تا «همه‌کاره» یک تصمیم صریح باشد، نه
  مجموعه‌ای از تیک‌ها که کسی یادش رفته بردارد.
- **نقش‌ها** از طریق `user_roles` به کاربر وصل می‌شوند. یک کاربر می‌تواند چند
  نقش داشته باشد و مجوزهایشان **جمع** می‌شود.
- **مجوزها** با `permission:*` روی مسیرها اعمال می‌شوند. یک بررسی در میدل‌ور
  کافی نیست که دکمه را مخفی کند — همان مسیر باید مستقل هم بررسی شود، وگرنه
  یک POST مستقیم از آن عبور می‌کند.
- **جداسازی عمدی:** `contracts.finalize` از `contracts.create` جداست؛
  `credentials.reveal` و `credentials.export` از `credentials.view` جدا هستند؛
  `payroll.manage` از `payroll.approve` جدا است. کارِ آماده‌کردن و کارِ تأیید
  کردن دو قدرت متفاوت‌اند.

## ماتریس

مقدار هر خانه: ✅ مجوز دارد، — ندارد. ⚠️ یعنی مجوز در `permissions.is_sensitive`
حساس علامت خورده است — خواندن یا بیرون‌بردن دادهٔ محرمانه، نه فقط دیدن فهرست.

| مجوز | حساس | مدیر | حسابدار | کارشناس حساب | مشاهده‌گر |
|---|---|---|---|---|---|
| **accounting** | | | | | |
| `accounting.close` | ⚠️ | — | ✅ | — | — |
| `accounting.post` | ⚠️ | — | ✅ | — | — |
| `accounting.reopen` | ⚠️ | — | ✅ | — | — |
| `accounting.view` | ⚠️ | ✅ | ✅ | — | — |
| **audit** | | | | | |
| `audit.view` | ⚠️ | — | — | — | — |
| **contracts** | | | | | |
| `contracts.create` |  | ✅ | — | ✅ | — |
| `contracts.delete` |  | ✅ | — | — | — |
| `contracts.finalize` |  | ✅ | — | — | — |
| `contracts.update` |  | ✅ | — | ✅ | — |
| `contracts.view` |  | ✅ | ✅ | ✅ | ✅ |
| `contracts.view_financials` | ⚠️ | ✅ | ✅ | — | — |
| **credentials** | | | | | |
| `credentials.export` | ⚠️ | ✅ | — | — | — |
| `credentials.manage` |  | ✅ | — | ✅ | — |
| `credentials.reveal` | ⚠️ | ✅ | — | — | — |
| `credentials.view` |  | ✅ | — | ✅ | — |
| **customers** | | | | | |
| `customers.create` |  | ✅ | — | ✅ | — |
| `customers.delete` |  | ✅ | — | — | — |
| `customers.update` |  | ✅ | — | ✅ | — |
| `customers.view` |  | ✅ | ✅ | ✅ | ✅ |
| **departments** | | | | | |
| `departments.delete` |  | ✅ | — | — | — |
| `departments.manage` |  | ✅ | — | — | — |
| `departments.view` |  | ✅ | — | — | — |
| **files** | | | | | |
| `files.download` |  | ✅ | — | ✅ | — |
| `files.download_confidential` | ⚠️ | ✅ | — | — | — |
| **invoices** | | | | | |
| `invoices.create` | ⚠️ | — | ✅ | — | — |
| `invoices.issue` | ⚠️ | — | ✅ | — | — |
| `invoices.record_payment` | ⚠️ | — | ✅ | — | — |
| `invoices.view` | ⚠️ | ✅ | ✅ | — | — |
| `invoices.void` | ⚠️ | — | ✅ | — | — |
| **notes** | | | | | |
| `notes.create` |  | ✅ | ✅ | ✅ | — |
| `notes.delete` |  | ✅ | — | — | — |
| `notes.update` |  | ✅ | — | ✅ | — |
| `notes.view` |  | ✅ | ✅ | ✅ | ✅ |
| **payroll** | | | | | |
| `payroll.approve` | ⚠️ | — | — | — | — |
| `payroll.manage` | ⚠️ | — | ✅ | — | — |
| `payroll.view` | ⚠️ | — | ✅ | — | — |
| **personnel** | | | | | |
| `personnel.create` |  | — | — | — | — |
| `personnel.delete` |  | — | — | — | — |
| `personnel.update` |  | — | — | — | — |
| `personnel.view` |  | ✅ | — | — | — |
| **projects** | | | | | |
| `projects.create` |  | ✅ | — | — | — |
| `projects.delete` |  | ✅ | — | — | — |
| `projects.update` |  | ✅ | — | ✅ | — |
| `projects.view` |  | ✅ | ✅ | ✅ | ✅ |
| `projects.view_financials` | ⚠️ | ✅ | ✅ | — | — |
| **reports** | | | | | |
| `reports.export` |  | ✅ | — | — | — |
| `reports.view` |  | ✅ | ✅ | — | — |
| **settings** | | | | | |
| `settings.manage` | ⚠️ | — | — | — | — |
| `settings.view` |  | — | — | — | — |
| **users** | | | | | |
| `users.manage` | ⚠️ | — | — | — | — |
| `users.view` |  | — | — | — | — |
| **work_items** | | | | | |
| `work_items.cancel` |  | ✅ | — | — | — |
| `work_items.complete` |  | ✅ | ✅ | ✅ | — |
| `work_items.create` |  | ✅ | ✅ | ✅ | — |
| `work_items.view` |  | ✅ | ✅ | ✅ | ✅ |

## جمع هر نقش

| نقش | کد | تعداد مجوز |
|---|---|---|
| مدیر | `manager` | 37 |
| حسابدار | `accountant` | 22 |
| کارشناس حساب | `account_manager` | 17 |
| مشاهده‌گر | `viewer` | 5 |

## مجوزهای بدون نقش

این مجوزها به هیچ نقشی داده نشده‌اند؛ فقط ابرمدیر می‌تواند از آن‌ها
استفاده کند. اگر این عمدی نیست، seed نقش‌ها ناقص است.

- `audit.view`
- `payroll.approve`
- `personnel.create`
- `personnel.delete`
- `personnel.update`
- `settings.manage`
- `settings.view`
- `users.manage`
- `users.view`

## آنچه این ماتریس نشان نمی‌دهد

1. **مجوزهای سطح ردیف.** این ماتریس می‌گوید چه کسی *چه کاری* می‌تواند بکند، نه
   *روی کدام ردیف*. محدودسازی ردیف (مثلاً فقط پروژه‌های خودم) در کوئری‌ها است.
2. **مالکیت.** برخی مسیرها علاوه بر مجوز، مالکیت رکورد را هم می‌سنجند.
3. **مسیرهایی که مجوز ندارند** (ورود، خروج، صفحهٔ اصلی). این‌ها در ماتریس نیستند.

## آزمون

| چه چیزی | کجا |
|---|---|
| هر مسیر محافظت‌شده مجوز دارد | `tools/checks/routes.php` |
| مجوز نبودن → ۴۰۳ (نه ۴۰۴ خاموش) | `tools/checks/permission-flow.mjs` |
| ماتریس با پایگاه هم‌خوان است | `tools/checks/docs-integrity.mjs` |
