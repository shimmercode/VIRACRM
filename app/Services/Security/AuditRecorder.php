<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * REQ-10.04 — single entry point for writing the audit trail.
 *
 * Every recordable event goes through here so redaction cannot be forgotten at
 * a call site, and so a failure to write the log never breaks the user's
 * action. The latter matters: a full audit table must not stop an accountant
 * from saving a payslip. Failures are reported to the application log instead.
 */
final class AuditRecorder
{
    /** Events named in the client document §10. */
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const REVEALED = 'revealed';
    public const EXPORTED = 'exported';
    public const LOGIN = 'login';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGOUT = 'logout';
    public const DENIED = 'denied';

    public static function record(
        string $event,
        ?Model $subject = null,
        ?string $description = null,
        ?array $changes = null,
        ?int $userId = null,
    ): ?AuditLog {
        try {
            // Auth is optional: seeding, importing and console commands write
            // audit rows without any authenticated user, and a test harness may
            // have no guard bound at all. Checking the binding first keeps the
            // recorder usable in both.
            $user = null;

            if (function_exists('auth') && app()->bound('auth')) {
                $user = auth()->user();
            }

            return AuditLog::create([
                'user_id' => $userId ?? ($user?->id),
                'event' => $event,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description !== null ? mb_substr($description, 0, 255) : null,
                'changes' => AuditLog::redact($changes),
                // The HTTP request is unavailable when no kernel has run
                // (seeders, imports, queue workers). Recording a null IP is far
                // better than dropping the audit row entirely, so the lookup is
                // guarded rather than assumed.
                'ip' => self::ip(),
                'user_agent' => self::requestValue(fn ($r) => mb_substr((string) $r->userAgent(), 0, 255)),
            ]);
        } catch (Throwable $e) {
            // Never let auditing break the operation the user asked for.
            //
            // `report()` itself needs a resolved exception handler, which a
            // bare test harness does not always provide. If reporting fails,
            // fall through to the error log so the original failure is still
            // visible somewhere instead of being swallowed.
            try {
                report($e);
            } catch (Throwable) {
                error_log('audit write failed: ' . $e->getMessage());
            }

            return null;
        }
    }

    private static function ip(): ?string
    {
        return self::requestValue(fn ($request) => $request->ip());
    }

    /**
     * Pull one value from the current HTTP request, or null when there is none.
     * `app()->bound('request')` is not a reliable test here: the helper can be
     * bound as a class name that the container cannot instantiate, so the call
     * is attempted and any failure is swallowed.
     */
    private static function requestValue(callable $extract): mixed
    {
        try {
            return $extract(app()->make('request'));
        } catch (Throwable) {
            return null;
        }
    }

    public static function created(Model $model, ?string $description = null): ?AuditLog
    {
        return self::record(self::CREATED, $model, $description ?? 'ایجاد رکورد', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    public static function updated(Model $model, array $changes, ?string $description = null): ?AuditLog
    {
        return self::record(self::UPDATED, $model, $description ?? 'ویرایش رکورد', $changes);
    }

    public static function deleted(Model $model, ?string $description = null): ?AuditLog
    {
        return self::record(self::DELETED, $model, $description ?? 'حذف رکورد');
    }

    /** §6 / §10: viewing a stored password is itself an auditable event. */
    public static function revealed(Model $model, string $field, ?string $description = null): ?AuditLog
    {
        return self::record(self::REVEALED, $model, $description ?? 'مشاهده رمز', [
            'field' => $field,
        ]);
    }

    /** §6: exporting confidential material must be attributable. */
    public static function exported(Model $model, string $what, ?string $description = null): ?AuditLog
    {
        return self::record(self::EXPORTED, $model, $description ?? 'خروجی گرفتن', ['what' => $what]);
    }

    /** §10: a refused attempt is as important as a successful one. */
    public static function denied(string $permission, ?Model $subject = null): ?AuditLog
    {
        return self::record(self::DENIED, $subject, 'دسترسی رد شد: ' . $permission, [
            'permission' => $permission,
        ]);
    }

    public static function login(?int $userId = null, ?string $username = null): ?AuditLog
    {
        return self::record(self::LOGIN, null, 'ورود موفق' . ($username ? ': ' . $username : ''), null, $userId);
    }

    public static function loginFailed(string $username, string $reason): ?AuditLog
    {
        // The reason is recorded; the attempted password never is.
        return self::record(self::LOGIN_FAILED, null, 'تلاش ناموفق ورود: ' . $username, ['reason' => $reason]);
    }
}
