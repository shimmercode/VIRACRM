<?php

namespace Database\Seeders;

use App\Models\ProjectType;
use App\Models\SeoPackage;
use Illuminate\Database\Seeder;

/**
 * REQ-05.01–05.05 / §15 — configurable project dictionaries.
 *
 * `project_types` and `seo_packages` are dictionaries, not enums, because §15
 * says the agency must be able to add and rename them without a code change.
 * The rows below are the minimum implied by §5 (SEO and design projects) plus
 * three commercially ordinary SEO packages.
 *
 * These are configuration, not sample business data: they carry no customer,
 * no amount and no date. They are safe to seed on a production install, and
 * seeding them here means the project form's package list is not empty on a
 * fresh system.
 */
class ProjectDictionarySeeder extends Seeder
{
    public function run(): void
    {
        // project_types has no sort_order column (unlike seo_packages) — the
        // dictionary is two rows and ordering is not a product concern.
        $types = [
            ['code' => 'seo', 'title' => 'سئو'],
            ['code' => 'design', 'title' => 'طراحی'],
        ];

        foreach ($types as $type) {
            ProjectType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true]
            );
        }

        $packages = [
            [
                'code' => 'seo-basic',
                'title' => 'سئو پایه',
                'description' => 'بهینه‌سازی فنی و محتوایی برای کسب‌وکارهای کوچک',
                'keyword_limit' => 15,
                'sort_order' => 10,
            ],
            [
                'code' => 'seo-standard',
                'title' => 'سئو استاندارد',
                'description' => 'پوشش کامل فنی، محتوا و لینک‌سازی',
                'keyword_limit' => 40,
                'sort_order' => 20,
            ],
            [
                'code' => 'seo-premium',
                'title' => 'سئو حرفه‌ای',
                'description' => 'پوشش گسترده با تمرکز بر رقابت‌های بالا',
                'keyword_limit' => 100,
                'sort_order' => 30,
            ],
        ];

        foreach ($packages as $package) {
            SeoPackage::updateOrCreate(
                ['code' => $package['code']],
                $package + ['is_active' => true]
            );
        }

        $this->command?->info('Project dictionaries: ' . count($types) . ' types, '
            . count($packages) . ' SEO packages synced.');
    }
}
