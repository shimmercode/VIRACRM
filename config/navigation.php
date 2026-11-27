<?php

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
|
| REQ-11.01 / §11 — the sidebar is generated from this list and filtered by
| the signed-in user's real permissions.
|
| SCOPE RULE: every entry here maps to a module named in the client document.
| Nothing is added because a CRM "usually" has it. Modules that are in the
| document but not yet built are marked `enabled => false` and render as a
| disabled row with a "in progress" note rather than as a dead link — a link
| that 404s is worse than an honest label.
|
| Icons are names resolved by <x-icon>, all from one set, so strokes match.
|
*/

return [

    'primary' => [

        [
            'label' => 'داشبورد',
            'icon' => 'home',
            'route' => 'dashboard',
            'permission' => null, // every authenticated user
            'enabled' => true,
        ],

        [
            'label' => 'مشتریان',
            'icon' => 'users',
            'route' => 'customers.index',
            'permission' => 'customers.view',
            'enabled' => true,
        ],

        [
            'label' => 'قراردادها',
            'icon' => 'document',
            'route' => 'contracts.index',
            'permission' => 'contracts.view',
            'enabled' => true,
        ],

        // §5 — project module (M3).
        [
            'label' => 'پروژه‌ها',
            'icon' => 'folder',
            'route' => 'projects.index',
            'permission' => 'projects.view',
            'enabled' => true,
        ],

        // EXP-WORKITEM-001 — the recurring obligations of every project.
        [
            'label' => 'کارتابل',
            'icon' => 'check',
            'route' => 'work-items.index',
            'permission' => 'work_items.view',
            'enabled' => true,
        ],

        /*
         * Project credentials are SCOPED TO A PROJECT — `credentials.index` takes
         * a `{project}` parameter, so there is no single page this could link to.
         * It used to carry the note «به‌زودی» ("coming soon"), which was untrue:
         * the feature has been built and working under every project. The note
         * now says where it actually lives, which is the honest thing to show a
         * user who goes looking for it here.
         */
        [
            'label' => 'دسترسی‌های پروژه',
            'icon' => 'key',
            'route' => null,
            'permission' => 'credentials.view',
            'enabled' => false,
            'note' => 'از صفحهٔ پروژه',
        ],

        // ── Review Environment entry ─────────────────────────────────────
        // Present only when the review surface is mounted, so a production
        // deploy never shows a link to a route that does not exist.
        [
            'label' => 'محیط بازبینی',
            'icon' => 'eye',
            'route' => 'review.index',
            'permission' => null,
            'enabled' => true,
            'review_only' => true,
        ],
    ],

    'secondary' => [

        // §15 — personnel file (REQ-07).
        [
            'label' => 'پرسنل',
            'icon' => 'briefcase',
            'route' => 'employees.index',
            'permission' => 'personnel.view',
            'enabled' => true,
        ],

        // §17 — departments and cost centres.
        [
            'label' => 'ساختار سازمانی',
            'icon' => 'settings',
            'route' => 'organization.index',
            'permission' => 'departments.view',
            'enabled' => true,
        ],

        // OD-16 — payroll.
        [
            'label' => 'حقوق و دستمزد',
            'icon' => 'banknote',
            'route' => 'payroll.index',
            'permission' => 'payroll.view',
            'enabled' => true,
        ],

        /*
         * «گزارش‌ها» was a routeless stub marked «به‌زودی», directly above three
         * WORKING report entries (contracts, workload, work items). A client
         * reading the sidebar concluded reporting was unbuilt while three
         * separate «گزارش …» links sat underneath it. It now leads to the report
         * area instead of contradicting it.
         */
        [
            'label' => 'گزارش‌ها',
            'icon' => 'chart',
            'route' => 'reports.contracts',
            'permission' => 'reports.view',
            'enabled' => true,
        ],

        // EXP-NOTE-001 — no longer a placeholder.
        [
            'label' => 'یادداشت و وظیفه',
            'icon' => 'bell',
            'route' => 'notes.index',
            'permission' => 'notes.view',
            'enabled' => true,
        ],

        // OD-41 — the ledger and its reports.
        [
            'label' => 'حسابداری',
            'icon' => 'chart',
            'route' => 'accounting.chart',
            'permission' => 'accounting.view',
            'enabled' => true,
        ],

        // OD-41 §19.6 — invoices.
        [
            'label' => 'فاکتورها',
            'icon' => 'document',
            'route' => 'invoices.index',
            'permission' => 'invoices.view',
            'enabled' => true,
        ],

        /*
         * Users and roles ARE implemented (settings/roles is enabled further
         * down this list as «نقش‌ها و مجوزها»). This entry claimed «به‌زودی»,
         * which told a client the opposite of the truth. It now points at the
         * same working screen. The duplication is deliberate and temporary: a
         * single top-level "users" entry versus a settings-section entry is a
         * product decision that has not been taken, and deleting either would
         * be changing the navigation architecture without authorization.
         */
        [
            'label' => 'کاربران و نقش‌ها',
            'icon' => 'user',
            'route' => 'settings.roles.index',
            'permission' => 'users.manage',
            'enabled' => true,
        ],

        [
            'label' => 'گزارش رویدادها',
            'icon' => 'clock',
            'route' => 'audit.index',
            'permission' => 'audit.view',
            'enabled' => true,
        ],

        [
            'label' => 'گزارش قراردادها',
            'icon' => 'document',
            'route' => 'reports.contracts',
            'permission' => 'reports.view',
            'enabled' => true,
        ],
        [
            'label' => 'گزارش بار کاری',
            'icon' => 'chart',
            'route' => 'reports.expert-workload',
            'permission' => 'reports.view',
            'enabled' => true,
        ],

        [
            'label' => 'گزارش اقدامات',
            'icon' => 'folder',
            'route' => 'reports.work-items',
            'permission' => 'reports.view',
            'enabled' => true,
        ],

        [
            'label' => 'پکیج‌های سئو',
            'icon' => 'folder',
            'route' => 'settings.seo-packages.index',
            'permission' => 'settings.view',
            'enabled' => true,
        ],
        [
            'label' => 'انواع قرارداد',
            'icon' => 'folder',
            'route' => 'settings.contract-types.index',
            'permission' => 'settings.view',
            'enabled' => true,
        ],
        [
            'label' => 'انواع پروژه',
            'icon' => 'folder',
            'route' => 'settings.project-types.index',
            'permission' => 'settings.view',
            'enabled' => true,
        ],
        [
            'label' => 'دسترسی‌های الزامی',
            'icon' => 'folder',
            'route' => 'settings.access-requirements.index',
            'permission' => 'settings.view',
            'enabled' => true,
        ],
        [
            'label' => 'روش محاسبهٔ حقوق',
            'icon' => 'folder',
            'route' => 'settings.payroll-rules.index',
            'permission' => 'settings.view',
            'enabled' => true,
        ],
        [
            'label' => 'نقش‌ها و مجوزها',
            'icon' => 'settings',
            'route' => 'settings.roles.index',
            'permission' => 'users.manage',
            'enabled' => true,
        ],
        [
            'label' => 'تنظیمات',
            'icon' => 'settings',
            'route' => 'settings.index',
            'permission' => 'settings.view',
            'enabled' => true,
        ],
    ],
];
