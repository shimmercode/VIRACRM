<?php

namespace Database\Seeders;

use App\Models\ContractType;
use Illuminate\Database\Seeder;

/**
 * REQ-15.01 — contract types.
 *
 * The client document §4 says contract categories are configurable. These four
 * are the minimum implied by the service areas the document describes (SEO,
 * design, and combined retainers). The owner can add or rename them; they are
 * data, not a hard-coded enum.
 */
class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'seo', 'title' => 'سئو', 'sort_order' => 10,
             'description' => 'قرارداد خدمات بهینه‌سازی موتور جست‌وجو'],
            ['code' => 'design', 'title' => 'طراحی', 'sort_order' => 20,
             'description' => 'قرارداد طراحی وب‌سایت، رابط کاربری یا هویت بصری'],
            ['code' => 'combined', 'title' => 'سئو و طراحی', 'sort_order' => 30,
             'description' => 'قرارداد ترکیبی شامل سئو و طراحی'],
            ['code' => 'maintenance', 'title' => 'پشتیبانی', 'sort_order' => 40,
             'description' => 'قرارداد پشتیبانی و نگهداری ماهانه'],
        ];

        foreach ($types as $type) {
            ContractType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true]
            );
        }

        $this->command?->info('Contract types: ' . count($types) . ' synced.');
    }
}
