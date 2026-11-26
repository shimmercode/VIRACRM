<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * REQ-05.05 / REQ-09.07 — how many projects each expert is carrying.
 *
 * THE COUNTING BASIS, STATED BECAUSE IT IS A DECISION AND NOT A FACT
 * ------------------------------------------------------------------
 * «تعداد پروژه‌های فعال هر کارشناس» does not say what "active" counts. This
 * report counts a project against an expert when all of the following hold:
 *
 *   1. the assignment row is current (`project_assignments.is_active = 1`), so
 *      an expert who was taken off a project in Mehr is not still carrying it
 *      in Aban — but the row survives, because a published payroll period that
 *      used it must stay reconstructible;
 *   2. the project is in one of the selected statuses, defaulting to `active`
 *      only. `draft`, `paused`, `completed` and `cancelled` are excluded, which
 *      is what «فعال» means in §5-5;
 *   3. neither side is soft-deleted.
 *
 * A project with three experts counts once for each of them. That is not double
 * counting: the figure answers "how many projects is this person on", not "how
 * many projects exist". The project total is reported separately so the
 * difference is visible rather than mysterious.
 *
 * WHY THE AMOUNTS ARE GATED SEPARATELY
 * ------------------------------------
 * `amount` and `coefficient` are payroll inputs. A project manager has a
 * legitimate need to see who is on what, and none to see what they are paid, so
 * the counts sit behind `reports.view` and the money behind
 * `projects.view_financials`.
 */
class ExpertWorkloadController extends Controller
{
    /** Project statuses the filter offers, in the order the document lists them. */
    private const STATUS_OPTIONS = ['active', 'paused', 'draft', 'completed', 'cancelled'];

    public function __invoke(Request $request): View
    {
        $filters = [
            'status' => (string) $request->query('status', 'active'),
            'employee_status' => (string) $request->query('employee_status', 'active'),
            'q' => trim((string) $request->query('q', '')),
            'sort' => (string) $request->query('sort', 'projects'),
            'dir' => $request->query('dir') === 'asc' ? 'asc' : 'desc',
        ];

        // An unknown status must not silently become "everything", which would
        // report a larger workload than the user asked for.
        if (! in_array($filters['status'], self::STATUS_OPTIONS, true)) {
            $filters['status'] = 'active';
        }

        if (! in_array($filters['employee_status'], Employee::STATUSES, true)) {
            $filters['employee_status'] = 'active';
        }

        $sortable = ['projects' => 'active_projects', 'name' => 'employee_name', 'all' => 'total_assignments'];
        $sortColumn = $sortable[$filters['sort']] ?? 'active_projects';

        /*
         * One grouped query rather than a loop over employees. Counting per
         * employee in PHP would be an N+1 across the whole payroll roster, and
         * the query-count suite would catch it.
         *
         * "Active projects" and "all assignments" are conditional aggregates
         * inside the same pass, so the two figures cannot disagree about which
         * rows they looked at. The status is bound through the connection's
         * quoter rather than interpolated, because it arrives from the query
         * string even though it is whitelisted above.
         */
        $rows = ProjectAssignment::query()
            ->select(
                'employees.id as employee_id',
                'employees.code as employee_code',
                DB::raw("employees.first_name || ' ' || employees.last_name as employee_name"),
                'employees.position as employee_position',
                DB::raw('count(distinct case when project_assignments.is_active = 1'
                    . " and projects.status = " . DB::getPdo()->quote($filters['status'])
                    . ' then project_assignments.project_id end) as active_projects'),
                DB::raw('count(distinct project_assignments.project_id) as total_assignments'),
                DB::raw('sum(case when project_assignments.is_active = 1'
                    . ' then project_assignments.amount * project_assignments.coefficient / 100'
                    . ' else 0 end) as earned_amount'),
            )
            ->join('employees', 'employees.id', '=', 'project_assignments.employee_id')
            ->join('projects', 'projects.id', '=', 'project_assignments.project_id')
            ->whereNull('employees.deleted_at')
            ->whereNull('projects.deleted_at')
            ->where('employees.status', $filters['employee_status'])
            ->when($filters['q'] !== '', function ($q) use ($filters): void {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%';
                $q->where(function ($w) use ($term): void {
                    $w->where('employees.first_name', 'like', $term)
                        ->orWhere('employees.last_name', 'like', $term)
                        ->orWhere('employees.code', 'like', $term);
                });
            })
            ->groupBy('employees.id', 'employees.code', 'employees.first_name',
                'employees.last_name', 'employees.position')
            ->orderByDesc($sortColumn === 'employee_name' ? 'employee_name' : $sortColumn)
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'experts' => $rows->total(),
            // Distinct projects, so a project with three experts is counted once
            // here and three times across the rows above — deliberately, and the
            // view says so.
            'projects' => ProjectAssignment::query()
                ->current()
                ->join('projects', 'projects.id', '=', 'project_assignments.project_id')
                ->whereNull('projects.deleted_at')
                ->where('projects.status', $filters['status'])
                ->distinct()
                ->count('project_assignments.project_id'),
        ];

        return view('reports.expert-workload', [
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
            'statusOptions' => self::STATUS_OPTIONS,
            'employeeStatuses' => Employee::STATUSES,
            'canSeeAmounts' => $request->user()->hasPermission('projects.view_financials'),
            'pd' => static fn ($v): string => Money::toPersianDigits((string) $v),
        ]);
    }
}
