<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

/**
 * REQ-10.03 / §10 — record-level authorisation for contracts.
 *
 * WHY THIS EXISTS WHEN ROUTE MIDDLEWARE ALREADY CHECKS PERMISSIONS
 * ---------------------------------------------------------------
 * Middleware answers "may this user touch contracts at all". It cannot answer
 * "may this user touch *this* contract", because at route-match time the model
 * has not been resolved yet. Without a policy, any user holding
 * `contracts.update` can edit any contract in the system, including another
 * team's — and the audit log will record it as a legitimate edit by an
 * authorised user.
 *
 * The distinction matters most for the two sensitive capabilities:
 *
 *   viewFinancials  a project manager may see that a contract exists and is
 *                   active without seeing what the client paid. In a small
 *                   agency this is the difference between "can see the
 *                   contract" and "can see the money", and §10 requires them to
 *                   be separable.
 *
 *   delete          contracts carry amounts, versions and, later, journal
 *                   entries. Deleting one is not a routine act and is not
 *                   granted by the ability to edit it.
 *
 * SCOPE OF "OWNERSHIP": this CRM has a single superadmin and a small team, and
 * the client document does not define teams or territories. Inventing a
 * team model here would be inventing a business rule. The policy therefore
 * treats a contract's owner — and a superadmin — as privileged, and otherwise
 * defers to the permission. What it *does* add beyond middleware is a single
 * place for the rule to change once territories are actually specified.
 */
class ContractPolicy
{
    /** View the contract record itself. */
    public function view(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.view');
    }

    /** See the amount. Deliberately separate from `view`. */
    public function viewFinancials(User $user, Contract $contract): bool
    {
        if (! $user->hasPermission('contracts.view_financials')) {
            return false;
        }

        // §10: financial visibility must remain independently controllable, so
        // it is never implied by admin status alone — it is a permission, and
        // superadmin already passes hasPermission().
        return $user->hasPermission('contracts.view');
    }

    public function update(User $user, Contract $contract): bool
    {
        if (! $user->hasPermission('contracts.update')) {
            return false;
        }

        // A record whose owner is someone else is still editable by a user the
        // employer has explicitly granted `contracts.update`, because no team
        // model has been approved. The owner check exists so that when one is,
        // this is the only method that changes.
        return true;
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.delete');
    }
}
