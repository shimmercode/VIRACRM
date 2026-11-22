<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * REQ-10.01 — baseline data for a real install.
 *
 * ONLY configuration-grade data is seeded here: permissions, roles, contract
 * types, settings and the single superadmin. No customers, no contracts, no
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
            SettingsSeeder::class,
            UserSeeder::class,
        ]);
    }
}
