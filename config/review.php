<?php

/*
|--------------------------------------------------------------------------
| config/review.php — the CRM Review Environment
|--------------------------------------------------------------------------
|
| The review environment exists so the client can open the real product in a
| browser without touching a development machine. It renders through the same
| Laravel kernel, the same Blade layout and the same design system as the
| application; what it adds is (a) a catalogue of every screen the requirements
| mention, (b) a status label for each so a preview can never be mistaken for
| working software, and (c) a hard isolation switch.
|
| THREE RULES THIS FILE ENFORCES
| ------------------------------
|   1. Nothing here can reach production. `guard()` refuses to activate when the
|      app looks like production, and the review routes are only registered when
|      `enabled` is true.
|   2. Nothing here can send mail, SMS or take payment. Those are forced off when
|      the review environment is on — not documented as off, forced off.
|   3. "UI Preview" is a first-class status, not an apology. A screen with no
|      backend says so on the page, in the sidebar, and in the inventory.
|
| FOUR DIMENSIONS per page (§20)
|   The client asked for more than a status label: each page must say what the
|   design is, whether it was measured responsively, what actually responds to a
|   click, and what is known to be broken. `design`, `responsive`, `interactive`
|   and `issue` are those four. They are NOT derived from `status`: a page can be
|   Functional and still have no measured mobile layout, and saying so is the
|   point.
|
| STATUS VOCABULARY (four values, no others)
|   Functional .... backend and real interaction exist today
|   Partial ....... some backend or interaction exists, but not the whole screen
|   UI Preview .... design only; the screen is here so it can be reviewed
|   Not Implemented  not built at all
|
*/

