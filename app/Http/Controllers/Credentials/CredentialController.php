<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CredentialType;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\Security\AuditRecorder;
use App\Services\Security\CredentialVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * REQ-06.01, REQ-06.03, REQ-06.04 — project access credentials.
 *
 * THREE SEPARATE PERMISSIONS, and the split is the security design:
 *
 *   credentials.view    see that a credential exists, its label, URL, username
 *   credentials.manage  create, edit, delete, and *write* a new secret
 *   credentials.reveal  read a stored secret back in plaintext
 *
 * An account manager needs `view` and `manage` to onboard a client, and should
 * still not be able to read the client's WordPress password six months later.
 * Conflating these is the most common way these systems leak.
 *
 * REVEAL IS A POST, NOT A GET.
 * -----------------------------------------------------------------------
 * REQ-06.04 says a secret must never travel in a URL. A `GET /credentials/{id}/
 * password` would put the plaintext in browser history, in the `Referer` header
 * of the next request, and in any proxy access log. Here the browser POSTs to a
 * route, the response carries the value in a JSON body with `no-store`, and the
 * default is that the value is not even in the HTML — the page renders bullets
 * and asks for a deliberate click.
 *
 * THE COMPLETENESS RULE (REQ-06.02) is deliberately NOT enforced here.
 * Blocking a project from activation until every mandatory SEO credential
 * exists is listed as an open decision needing the client's own mandatory
 * list, which does not exist yet. The controller reports completeness; it does
 * not gate on it.
 */
class CredentialController extends Controller
{
    public function __construct(private readonly CredentialVault $vault)
    {
    }

