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
        ],
        [
            'key' => 'customers', 'module' => 'مشتریان', 'title' => 'فهرست مشتریان',
            'route' => 'customers.index', 'path' => '/customers', 'status' => 'Functional',
            'permission' => 'customers.view', 'group' => 'core',
            'note' => 'جست‌وجو، فیلتر وضعیت، صفحه‌بندی و ستون‌های قابل مرتب‌سازی واقعی.',
        ],
        [
            'key' => 'customers-create', 'module' => 'مشتریان', 'title' => 'افزودن مشتری',
            'route' => 'customers.create', 'path' => '/customers/create', 'status' => 'Functional',
            'permission' => 'customers.create', 'group' => 'core',
            'note' => 'فرم کامل با اعتبارسنجی سمت سرور.',
        ],
        [
            'key' => 'contracts', 'module' => 'قراردادها', 'title' => 'فهرست قراردادها',
            'route' => 'contracts.index', 'path' => '/contracts', 'status' => 'Functional',
            'permission' => 'contracts.view', 'group' => 'core',
            'note' => 'هشدار انقضا و مبالغ مشروط به مجوز.',
        ],
        [
            'key' => 'contracts-create', 'module' => 'قراردادها', 'title' => 'افزودن قرارداد',
            'route' => 'contracts.create', 'path' => '/contracts/create', 'status' => 'Functional',
            'permission' => 'contracts.create', 'group' => 'core',
            'note' => 'انتخاب مشتری، نوع قرارداد و مبلغ.',
        ],
        [
            'key' => 'projects', 'module' => 'پروژه‌ها', 'title' => 'فهرست پروژه‌ها',
            'route' => 'projects.index', 'path' => '/projects', 'status' => 'Functional',
            'permission' => 'projects.view', 'group' => 'core',
            'note' => 'وضعیت پروژه با رنگ‌بندی یکسان در کل محصول.',
        ],
        [
            'key' => 'projects-create', 'module' => 'پروژه‌ها', 'title' => 'افزودن پروژه',
            'route' => 'projects.create', 'path' => '/projects/create', 'status' => 'Functional',
            'permission' => 'projects.create', 'group' => 'core',
            'note' => 'اتصال پروژه به مشتری و قرارداد.',
        ],

        // ── Not built: named in the requirements, no route yet ─────────────
        [
            'key' => 'employees', 'module' => 'پرسنل', 'title' => 'فهرست پرسنل',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'personnel.view', 'group' => 'operations',
            'note' => 'جدول employees وجود دارد ولی هنوز صفحه و کنترلر ندارد.',
        ],
        [
            'key' => 'payroll', 'module' => 'حقوق و دستمزد', 'title' => 'دوره‌های حقوق',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'payroll.view', 'group' => 'operations',
            'note' => 'جدول‌های payroll موجودند؛ محاسبه و صفحه پیاده‌سازی نشده (M7، OD-16 باز است).',
        ],
        [
            'key' => 'notes', 'module' => 'یادداشت و یادآوری', 'title' => 'یادداشت‌ها',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'notes.view', 'group' => 'operations',
            'note' => 'جدول notes وجود دارد؛ صفحه ندارد.',
        ],
        [
            'key' => 'search', 'module' => 'جست‌وجوی سراسری', 'title' => 'نتایج جست‌وجو',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => null, 'group' => 'operations',
            'note' => 'جست‌وجوی نوار بالا فعلاً فقط به فهرست مشتریان می‌رود؛ جست‌وجوی سراسری وجود ندارد.',
        ],
        [
            'key' => 'reports', 'module' => 'گزارش‌ها', 'title' => 'گزارش‌های مدیریتی',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'reports.view', 'group' => 'operations',
            'note' => 'هیچ کنترلر گزارشی وجود ندارد.',
        ],
        [
            'key' => 'users', 'module' => 'کاربران', 'title' => 'مدیریت کاربران',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'users.view', 'group' => 'admin',
            'note' => 'جدول users و نقش‌ها موجودند؛ صفحه مدیریت کاربر ساخته نشده.',
        ],
        [
            'key' => 'roles', 'module' => 'نقش‌ها و مجوزها', 'title' => 'نقش‌ها و مجوزها',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'users.view', 'group' => 'admin',
            'note' => '۳۹ مجوز و ۴ نقش در دیتابیس؛ ویرایشگر مجوز ساخته نشده.',
        ],
        [
            'key' => 'accounting', 'module' => 'حسابداری', 'title' => 'داشبورد حسابداری',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'طراحی حسابداری در فاز ۳ انجام شده؛ هیچ صفحه‌ای وجود ندارد.',
        ],
        [
            'key' => 'accounting-income', 'module' => 'حسابداری', 'title' => 'درآمد و هزینه',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'بدون صفحه و بدون Backend.',
        ],
        [
            'key' => 'accounting-cash', 'module' => 'حسابداری', 'title' => 'نقد و بانک',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'بدون صفحه و بدون Backend.',
        ],
        [
            'key' => 'accounting-receivables', 'module' => 'حسابداری', 'title' => 'مطالبات و بدهی‌ها',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'بدون صفحه و بدون Backend.',
        ],
        [
            'key' => 'accounting-reports', 'module' => 'حسابداری', 'title' => 'گزارش‌های مالی',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'payroll.view', 'group' => 'finance',
            'note' => 'بدون صفحه و بدون Backend.',
        ],
        [
            'key' => 'settings', 'module' => 'تنظیمات', 'title' => 'تنظیمات سیستم',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'settings.view', 'group' => 'admin',
            'note' => 'جدول settings موجود است؛ صفحه تنظیمات ساخته نشده.',
        ],
        [
            'key' => 'project-credentials', 'module' => 'دسترسی‌های پروژه', 'title' => 'دسترسی‌های پروژه',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'credentials.view', 'group' => 'admin',
            'note' => 'جدول‌های credential موجودند؛ صفحه رمزنگاری‌شده ساخته نشده.',
        ],
        [
            'key' => 'admin', 'module' => 'مدیریت سیستم', 'title' => 'مدیریت سیستم',
            'route' => null, 'path' => null, 'status' => 'Not Implemented',
            'permission' => 'settings.view', 'group' => 'admin',
            'note' => 'سلامت سیستم، صف، بکاپ — هیچ‌کدام صفحه ندارند.',
        ],

        // ── UI Preview: design only, deliberately marked ───────────────────
        [
            'key' => 'drive', 'module' => 'Vira Drive', 'title' => 'فضای فایل (Vira Drive)',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'drive', 'permission' => 'files.download', 'group' => 'drive',
            'note' => 'F0 فقط مستندات بود؛ هیچ کد ماژول فایل نوشته نشده. این صفحه طراحی است، نه عملکرد.',
        ],
        [
            'key' => 'drive-permissions', 'module' => 'Vira Drive', 'title' => 'مجوزهای فایل',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'drive-permissions', 'permission' => 'files.download', 'group' => 'drive',
            'note' => 'مدل مجوز در سند طراحی است؛ تصمیم‌های OD-104/105 هنوز بازند.',
        ],
        [
            'key' => 'drive-versions', 'module' => 'Vira Drive', 'title' => 'نسخه‌ها و قفل‌ها',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'drive-versions', 'permission' => 'files.download', 'group' => 'drive',
            'note' => 'قفل و نسخه‌بندی طراحی شده‌اند؛ هیچ تست ویندوزی اجرا نشده.',
        ],
        [
            'key' => 'org', 'module' => 'ساختار سازمانی', 'title' => 'ساختار سازمانی و کارمندان',
            'route' => null, 'path' => null, 'status' => 'UI Preview',
            'preview' => 'org', 'permission' => 'users.view', 'group' => 'admin',
            'note' => 'Model A طراحی شده؛ هیچ جدول departments یا file_groups وجود ندارد. کارمندان از جدول واقعی خوانده می‌شوند.',
        ],
        [
            'key' => 'project-team', 'module' => 'ساختار سازمانی', 'title' => 'تیم پروژه',
            'route' => null, 'path' => null, 'status' => 'Partial',
            'preview' => 'project-team', 'permission' => 'projects.view', 'group' => 'admin',
            'note' => 'داده از project_assignments واقعی؛ اما هیچ مجوزی از این عضویت حل نمی‌شود — OD-118 باز است.',
        ],
    ],
];
