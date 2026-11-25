<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\AuditRecorder;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * EXP-NOTE-001 — notes and tasks.
 *
 * `notes` is a single table serving two jobs, and the difference matters:
 *
 *   A NOTE has a title and a body. It is never "done"; archiving it is enough.
 *   A TASK has a due date and an assignee. It is either open or finished.
 *
 * They share a table because both attach to exactly one of customer/contract/
 * project, and both need the same permission model. What they do NOT share is
 * the completion rule: a task with no due date is a note that forgot its
 * status, so the form makes the due date required whenever a task is created
 * as a task. Validating that at the edge is cheaper than a nullable column
 * that every future report has to defend against.
 */
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'open'),
            'priority' => (string) $request->query('priority', ''),
            'assigned_to' => (string) $request->query('assigned_to', ''),
            'mine' => (string) $request->query('mine', ''),
        ];

        $query = Note::query()
            ->with([
                'customer:id,company_name,first_name,last_name',
                'project:id,code,title',
                'assignee:id,name',
            ]);

        if ($filters['status'] === 'open') {
            $query->where('status', 'open');
        } elseif ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['priority'] !== '') {
            $query->where('priority', $filters['priority']);
        }

        if ($filters['assigned_to'] !== '') {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if ($filters['mine'] === '1') {
            $query->where('assigned_to', $request->user()->id);
        }

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        return view('notes.index', [
            'notes' => $query->orderByRaw("case when priority = 'urgent' then 0 when priority = 'high' then 1 when priority = 'normal' then 2 else 3 end")
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => Note::STATUSES,
            'priorities' => Note::PRIORITIES,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'overdueCount' => Note::query()
                ->where('status', 'open')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->startOfDay())
                ->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('notes.create', [
            'priorities' => Note::PRIORITIES,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('company_name')->get(['id', 'company_name', 'first_name', 'last_name']),
            'contracts' => Contract::query()->orderBy('title')->get(['id', 'title']),
            'projects' => Project::query()->orderBy('title')->get(['id', 'code', 'title']),
            // Prefilling from a record page is the common case: a note written
            // while looking at a project belongs to that project.
            'preset' => [
                'customer_id' => $request->query('customer_id'),
                'contract_id' => $request->query('contract_id'),
                'project_id' => $request->query('project_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // A date the user typed but which does not exist (1404/12/30 — 1404 is
        // not a leap year) must be an error, never a silent NULL. the first
        // version of this controller mapped the failed parse to null, which
        // saved the note with no due date and no warning: the user believes the
        // deadline is set, and the task quietly stops being overdue. Caught by
        // T-WI-12 in tools/checks/work-item-flow.mjs.
        $dueDate = $this->dueDate($data['due_date'] ?? null);
        if ($dueDate === false) {
            return back()
                ->withInput()
                ->withErrors(['due_date' => 'تاریخ وارد‌شده معتبر نیست (روز یا ماه وجود ندارد).']);
        }

        $note = Note::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $dueDate,
            'priority' => $data['priority'],
            'status' => 'open',
            'customer_id' => $data['customer_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        AuditRecorder::created($note, 'ایجاد یادداشت: ' . $note->title);

        return redirect()->route('notes.index')->with('success', 'یادداشت ثبت شد.');
    }

    public function edit(Note $note): View
    {
        return view('notes.edit', [
            'note' => $note,
            'priorities' => Note::PRIORITIES,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('company_name')->get(['id', 'company_name', 'first_name', 'last_name']),
            'contracts' => Contract::query()->orderBy('title')->get(['id', 'title']),
            'projects' => Project::query()->orderBy('title')->get(['id', 'code', 'title']),
        ]);
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $data = $this->validated($request);

        $dueDate = $this->dueDate($data['due_date'] ?? null);
        if ($dueDate === false) {
            return back()
                ->withInput()
                ->withErrors(['due_date' => 'تاریخ وارد‌شده معتبر نیست (روز یا ماه وجود ندارد).']);
        }

        $note->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $dueDate,
            'priority' => $data['priority'],
            'customer_id' => $data['customer_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);

        AuditRecorder::updated($note, [], 'ویرایش یادداشت: ' . $note->title);

        return redirect()->route('notes.index')->with('success', 'یادداشت به‌روزرسانی شد.');
    }

    /**
     * Mark a task finished.
     *
     * `done_by` records who actually did it, which is not always the assignee
     * — a manager closes tasks for people on leave, and the audit needs to
     * reflect who pressed the button rather than who was expected to.
     */
    public function complete(Request $request, Note $note): RedirectResponse
    {
        if ($note->status === 'done') {
            return back()->with('error', 'این مورد قبلاً انجام شده است.');
        }

        // Captured BEFORE the save: after it, getOriginal() returns the value we
        // just wrote, and the audit entry would read "done → done".
        $previous = $note->status;

        $note->forceFill([
            'status' => 'done',
            'done_at' => now(),
            'done_by' => $request->user()->id,
        ])->save();

        AuditRecorder::updated($note, ['status' => [$previous, 'done']],
            'انجام یادداشت: ' . $note->title);

        return back()->with('success', 'مورد انجام شد.');
    }

    public function reopen(Note $note): RedirectResponse
    {
        $previous = $note->status;

        $note->forceFill(['status' => 'open', 'done_at' => null, 'done_by' => null])->save();

        AuditRecorder::updated($note, ['status' => [$previous, 'open']], 'بازکردن یادداشت: ' . $note->title);

        return back()->with('success', 'مورد باز شد.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $title = $note->title;
        $note->delete();

        AuditRecorder::deleted($note, 'حذف یادداشت: ' . $title);

        return redirect()->route('notes.index')->with('success', 'یادداشت حذف شد.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'string', 'max:10'],
            'priority' => ['required', Rule::in(array_keys(Note::PRIORITIES))],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    /**
     * Jalali input → Gregorian storage string.
     *
     * Returns null for "no date given" and **false** for "a date was given but
     * it does not exist". Those are different facts and the caller must be able
     * to tell them apart: collapsing both to null is how an impossible date
     * (1404/12/30) silently becomes "no deadline".
     */
    private function dueDate(?string $jalali): string|false|null
    {
        if ($jalali === null || trim($jalali) === '') {
            return null;
        }

        $parsed = JalaliDate::parse($jalali);

        return $parsed === null ? false : $parsed->toDateString();
    }
}
