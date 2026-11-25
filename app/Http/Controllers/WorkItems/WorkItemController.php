<?php

namespace App\Http\Controllers\WorkItems;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\WorkItem;
use App\Services\Security\AuditRecorder;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * EXP-WORKITEM-001 — the کارتابل, made completable.
 *
 * THE GAP THIS CLOSES
 * -------------------
 * `work_items` held nine real obligations and the dashboard listed them, but no
 * route, controller or method anywhere could close one. `state` was written
 * once at seed time and `done_at` was permanently null. A recurring obligation
 * that can only be created and never discharged is a read-only report of work
 * nobody can finish.
 *
 * WHAT AN OPERATOR ACTUALLY NEEDS HERE
 * ------------------------------------
 * For a **report** item: mark it done, optionally with a note.
 * For an **invoice** item: §5 requires that once the invoice is issued its
 * number, amount and issue date are recorded against the item. That is not
 * decoration — the invoice numbers are the only link between the کارتابل and
 * the accounting module, so the completion form asks for them and the model
 * writes them in the same call as `done_at`. Capturing them separately would
 * allow an item to be marked done with no money recorded, which is how revenue
 * silently goes missing.
 *
 * CANCELLATION IS NOT COMPLETION
 * ------------------------------
 * `cancelled` exists because a retainer can end mid-period. Folding that into
 * `done` would inflate "invoices issued" and every downstream report. The two
 * outcomes are distinct records.
 */
