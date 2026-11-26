<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AssignmentRequest;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;

/**
 * §5-3 / REQ-05.05 — who is on a project.
 *
 * Before this controller existed, `project_assignments` could only be filled by
 * a seeder. The workload report at GET /reports/expert-workload was therefore
 * reading a table the product itself could never write to — a report with no
 * way to become true. This is the missing half of REQ-05.05.
 *
 * Every mutation is audited, because attaching a person to a project is how
 * their fee gets into the payroll input, and a change to it must be answerable.
 */
final class AssignmentController extends Controller
{
    /**
     * Attach an expert. A duplicate is refused by the form request, not by the
     * UNIQUE(project_id, employee_id) index — see AssignmentRequest.
     */
    public function store(AssignmentRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validatedForModel();
        $data['project_id'] = (int) $project->id;

        $assignment = ProjectAssignment::query()->create($data);
        $assignment->load('employee:id,first_name,last_name');

        AuditRecorder::created(
            $assignment,
            sprintf(
                'تخصیص کارشناس %s به پروژه «%s» با ضریب %d%%',
                trim($assignment->employee?->first_name . ' ' . $assignment->employee?->last_name) ?: $assignment->employee_id,
                $project->title,
                (int) $assignment->coefficient,
            ),
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'کارشناس به پروژه تخصیص یافت.');
    }

    /**
     * Change the role, fee, coefficient, dates or active flag.
     *
     * Editing here does not move a project between experts: `employee_id` is
     * validated for uniqueness against the same project and the same row, so a
     * genuine reassignment goes through detach-then-attach and leaves two audit
     * entries rather than silently rewriting who was on the work.
     */
    public function update(
        AssignmentRequest $request,
        Project $project,
        ProjectAssignment $assignment,
    ): RedirectResponse {
        $this->assertBelongs($project, $assignment);

        $before = $assignment->only(['role', 'amount', 'coefficient', 'is_active']);
        $assignment->fill($request->validatedForModel())->save();

        // updated(Model, changes, description) — the changes array comes first.
        AuditRecorder::updated(
            $assignment,
            $assignment->getChanges(),
            'ویرایش تخصیص کارشناس پروژه «' . $project->title . '» از ' . json_encode($before, JSON_UNESCAPED_UNICODE),
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'تخصیص به‌روزرسانی شد.');
    }

    /**
     * Detach.
     *
     * A hard delete: the table has no `deleted_at`. That is deliberate — an
     * assignment is a statement about the present, and the audit log is what
     * preserves the history. Leaving inactive rows behind would let a project
     * keep counting against someone who was taken off it.
     */
    public function destroy(Project $project, ProjectAssignment $assignment): RedirectResponse
    {
        $this->assertBelongs($project, $assignment);

        $assignment->load('employee:id,first_name,last_name');
        $who = trim($assignment->employee?->first_name . ' ' . $assignment->employee?->last_name);

        $assignment->delete();

        AuditRecorder::deleted(
            $assignment,
            sprintf(
                'حذف تخصیص کارشناس %s از پروژه «%s»',
                $who !== '' ? $who : (string) $assignment->employee_id,
                $project->title,
            ),
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'تخصیص کارشناس حذف شد.');
    }

    /**
     * An assignment id from another project must not be reachable through this
     * project's URL. Both ids are valid on their own, so route-model binding
     * alone does not catch it — this is an IDOR check.
     */
    private function assertBelongs(Project $project, ProjectAssignment $assignment): void
    {
        abort_unless(
            $assignment->project_id === $project->id,
            404,
            'This assignment does not belong to this project.',
        );
    }
}
