<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The CRM Review Environment.
 *
 * This controller renders a catalogue of every screen the requirements mention,
 * with an honest status label for each. It exists because the client asked to
 * *see* the product in a browser without running code, and because a screenshot
 * set cannot be navigated.
 *
 * WHAT IT IS NOT
 * --------------
 * It is not a second application. Every page it renders uses the real layout,
 * the real design system and the real Blade components. The only screens this
 * controller draws from scratch are the ones that have no backend yet, and those
 * carry the "UI Preview" badge on the page itself.
 *
 * SAFETY
 * ------
 *   - Refuses to run outside local/review/testing (see guard()).
 *   - Every review route is behind the same `auth` middleware as the product.
 *   - POST endpoints are deliberately absent: this environment cannot write.
 */
class ReviewController extends Controller
{
    /** Refuse to expose the review surface on anything resembling production. */
    private function guard(): void
    {
        $allowed = (array) config('review.allowed_environments', ['local']);

        abort_unless(config('review.enabled') === true, 404);
        abort_unless(in_array(app()->environment(), $allowed, true), 404);
    }

    /** The catalogue: every screen, its status, and whether the browser can open it. */
    public function index(): View
    {
        $this->guard();

        $pages = collect(config('review.pages', []))->map(function (array $p) {
            $p['reachable'] = $this->isReachable($p);
            $p['url'] = $this->urlFor($p);

            return $p;
        });

        return view('review.index', [
            'pages' => $pages,
            'pageTitle' => 'فهرست صفحات — محیط بازبینی',
            'counts' => $pages->countBy('status'),
        ]);
    }

    /** A preview page for a screen that has no backend. */
    public function preview(string $key): View
    {
        $this->guard();

        $page = collect(config('review.pages', []))->firstWhere('key', $key);

        if ($page === null || empty($page['preview'])) {
            throw new NotFoundHttpException();
        }

        /*
         * The catalogue declares a `permission` for every page, mirroring the
         * middleware on the real route. Until this check existed the field was
         * documentation only, and a viewer could open an admin-only preview by
         * typing its URL — the exact "looks reviewed, was never enforced" failure
         * the directive warns about. It is enforced here now.
         */
        $this->authorizePage($page);

        $view = 'review.previews.' . $page['preview'];

        // A preview key that resolves to no view is a wiring bug, not a missing
        // page. Fail loudly in the log so it cannot hide behind a 404, but still
        // answer 404 to the browser.
        if (! view()->exists($view)) {
            report(new \RuntimeException("Review preview wired to a missing view: {$view} (key={$key})"));
            abort(404);
        }

        $data = $this->previewData($page['preview']);

        return view($view, [
            'page' => $page,
            'pageTitle' => $page['title'],
            'reviewPage' => $page,
        ] + $data);
    }

    /**
     * Real rows for the previews that can show real rows.
     *
     * The other previews are design only and receive nothing, which is exactly
     * what their provenance banner says. Returning an empty array here is not a
     * failure: it is the honest statement that the screen has no backend.
     */
    private function previewData(string $preview): array
    {
        try {
            return match ($preview) {
                'org' => $this->orgData(),
                'project-team' => $this->projectTeamData(),
                default => [],
            };
        } catch (\Throwable $e) {
            // A broken query must not take the whole catalogue down, and it must
            // not be disguised as "no data". Log it and let the page say so.
            report($e);

            return ['dataError' => 'خواندن داده با خطا مواجه شد؛ جزئیات در لاگ سرور ثبت شده است.'];
        }
    }

    /**
     * The organisation screen, backed by the review database.
     *
     * This deliberately shows the model's *current* limits rather than dressing
     * them up: there is no departments table, so department rows are derived
     * from the employee's `position`-independent fields only where the schema
     * supports it. What cannot be derived is labelled as not modelled.
     */
    private function orgData(): array
    {
        $employees = DB::table('employees')
            ->where('code', 'like', 'RVW-E-%')
            ->orderBy('code')
            ->get(['code', 'first_name', 'last_name', 'position', 'status', 'user_id']);

        $users = DB::table('users')
            ->whereIn('id', $employees->pluck('user_id')->filter()->all() ?: [0])
            ->get(['id', 'username', 'is_active'])
            ->keyBy('id');

        $roles = DB::table('roles')->orderBy('id')->get(['code', 'title']);
        $roleCounts = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->selectRaw('roles.title as title, count(*) as n')
            ->groupBy('roles.id')
            ->get();

        $rows = $employees->map(function ($e) use ($users) {
            $user = $e->user_id ? $users->get($e->user_id) : null;

            return [
                'code' => $e->code,
                'name' => trim($e->first_name . ' ' . $e->last_name),
                'position' => $e->position,
                'status' => $e->status,
                'username' => $user->username ?? null,
                'user_active' => $user ? (bool) $user->is_active : null,
            ];
        });

        return [
            'rows' => $rows,
            'roles' => $roles,
            'roleCounts' => $roleCounts,
            'provenance' => \App\Support\ReviewProvenance::of($rows->count(), 'جدول employees محیط بازبینی'),
        ];
    }

