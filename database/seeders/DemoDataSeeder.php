<?php

namespace Database\Seeders;

use App\Enums\CustomerType;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Project;
use App\Models\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT ONLY — demo records for visual review.
 *
 * NEVER run on a real installation. This seeder is NOT called from
 * DatabaseSeeder; it only runs when tools/seed.php is invoked with --demo.
 *
 * The data is deliberately realistic but obviously fictional (sample company
 * names, example.com domains) so nobody can mistake it for real client data —
 * the phase brief forbids presenting sample data as a real product.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['legal', 'شرکت آرمان داده پرداز', 'active', '02188776655', '09121234567', 'arman.example.com', 'تهران'],
            ['legal', 'گروه صنعتی پارس نوین', 'active', '02166554433', '09127654321', 'parsnovin.example.com', 'اصفهان'],
            ['legal', 'فروشگاه اینترنتی دیبا', 'prospect', '02144332211', '09351112233', 'diba.example.com', 'تهران'],
            ['natural', 'سارا محمدی', 'active', null, '09122223344', 'saramd.example.com', 'شیراز'],
            ['natural', 'رضا کریمی', 'lead', null, '09133334455', 'rezak.example.com', 'مشهد'],
            ['legal', 'کلینیک تخصصی مهرگان', 'inactive', '02155443322', '09194445566', 'mehregan.example.com', 'کرج'],
        ];

        $created = [];

        foreach ($customers as [$type, $name, $status, $phone, $mobile, $domain, $city]) {
            $attributes = [
                'type' => $type,
                'status' => $status,
                'phone' => $phone,
                'mobile' => $mobile,
                'city' => $city,
                'email' => 'info@' . $domain,
                'acquaintance_source' => 'معرفی مشتریان قبلی',
            ];

            if ($type === 'legal') {
                $attributes['company_name'] = $name;
                $attributes['registration_number'] = (string) random_int(100000, 999999);
            } else {
                [$first, $last] = explode(' ', $name);
                $attributes['first_name'] = $first;
                $attributes['last_name'] = $last;
            }

            $customer = Customer::query()->firstOrCreate(
                $type === 'legal' ? ['company_name' => $name] : ['first_name' => $attributes['first_name'], 'last_name' => $attributes['last_name']],
                $attributes
            );

            if ($customer->websites()->count() === 0) {
                $customer->websites()->create([
                    'url' => 'https://' . $domain,
                    'domain' => $domain,
                    'label' => 'سایت اصلی',
                    'is_primary' => true,
                ]);
            }

            $created[] = $customer;
        }

        $today = CarbonImmutable::now()->startOfDay();

        // Contracts spread across the status range, including one that is
        // inside the expiry window so the warning UI has something real to show.
        $contracts = [
            [0, 'قرارداد سئو و بهینه‌سازی سالانه', 240_000_000, -300, 12, 'active'],
            [1, 'قرارداد طراحی وب‌سایت شرکتی', 480_000_000, -200, 8, 'active'],
            [2, 'قرارداد راه‌اندازی فروشگاه اینترنتی', 850_000_000, -60, 160, 'draft'],
            [3, 'قرارداد پشتیبانی ماهانه سئو', 96_000_000, -120, 20, 'active'],
            [0, 'قرارداد گزارش‌های ماهانه', 120_000_000, -400, -30, 'expired'],
        ];

        foreach ($contracts as $i => [$customerIndex, $title, $amount, $startOffset, $endOffset, $status]) {
            $customer = $created[$customerIndex] ?? null;
            if ($customer === null) {
                continue;
            }

            Contract::query()->firstOrCreate(
                ['title' => $title],
                [
                    'customer_id' => $customer->id,
                    'title' => $title,
                    'amount' => $amount,
                    'start_date' => $today->addDays($startOffset)->toDateString(),
                    'end_date' => $today->addDays($endOffset)->toDateString(),
                    'status' => $status,
                    'number' => 'VIRA-D-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'description' => 'این قرارداد نمونه برای بررسی رابط کاربری ثبت شده است.',
                ]
            );
        }

        // A couple of projects with open work items, so the dashboard کارتابل
        // renders real rows in each bucket.
        $projectSpecs = [
            [0, 'seo', 'پروژهٔ سئو آرمان داده', 'active', -90, 270],
            [1, 'design', 'پروژهٔ طراحی پارس نوین', 'active', -40, 140],
            [3, 'seo', 'پروژهٔ سئو سارا محمدی', 'active', -20, 300],
        ];

        foreach ($projectSpecs as [$customerIndex, $type, $title, $status, $startOffset, $endOffset]) {
            $customer = $created[$customerIndex] ?? null;
            if ($customer === null) {
                continue;
            }

            $project = Project::query()->firstOrCreate(
                ['title' => $title],
                [
                    'customer_id' => $customer->id,
                    'type' => $type,
                    'title' => $title,
                    'status' => $status,
                    'amount' => 150_000_000,
                    'start_date' => $today->addDays($startOffset)->toDateString(),
                    'end_date' => $today->addDays($endOffset)->toDateString(),
                ]
            );

            // One overdue, one due today, one upcoming: all three buckets fill.
            $offsets = [-6, 0, 9];

            foreach ($offsets as $k => $offset) {
                $due = $today->addDays($offset);

                // `period` is a JALALI year-month ("1405-02"), per the schema
                // comment and the client's billing calendar (§11). Formatting it
                // with Carbon's Y-m would store Gregorian and then be rendered
                // with Persian month names — producing "بهمن ۲۰۲۶" for a
                // November date. The conversion belongs here, once.
                $period = \App\Support\JalaliDate::fromCarbon($due)->formatAscii();
                $period = substr($period, 0, 7); // YYYY/MM

                WorkItem::query()->firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'kind' => $k === 1 ? 'report' : 'invoice',
                        'period' => $period,
                    ],
                    [
                        'project_id' => $project->id,
                        'kind' => $k === 1 ? 'report' : 'invoice',
                        'due_date' => $due->toDateString(),
                        'period' => $period,
                        'state' => 'pending',
                    ]
                );
            }
        }

        $this->command?->info(
            'Demo data: ' . Customer::query()->count() . ' customers, '
            . Contract::query()->count() . ' contracts, '
            . Project::query()->count() . ' projects, '
            . WorkItem::query()->count() . ' work items.'
        );
    }
}
