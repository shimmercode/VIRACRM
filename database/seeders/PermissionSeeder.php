<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Services\Security\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * REQ-10.01 — seed the permission catalogue.
 *
 * IDEMPOTENT: uses updateOrCreate keyed on `code`, so running it repeatedly
 * neither duplicates rows nor resets the grid. Safe to run on deploy.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']],
                [
                    'area' => $permission['area'],
                    'action' => $permission['action'],
                    'title' => $permission['title'],
                    'is_sensitive' => $permission['is_sensitive'],
                ]
            );
        }

        $this->command?->info('Permissions: ' . count(PermissionCatalog::all()) . ' synced.');
    }
}
