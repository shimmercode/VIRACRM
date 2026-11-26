<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The audit trail, read-only.
 *
 * WHY IT IS READ-ONLY
 * -------------------
 * There is exactly one route: this index. No create, no update, no destroy. An
 * audit log an administrator can edit is not evidence, and the absence of those
 * routes is the control — not a hidden button. AuditLogPageTest asserts that
 * PUT/PATCH/DELETE against the path are refused, so the property cannot be lost
 * by someone adding a route later without a test failing.
 *
 * WHY IT EXISTS AT ALL
 * --------------------
 * `audit.view` existed in the permissions table from the beginning but was
 * granted to no role and referenced nowhere in app/ or routes/: the system
 * collected an audit trail with no way to read it. This is that interface.
 *
 * SECRETS
 * -------
 * Two layers. `AuditRecorder` redacts before a row is ever written; this
 * controller redacts again on the way out, because a row can predate the
 * redactor, be written by a seeder, or arrive from a future code path that
 * forgets. The second pass costs nothing and means the page has no way to
 * display a password even if one is in the table. See AuditLog::redact() and
 * AuditLog::scrub().
 */
final class AuditLogController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'user' => (int) $request->query('user', 0),
            'event' => (string) $request->query('event', ''),
            'subject' => (string) $request->query('subject', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                /* description is the only free-text column worth searching.
                 * `changes` is JSON and searching it would match ciphertext
                 * fragments and redaction markers rather than anything a person
                 * means by the term. */
                $query->where('description', 'like', '%' . $filters['q'] . '%');
            })
            ->when($filters['user'] > 0, fn ($q) => $q->where('user_id', $filters['user']))
            ->when($filters['event'] !== '', fn ($q) => $q->where('event', $filters['event']))
            ->when($filters['subject'] !== '', fn ($q) => $q->where('subject_type', $this->expandSubject($filters['subject'])))
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        /*
         * Record that the log was read — but not WHAT was searched for.
         *
         * A search term can itself be sensitive: someone hunting for a leaked
         * password would type it into the search box, and echoing that into the
         * audit trail would put the secret in the table this page is supposed to
         * protect. So the event names which filters were active, never their
         * values.
         */
        $active = array_keys(array_filter([
            'q' => $filters['q'] !== '',
            'user' => $filters['user'] > 0,
            'event' => $filters['event'] !== '',
            'subject' => $filters['subject'] !== '',
            'from' => $filters['from'] !== '',
            'to' => $filters['to'] !== '',
        ]));

        AuditRecorder::record(
            'audit.viewed',
            null,
            sprintf(
                'بازبینی گزارش رویدادها — صفحه %d از %d، فیلترهای فعال: %s',
                $logs->currentPage(),
                max(1, $logs->lastPage()),
                $active === [] ? 'هیچ' : implode('، ', $active),
            ),
        );

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'total' => AuditLog::query()->count(),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'subjects' => $this->subjectChoices(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Redact a row for display.
     *
     * Called from the view, so every rendering path goes through it — there is
     * no template branch that can print the raw column.
     *
     * @return array{event:string,description:?string,changes:?array}
     */
    public static function present(AuditLog $log): array
    {
        return [
            'event' => (string) $log->event,
            'description' => AuditLog::scrub($log->description),
            /* redact() is idempotent, so running it over an already-redacted row
             * changes nothing and costs nothing. */
            'changes' => AuditLog::redact($log->changes),
        ];
    }

    /**
     * Short label => model class, for the entity filter.
     *
     * Derived from the table rather than hardcoded: a filter list that drifts
     * from the data silently shows "no results" for entities that exist.
     *
     * @return array<string,string>
     */
    private function subjectChoices(): array
    {
        $out = [];

        foreach (AuditLog::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type') as $class) {
            $short = self::shortName((string) $class);
            $out[$short] = (string) $class;
        }

        ksort($out);

        return $out;
    }

    /** `App\Models\Customer` => `Customer`. */
    private static function shortName(string $class): string
    {
        $at = strrpos($class, '\\');

        return $at === false ? $class : substr($class, $at + 1);
    }

    /** Reverse of shortName(): the filter passes the short name. */
    private function expandSubject(string $short): string
    {
        return $this->subjectChoices()[$short] ?? $short;
    }
}