return [

    // Master switch. When false, /review/* does not exist at all (404), so a
    // production deploy cannot expose it by accident.
    'enabled' => env('VIRA_REVIEW_ENABLED', false),

    // Refuse to boot the review surface anywhere that looks like production.
    // Enforced in ReviewController::guard() so a misconfigured .env fails loudly
    // instead of exposing a review instance on a live host.
    'allowed_environments' => ['local', 'review', 'testing'],

    // Demo accounts created by `php artisan review:seed`. These are development
    // credentials; they are printed to the console by the seeder and never
    // committed. The password is generated per seed run.
    'demo_users' => [
        ['username' => 'review.admin', 'name' => 'مدیر سیستم (Demo)', 'role' => 'manager', 'superadmin' => true],
        ['username' => 'review.manager', 'name' => 'مدیر عملیات (Demo)', 'role' => 'manager', 'superadmin' => false],
        ['username' => 'review.employee', 'name' => 'کارشناس پروژه (Demo)', 'role' => 'account_manager', 'superadmin' => false],
        ['username' => 'review.viewer', 'name' => 'بازدیدکننده (Demo)', 'role' => 'viewer', 'superadmin' => false],
    ],

    /*
    | The page inventory. One row per screen the client asked to see (§12 of the
    | directive) plus the screens the requirements document names but which have
    | no route yet.
    |
    | `route` is null for screens that do not exist; those render a preview at
    | /review/preview/{key} using the same layout. `permission` mirrors the real
    | route's middleware so role visibility can be tested honestly.
    */
    'pages' => [

        // ── Functional: real routes, real data ─────────────────────────────
        [
            'key' => 'dashboard', 'module' => 'داشبورد', 'title' => 'داشبورد',
            'route' => 'dashboard', 'path' => '/', 'status' => 'Functional',
            'permission' => null, 'group' => 'core',
            'note' => 'داده از مشتریان، قراردادها، پروژه‌ها و کارهای عقب‌افتاده.',
            'design' => 'داشبورد واقعی',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'لینک‌های ناوبری، فیلترها',
            'issue' => '—',
        ],
        [
            'key' => 'customers', 'module' => 'مشتریان', 'title' => 'فهرست مشتریان',
            'route' => 'customers.index', 'path' => '/customers', 'status' => 'Functional',
            'permission' => 'customers.view', 'group' => 'core',
            'note' => 'جست‌وجو، فیلتر وضعیت، صفحه‌بندی و ستون‌های قابل مرتب‌سازی واقعی.',
            'design' => 'فهرست و فیلتر واقعی',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'جست‌وجو، فیلتر، مرتب‌سازی، صفحه‌بندی',
            'issue' => '—',
        ],
        [
            'key' => 'customers-create', 'module' => 'مشتریان', 'title' => 'افزودن مشتری',
            'route' => 'customers.create', 'path' => '/customers/create', 'status' => 'Functional',
            'permission' => 'customers.create', 'group' => 'core',
            'note' => 'فرم کامل با اعتبارسنجی سمت سرور.',
            'design' => 'فرم کامل + اعتبارسنجی',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'ثبت واقعی در پایگاه داده',
            'issue' => '—',
        ],
        [
            'key' => 'contracts', 'module' => 'قراردادها', 'title' => 'فهرست قراردادها',
            'route' => 'contracts.index', 'path' => '/contracts', 'status' => 'Functional',
            'permission' => 'contracts.view', 'group' => 'core',
            'note' => 'هشدار انقضا و مبالغ مشروط به مجوز.',
            'design' => 'فهرست واقعی',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'جست‌وجو، فیلتر وضعیت',
            'issue' => '—',
        ],
        [
            'key' => 'contracts-create', 'module' => 'قراردادها', 'title' => 'افزودن قرارداد',
            'route' => 'contracts.create', 'path' => '/contracts/create', 'status' => 'Functional',
            'permission' => 'contracts.create', 'group' => 'core',
            'note' => 'انتخاب مشتری، نوع قرارداد و مبلغ.',
            'design' => 'فرم کامل + اعتبارسنجی',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'ثبت واقعی',
            'issue' => '—',
        ],
        [
            'key' => 'projects', 'module' => 'پروژه‌ها', 'title' => 'فهرست پروژه‌ها',
            'route' => 'projects.index', 'path' => '/projects', 'status' => 'Functional',
            'permission' => 'projects.view', 'group' => 'core',
            'note' => 'وضعیت پروژه با رنگ‌بندی یکسان در کل محصول.',
            'design' => 'فهرست واقعی',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'جست‌وجو، فیلتر',
            'issue' => '—',
        ],
        [
            'key' => 'projects-create', 'module' => 'پروژه‌ها', 'title' => 'افزودن پروژه',
            'route' => 'projects.create', 'path' => '/projects/create', 'status' => 'Functional',
            'permission' => 'projects.create', 'group' => 'core',
            'note' => 'اتصال پروژه به مشتری و قرارداد.',
            'design' => 'فرم کامل',
            'responsive' => '✔ ۴ عرض',
            'interactive' => 'ثبت واقعی',
            'issue' => '—',
        ],

        // ── Not built: named in the requirements, no route yet ─────────────
        [
            'key' => 'employees', 'module' => 'پرسنل', 'title' => 'فهرست پرسنل',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'personnel.view', 'group' => 'operations',
            'note' => 'فهرست و پروفایل کارمند؛ طرح است و از جدول employees خوانده نمی‌شود.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'صفحه‌ای وجود ندارد',
            'preview' => 'employees',
            'design' => 'فهرست + پروفایل + تخصیص پروژه',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — همهٔ دکمه‌ها غیرفعال',
            'issue' => 'هیچ مسیر /employees وجود ندارد',
        ],
        [
            'key' => 'payroll', 'module' => 'حقوق و دستمزد', 'title' => 'دوره‌های حقوق',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'payroll.view', 'group' => 'operations',
            'note' => 'دوره‌های حقوق و فیش؛ هیچ کنترلری وجود ندارد.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'صفحه‌ای وجود ندارد',
            'preview' => 'payroll',
            'design' => 'دوره‌ها + فیش + مسیر تأیید',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — «محاسبه» غیرفعال',
            'issue' => 'هیچ جدول پرداختی خوانده نمی‌شود',
        ],
        [
            'key' => 'notes', 'module' => 'یادداشت و یادآوری', 'title' => 'یادداشت‌ها',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'notes.view', 'group' => 'operations',
            'note' => 'نمای تابلویی و فهرست؛ جدول notes ۸ ردیف دارد ولی صفحه ندارد.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'جدول notes داده دارد اما صفحه ندارد',
            'preview' => 'notes',
            'design' => 'تابلوی ۳ ستون + فهرست',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — کشیدن کارت کار نمی‌کند',
            'issue' => 'صفحه‌ای برای خواندن ۸ ردیف موجود وجود ندارد',
        ],
        [
            'key' => 'search', 'module' => 'جست‌وجوی سراسری', 'title' => 'نتایج جست‌وجو',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => null, 'group' => 'operations',
            'note' => 'نتایج ترکیبی؛ Topbar به هیچ مسیری وصل نیست.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'جست‌وجوی سراسری در topbar نمایشی است',
            'preview' => 'search',
            'design' => 'فیلتر + نتایج گروه‌بندی‌شده',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — تایپ نتیجه نمی‌دهد',
            'issue' => 'هیچ اندیس جست‌وجویی وجود ندارد',
        ],
        [
            'key' => 'reports', 'module' => 'گزارش‌ها', 'title' => 'گزارش‌های مدیریتی',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'reports.view', 'group' => 'operations',
            'note' => 'داشبورد مدیریتی، KPI، نمودار میله‌ای و جدول.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'صفحه‌ای وجود ندارد',
            'preview' => 'reports',
            'design' => 'KPI + نمودار + جدول',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — بازهٔ تاریخ اثر ندارد',
            'issue' => 'هیچ مسیر /reports وجود ندارد',
        ],
        [
            'key' => 'users', 'module' => 'کاربران', 'title' => 'مدیریت کاربران',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'users.view', 'group' => 'admin',
            'note' => 'فهرست کاربران و فرم ساخت؛ /users در محصول ۴۰۴ است.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'مسیر /users وجود ندارد — ۴۰۴',
            'preview' => 'users',
            'design' => 'فهرست + فرم + عملیات حساس',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — ایجاد کاربر غیرفعال',
            'issue' => 'هیچ مسیر /users وجود ندارد',
        ],
        [
            'key' => 'roles', 'module' => 'نقش‌ها و مجوزها', 'title' => 'نقش‌ها و مجوزها',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'users.view', 'group' => 'admin',
            'note' => 'ماتریس نقش × مجوز از دادهٔ واقعی role_permissions.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'صفحه‌ای وجود ندارد',
            'preview' => 'roles',
            'design' => 'ماتریس ۳۹×۴ (خواندنی)',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — فقط خواندن',
            'issue' => 'هیچ صفحهٔ ویرایش مجوز وجود ندارد',
        ],
        [
            'key' => 'accounting', 'module' => 'حسابداری', 'title' => 'داشبورد حسابداری',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'داشبورد مالی، اسناد و ترازنامه؛ فاز ۳.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'فاز ۳',
            'preview' => 'accounting',
            'design' => 'KPI + اسناد + ترازنامه',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — بستن دوره عمداً غیرفعال',
            'issue' => 'هیچ جدول حسابداری وجود ندارد',
        ],
        [
            'key' => 'accounting-income', 'module' => 'حسابداری', 'title' => 'درآمد و هزینه',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'تفکیک ماهانهٔ درآمد و هزینه با نمودار جفت‌ستونی.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'فاز ۳',
            'preview' => 'accounting-income',
            'design' => 'نمودار جفتی + جدول',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — سال را عوض نمی‌کند',
            'issue' => 'فاز ۳',
        ],
        [
            'key' => 'accounting-cash', 'module' => 'حسابداری', 'title' => 'نقد و بانک',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'حساب‌های بانکی، موجودی و مغایرت‌گیری.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'فاز ۳',
            'preview' => 'accounting-cash',
            'design' => 'کارت حساب + تراکنش',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — مغایرت‌گیری غیرفعال',
            'issue' => 'اتصال بانکی وجود ندارد',
        ],
        [
            'key' => 'accounting-receivables', 'module' => 'حسابداری', 'title' => 'مطالبات و بدهی‌ها',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'سن مطالبات و فهرست مطالبات باز.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'فاز ۳',
            'preview' => 'accounting-receivables',
            'design' => 'نوار سنی + جدول',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — یادآوری ایمیلی نمی‌فرستد',
            'issue' => 'فاز ۳',
        ],
        [
            'key' => 'accounting-reports', 'module' => 'حسابداری', 'title' => 'گزارش‌های مالی',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'سود و زیان، ترازنامه، جریان نقد.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'فاز ۳',
            'preview' => 'accounting-reports',
            'design' => 'جدول مالی + خلاصهٔ نقد',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — خروجی PDF/Excel کار نمی‌کند',
            'issue' => 'گزارش مالی بدون دفتر کل معتبر نیست',
        ],
        [
            'key' => 'settings', 'module' => 'تنظیمات', 'title' => 'تنظیمات سیستم',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'settings.view', 'group' => 'admin',
            'note' => 'چهار بخش تنظیمات؛ جدول settings ۴ ردیف دارد.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'صفحه‌ای وجود ندارد',
            'preview' => 'settings',
            'design' => 'آکاردئون ۴ بخش',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — ذخیره نمی‌شود',
            'issue' => 'هیچ مسیر /settings وجود ندارد',
        ],
        [
            'key' => 'project-credentials', 'module' => 'دسترسی‌های پروژه', 'title' => 'دسترسی‌های پروژه',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'credentials.view', 'group' => 'admin',
            'note' => 'مخزن رمز؛ هیچ رمزی ذخیره نشده و هیچ رمزی نمایش داده نمی‌شود.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'رمز دسترسی‌ها صفحه ندارد',
            'preview' => 'project-credentials',
            'design' => 'جدول نیاز رمز + ردیف ماسک‌شده',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — «نمایش» عمداً غیرفعال',
            'issue' => 'ذخیره و نمایش رمز تصمیم امنیتی باز است',
        ],
        [
            'key' => 'admin', 'module' => 'مدیریت سیستم', 'title' => 'مدیریت سیستم',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'permission' => 'settings.view', 'group' => 'admin',
            'note' => 'سلامت، صف، بکاپ و audit؛ هیچ صفحه‌ای وجود ندارد.',
            'design' => 'طراحی‌نشده',
            'responsive' => '✘',
            'interactive' => 'هیچ',
            'issue' => 'سلامت/صف/بکاپ صفحه ندارد',
            'preview' => 'admin',
            'design' => 'کارت سلامت + صف + audit',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — تست بکاپ عمداً حذف شده',
            'issue' => 'بکاپ هرگز تست نشده',
        ],

        // ── UI Preview: design only, deliberately marked ───────────────────
        [
            'key' => 'drive', 'module' => 'Vira Drive', 'title' => 'فضای فایل (Vira Drive)',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'drive', 'permission' => 'files.download', 'group' => 'drive',
            'note' => 'F0 فقط مستندات بود؛ هیچ کد ماژول فایل نوشته نشده. این صفحه طراحی است، نه عملکرد.',
            'design' => 'سه‌پنلی، RTL',
            'responsive' => '✔ ۳ عرض اندازه‌گیری',
            'interactive' => 'هیچ — همهٔ دکمه‌ها نمایشی',
            'issue' => 'بدون backend؛ کلیک روی فایل کاری نمی‌کند',
        ],
        [
            'key' => 'drive-permissions', 'module' => 'Vira Drive', 'title' => 'مجوزهای فایل',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'drive-permissions', 'permission' => 'files.download', 'group' => 'drive',
            'note' => 'مدل مجوز در سند طراحی است؛ تصمیم‌های OD-104/105 هنوز بازند.',
            'design' => 'جدول ماتریس + تقدم ۶ مرحله',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ',
            'issue' => 'OD-104/105 باز؛ ماتریس اجرا نمی‌شود',
        ],
        [
            'key' => 'drive-versions', 'module' => 'Vira Drive', 'title' => 'نسخه‌ها و قفل‌ها',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'drive-versions', 'permission' => 'files.download', 'group' => 'drive',
            'note' => 'قفل و نسخه‌بندی طراحی شده‌اند؛ هیچ تست ویندوزی اجرا نشده.',
            'design' => 'تایم‌لاین + قفل + فعالیت',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ',
            'issue' => 'قفل و TTL نمایشی؛ هیچ تست ویندوزی',
        ],
        [
            'key' => 'org', 'module' => 'ساختار سازمانی', 'title' => 'ساختار سازمانی و کارمندان',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'org', 'permission' => 'users.view', 'group' => 'admin',
            'note' => 'Model A طراحی شده؛ هیچ جدول departments یا file_groups وجود ندارد. کارمندان از جدول واقعی خوانده می‌شوند.',
            'design' => 'کارت نقش + جدول کارمند',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — فقط خواندن',
            'issue' => 'جدول departments وجود ندارد',
        ],
        [
            'key' => 'project-team', 'module' => 'ساختار سازمانی', 'title' => 'تیم پروژه',
            'route' => null, 'path' => null, 'status' => 'Partial',
            'preview' => 'project-team', 'permission' => 'projects.view', 'group' => 'admin',
            'note' => 'داده از project_assignments واقعی؛ اما هیچ مجوزی از این عضویت حل نمی‌شود — OD-118 باز است.',
            'design' => 'کارت پروژه + جدول اعضا',
            'responsive' => '✔ ۷ عرض',
            'interactive' => 'هیچ — فقط خواندن',
            'issue' => 'هیچ مجوزی از عضویت حل نمی‌شود (OD-118)',
        ],
    ],
];
