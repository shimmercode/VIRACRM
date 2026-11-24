<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * REQ-05.01–05.05 / §10 — record-level authorisation for projects.
 *
 * The counterpart to ContractPolicy; see that file for why a policy layer is
 * needed on top of route middleware. A project additionally carries a nullable
 * owner, and "the owner can always see their own project's schedule" is a real
 * distinction: a staff member assigned to a project should be able to open it
 * even without the blanket `projects.view` permission, or they cannot do the
 * work.
 *
 * That is not an invented business rule — it follows from the fact that
 * `owner_user_id` exists and §5-5 requires assigning a responsible person to a
 * project. A record assigned to someone who cannot open it is unusable.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        if ($user->hasPermission('projects.view')) {
            return true;
        }

        return $project->owner_user_id !== null
            && (int) $project->owner_user_id === (int) $user->id;
    }

    /** See the project's amount. Separate from `view`, as with contracts. */
    public function viewFinancials(User $user, Project $project): bool
    {
        return $this->view($user, $project)
            && $user->hasPermission('projects.view_financials');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete');
    }
}
