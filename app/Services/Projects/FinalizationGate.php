<?php

namespace App\Services\Projects;

use App\Models\CredentialRequirement;
use App\Models\CredentialType;
use App\Models\Project;

/**
 * §6 / §14 — an SEO project may not be finalised or activated until its
 * mandatory accesses exist.
 *
 * WHAT THE DOCUMENT ACTUALLY SAYS
 * -------------------------------
 * «تا زمانی که تمام فیلدهای اجباری تکمیل نشده‌اند، اجازه نهایی‌سازی/فعال‌سازی
 * پروژه سئو را ندهد. در صورت نیاز، امکان ذخیره پیش‌نویس ناقص وجود داشته باشد؛
 * اما پروژه ناقص نباید به‌عنوان پروژه نهایی‌شده ثبت شود.»
 *
 * So a DRAFT may be incomplete — that is explicitly allowed, and it is how the
 * documented workflow runs (create → fill in accesses → finalise). What is not
 * allowed is an incomplete project masquerading as a live one. The gate therefore
 * applies to the statuses that mean "this is real", and leaves draft, paused and
 * cancelled alone: you must always be able to park or cancel a project, and
 * blocking that would trap bad data in the system.
 *
 * WHY THIS IS A SERVICE AND NOT A RULE INSIDE THE FORM REQUEST
 * ------------------------------------------------------------
 * The same question — "which mandatory accesses are missing?" — is needed in
 * three places: validation (to refuse the save), the project page (to tell the
 * user what is still missing) and the PDF/export path. Three copies of the query
 * is three chances for them to disagree, and a gate that disagrees with the
 * message explaining it is worse than no gate.
 */
final class FinalizationGate
{
    /**
     * Statuses that mean the project is real, and so are gated.
     *
     * `draft`, `paused` and `cancelled` are deliberately absent — see above.
     */
    public const GATED_STATUSES = ['active', 'completed'];

    /**
     * The mandatory credential types this project does not have yet.
     *
     * A null project id means "not saved yet", so nothing can exist and every
     * blocking type is missing — which is exactly why creating an SEO project
     * straight into `active` is refused and the user is sent through the draft
     * first.
     *
     * @return list<CredentialType>
     */
    public static function missing(?int $projectId, string $projectType): array
    {
        $required = CredentialRequirement::blockingFor($projectType);

        if ($required->isEmpty()) {
            /* No requirements configured means no gate. That is the honest
             * reading of an empty table — inventing a default list here would
             * hide the fact that the client has not defined one. */
            return [];
        }

        if ($projectId === null) {
            return $required->all();
        }

        $present = Project::query()
            ->whereKey($projectId)
            ->first()?->credentials()
            ->whereNotNull('credential_type_id')
            ->pluck('credential_type_id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        return $required
            ->reject(fn (CredentialType $type) => in_array((int) $type->id, $present, true))
            ->values()
            ->all();
    }

    /** Should this combination of type and status be refused? */
    public static function blocks(?int $projectId, string $projectType, string $status): bool
    {
        if (! in_array($status, self::GATED_STATUSES, true)) {
            return false;
        }

        return self::missing($projectId, $projectType) !== [];
    }

    /**
     * The Persian message shown when the gate refuses a save.
     *
     * Names what is missing rather than saying "incomplete": a user who has to
     * guess which of six access kinds the system wants will guess wrong.
     *
     * @param list<CredentialType> $missing
     */
    public static function message(array $missing): string
    {
        $titles = array_map(static fn (CredentialType $t) => '«' . $t->title . '»', $missing);

        return 'پروژه سئو بدون تکمیل دسترسی‌های اجباری قابل فعال‌سازی نیست. موارد ناقص: '
            . implode('، ', $titles)
            . '. پروژه را به‌صورت پیش‌نویس ذخیره کنید و پس از ثبت دسترسی‌ها آن را فعال کنید.';
    }
}
