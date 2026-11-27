<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccessRequirementRequest;
use App\Models\CredentialRequirement;
use App\Models\CredentialType;
use App\Models\ProjectType;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * §15 / REQ-15.01 — which accesses a project type must have before it can be
 * finalised, and §6's gate in editable form.
 *
 * Effect is immediate and real: FinalizationGate::blockingFor() reads this table
 * on every save of a gated status, so a requirement added here starts refusing
 * activation the moment it is stored, and removing one lifts the block.
 */
final class AccessRequirementController extends Controller
{
    public function index(): View
    {
        return view('settings.access-requirements.index', [
            'requirements' => CredentialRequirement::query()
                ->with('credentialType')
                ->orderBy('project_type')
                ->orderBy('credential_type_id')
                ->get(),
            'projectTypes' => ProjectType::options(),
        ]);
    }

    public function create(): View
    {
        return view('settings.access-requirements.create', [
            'requirement' => new CredentialRequirement(),
            'projectTypes' => ProjectType::options(),
            'credentialTypes' => CredentialType::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(AccessRequirementRequest $request): RedirectResponse
    {
        $requirement = CredentialRequirement::query()->create($request->validatedForModel());
        $requirement->load('credentialType');

        AuditRecorder::created(
            $requirement,
            sprintf(
                'ثبت دسترسی الزامی «%s» برای پروژهٔ %s%s',
                $requirement->credentialType?->title ?? $requirement->credential_type_id,
                $requirement->project_type,
                $requirement->blocks_finalize ? ' (مانع نهایی‌سازی)' : '',
            ),
        );

        return redirect()
            ->route('settings.access-requirements.index')
            ->with('success', 'دسترسی الزامی ثبت شد.');
    }

    public function edit(CredentialRequirement $accessRequirement): View
    {
        return view('settings.access-requirements.edit', [
            'requirement' => $accessRequirement,
            'projectTypes' => ProjectType::options(),
            'credentialTypes' => CredentialType::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(AccessRequirementRequest $request, CredentialRequirement $accessRequirement): RedirectResponse
    {
        $accessRequirement->fill($request->validatedForModel())->save();
        $accessRequirement->load('credentialType');

        AuditRecorder::updated(
            $accessRequirement,
            $accessRequirement->getChanges(),
            'ویرایش دسترسی الزامی: ' . ($accessRequirement->credentialType?->title ?? ''),
        );

        return redirect()
            ->route('settings.access-requirements.index')
            ->with('success', 'دسترسی الزامی به‌روزرسانی شد.');
    }

    /**
     * Drop a requirement.
     *
     * Deleting is right here, unlike the type dictionaries: a requirement row
     * holds no history, and the accesses themselves live on the project. What
     * has to be visible is that deleting one lifts the gate — so the audit row
     * says so in those words.
     */
    public function destroy(CredentialRequirement $accessRequirement): RedirectResponse
    {
        $accessRequirement->load('credentialType');
        $wasBlocking = $accessRequirement->is_required && $accessRequirement->blocks_finalize;

        $description = sprintf(
            'حذف دسترسی الزامی «%s» از پروژهٔ %s%s',
            $accessRequirement->credentialType?->title ?? $accessRequirement->credential_type_id,
            $accessRequirement->project_type,
            $wasBlocking ? ' — این دسترسی دیگر مانع نهایی‌سازی نیست' : '',
        );

        $accessRequirement->delete();

        AuditRecorder::deleted($accessRequirement, $description);

        return redirect()
            ->route('settings.access-requirements.index')
            ->with('success', 'دسترسی الزامی حذف شد.');
    }
}