class WorkItemController extends Controller
{
    /** The کارتابل page: every open obligation, bucketed the way a person works. */
    public function index(Request $request): View
    {
        $filters = [
            'kind' => (string) $request->query('kind', ''),
            'state' => (string) $request->query('state', 'open'),
            'project_id' => (string) $request->query('project_id', ''),
            'overdue' => (string) $request->query('overdue', ''),
        ];

        $query = WorkItem::query()
            ->with(['project:id,code,title,customer_id', 'project.customer:id,company_name,first_name,last_name', 'completedBy:id,name']);

        // "open" is the default because that is the working set. "all" exists so
        // a supervisor can audit what was completed and when.
        if ($filters['state'] === 'open') {
            $query->whereNull('done_at')->where('state', '!=', 'cancelled');
        } elseif ($filters['state'] !== '' && $filters['state'] !== 'all') {
            $query->where('state', $filters['state']);
        }

        if ($filters['kind'] !== '' && isset(WorkItem::KINDS[$filters['kind']])) {
            $query->where('kind', $filters['kind']);
        }

        if ($filters['project_id'] !== '') {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if ($filters['overdue'] === '1') {
            $query->whereNull('done_at')->whereDate('due_date', '<', now()->startOfDay());
        }

        $items = $query->orderBy('due_date')->orderBy('id')->paginate(25)->withQueryString();

        $today = now()->startOfDay();

        return view('work-items.index', [
            'items' => $items,
            'filters' => $filters,
            'kinds' => WorkItem::KINDS,
            'states' => WorkItem::STATES,
            'projects' => Project::query()->orderBy('title')->get(['id', 'code', 'title']),
            'today' => $today,
            // Counts are computed over the whole open set, not the current page:
            // a badge that counts only the visible page is a lie at 25+ items.
            'summary' => [
                'overdue' => WorkItem::query()->overdue()->count(),
                'due_today' => WorkItem::query()->open()->whereDate('due_date', $today)->count(),
                'open' => WorkItem::query()->open()->count(),
            ],
        ]);
    }

    /** The completion form. Invoice items additionally ask for the invoice facts. */
    public function show(WorkItem $workItem): View
    {
        $workItem->load(['project.customer', 'completedBy:id,name']);

        return view('work-items.show', [
            'item' => $workItem,
            'states' => WorkItem::STATES,
        ]);
    }

    /**
     * Close an obligation.
     *
     * The invoice fields are required *only* for invoice items: asking a report
     * item for an invoice number would train the user to ignore the field.
     */
    public function complete(Request $request, WorkItem $workItem): RedirectResponse
    {
        if ($workItem->done_at !== null) {
            return back()->with('error', 'این مورد قبلاً بسته شده است.');
        }

        $rules = [
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($workItem->kind === 'invoice') {
            $rules['invoice_number'] = ['required', 'string', 'max:64'];
            $rules['invoice_amount'] = ['required', 'string', 'max:32'];
            $rules['invoice_issue_date'] = ['nullable', 'string', 'max:10'];
        }

        $data = $request->validate($rules);

        $amount = isset($data['invoice_amount'])
            ? (int) (Money::parse($data['invoice_amount'])?->amount ?? -1)
            : null;

        // Money::parse returns null for garbage; -1 distinguishes "unparseable"
        // from a legitimate zero. Storing a 0 that the user did not type would
        // understate revenue, and nothing later would question it.
        if ($amount !== null && $amount < 0) {
            return back()
                ->withInput()
                ->withErrors(['invoice_amount' => 'مبلغ صورتحساب معتبر نیست.']);
        }

        $issueDate = null;
        if (($data['invoice_issue_date'] ?? '') !== '') {
            $issueDate = JalaliDate::parse($data['invoice_issue_date'])?->toDateString();

            if ($issueDate === null) {
                return back()
                    ->withInput()
                    ->withErrors(['invoice_issue_date' => 'تاریخ صدور صورتحساب معتبر نیست.']);
            }
        }

        $workItem->complete(
            $request->user()->id,
            $data['completion_notes'] ?? null,
            [
                'number' => $data['invoice_number'] ?? null,
                'amount' => $amount,
                'issue_date' => $issueDate,
            ],
        );

        AuditRecorder::updated(
            $workItem,
            [
                'state' => ['pending', 'done'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_amount' => $amount,
            ],
            'بستن مورد کارتابل: ' . $workItem->title,
        );

        return redirect()
            ->route('work-items.index')
            ->with('success', 'مورد کارتابل بسته شد.');
    }

    /** Undo a closure. Mistakes happen and the audit keeps both events. */
    public function reopen(Request $request, WorkItem $workItem): RedirectResponse
    {
        if ($workItem->done_at === null) {
            return back()->with('error', 'این مورد باز است.');
        }

        $workItem->reopen();

        AuditRecorder::updated($workItem, ['state' => ['done', 'pending']],
            'بازکردن مورد کارتابل: ' . $workItem->title);

        return redirect()
            ->route('work-items.index')
            ->with('success', 'مورد کارتابل باز شد.');
    }

    /** Cancel an obligation that should not happen. Distinct from done. */
    public function cancel(Request $request, WorkItem $workItem): RedirectResponse
    {
        if ($workItem->done_at !== null) {
            return back()->with('error', 'مورد بسته‌شده را نمی‌توان لغو کرد؛ ابتدا آن را باز کنید.');
        }

        $data = $request->validate([
            'completion_notes' => ['required', 'string', 'max:2000'],
        ]);

        $workItem->forceFill([
            'state' => 'cancelled',
            'completion_notes' => $data['completion_notes'],
        ])->save();

        AuditRecorder::updated($workItem, ['state' => ['pending', 'cancelled']],
            'لغو مورد کارتابل: ' . $workItem->title);

        return redirect()
            ->route('work-items.index')
            ->with('success', 'مورد کارتابل لغو شد.');
    }

    /** Create a one-off obligation on a project. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'kind' => ['required', Rule::in(array_keys(WorkItem::KINDS))],
            'due_date' => ['required', 'string', 'max:10'],
            'period' => ['nullable', 'string', 'max:16'],
            'recurrence' => ['nullable', 'string', 'max:16'],
            'reminder_offset_days' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);

        // JalaliDate::parse returns null for an impossible date (1404/12/30 —
        // 1404 is not a leap year). Treating that as "no date" would create a
        // due-date-less obligation, so it is a validation error instead.
        $due = JalaliDate::parse($data['due_date']);

        if ($due === null) {
            return back()->withInput()->withErrors(['due_date' => 'تاریخ سررسید معتبر نیست.']);
        }

        $item = WorkItem::create([
            'project_id' => (int) $data['project_id'],
            'kind' => $data['kind'],
            'due_date' => $due->toDateString(),
            'period' => $data['period'] ?? null,
            'state' => 'pending',
            'recurrence' => $data['recurrence'] ?? null,
            'reminder_offset_days' => (int) ($data['reminder_offset_days'] ?? 2),
        ]);

        AuditRecorder::created($item, 'ایجاد مورد کارتابل: ' . $item->title);

        return redirect()
            ->route('work-items.index')
            ->with('success', 'مورد کارتابل ثبت شد.');
    }
}
