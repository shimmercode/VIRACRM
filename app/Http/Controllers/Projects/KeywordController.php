<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\KeywordRequest;
use App\Models\Project;
use App\Models\ProjectKeyword;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;

/**
 * §5-1 / REQ-05.03 — «فهرست کلمات کلیدی هدف و تعداد کلمات».
 *
 * The project page already displayed the count with `$project->keywords->count()`
 * — computed from real rows, as the requirement insists — but nothing could
 * create a row, so the count was permanently zero and the requirement was
 * satisfied by an empty set. This is the CRUD that makes it real.
 *
 * Gated by `projects.update`: adding a target keyword is editing the project.
 */
final class KeywordController extends Controller
{
    public function store(KeywordRequest $request, Project $project): RedirectResponse
    {
        $keyword = $project->keywords()->create([
            'keyword' => (string) $request->input('keyword'),
            'normalized' => ProjectKeyword::normalize((string) $request->input('keyword')),
            'status' => (string) $request->input('status', 'pending'),
            'notes' => ($notes = $request->input('notes')) === '' || $notes === null ? null : (string) $notes,
        ]);

        AuditRecorder::created(
            $keyword,
            'ثبت کلمهٔ کلیدی «' . $keyword->keyword . '» برای پروژه «' . $project->title . '»',
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'کلمهٔ کلیدی ثبت شد.');
    }

    /**
     * Correct a keyword or move it through pending -> working -> done.
     *
     * Status is a full field here rather than a dedicated transition endpoint:
     * §5-1 tracks per-keyword state and there are only four values, so a
     * select on the row is both sufficient and honest about what it changes.
     */
    public function update(KeywordRequest $request, Project $project, ProjectKeyword $keyword): RedirectResponse
    {
        $this->assertBelongs($project, $keyword);

        $before = $keyword->only(['keyword', 'status']);

        $keyword->fill([
            'keyword' => (string) $request->input('keyword'),
            'normalized' => ProjectKeyword::normalize((string) $request->input('keyword')),
            'status' => (string) $request->input('status'),
            'notes' => ($notes = $request->input('notes')) === '' || $notes === null ? null : (string) $notes,
        ])->save();

        AuditRecorder::updated(
            $keyword,
            $keyword->getChanges(),
            'ویرایش کلمهٔ کلیدی «' . ($before['keyword'] ?? '') . '» در پروژه «' . $project->title . '»',
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'کلمهٔ کلیدی به‌روزرسانی شد.');
    }

    /**
     * Remove a keyword.
     *
     * A hard delete, and the count on the page falls with it — which is the
     * whole reason the count is computed from rows. A soft "removed" status
     * also exists and is the right choice when the keyword was worked on and
     * the history matters; deleting is for a keyword that should never have
     * been there. Both are available, and only the delete frees a package slot
     * immediately, because the limit ignores `removed` rows.
     */
    public function destroy(Project $project, ProjectKeyword $keyword): RedirectResponse
    {
        $this->assertBelongs($project, $keyword);

        $text = $keyword->keyword;
        $keyword->delete();

        AuditRecorder::deleted(
            $keyword,
            'حذف کلمهٔ کلیدی «' . $text . '» از پروژه «' . $project->title . '»',
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'کلمهٔ کلیدی حذف شد.');
    }

    /**
     * A keyword id from another project must not be reachable through this
     * project's URL. Both ids are valid, so route-model binding will not catch it.
     */
    private function assertBelongs(Project $project, ProjectKeyword $keyword): void
    {
        abort_unless(
            $keyword->project_id === $project->id,
            404,
            'This keyword does not belong to this project.',
        );
    }
}
