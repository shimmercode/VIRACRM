<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SeoPackage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * REQ-05.01–05.05 — the project module.
 *
 * FINANCIAL AMOUNTS are gated by `projects.view_financials`, separately from
 * `projects.view`, for the same reason contracts are (§10): a project manager
 * who should see schedules is not automatically entitled to see what the client
 * paid. The amount is never sent to the view when the user lacks the permission
 * — it is not hidden with CSS.
 *
 * TYPE SWITCHING. A project is either `seo` or `design`, and each type has its
 * own detail row. When the type changes on edit, the other type's row is
 * deleted in the same transaction. Leaving it behind would mean a design
 * project that still carries an SEO package and keyword count, and any later
 * reader of `seoDetails` would find data that contradicts `projects.type`.
 */
class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'customer_id' => (string) $request->query('customer_id', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => (string) $request->query('sort', 'created_at'),
            'dir' => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        // Whitelist. A sort column is interpolated into SQL, so an arbitrary
        // value from the query string would be an injection point.
        $sortable = ['created_at', 'title', 'start_date', 'end_date', 'amount', 'status'];
        if (! in_array($filters['sort'], $sortable, true)) {
            $filters['sort'] = 'created_at';
        }

        $projects = Project::query()
            ->with([
                'customer:id,type,company_name,first_name,last_name',
                'owner:id,name,username',
                // website_url is listed even though the list does not show it: a
                // column-limited eager load silently returns null for anything
                // omitted, which is how a field goes missing without an error.
                'seoDetails:id,project_id,seo_package_id,keyword_count,website_url',
                'designDetails:id,project_id,domain',
            ])
            ->filter($filters)
            ->orderBy($filters['sort'], $filters['dir'])
            ->paginate(20)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'statuses' => Project::STATUSES,
            'types' => Project::TYPES,
            'canSeeAmounts' => $request->user()->hasPermission('projects.view_financials'),
            'total' => Project::query()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('projects.create', [
            'statuses' => Project::STATUSES,
            'types' => Project::TYPES,
            'customers' => $this->customerOptions(),
            'users' => $this->userOptions(),
            'packages' => $this->packageOptions(),
            'preselectedCustomer' => $request->query('customer_id'),
            'preselectedContract' => $request->query('contract_id'),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validatedForModel();

        // REQ-05.01: project + optional type detail row must land together.
        $project = DB::transaction(function () use ($request, $data): Project {
            $project = new Project($data);
            $project->code = $this->nextNumber();
            $project->owner_user_id ??= $request->user()->id;
            $project->save();

            if ($seo = $request->seoDetailAttributes()) {
                $project->seoDetails()->create($seo);
            }

            if ($design = $request->designDetailAttributes()) {
                $project->designDetails()->create($design);
            }

            return $project;
        });

        AuditRecorder::created($project, 'ایجاد پروژه: ' . $project->title);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'پروژه با موفقیت ثبت شد.');
    }

    public function show(Request $request, Project $project): View
    {
        $project->load([
            'customer',
            'contract',
            'owner:id,name,username',
            'seoDetails.package',
            'designDetails',
            'keywords' => fn ($q) => $q->orderByRaw("case when status = 'working' then 0 when status = 'pending' then 1 when status = 'done' then 2 else 3 end")->orderBy('id'),
            'workItems' => fn ($q) => $q->orderBy('due_date'),
            // §5-3 / REQ-05.05 — who is on this project. Loaded here so the
            // section is one query per relation rather than one per row.
            'assignments' => fn ($q) => $q->orderBy('is_active', 'desc')->orderBy('id'),
        ]);

        return view('projects.show', [
            'project' => $project,
            'canSeeAmounts' => $request->user()->hasPermission('projects.view_financials'),
            'canAssign' => $request->user()->hasPermission('projects.update'),
            /*
             * Only employable staff are offered. A terminated or soft-deleted
             * expert would attach fine and then distort the workload report,
             * which counts assignments joined to employees.
             */
            'assignableEmployees' => \App\Models\Employee::query()
                ->whereNull('deleted_at')
                ->where('status', '!=', 'terminated')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'position', 'status']),
            'assignedEmployeeIds' => $project->assignments->pluck('employee_id')->all(),
            /*
             * §6 — which mandatory accesses are still missing.
             *
             * Shown on the page, not only as a validation error, because a gate
             * that refuses a save without saying what to do first just gets the
             * user to retry. Only for types that actually have requirements
             * configured, so a design project gets no banner about SEO accesses.
             */
            'missingAccesses' => \App\Services\Projects\FinalizationGate::missing(
                (int) $project->id,
                (string) $project->type,
            ),
            'auditTrail' => AuditLog::query()
                ->with('user:id,name,username')
                ->where('subject_type', Project::class)
                ->where('subject_id', $project->id)
                ->orderByDesc('created_at')
                ->limit(12)
                ->get(),
        ]);
    }

    public function edit(Project $project): View
    {
        $project->load(['seoDetails', 'designDetails']);

        return view('projects.edit', [
            'project' => $project,
            'statuses' => Project::STATUSES,
            'types' => Project::TYPES,
            'customers' => $this->customerOptions(),
            'users' => $this->userOptions(),
            'packages' => $this->packageOptions(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $before = $project->getAttributes();
        $typeBefore = $project->type;
        $data = $request->validatedForModel();
        $typeAfter = $data['type'];

        DB::transaction(function () use ($request, $project, $data, $typeBefore, $typeAfter): void {
            $project->update($data);

            // The type changed: the old type's detail row no longer describes
            // this project and must go, not linger.
            if ($typeBefore !== $typeAfter) {
                if ($typeBefore === 'seo') {
                    $project->seoDetails()->delete();
                } else {
                    $project->designDetails()->delete();
                }
            }

            if ($seo = $request->seoDetailAttributes()) {
                $project->seoDetails()->updateOrCreate(['project_id' => $project->id], $seo);
            }

            if ($design = $request->designDetailAttributes()) {
                $project->designDetails()->updateOrCreate(['project_id' => $project->id], $design);
            }
        });

        $changes = AuditLog::diff($before, $project->fresh()->getAttributes());
        if ($typeBefore !== $typeAfter) {
            $changes['type'] = [$typeBefore, $typeAfter];
        }

        if ($changes !== []) {
            AuditRecorder::updated($project, $changes, 'ویرایش پروژه: ' . $project->title);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'تغییرات پروژه ذخیره شد.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $title = $project->title;
        $project->delete();

        AuditRecorder::deleted($project, 'حذف پروژه: ' . $title);

        return redirect()
            ->route('projects.index')
            ->with('success', 'پروژه حذف شد.');
    }

    // ── Option lists ─────────────────────────────────────────────────────

    private function customerOptions()
    {
        return Customer::query()
            ->select('id', 'type', 'company_name', 'first_name', 'last_name')
            ->orderBy('company_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get();
    }

    private function userOptions()
    {
        return User::query()
            ->select('id', 'name', 'username')
            ->orderBy('name')
            ->get();
    }

    private function packageOptions()
    {
        return SeoPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    /**
     * Human-readable project number: VIRA-P-0001.
     * Derived from the max id so a soft delete cannot make two projects share
     * a code.
     */
    private function nextNumber(): string
    {
        $max = (int) Project::withTrashed()->max('id');

        return 'VIRA-P-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
