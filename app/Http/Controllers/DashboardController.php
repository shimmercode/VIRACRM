<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Setting;
use App\Models\WorkItem;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * REQ-02.01–02.05 — dashboard and کارتابل.
 *
 * §2 asks for exactly five counts and a task list with four buckets
 * (today / future / overdue / done). §13 and the phase brief forbid invented
 * KPIs and charts, so there are no charts here and every number is a real
 * COUNT over rows the user is allowed to see.
 *
 * "Done" items leave the list but the history is kept (§2), so the done bucket
 * is a separate count rather than a filter on the same list.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $warningDays = (int) (Setting::get('contracts.expiry_warning_days') ?? 30);
        $canSeeContracts = $user->hasPermission('contracts.view');

        // ── The five counts from §2 ─────────────────────────────────────
        $counts = [
            'customers' => $user->hasPermission('customers.view')
                ? Customer::query()->count()
                : null,
            'active_contracts' => $canSeeContracts
                ? Contract::query()->live()->count()
                : null,
            'expiring_contracts' => $canSeeContracts
                ? Contract::query()->expiringWithin($warningDays)->count()
                : null,
            'active_projects' => $user->hasPermission('projects.view')
                ? Project::query()->where('status', 'active')->count()
                : null,
            // Overdue work is the number that actually drives behaviour (§5-2).
            'overdue_tasks' => $this->overdueCount($user),
        ];

        // ── کارتابل buckets ─────────────────────────────────────────────
        $today = now()->startOfDay();

        $openItems = WorkItem::query()
            ->with(['project.customer'])
            ->whereNull('done_at')
            ->orderBy('due_date')
            ->limit(60)
            ->get();

        $buckets = [
            'overdue' => $openItems->filter(fn (WorkItem $i) => $i->due_date->lt($today)),
            'today' => $openItems->filter(fn (WorkItem $i) => $i->due_date->equalTo($today)),
            'future' => $openItems->filter(fn (WorkItem $i) => $i->due_date->gt($today)),
        ];

        $doneThisMonth = WorkItem::query()
            ->whereNotNull('done_at')
            ->where('done_at', '>=', now()->startOfMonth())
            ->count();

        // ── Contracts approaching expiry (§2 warning list) ──────────────
        $expiring = $canSeeContracts
            ? Contract::query()
                ->with('customer')
                ->expiringWithin($warningDays)
                ->orderBy('end_date')
                ->limit(6)
                ->get()
            : collect();

        return view('dashboard', [
            'counts' => $counts,
            'buckets' => $buckets,
            'doneThisMonth' => $doneThisMonth,
            'expiring' => $expiring,
            'warningDays' => $warningDays,
            'todayJalali' => JalaliDate::fromGregorian(now()->toImmutable())->formatLongPersian(),
            'greeting' => $this->greeting(),
        ]);
    }

    private function overdueCount($user): ?int
    {
        if (! $user->hasPermission('projects.view')) {
            return null;
        }

        return WorkItem::query()
            ->whereNull('done_at')
            ->whereDate('due_date', '<', now()->startOfDay())
            ->count();
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'صبح بخیر',
            $hour < 17 => 'وقت بخیر',
            default => 'شب بخیر',
        };
    }
}
