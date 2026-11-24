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

        [
            'label' => 'دسترسی‌های پروژه',
            'icon' => 'key',
            'route' => null,
            'permission' => 'credentials.view',
            'enabled' => false,
            'note' => 'به‌زودی',
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
