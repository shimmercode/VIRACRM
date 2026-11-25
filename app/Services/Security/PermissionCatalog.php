<?php

namespace App\Services\Security;

/**
 * REQ-10.01–10.07 — the permission catalogue.
 *
 * SOURCE OF TRUTH: client document §10 lists the areas that must be
 * permission-controlled:
 *
 *   «سطح دسترسی کاربران داخلی ... به تفکیک حوزه‌ها: مشتریان، قراردادها،
 *    پروژه‌ها، دسترسی‌های پروژه، پرسنل، حقوق و دستمزد»
 *
 * and separately demands that financial data and passwords are restricted and
 * that viewing a password is recorded.
 *
 * DESIGN: there is exactly one superadmin (§10 first release). Every other
 * capability is a named permission so it can be granted per area without
 * inventing roles the client has not approved.
 *
 * `sensitive` marks permissions that expose money or secrets. §10 says these
 * must be controllable independently of ordinary record access, and every
 * grant/use of a sensitive permission is written to the audit log.
 */
final class PermissionCatalog
{
    /**
     * Area => [action => [title, sensitive]]
     *
     * Actions are deliberately coarse: view, create, update, delete, plus the
     * two special ones the document requires (reveal secrets, export).
     */
    private const AREAS = [
        'customers' => [
            'view' => ['مشاهده مشتریان', false],
            'create' => ['ایجاد مشتری', false],
            'update' => ['ویرایش مشتری', false],
            'delete' => ['حذف مشتری', false],
        ],
        'contracts' => [
            'view' => ['مشاهده قراردادها', false],
            'create' => ['ایجاد قرارداد', false],
            'update' => ['ویرایش قرارداد', false],
            'delete' => ['حذف قرارداد', false],
            // §4: contracts carry financial amounts.
            'view_financials' => ['مشاهده مبالغ قرارداد', true],
            'finalize' => ['نهایی‌سازی و قالب قرارداد', false],
        ],
        'projects' => [
            'view' => ['مشاهده پروژه‌ها', false],
            'create' => ['ایجاد پروژه', false],
            'update' => ['ویرایش پروژه', false],
            'delete' => ['حذف پروژه', false],
            'view_financials' => ['مشاهده مبالغ پروژه', true],
        ],
        'credentials' => [
            'view' => ['مشاهده فهرست دسترسی‌ها', false],
            // §6: revealing a stored password is the sensitive act, and must be audited.
            'reveal' => ['مشاهده رمز دسترسی‌ها', true],
            'manage' => ['ثبت و ویرایش دسترسی‌ها', false],
            'export' => ['خروجی PDF محرمانه دسترسی‌ها', true],
        ],
        'personnel' => [
            'view' => ['مشاهده پرسنل', false],
            'create' => ['ایجاد پرسنل', false],
            'update' => ['ویرایش پرسنل', false],
            'delete' => ['حذف پرسنل', false],
        ],
        'payroll' => [
            'view' => ['مشاهده فیش حقوقی', true],
            'manage' => ['ثبت و محاسبه حقوق', true],
            'approve' => ['تأیید حقوق', true],
        ],
        'notes' => [
            'view' => ['مشاهده یادداشت‌ها', false],
            'create' => ['ایجاد یادداشت', false],
            'update' => ['ویرایش یادداشت', false],
            'delete' => ['حذف یادداشت', false],
        ],
        'reports' => [
            'view' => ['مشاهده گزارش‌ها', false],
            'export' => ['خروجی گزارش‌ها', false],
        ],
        'users' => [
            'view' => ['مشاهده کاربران', false],
            'manage' => ['مدیریت کاربران و نقش‌ها', true],
        ],
        'settings' => [
            'view' => ['مشاهده تنظیمات', false],
            'manage' => ['تغییر تنظیمات سامانه', true],
        ],
        'audit' => [
            'view' => ['مشاهده گزارش رویدادها', true],
        ],
        'files' => [
            'download' => ['دریافت فایل‌های پیوست', false],
            'download_confidential' => ['دریافت فایل محرمانه', true],
        ],

        /*
         * EXP-WORKITEM-001 — the کارتابل.
         *
         * `view` is separate from `complete` on purpose: reading that an
         * invoice is due is ordinary operational awareness; declaring it
         * issued is the act that creates revenue, so it is a distinct grant.
         */
        'work_items' => [
            'view' => ['مشاهده کارتابل', false],
            'complete' => ['بستن مورد کارتابل', false],
            'create' => ['ایجاد مورد کارتابل', false],
            'cancel' => ['لغو مورد کارتابل', false],
        ],

        /* EXP-NOTE-001 — notes and tasks. Own permissions, not folded into
           `customers`, because a note can belong to a customer, a contract or a
           project and its access must be independent of all three. */
        'notes' => [
            'view' => ['مشاهده یادداشت‌ها', false],
            'create' => ['ایجاد یادداشت', false],
            'update' => ['ویرایش یادداشت', false],
            'delete' => ['حذف یادداشت', false],
        ],
    ];

    /**
     * Flat list of every permission, ready to seed.
     *
     * @return list<array{code:string, area:string, action:string, title:string, is_sensitive:bool}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::AREAS as $area => $actions) {
            foreach ($actions as $action => [$title, $sensitive]) {
                $out[] = [
                    'code' => $area . '.' . $action,
                    'area' => $area,
                    'action' => $action,
                    'title' => $title,
                    'is_sensitive' => $sensitive,
                ];
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    /** @return array<string, array<string, array{0:string,1:bool}>> */
    public static function areas(): array
    {
        return self::AREAS;
    }

    public static function code(string $area, string $action): string
    {
        return $area . '.' . $action;
    }

    public static function isSensitive(string $code): bool
    {
        foreach (self::all() as $permission) {
            if ($permission['code'] === $code) {
                return $permission['is_sensitive'];
            }
        }

        return false;
    }
}
