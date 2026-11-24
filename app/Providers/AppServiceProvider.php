<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Project;
use App\Policies\ContractPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * REQ-10.03 — record-level policies. Registered explicitly rather than
     * relying on Laravel's convention so the full set is visible in one place:
     * a policy that is discoverable only by naming accident is one nobody
     * remembers to add when a new model arrives.
     *
     * Contract and Project are covered. Credential and Payslip policies are
     * part of M4/M7 and are recorded as NOT IMPLEMENTED in the security review
     * until their models exist.
     */
    public function boot(): void
    {
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
    }
}