    /** Every credential on one project. */
    public function index(Request $request, Project $project): View
    {
        $project->load('customer');

        $credentials = ProjectCredential::query()
            ->with(['type', 'values'])
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        return view('credentials.index', [
            'project' => $project,
            'credentials' => $credentials,
            'canManage' => $request->user()->hasPermission('credentials.manage'),
            'canReveal' => $request->user()->hasPermission('credentials.reveal'),
            'vaultReady' => $this->vault->isReady(),
            'auditTrail' => AuditLog::query()
                ->with('user:id,name,username')
                ->where('subject_type', ProjectCredential::class)
                ->whereIn('subject_id', $credentials->pluck('id'))
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        return view('credentials.create', [
            'project' => $project,
            'vaultReady' => $this->vault->isReady(),
            'types' => CredentialType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'selectedType' => $request->query('type'),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'credential_type_id' => ['required', 'exists:credential_types,id'],
            'label' => ['required', 'string', 'max:120'],
            'public_url' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = CredentialType::findOrFail($data['credential_type_id']);
        $secretField = $type->secretFields()[0] ?? 'password';

        // Refuse before creating a half-record: a credential row whose secret
        // was silently dropped looks complete in the list and is not.
        if (($data['secret'] ?? '') !== '' && ! $this->vault->isReady()) {
            return back()
                ->withInput()
                ->with('error', 'کلید رمزنگاری روی این سرور تنظیم نشده است؛ ذخیرهٔ رمز غیرفعال است.');
        }

        $credential = ProjectCredential::create([
            'project_id' => $project->id,
            'credential_type_id' => $type->id,
            'label' => $data['label'],
            'public_url' => $data['public_url'] ?? null,
            'username' => $data['username'] ?? null,
            'is_complete' => false,
            'updated_by' => $request->user()->id,
        ]);

        if (($data['secret'] ?? '') !== '') {
            $this->vault->store($credential, $secretField, $data['secret']);
        }

        // The secret is never part of the audit payload; AuditLog::redact()
        // is the second line of defence, this is the first.
        AuditRecorder::created($credential, 'ایجاد دسترسی: ' . $credential->label . ' — ' . $project->title);

        return redirect()
            ->route('credentials.index', $project)
            ->with('success', 'دسترسی ثبت شد.');
    }

    public function edit(Project $project, ProjectCredential $credential): View
    {
        $this->assertBelongs($project, $credential);

        $credential->load('type');

        return view('credentials.edit', [
            'project' => $project,
            'credential' => $credential,
            'secretField' => $credential->type?->secretFields()[0] ?? 'password',
            'hasSecret' => $credential->values()->exists(),
            'vaultReady' => $this->vault->isReady(),
        ]);
    }

    public function update(Request $request, Project $project, ProjectCredential $credential): RedirectResponse
    {
        $this->assertBelongs($project, $credential);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'public_url' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:1000'],
        ]);

        $credential->update([
            'label' => $data['label'],
            'public_url' => $data['public_url'] ?? null,
            'username' => $data['username'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $rotated = false;

        // A blank secret field means "leave the stored secret alone". Writing
        // an empty string in its place would destroy a working password
        // because the user edited the label.
        if (($data['secret'] ?? '') !== '') {
            $field = $credential->type?->secretFields()[0] ?? 'password';
            $rotated = ! $this->vault->sameAsPrevious($credential, $field, $data['secret']);
            $this->vault->store($credential, $field, $data['secret']);

            if ($rotated) {
                $credential->forceFill(['rotated_at' => now()])->save();
            }
        }

        AuditRecorder::updated($credential, $rotated ? ['secret' => 'rotated'] : [], 'ویرایش دسترسی: ' . $credential->label);

        return redirect()
            ->route('credentials.index', $project)
            ->with('success', $rotated ? 'دسترسی و رمز به‌روزرسانی شد.' : 'دسترسی به‌روزرسانی شد.');
    }

    /**
     * REQ-06.04 — return one secret to the browser, and record that it happened.
     *
     * POST-only, JSON, `no-store`. The audit row is written *before* the value
     * leaves the server: if the write fails, the reveal does not happen. A
     * reveal that cannot be attributed is worse than no reveal.
     */
    public function reveal(Request $request, Project $project, ProjectCredential $credential): JsonResponse
    {
        $this->assertBelongs($project, $credential);

        $field = $credential->type?->secretFields()[0] ?? 'password';

        if (! $this->vault->isReady()) {
            return response()->json([
                'ok' => false,
                'message' => 'کلید رمزنگاری تنظیم نشده است؛ رمزها قابل بازیابی نیستند.',
            ], 503, ['Cache-Control' => 'no-store, private']);
        }

        try {
            $plaintext = $this->vault->reveal($credential, $field);
        } catch (RuntimeException $e) {
            // Wrong key or tampered row. Report it, do not pretend.
            return response()->json([
                'ok' => false,
                'message' => 'رمز قابل بازیابی نیست. کلید رمزنگاری تغییر کرده یا رکورد آسیب دیده است.',
            ], 409, ['Cache-Control' => 'no-store, private']);
        }

        if ($plaintext === null) {
            return response()->json([
                'ok' => false,
                'message' => 'رمزی برای این دسترسی ذخیره نشده است.',
            ], 404, ['Cache-Control' => 'no-store, private']);
        }

        AuditRecorder::revealed($credential, $field, 'مشاهده رمز: ' . $credential->label . ' — ' . $project->title);

        return response()->json([
            'ok' => true,
            'value' => $plaintext,
        ], 200, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function destroy(Project $project, ProjectCredential $credential): RedirectResponse
    {
        $this->assertBelongs($project, $credential);

        // Values cascade on the foreign key, so the ciphertext goes with it.
        $label = $credential->label;
        $credential->delete();

        AuditRecorder::deleted($credential, 'حذف دسترسی: ' . $label . ' — ' . $project->title);

        return redirect()
            ->route('credentials.index', $project)
            ->with('success', 'دسترسی حذف شد.');
    }

    /**
     * A credential id from another project must not be reachable through this
     * project's URL — that is an IDOR, and route-model binding alone does not
     * prevent it because both ids are valid.
     */
    private function assertBelongs(Project $project, ProjectCredential $credential): void
    {
        abort_unless($credential->project_id === $project->id, 404);
    }
}
