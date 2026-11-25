<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * REQ-10.01 — baseline data for a real install.
 *
 * ONLY configuration-grade data is seeded here: permissions, roles, contract
 * types, project dictionaries (types and SEO packages), settings and the single
 * superadmin. No customers, no contracts, no
 * projects.
 *
 * The phase brief forbids sample data presented as a real product. Demo
 * records, if ever needed, belong in a separate seeder that is never run on a
 * production install (see DemoDataSeeder, which is NOT called from here).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            ContractTypeSeeder::class,
            ProjectDictionarySeeder::class,
            CredentialTypeSeeder::class,
            SettingsSeeder::class,
            UserSeeder::class,
            // OD-41 — the approved chart of accounts and the fiscal periods it
            // needs. Configuration-grade, not sample data: an install with no
            // open period can record no financial event at all.
            ChartOfAccountsSeeder::class,
            FiscalPeriodSeeder::class,
        ]);
    }
}
