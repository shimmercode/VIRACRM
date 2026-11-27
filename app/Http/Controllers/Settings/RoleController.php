<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * §15 / REQ-15.01 — roles and the permissions attached to them.
 *
 * Gated by `users.manage`, which no seeded role holds: out of the box only a
 * superadmin can reach this screen. That is deliberate and this controller does
 * not change it — widening who may edit roles is itself a role edit.
 *
 * The escalation guard lives in RoleRequest::escalationAttempt() and is applied
 * on both write paths, so a user who is later given `users.manage` still cannot
 * hand out a permission they do not have.
 */
final class RoleController extends Controller
{
    public function index(): View
    {
        return view('settings.roles.index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.roles.create', [
            'role' => new Role(),
            'granted' => [],
            'grouped' => $this->groupedPermissions(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        if (($attempt = $request->escalationAttempt()) !== []) {
            return $this->refuseEscalation($request, $attempt);
        }

        $role = Role::query()->create($request->validatedForModel());
        $unknown = $role->syncPermissions($request->permissionCodes());

        AuditRecorder::created(
            $role,
            sprintf('ثبت نقش «%s» با %d مجوز', $role->title, count($request->permissionCodes())),
        );

        if ($unknown !== []) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $role,
                'مجوزهای ناشناخته در ثبت نقش نادیده گرفته شدند: ' . implode('، ', $unknown),
                ['unknown' => $unknown],
            );
        }

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'نقش ثبت شد.');
    }

    public function edit(Role $role): View
    {
        return view('settings.roles.edit', [
            'role' => $role,
            'granted' => $role->permissions()->pluck('code')->all(),
            'grouped' => $this->groupedPermissions(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        if (($attempt = $request->escalationAttempt()) !== []) {
            return $this->refuseEscalation($request, $attempt);
        }

        /*
         * Users are attached by role id and hold no copy of the code, so the
         * code is in principle editable. It is still the string the seeder,
         * the RBAC document and every audit note refer to, so a role people are
         * attached to keeps it.
         */
        $data = $request->validatedForModel();

        if ($role->users()->exists() && $data['code'] !== $role->code) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['code' => 'کاربری به این نقش اختصاص یافته است؛ کد آن قابل تغییر نیست.']);
        }

        $before = $role->permissions()->pluck('code')->sort()->values()->all();
        $role->fill($data)->save();
        $unknown = $role->syncPermissions($request->permissionCodes());
        $after = $role->permissions()->pluck('code')->sort()->values()->all();

        /*
         * The audit row carries the actual diff. "role updated" tells an
         * auditor nothing; which permissions appeared and which disappeared is
         * the whole point of the change history for this entity.
         */
        AuditRecorder::updated($role, $role->getChanges(), 'ویرایش نقش: ' . $role->title);
        AuditRecorder::record(
            AuditRecorder::UPDATED,
            $role,
            sprintf(
                'تغییر مجوزهای نقش «%s»: %s',
                $role->title,
                $before === $after
                    ? 'بدون تغییر'
                    : sprintf(
                        '+%s / -%s',
                        implode('، ', array_diff($after, $before)) ?: '—',
                        implode('، ', array_diff($before, $after)) ?: '—',
                    ),
            ),
            ['added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after))],
        );

        if ($unknown !== []) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $role,
                'مجوزهای ناشناخته در ویرایش نقش نادیده گرفته شدند: ' . implode('، ', $unknown),
                ['unknown' => $unknown],
            );
        }

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'نقش به‌روزرسانی شد.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $users = $role->users()->count();

        if ($users > 0) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $role,
                sprintf('حذف نقش «%s» رد شد: %d کاربر به آن اختصاص یافته است', $role->title, $users),
                ['refused' => 'in_use', 'users' => $users],
            );

            return redirect()
                ->route('settings.roles.index')
                ->with('error', sprintf(
                    '%d کاربر این نقش را دارند؛ ابتدا نقش آن‌ها را تغییر دهید.',
                    $users,
                ));
        }

        $title = $role->title;
        // Detach first so no orphan row survives in role_permissions.
        $role->permissions()->detach();
        $role->delete();

        AuditRecorder::deleted($role, 'حذف نقش: ' . $title);

        return redirect()
            ->route('settings.roles.index')
            ->with('success', 'نقش حذف شد.');
    }

    /**
     * Permissions grouped by area, for the checkbox matrix.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Permission>>
     */
    private function groupedPermissions()
    {
        return Permission::query()
            ->orderBy('area')
            ->orderBy('id')
            ->get()
            ->groupBy('area');
    }

    /**
     * Refuse an attempt to hand out a permission the actor does not hold.
     *
     * @param  list<string>  $attempt
     */
    private function refuseEscalation(RoleRequest $request, array $attempt): RedirectResponse
    {
        AuditRecorder::denied('users.manage', null);
        AuditRecorder::record(
            AuditRecorder::UPDATED,
            null,
            'تلاش برای اعطای مجوز بدون داشتن آن رد شد: ' . implode('، ', $attempt),
            ['refused' => 'privilege_escalation', 'permissions' => $attempt],
        );

        return redirect()
            ->back()
            ->withInput()
            ->withErrors([
                'permissions' => 'شما نمی‌توانید مجوزی را اعطا کنید که خودتان ندارید: ' . implode('، ', $attempt),
            ]);
    }
}