    /**
     * The project-team screen.
     *
     * Reads `project_assignments` joined to `employees` and `projects`. This is
     * the only table that ties a person to a project, which is why the directive
     * asks whether "Team" means this: if it does, the UI must call it
     * **Project Team** and permissions must resolve through it. That decision is
     * open (OD-118); this screen shows the data, not a resolution.
     */
    private function projectTeamData(): array
    {
        $assignments = DB::table('project_assignments')
            ->join('projects', 'projects.id', '=', 'project_assignments.project_id')
            ->join('employees', 'employees.id', '=', 'project_assignments.employee_id')
            ->where('projects.code', 'like', 'RVW-%')
            ->orderBy('projects.code')
            ->orderBy('project_assignments.coefficient', 'desc')
            ->get([
                'projects.code as project_code',
                'projects.title as project_title',
                'projects.status as project_status',
                'employees.code as employee_code',
                'employees.first_name',
                'employees.last_name',
                'employees.status as employee_status',
                'project_assignments.role',
                'project_assignments.coefficient',
                'project_assignments.is_active',
                'project_assignments.started_on',
                'project_assignments.ended_on',
            ]);

        $byProject = $assignments->groupBy('project_code')->map(function ($group) {
            return [
                'title' => $group->first()->project_title,
                'status' => $group->first()->project_status,
                'members' => $group->map(fn ($a) => [
                    'name' => trim($a->first_name . ' ' . $a->last_name),
                    'code' => $a->employee_code,
                    'role' => $a->role,
                    'coefficient' => (float) $a->coefficient,
                    'active' => (bool) $a->is_active,
                    'employee_status' => $a->employee_status,
                    'period' => $a->started_on . ($a->ended_on ? ' → ' . $a->ended_on : ' → اکنون'),
                ])->values()->all(),
            ];
        });

        return [
            'teams' => $byProject,
            'assignmentRows' => $assignments->count(),
            'provenance' => \App\Support\ReviewProvenance::of(
                $assignments->count(),
                'جدول project_assignments محیط بازبینی'
            ),
        ];
    }

    /**
     * Environment health, shown above the catalogue.
     *
     * Reads only counts — never row contents — so the review environment cannot
     * become a data-export path even if it were somehow exposed.
     */
    public function health(): View
    {
        $this->guard();

        $tables = ['users', 'roles', 'permissions', 'customers', 'contracts', 'projects', 'work_items'];
        $counts = [];

        foreach ($tables as $table) {
            try {
                $counts[$table] = DB::table($table)->count();
            } catch (\Throwable) {
                $counts[$table] = null;
            }
        }

        return view('review.health', [
            'counts' => $counts,
            'pageTitle' => 'وضعیت محیط بازبینی',
            'dbPath' => config('database.connections.' . config('database.default') . '.database'),
            'env' => app()->environment(),
        ]);
    }

    /**
     * Refuse a page the signed-in user could not open in the product itself.
     *
     * A superadmin passes everything, exactly as in the application. Everyone
     * else is checked against the same `hasPermission()` the sidebar uses.
     */
    private function authorizePage(array $page): void
    {
        $user = auth()->user();
        $permission = $page['permission'] ?? null;

        if ($permission === null || $user === null) {
            return;
        }

        abort_unless($user->hasPermission($permission), 403);
    }

    private function isReachable(array $page): bool
    {
        if (empty($page['route'])) {
            return ! empty($page['preview']);
        }

        return \Illuminate\Support\Facades\Route::has($page['route']);
    }

    private function urlFor(array $page): ?string
    {
        if (! empty($page['route']) && \Illuminate\Support\Facades\Route::has($page['route'])) {
            try {
                return route($page['route']);
            } catch (\Throwable) {
                return null;
            }
        }

        if (! empty($page['preview'])) {
            return route('review.preview', ['key' => $page['key']]);
        }

        return null;
    }
}
