<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProjectTypeRequest;
use App\Models\Project;
use App\Models\ProjectType;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * §15 / REQ-15.01 — the project type list.
 *
 * Effect: `ProjectType::options()` is what the project form offers and what
 * StoreProjectRequest/UpdateProjectRequest validate against, so a type added
 * here is immediately selectable and a type retired here cannot be chosen for
 * a new project.
 *
 * `Project::TYPES` is deliberately left alone. It is the behavioural map — it
 * decides which detail fieldset a type gets and which accesses block
 * finalisation — and a row in this table cannot supply that. A newly added
 * type therefore gets a project with no SEO or design detail row, which is the
 * correct meaning of "a kind of project this product has no special workflow
 * for" rather than a half-built SEO project.
 */
final class ProjectTypeController extends Controller
{
    public function index(): View
    {
        return view('settings.project-types.index', [
            'types' => ProjectType::query()->orderBy('id')->get()
                ->map(function (ProjectType $type) {
                    // projects.type stores the code, not an id, and Project
                    // soft-deletes — count() through the model honours both.
                    $type->projects_count = Project::query()->where('type', $type->code)->count();

                    return $type;
                }),
        ]);
    }

    public function create(): View
    {
        return view('settings.project-types.create', ['type' => new ProjectType()]);
    }

    public function store(ProjectTypeRequest $request): RedirectResponse
    {
        $type = ProjectType::query()->create($request->validatedForModel());

        AuditRecorder::created($type, 'ثبت نوع پروژه: ' . $type->title);

        return redirect()
            ->route('settings.project-types.index')
            ->with('success', 'نوع پروژه ثبت شد.');
    }

    public function edit(ProjectType $projectType): View
    {
        // The form reads this to decide whether the code is locked, so it has
        // to be set here too and not only on the index.
        $projectType->projects_count = Project::query()->where('type', $projectType->code)->count();

        return view('settings.project-types.edit', ['type' => $projectType]);
    }

    public function update(ProjectTypeRequest $request, ProjectType $projectType): RedirectResponse
    {
        /*
         * The code is what projects store. Renaming it would orphan every
         * project written under the old code — they would keep a type string
         * no row describes, and the project page would show a type that is not
         * in the list. The code is therefore immutable once projects use it.
         */
        $data = $request->validatedForModel();
        $inUse = Project::query()->where('type', $projectType->code)->count();

        if ($inUse > 0 && $data['code'] !== $projectType->code) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $projectType,
                sprintf('تغییر کد نوع پروژه «%s» رد شد: %d پروژه روی این کد ثبت شده است', $projectType->title, $inUse),
                ['refused' => 'code_in_use', 'projects' => $inUse],
            );

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['code' => sprintf(
                    '%d پروژه با این کد ثبت شده است؛ کد قابل تغییر نیست.',
                    $inUse,
                )]);
        }

        $projectType->fill($data)->save();

        AuditRecorder::updated($projectType, $projectType->getChanges(), 'ویرایش نوع پروژه');

        return redirect()
            ->route('settings.project-types.index')
            ->with('success', 'نوع پروژه به‌روزرسانی شد.');
    }

    public function destroy(ProjectType $projectType): RedirectResponse
    {
        $inUse = Project::query()->where('type', $projectType->code)->count();

        if ($inUse > 0) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $projectType,
                sprintf('حذف نوع پروژه «%s» رد شد: %d پروژه روی آن ثبت شده است', $projectType->title, $inUse),
                ['refused' => 'in_use', 'projects' => $inUse],
            );

            return redirect()
                ->route('settings.project-types.index')
                ->with('error', sprintf(
                    '%d پروژه با این نوع ثبت شده است؛ به‌جای حذف، آن را غیرفعال کنید.',
                    $inUse,
                ));
        }

        $title = $projectType->title;
        $projectType->delete();

        AuditRecorder::deleted($projectType, 'حذف نوع پروژه: ' . $title);

        return redirect()
            ->route('settings.project-types.index')
            ->with('success', 'نوع پروژه حذف شد.');
    }
}
