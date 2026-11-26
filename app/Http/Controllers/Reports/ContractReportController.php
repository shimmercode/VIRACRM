<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §9-4 / REQ-09.04 — «گزارش قراردادهای فعال، پایان‌یافته و نزدیک به انقضا».
 *
 * WHY THIS IS NOT JUST THE CONTRACTS INDEX
 * ----------------------------------------
 * The index filters by status; a report has to *classify* and *reconcile*. Two
 * things here are decisions, and both are asserted by contract-report.mjs:
 *
 * 1. `expiring` is DERIVED, never stored (see ContractStatus::derived()). A
 *    report that selected `status = 'expiring'` would return an empty set and
 *    look like a working report with no data. The near-expiry count comes from
 *    the same `expiringWithin()` scope the dashboard uses, over the same
 *    configurable window, so the two cannot disagree.
 *
 * 2. The buckets are MUTUALLY EXCLUSIVE and their sum equals the contract
 *    count. A contract inside the warning window is reported as near-expiry and
 *    NOT as active; otherwise the same contract appears in two totals and the
 *    numbers cannot be trusted.
 *
 * The `overdue` bucket is not one of the three the document names. It exists
 * because a contract can be marked active with an end date already past, and a
 * report that silently dropped it would understate both categories it feeds.
 * It is labelled separately rather than folded into "near expiry", because
 * "expires in 3 days" and "expired 200 days ago and nobody noticed" need
 * different responses.
 */
final class ContractReportController extends Controller
{
    /** Buckets in display order. Every contract falls in exactly one. */
    public const CATEGORIES = ['expiring', 'overdue', 'active', 'finished', 'draft'];

    public function index(Request $request): View
    {
        $warningDays = (int) (Setting::get('contracts.expiry_warning_days') ?? 30);
        if ($warningDays < 0 || $warningDays > 3650) {
            $warningDays = 30;
        }

        $category = (string) $request->query('category', 'expiring');
        // An unknown bucket must fall back, never widen to "everything".
        if (! in_array($category, self::CATEGORIES, true)) {
            $category = 'expiring';
        }

        $today = now()->startOfDay();
        $windowEnd = $today->copy()->addDays($warningDays);

        $buckets = [
            'expiring' => fn (): Builder => Contract::query()->expiringWithin($warningDays),

            'overdue' => fn (): Builder => Contract::query()->live()
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', $today),

            'active' => fn (): Builder => Contract::query()->live()
                ->where(function (Builder $q) use ($windowEnd): void {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>', $windowEnd);
                }),

            'finished' => fn (): Builder => Contract::query()->whereIn('status', [
                ContractStatus::Expired->value,
                ContractStatus::Renewed->value,
                ContractStatus::Cancelled->value,
            ]),

            'draft' => fn (): Builder => Contract::query()
                ->where('status', ContractStatus::Draft->value),
        ];

        $counts = [];
        foreach ($buckets as $key => $build) {
            $counts[$key] = $build()->count();
        }
        $counts['total'] = Contract::query()->count();

        /*
         * Reconciled in the controller, not just asserted in a test: if a
         * future status or scope change makes a contract fall in no bucket, or
         * in two, the page says so instead of quietly showing wrong numbers.
         */
        $sum = $counts['expiring'] + $counts['overdue'] + $counts['active']
            + $counts['finished'] + $counts['draft'];

        $contracts = $buckets[$category]()
            ->with('customer:id,legal_name,first_name,last_name')
            ->orderBy('end_date')
            ->paginate(20)
            ->withQueryString();

        return view('reports.contracts', [
            'counts' => $counts,
            'reconciles' => $sum === $counts['total'],
            'sumOfBuckets' => $sum,
            'category' => $category,
            'warningDays' => $warningDays,
            'contracts' => $contracts,
            'today' => $today,
            'canSeeAmounts' => $request->user()->hasPermission('contracts.view_financials'),
            'pd' => static fn ($v): string => \App\Support\Money::toPersianDigits((string) $v),
        ]);
    }
}
