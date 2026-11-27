<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §9-4 / REQ-09.04 — «مشاهده فهرست فاکتورها و گزارش‌های موعددار، انجام‌شده و
 * عقب‌افتاده».
 *
 * WHY THIS IS NOT THE WORK-ITEMS INDEX
 * ------------------------------------
 * The index is a working list: it defaults to the open items and offers an
 * `overdue=1` tick. A report has to classify and reconcile, so that "six things
 * are late" is a number that can be checked against the rows.
 *
 * The two kinds the document names — invoices and reports — are the two values
 * of `work_items.kind`, so no new entity is introduced here; this reads the same
 * rows the operational screens write.
 *
 * THE COUNTING BASIS, STATED BECAUSE IT IS A DECISION
 * ---------------------------------------------------
 * Every row falls in exactly one bucket, and the buckets sum to the row count:
 *
 *   cancelled    state = cancelled, whatever its dates say
 *   done         state = done
 *   overdue      still open, due_date < today
 *   due_today    still open, due_date = today
 *   upcoming     still open, due_date > today
 *   unscheduled  still open with no due_date at all
 *
 * "Still open" means state = pending. Cancelled is tested first, because a
 * cancelled item whose due date has passed is not late work — counting it as
 * overdue would inflate the number a supervisor acts on. `unscheduled` exists
 * because a pending row with a NULL due date falls in none of the three date
 * buckets; without it the sum would silently be short by those rows and every
 * other total on the page would be untrustworthy.
 *
 * Overdue is kept separate from due-today and from upcoming. "Due today" is a
 * reminder and "nine days late" is a problem, and the document lists عقب‌افتاده
 * as its own category.
 */
final class WorkItemReportController extends Controller
{
    /** Buckets in display order. Every work item falls in exactly one. */
    public const CATEGORIES = ['overdue', 'due_today', 'upcoming', 'unscheduled', 'done', 'cancelled'];

    public function index(Request $request): View
    {
        $today = now()->startOfDay();

        $category = (string) $request->query('category', 'overdue');
        // An unknown bucket must fall back, never widen to "everything".
        if (! in_array($category, self::CATEGORIES, true)) {
            $category = 'overdue';
        }

        $kind = (string) $request->query('kind', '');
        if (! isset(WorkItem::KINDS[$kind])) {
            $kind = '';
        }

        $filters = [
            'kind' => $kind,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => (string) $request->query('sort', 'due_date'),
            'dir' => strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];

        $sortable = ['due_date', 'state', 'kind', 'id'];
        if (! in_array($filters['sort'], $sortable, true)) {
            $filters['sort'] = 'due_date';
        }

        /**
         * Every bucket starts from the same filtered base, so the tiles and the
         * list can never be describing different sets.
         */
        $base = function () use ($filters): Builder {
            return WorkItem::query()
                ->when($filters['kind'] !== '', fn (Builder $q) => $q->where('kind', $filters['kind']))
                // The date range filters on the DUE date: this report is about
                // when things fall due, not when they were typed in.
                ->when($filters['from'] !== '', fn (Builder $q) => $q->whereDate('due_date', '>=', $filters['from']))
                ->when($filters['to'] !== '', fn (Builder $q) => $q->whereDate('due_date', '<=', $filters['to']));
        };

        $open = fn (): Builder => $base()->where('state', 'pending');

        $buckets = [
            'overdue' => fn (): Builder => $open()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today),

            'due_today' => fn (): Builder => $open()
                ->whereDate('due_date', '=', $today),

            'upcoming' => fn (): Builder => $open()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>', $today),

            'unscheduled' => fn (): Builder => $open()->whereNull('due_date'),

            'done' => fn (): Builder => $base()->where('state', 'done'),

            'cancelled' => fn (): Builder => $base()->where('state', 'cancelled'),
        ];

        $counts = [];
        foreach ($buckets as $key => $build) {
            $counts[$key] = $build()->count();
        }
        $counts['total'] = $base()->count();

        /*
         * Reconciled in the controller, not only asserted in a test: if a state
         * is added to WorkItem::STATES and no bucket claims it, the page says so
         * rather than showing totals that quietly do not add up.
         */
        $sum = 0;
        foreach (self::CATEGORIES as $key) {
            $sum += $counts[$key];
        }

        $items = $buckets[$category]()
            ->with([
                'project:id,code,title,customer_id',
                // displayName() branches on `type`, so it has to be selected or a
                // legal customer renders as «مشتری بدون نام».
                'project.customer:id,type,company_name,first_name,last_name',
                'completedBy:id,name',
            ])
            ->orderBy($filters['sort'], $filters['dir'])
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('reports.work-items', [
            'counts' => $counts,
            'reconciles' => $sum === $counts['total'],
            'sumOfBuckets' => $sum,
            'category' => $category,
            'filters' => $filters,
            'kinds' => WorkItem::KINDS,
            'items' => $items,
            'today' => $today,
            // invoice_amount is a financial figure; the rest of the product
            // gates invoice data on invoices.view, and this report is no
            // different for reading the same column.
            'canSeeAmounts' => $request->user()->hasPermission('invoices.view'),
            'pd' => static fn ($v): string => \App\Support\Money::toPersianDigits((string) $v),
        ]);
    }
}
