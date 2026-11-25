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

        [
            'label' => 'دسترسی‌های پروژه',
            'icon' => 'key',
            'route' => null,
            'permission' => 'credentials.view',
            'enabled' => false,
            'note' => 'به‌زودی',
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

        [
            'label' => 'پرسنل',
            'icon' => 'briefcase',
            'route' => null,
            'permission' => 'personnel.view',
            'enabled' => false,
            'note' => 'به‌زودی',
        ],

        [
            'label' => 'حقوق و دستمزد',
            'icon' => 'banknote',
            'route' => null,
            'permission' => 'payroll.view',
            'enabled' => false,
            'note' => 'به‌زودی',
        ],

        [
            'label' => 'گزارش‌ها',
            'icon' => 'chart',
            'route' => null,
            'permission' => 'reports.view',
            'enabled' => false,
            'note' => 'به‌زودی',
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

        [
            'label' => 'کاربران و نقش‌ها',
            'icon' => 'user',
            'route' => null,
            'permission' => 'users.view',
            'enabled' => false,
            'note' => 'به‌زودی',
        ],

        [
            'label' => 'تنظیمات',
            'icon' => 'settings',
            'route' => null,
            'permission' => 'settings.view',
            'enabled' => false,
            'note' => 'به‌زودی',
        ],
    ],
];
