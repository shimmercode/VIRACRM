<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Security\AuditRecorder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §15 / REQ-07 — the personnel file.
 *
 * PERSONAL DATA IS A PERMISSION, NOT A CSS CLASS
 * ----------------------------------------------
 * An employee record contains a national id, a home address and a mobile number.
 * `personnel.view` gates the module and every route re-checks it in middleware.
 * A user without `payroll.view` who reaches an employee page sees the personnel
 * file and the employment history — never the salary, because the payslip query
 * is not even executed for them (see show()).
 *
 * NO SALARY COLUMN — OD-16
 * ------------------------
 * There is deliberately no "salary" field on this form. Pay comes from the
 * payslip of a given month (base + manual lines), and an employee-level salary
 * would be a second competing truth that goes stale the first time a raise is
 * applied to only one of them.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'position' => trim((string) $request->query('position', '')),
        ];

        $query = Employee::query()->withCount('payslips');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['position'] !== '') {
            $query->where('position', 'like', '%' . $filters['position'] . '%');
        }

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($term): void {
                $w->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('national_id', 'like', $term)
                    ->orWhere('mobile', 'like', $term);
            });
        }

        $counts = [
            'total' => Employee::query()->count(),
            'active' => Employee::query()->where('status', 'active')->count(),
        ];

        return view('employees.index', [
            'employees' => $query->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => Employee::STATUSES,
            'counts' => $counts,
            'canSeePayroll' => $request->user()->hasPermission('payroll.view'),
        ]);
    }

    public function create(): View
    {
        return view('employees.create', [
            'statuses' => Employee::STATUSES,
            'users' => $this->userOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validatedForModel();

        $employee = new Employee($data);
        $employee->code = $data['code'] ?? $this->nextCode();
        $employee->save();

        AuditRecorder::created($employee, 'ثبت پرسنل: ' . $employee->displayName());

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'پروندهٔ پرسنلی ثبت شد.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $canSeePayroll = $request->user()->hasPermission('payroll.view');

        $employee->load([
            'department:id,title',
            'manager:id,first_name,last_name',
            'user:id,name,username',
            'contracts' => fn ($q) => $q->orderByDesc('start_date'),
        ]);

        // The salary history is not merely hidden when the user lacks the
        // permission — it is never read. Hiding it in Blade would still ship the
        // numbers to the browser in the HTML source.
        $payslips = $canSeePayroll
            ? Payslip::query()
                ->with('period:id,jalali_year,jalali_month,state')
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->limit(24)
                ->get()
            : collect();

        return view('employees.show', [
            'employee' => $employee,
            'payslips' => $payslips,
            'canSeePayroll' => $canSeePayroll,
            'auditTrail' => AuditLog::query()
                ->with('user:id,name,username')
                ->where('subject_type', Employee::class)
                ->where('subject_id', $employee->id)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.edit', [
            'employee' => $employee,
            'statuses' => Employee::STATUSES,
            'users' => $this->userOptions(),
        ]);
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $before = $employee->getAttributes();
        $employee->update($request->validatedForModel());

        $changes = AuditLog::diff($before, $employee->fresh()->getAttributes());

        if ($changes !== []) {
            AuditRecorder::updated($employee, $changes, 'ویرایش پرسنل: ' . $employee->displayName());
        }

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'تغییرات پروندهٔ پرسنلی ذخیره شد.');
    }

    /**
     * Terminate rather than delete.
     *
     * A hard delete would orphan payslips and, worse, remove the record a wage
     * claim or an audit needs years later. Soft-delete plus an explicit
     * `terminated` status keeps history readable while removing the person from
     * the active payroll run.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $name = $employee->displayName();

        $employee->forceFill([
            'status' => 'terminated',
            'end_date' => $employee->end_date ?? now()->toDateString(),
        ])->save();

        $employee->delete();

        AuditRecorder::deleted($employee, 'پایان همکاری و حذف پروندهٔ پرسنلی: ' . $name);

        return redirect()
            ->route('employees.index')
            ->with('success', 'پروندهٔ پرسنلی «' . $name . '» بایگانی شد.');
    }

    /** Next personnel code: VIRA-E-0001, from the max id so soft deletes cannot collide. */
    private function nextCode(): string
    {
        $max = (int) Employee::withTrashed()->max('id');

        return 'VIRA-E-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function userOptions()
    {
        return User::query()
            ->select('id', 'name', 'username')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
