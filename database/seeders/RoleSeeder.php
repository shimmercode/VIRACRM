<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * REQ-10.02 — the staff roles.
 *
 * SCOPE CAUTION: the client document names one superadmin and says access is
 * granted per area (§10). It does NOT prescribe a role matrix. The roles below
 * are therefore the minimum set needed to operate, each a *suggestion* the
 * administrator can change in the permission grid — not an invented policy.
 *
 * This is recorded in the Open Decisions Register (OD-07) so the client can
 * confirm or replace the matrix.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, array{title:string, description:string, permissions:list<string>}> */
    public const ROLES = [
        'manager' => [
            'title' => 'مدیر',
            'description' => 'دسترسی کامل به مشتریان، قراردادها، پروژه‌ها و گزارش‌ها؛ بدون دسترسی به حقوق و مدیریت کاربران.',
            'permissions' => [
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'contracts.view', 'contracts.create', 'contracts.update', 'contracts.delete',
                'contracts.view_financials', 'contracts.finalize',
                'projects.view', 'projects.create', 'projects.update', 'projects.delete',
                'projects.view_financials',
                // reveal is the sensitive one: reading a stored password back.
                // It is granted to the manager only, deliberately (see docs).
                'credentials.view', 'credentials.manage', 'credentials.reveal',
                'notes.view', 'notes.create', 'notes.update', 'notes.delete',
                'reports.view', 'reports.export',
                'files.download', 'files.download_confidential',
                'personnel.view',
            ],
        ],

        'accountant' => [
            'title' => 'حسابدار',
            'description' => 'دسترسی به قراردادها و مبالغ، و حقوق و دستمزد؛ بدون مدیریت کاربران.',
            'permissions' => [
                'customers.view',
                'contracts.view', 'contracts.view_financials',
                'projects.view', 'projects.view_financials',
                'payroll.view', 'payroll.manage',
                'reports.view',
                'notes.view', 'notes.create',
            ],
        ],

        'account_manager' => [
            'title' => 'کارشناس حساب',
            'description' => 'مدیریت مشتریان و قراردادهای خود؛ بدون دسترسی به مبالغ قرارداد یا رمز دسترسی‌ها.',
            'permissions' => [
                'customers.view', 'customers.create', 'customers.update',
                'contracts.view', 'contracts.create', 'contracts.update',
                'projects.view', 'projects.update',
                'credentials.view', 'credentials.manage',
                'notes.view', 'notes.create', 'notes.update',
                'files.download',
            ],
        ],

        'viewer' => [
            'title' => 'مشاهده‌گر',
            'description' => 'فقط مشاهدهٔ مشتریان و قراردادها، بدون هیچ ویرایشی و بدون اطلاعات مالی.',
            'permissions' => [
                'customers.view',
                'contracts.view',
                'projects.view',
                'notes.view',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => $definition) {
            $role = Role::updateOrCreate(
                ['code' => $code],
                ['title' => $definition['title'], 'description' => $definition['description']]
            );

            // sync() rather than attach(): re-running must not duplicate rows,
            // and an edited matrix in the seeder should be reflected.
            if ($role->wasRecentlyCreated || $code === 'manager') {
                $role->syncPermissions($definition['permissions']);
            }
        }

        $this->command?->info('Roles: ' . count(self::ROLES) . ' synced.');
    }
}
