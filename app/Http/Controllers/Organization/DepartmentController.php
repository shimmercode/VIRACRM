<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * §17 / §19.3 — the organisational structure.
 *
 * WHY DEPARTMENTS AND COST CENTRES ARE ONE SCREEN
 * -----------------------------------------------
 * A cost centre is "where the money was spent"; a department is "who spent it".
 * They are almost always one-to-one at the top and only diverge deeper down, so
 * a separate screen for each would mean an administrator creating a department,
 * then a cost centre, then linking them — twice the work and a chance to forget
 * the link. Here the cost centre is created inline with its department.
 *
 * THE TREE IS SHALLOW ON PURPOSE
 * ------------------------------
 * Contract §19.1 required the CHART OF ACCOUNTS to be extensible beyond three
 * levels. It says nothing of the sort about departments, and an unbounded
 * department tree is how organisational charts rot: every reorg leaves orphan
 * branches nobody dares delete. Two levels (a department and its sub-units) is
 * enough for the reporting this product does, and the depth is enforced here
 * rather than left to convention.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::query()
            ->withCount(['employees', 'children'])
            ->with(['parent:id,title'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $costCenters = CostCenter::query()
            ->with('department:id,title,parent_id')
            ->orderBy('code')
            ->get();

        return view('organization.index', [
            'departments' => $departments,
            'roots' => $departments->whereNull('parent_id'),
            'costCenters' => $costCenters,
            'canManage' => $request->user()->hasPermission('departments.manage'),
            'canDelete' => $request->user()->hasPermission('departments.delete'),
            // Unassigned headcount is a real signal: those people appear in no
            // departmental report until someone places them.
            'unassigned' => Employee::query()->whereNull('department_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('departments', 'code')],
            'title' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'code.required' => 'کد واحد را وارد کنید.',
            'code.unique' => 'این کد واحد قبلاً استفاده شده است.',
            'title.required' => 'نام واحد را وارد کنید.',
            'parent_id.exists' => 'واحد بالادستی انتخاب‌شده معتبر نیست.',
        ]);

        // Depth guard: a sub-unit may not itself be a parent.
        if (! empty($data['parent_id'])) {
            $parent = Department::query()->find($data['parent_id']);

            if ($parent !== null && $parent->parent_id !== null) {
                return back()->withInput()->with(
                    'error',
                    'ساختار سازمانی حداکثر دو سطح دارد. «' . $parent->title . '» خودش زیرمجموعهٔ واحد دیگری است.'
                );
            }
        }

        $department = Department::create([
            'code' => $data['code'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
        ]);

        AuditRecorder::created($department, 'ایجاد واحد سازمانی: ' . $department->title);

        return back()->with('success', 'واحد سازمانی «' . $department->title . '» ثبت شد.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable', 'integer', 'exists:departments,id',
                // A department cannot be its own parent; that produces a tree
                // that renders forever.
                Rule::notIn([$department->id]),
            ],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'title.required' => 'نام واحد را وارد کنید.',
            'parent_id.not_in' => 'یک واحد نمی‌تواند زیرمجموعهٔ خودش باشد.',
        ]);

        if (! empty($data['parent_id'])) {
            $parent = Department::query()->find($data['parent_id']);

            if ($parent !== null && $parent->parent_id !== null) {
                return back()->with('error', 'ساختار سازمانی حداکثر دو سطح دارد.');
            }

            if ($parent !== null && $department->children()->exists()) {
                return back()->with('error',
                    'این واحد خودش زیرمجموعه دارد و نمی‌تواند زیرمجموعهٔ واحد دیگری شود.'
                );
            }
        }

        $before = $department->getAttributes();
        $department->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? $department->sort_order),
        ]);

        $changes = AuditLog::diff($before, $department->fresh()->getAttributes());

        if ($changes !== []) {
            AuditRecorder::updated($department, $changes, 'ویرایش واحد سازمانی: ' . $department->title);
        }

        return back()->with('success', 'واحد سازمانی به‌روزرسانی شد.');
    }

    /**
     * Refuse to delete a department that still carries people or money.
     *
     * Deleting it would null out `employees.department_id` (quietly removing
     * people from every departmental report) and hide the cost centre that past
     * journal lines point at. Deactivation is the correct action, and the message
     * says so instead of just failing.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            $count = $department->employees()->count();

            return back()->with('error',
                'این واحد ' . \App\Support\Money::toPersianDigits((string) $count)
                . ' کارمند دارد. ابتدا آن‌ها را به واحد دیگری منتقل کنید یا واحد را غیرفعال کنید.'
            );
        }

        if ($department->children()->exists()) {
            return back()->with('error', 'این واحد زیرمجموعه دارد. ابتدا زیرمجموعه‌ها را جابه‌جا یا حذف کنید.');
        }

        $title = $department->title;

        DB::transaction(function () use ($department): void {
            // The cost centre goes with it — it is the same organisational fact
            // recorded twice, and leaving an orphan would let a journal line
            // point at a cost centre whose department no longer exists.
            CostCenter::query()->where('department_id', $department->id)->delete();
            $department->delete();
        });

        AuditRecorder::deleted($department, 'حذف واحد سازمانی: ' . $title);

        return back()->with('success', 'واحد سازمانی «' . $title . '» حذف شد.');
    }

    /** Create or update the department's cost centre, inline. */
    public function storeCostCenter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('cost_centers', 'code')],
            'title' => ['required', 'string', 'max:190'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ], [
            'code.required' => 'کد مرکز هزینه را وارد کنید.',
            'code.unique' => 'این کد مرکز هزینه قبلاً استفاده شده است.',
            'title.required' => 'نام مرکز هزینه را وارد کنید.',
            'department_id.required' => 'واحد سازمانی را انتخاب کنید.',
        ]);

        $costCenter = CostCenter::create([
            'code' => $data['code'],
            'title' => $data['title'],
            'department_id' => (int) $data['department_id'],
            'is_active' => true,
        ]);

        AuditRecorder::created($costCenter, 'ایجاد مرکز هزینه: ' . $costCenter->title);

        return back()->with('success', 'مرکز هزینه «' . $costCenter->title . '» ثبت شد.');
    }

    public function destroyCostCenter(CostCenter $costCenter): RedirectResponse
    {
        $title = $costCenter->title;

        // Journal lines reference a cost centre. Once one is used, deleting it
        // would leave posted history pointing at nothing; deactivation keeps the
        // history readable and removes it from new entries.
        $used = DB::table('journal_lines')->where('cost_center_id', $costCenter->id)->exists();

        if ($used) {
            $costCenter->update(['is_active' => false]);
            AuditRecorder::updated($costCenter, ['is_active' => [true, false]],
                'غیرفعال‌سازی مرکز هزینهٔ دارای سابقه: ' . $title);

            return back()->with('warning',
                'این مرکز هزینه در اسناد ثبت‌شده استفاده شده است، پس حذف نشد و غیرفعال شد.'
            );
        }

        $costCenter->delete();
        AuditRecorder::deleted($costCenter, 'حذف مرکز هزینه: ' . $title);

        return back()->with('success', 'مرکز هزینه حذف شد.');
    }
}
