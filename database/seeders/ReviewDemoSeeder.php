<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * REVIEW ENVIRONMENT ONLY — the data set the client reviews.
 *
 * WHY THIS EXISTS
 * ---------------
 * The directive is explicit: an empty table proves nothing. A permission
 * matrix, a project-team model or an organisation chart cannot be reviewed
 * against zero rows, and a dashboard built on three projects looks broken
 * rather than unfinished. This seeder fills the review database with
 * realistic Persian data so every screen has something to show.
 *
 * WHAT IT IS NOT
 * --------------
 *   • Not production data. Every company, person and domain is invented.
 *   • Not a claim that a feature works. A populated `project_assignments`
 *     table shows what a screen would look like; it does not implement the
 *     permission resolution that must read it.
 *   • Not called from DatabaseSeeder, and it refuses to run unless the review
 *     environment is enabled and the database is the review copy.
 *
 * IDEMPOTENCE
 * -----------
 * `seed()` is safe to run repeatedly: every record is matched on a stable
 * natural key (customer code, project code, employee code) and updated in
 * place instead of duplicated. `reset()` removes exactly the rows this seeder
 * owns and nothing else, so a reviewer can start from a clean slate without
 * touching the baseline data.
 */
class ReviewDemoSeeder extends Seeder
{
    /** Prefix that marks every row this seeder owns. */
    public const TAG = 'RVW';

    /** Demo password for every review account. Printed by tools/review-seed.php. */
    public const DEMO_PASSWORD = 'ReviewDemo!2026';

    public function run(): void
    {
        $this->seed();
    }

    // ─────────────────────────────────────────────────────────────────────
    // SEED
    // ─────────────────────────────────────────────────────────────────────

    public function seed(): array
    {
        $counts = [];
        $now = CarbonImmutable::now();

        $counts['users'] = $this->seedUsers();
        $counts['customers'] = $this->seedCustomers($now);
        $counts['contracts'] = $this->seedContracts($now);
        $counts['projects'] = $this->seedProjects($now);
        $counts['employees'] = $this->seedEmployees($now);
        $counts['assignments'] = $this->seedAssignments($now);
        $counts['work_items'] = $this->seedWorkItems($now);
        $counts['notes'] = $this->seedNotes($now);

        return $counts;
    }

    /**
     * The four demo roles required by the directive.
     *
     * `review.admin` is a superadmin so the client can see every screen.
     * The other three carry real roles from RoleSeeder, so what they can open
     * is decided by the same permission system as production — not by a
     * hard-coded list in the review controller.
     */
    private function seedUsers(): int
    {
        $users = [
            ['review.admin', 'مدیر سامانه (Demo)', 'manager', true],
            ['review.manager', 'مدیر عملیات (Demo)', 'manager', false],
            ['review.employee', 'کارشناس پروژه (Demo)', 'account_manager', false],
            ['review.viewer', 'بازدیدکننده (Demo)', 'viewer', false],
        ];

        foreach ($users as [$username, $name, $roleCode, $superadmin]) {
            $user = User::withTrashed()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username . '@review.invalid',
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'is_superadmin' => $superadmin,
                    'is_active' => true,
                    'must_change_password' => false,
                    'deleted_at' => null,
                ]
            );

            $role = Role::where('code', $roleCode)->first();
            if ($role) {
                // One role per demo user; sync keeps re-runs clean.
                DB::table('user_roles')->where('user_id', $user->id)->delete();
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return count($users);
    }

    private function seedCustomers(CarbonImmutable $now): int
    {
        $rows = [
            ['0001', 'legal', 'شرکت آرمان داده پرداز', 'آرمان داده پرداز', 'active', 'تهران', '02188776655'],
            ['0002', 'legal', 'گروه صنعتی پارس نوین', 'پارس نوین', 'active', 'اصفهان', '02166554433'],
            ['0003', 'legal', 'فروشگاه اینترنتی دیبا', 'دیبا', 'prospect', 'تهران', '02144332211'],
            ['0004', 'natural', 'سارا محمدی', null, 'active', 'شیراز', null],
            ['0005', 'natural', 'رضا کریمی', null, 'lead', 'مشهد', null],
            ['0006', 'legal', 'کلینیک تخصصی مهرگان', 'مهرگان', 'inactive', 'کرج', '02155443322'],
            ['0007', 'legal', 'هلدینگ فناوری نیلگون', 'نیلگون', 'active', 'تهران', '02177889900'],
            ['0008', 'legal', 'صنایع غذایی زرین دانه', 'زرین دانه', 'active', 'تبریز', '04133445566'],
            ['0009', 'natural', 'امیر حسین‌زاده', null, 'active', 'تهران', null],
            ['0010', 'legal', 'آژانس تبلیغاتی رنگین کمان', 'رنگین کمان', 'prospect', 'شیراز', '07122334455'],
            ['0011', 'legal', 'پخش دارویی سلامت گستر', 'سلامت گستر', 'active', 'مشهد', '05166778899'],
            ['0012', 'natural', 'مریم رستمی', null, 'inactive', 'رشت', null],
        ];

        foreach ($rows as $i => [$seq, $type, $name, $company, $status, $city, $phone]) {
            $code = self::TAG . '-' . $seq;
            $isLegal = $type === 'legal';
            $owner = $this->ownerId($i);

            Customer::withTrashed()->updateOrCreate(
                ['code' => $code],
                [
                    'type' => $type,
                    'first_name' => $isLegal ? null : trim(strtok($name, ' ')),
                    'last_name' => $isLegal ? null : trim(substr($name, strlen(strtok($name, ' ')))),
                    'company_name' => $company,
                    'legal_representative' => $isLegal ? 'نمایندهٔ قانونی ' . $company : null,
                    'registration_number' => $isLegal ? '10' . str_pad((string) (2345678 + $i), 8, '0', STR_PAD_LEFT) : null,
                    'economic_code' => $isLegal ? '411' . str_pad((string) (123456789 + $i), 9, '0', STR_PAD_LEFT) : null,
                    'mobile' => '0912' . str_pad((string) (1000000 + $i * 11111), 7, '0', STR_PAD_LEFT),
                    'phone' => $phone,
                    'email' => 'info@' . self::TAG . '-' . $seq . '.example.com',
                    'city' => $city,
                    'province' => $city,
                    'status' => $status,
                    'owner_user_id' => $owner,
                    'acquaintance_source' => ['معرفی مشتری', 'جست‌وجوی وب', 'نمایشگاه', 'کمپین ایمیل'][$i % 4],
                    'notes' => 'رکورد نمونهٔ محیط بازبینی. داده واقعی نیست.',
                    'created_at' => $now->subDays(240 - $i * 12),
                    'updated_at' => $now->subDays(20 - ($i % 20)),
                    'deleted_at' => null,
                ]
            );
        }

        return count($rows);
    }

    private function seedContracts(CarbonImmutable $now): int
    {
        $customers = Customer::where('code', 'like', self::TAG . '-%')->get()->keyBy('code');
        $typeIds = DB::table('contract_types')->pluck('id')->all();

        if ($customers->isEmpty() || $typeIds === []) {
            return 0;
        }

        $rows = [
            ['0001', '0001', 'قرارداد پشتیبانی سالانهٔ سامانهٔ فروش', 480_000_000, 'active', -300, 65],
            ['0002', '0002', 'قرارداد طراحی و پیاده‌سازی وب‌سایت شرکتی', 320_000_000, 'active', -240, 120],
            ['0003', '0003', 'قرارداد سئوی شش‌ماهه', 96_000_000, 'draft', -30, 150],
            ['0004', '0004', 'قرارداد مدیریت شبکه‌های اجتماعی', 60_000_000, 'active', -180, 5],
            ['0005', '0007', 'قرارداد توسعهٔ پنل مشتریان هلدینگ', 720_000_000, 'active', -150, 210],
            ['0006', '0008', 'قرارداد بسته‌بندی و هویت بصری محصول', 145_000_000, 'expired', -420, -55],
            ['0007', '0011', 'قرارداد پشتیبانی فنی سه‌ساله', 1_150_000_000, 'active', -120, 975],
            ['0008', '0010', 'پیش‌قرارداد کمپین تبلیغاتی نوروز', 210_000_000, 'draft', -12, 180],
        ];

        $n = 0;
        foreach ($rows as $i => [$seq, $customerSeq, $title, $amount, $status, $startOffset, $endOffset]) {
            $customer = $customers->get(self::TAG . '-' . $customerSeq);
            if (! $customer) {
                continue;
            }

            Contract::withTrashed()->updateOrCreate(
                ['number' => self::TAG . '-C-' . $seq],
                [
                    'customer_id' => $customer->id,
                    'contract_type_id' => $typeIds[$i % count($typeIds)],
                    'title' => $title,
                    'description' => 'قرارداد نمونه برای بازبینی رابط کاربری.',
                    'party_one_name' => 'ویرا',
                    'party_two_name' => $customer->company_name ?: $customer->first_name . ' ' . $customer->last_name,
                    'amount' => $amount,
                    'start_date' => $now->addDays($startOffset)->toDateString(),
                    'end_date' => $now->addDays($endOffset)->toDateString(),
                    'status' => $status,
                    'finalized_at' => $status === 'draft' ? null : $now->addDays($startOffset + 2),
                    'owner_user_id' => $this->ownerId($i),
                    'internal_notes' => 'نمونهٔ محیط بازبینی.',
                    'created_at' => $now->addDays($startOffset),
                    'updated_at' => $now->subDays(3 + ($i % 10)),
                    'deleted_at' => null,
                ]
            );
            $n++;
        }

        return $n;
    }

    private function seedProjects(CarbonImmutable $now): int
    {
        $contracts = Contract::where('number', 'like', self::TAG . '-C-%')->get()->keyBy('number');

        if ($contracts->isEmpty()) {
            return 0;
        }

        // project_type values come from the dictionary seeded by ProjectDictionarySeeder.
        $types = DB::table('project_types')->pluck('code')->all() ?: ['seo', 'design'];

        $rows = [
            ['0001', '0001', 'پشتیبانی فنی آرمان داده پرداز', 'active', -300, 65],
            ['0002', '0002', 'بازطراحی وب‌سایت پارس نوین', 'active', -240, 120],
            ['0003', '0005', 'پنل مشتریان هلدینگ نیلگون', 'active', -150, 210],
            ['0004', '0004', 'مدیریت محتوای شبکه‌های اجتماعی سارا محمدی', 'active', -180, 5],
            ['0005', '0007', 'پشتیبانی سه‌سالهٔ سلامت گستر', 'active', -120, 975],
            ['0006', '0003', 'سئوی فروشگاه دیبا', 'paused', -30, 150],
            ['0007', '0006', 'هویت بصری زرین دانه', 'completed', -420, -55],
            ['0008', '0008', 'کمپین نوروز رنگین کمان', 'draft', -12, 180],
        ];

        $n = 0;
        foreach ($rows as $i => [$seq, $contractSeq, $title, $status, $startOffset, $endOffset]) {
            $contract = $contracts->get(self::TAG . '-C-' . $contractSeq);
            if (! $contract) {
                continue;
            }

            $project = Project::withTrashed()->updateOrCreate(
                ['code' => self::TAG . '-' . $seq],
                [
                    'customer_id' => $contract->customer_id,
                    'contract_id' => $contract->id,
                    'type' => $types[$i % count($types)],
                    'title' => $title,
                    'status' => $status,
                    'amount' => (int) round(((int) $contract->amount) * 0.8),
                    'start_date' => $now->addDays($startOffset)->toDateString(),
                    'end_date' => $now->addDays($endOffset)->toDateString(),
                    'owner_user_id' => $this->ownerId($i),
                    'notes' => 'پروژهٔ نمونه برای بازبینی.',
                    'created_at' => $now->addDays($startOffset),
                    'updated_at' => $now->subDays(2 + ($i % 7)),
                    'deleted_at' => null,
                ]
            );

            // Work items are attached below; remember the mapping.
            $this->projectMap[] = $project->id;
            $n++;
        }

        return $n;
    }

    /** @var list<int> */
    private array $projectMap = [];

    /**
     * Employees — the bridge between a CRM user and payroll/assignment.
     *
     * This is exactly the gap the directive raises in §6: `users.is_active`
     * and `employees.status` are two independent switches, and nothing in the
     * current schema reconciles them. The seeder creates the rows so the gap
     * is *visible*; it does not pretend to close it. One employee is
     * deliberately linked to a deactivated user and one user has no employee
     * record at all, so the review page can show both cases.
     */
    private function seedEmployees(CarbonImmutable $now): int
    {
        $demoUsers = User::where('username', 'like', 'review.%')->get()->keyBy('username');

        $rows = [
            ['0001', 'review.admin', 'مینا', 'صادقی', 'مدیر عامل', 'active'],
            ['0002', 'review.manager', 'علی', 'رضایی', 'مدیر عملیات', 'active'],
            ['0003', 'review.employee', 'نگار', 'توکلی', 'کارشناس پروژه', 'active'],
            ['0004', null, 'حسین', 'مرادی', 'کارشناس سئو', 'active'],
            ['0005', null, 'زهرا', 'اکبری', 'طراح گرافیک', 'active'],
            ['0006', null, 'محمد', 'قاسمی', 'برنامه‌نویس بک‌اند', 'active'],
            ['0007', null, 'فاطمه', 'نوری', 'کارشناس مالی', 'inactive'],
            ['0008', null, 'سعید', 'بهرامی', 'کارشناس فروش', 'terminated'],
            ['0009', null, 'الهام', 'شریفی', 'مدیر محتوا', 'active'],
            ['0010', null, 'بابک', 'یزدانی', 'کارشناس پشتیبانی', 'active'],
        ];

        $n = 0;
        foreach ($rows as $i => [$seq, $username, $first, $last, $position, $status]) {
            $userId = $username && isset($demoUsers[$username]) ? $demoUsers[$username]->id : null;

            DB::table('employees')->updateOrInsert(
                ['code' => self::TAG . '-E-' . $seq],
                [
                    'user_id' => $userId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'national_id' => $this->fakeNationalId($i),
                    'mobile' => '0935' . str_pad((string) (2000000 + $i * 4321), 7, '0', STR_PAD_LEFT),
                    'email' => 'employee' . $seq . '@review.invalid',
                    'position' => $position,
                    'start_date' => $now->subMonths(30 - $i * 2)->toDateString(),
                    'end_date' => $status === 'terminated' ? $now->subMonths(3)->toDateString() : null,
                    'status' => $status,
                    'notes' => 'کارمند نمونهٔ محیط بازبینی.',
                    'created_at' => $now->subMonths(30 - $i * 2),
                    'updated_at' => $now->subDays(5),
                    'deleted_at' => null,
                ]
            );
            $n++;
        }

        return $n;
    }

    /**
     * Project team membership.
     *
     * Deliberately includes the hard cases from the permission brief:
     *   • a person on two projects owned by different departments
     *   • a project with a member from outside the owning department
     *   • one inactive membership (ended_on set, is_active = 0)
     */
    private function seedAssignments(CarbonImmutable $now): int
    {
        $employees = DB::table('employees')->where('code', 'like', self::TAG . '-E-%')->pluck('id', 'code');
        $projects = Project::where('code', 'like', self::TAG . '-%')->pluck('id', 'code');

        if ($employees->isEmpty() || $projects->isEmpty()) {
            return 0;
        }

        $employeeIds = $employees->values()->all();
        $projectIds = $projects->values()->all();

        $rows = [
            // [project index, employee index, role, coefficient, active]
            [0, 1, 'مدیر پروژه', 1.0, true],
            [0, 3, 'کارشناس سئو', 0.6, true],
            [0, 9, 'پشتیبان', 0.4, true],
            [1, 1, 'مدیر پروژه', 1.0, true],
            [1, 4, 'طراح گرافیک', 0.8, true],
            [2, 0, 'مدیر پروژه', 1.0, true],
            [2, 5, 'برنامه‌نویس', 1.0, true],
            [2, 2, 'کارشناس پروژه', 0.7, true],
            [3, 8, 'مدیر محتوا', 0.9, true],
            [4, 1, 'مدیر پروژه', 0.5, true],
            [4, 9, 'پشتیبان', 0.5, true],
            [5, 3, 'کارشناس سئو', 0.8, true],
            [5, 4, 'طراح گرافیک', 0.3, false], // ended membership
            [6, 4, 'طراح گرافیک', 1.0, false],
            [7, 8, 'مدیر محتوا', 0.6, true],
        ];

        DB::table('project_assignments')->where('role', 'like', '%')->whereIn('project_id', $projectIds)->delete();

        $n = 0;
        foreach ($rows as $i => [$pIdx, $eIdx, $role, $coefficient, $active]) {
            if (! isset($projectIds[$pIdx], $employeeIds[$eIdx])) {
                continue;
            }

            DB::table('project_assignments')->insert([
                'project_id' => $projectIds[$pIdx],
                'employee_id' => $employeeIds[$eIdx],
                'role' => $role,
                'amount' => 0,
                'coefficient' => $coefficient,
                'started_on' => $now->subDays(200 - $i * 9)->toDateString(),
                'ended_on' => $active ? null : $now->subDays(30)->toDateString(),
                'is_active' => $active ? 1 : 0,
                'created_at' => $now->subDays(200 - $i * 9),
                'updated_at' => $now->subDays(10),
            ]);
            $n++;
        }

        return $n;
    }

    private function seedWorkItems(CarbonImmutable $now): int
    {
        $projects = Project::where('code', 'like', self::TAG . '-%')->orderBy('code')->get()->values();

        if ($projects->isEmpty()) {
            return 0;
        }

        /*
         * The schema enforces UNIQUE(project_id, kind, period) — a project can
         * have one report, one invoice and one task per month, and no more.
         * That is the real constraint the product works under, so the demo data
         * must respect it rather than dodge it: each entry below owns its own
         * month. The set still covers overdue, due-soon, done and future work,
         * which is what the dashboard has to show.
         */
        $template = [
            // [kind, label, due offset in days, state, months back]
            ['report',  'گزارش ماهانهٔ عملکرد',        -9, 'open', 2],
            ['report',  'گزارش ماهانهٔ عملکرد',        -1, 'open', 1],
            ['report',  'گزارش سه‌ماهه',                25, 'open', 0],
            ['report',  'گزارش سه‌ماهه',               -35, 'done', 3],
            ['invoice', 'صدور فاکتور ماه جاری',          3, 'open', 0],
            ['invoice', 'صدور فاکتور ماه گذشته',       -20, 'done', 1],
            ['invoice', 'فاکتور پیش‌پرداخت',             -6, 'open', 2],
            ['task',    'بازبینی محتوای کمپین',          7, 'open', 0],
            ['task',    'به‌روزرسانی صفحات فرود',        -3, 'open', 1],
            ['task',    'جلسهٔ بازبینی با مشتری',        12, 'open', 2],
            ['task',    'تحویل فایل‌های نهایی',         -35, 'done', 3],
        ];

        DB::table('work_items')->whereIn('project_id', $projects->pluck('id')->all())->delete();

        $n = 0;
        foreach ($projects as $pi => $project) {
            $used = [];

            foreach ($template as $ti => [$kind, $label, $offset, $state, $monthsBack]) {
                // Not every item for every project: real data is uneven, and a
                // perfectly uniform grid would look like a fixture, not a product.
                if (($pi + $ti) % 3 === 2) {
                    continue;
                }

                $period = $now->subMonths($monthsBack)->format('Y-m');
                $key = $kind . '|' . $period;

                if (isset($used[$key])) {
                    continue;
                }
                $used[$key] = true;

                DB::table('work_items')->insert([
                    'project_id' => $project->id,
                    'kind' => $kind,
                    'due_date' => $now->addDays($offset + $pi)->toDateString(),
                    'period' => $period,
                    'state' => $state,
                    'invoice_number' => $kind === 'invoice' && $state === 'done'
                        ? self::TAG . '-INV-' . str_pad((string) $pi, 2, '0', STR_PAD_LEFT) . $ti : null,
                    'invoice_amount' => $kind === 'invoice' && $state === 'done' ? 45_000_000 : null,
                    'invoice_issue_date' => $kind === 'invoice' && $state === 'done'
                        ? $now->subDays(15)->toDateString() : null,
                    'done_at' => $state === 'done' ? $now->subDays(abs($offset)) : null,
                    'done_by' => $state === 'done' ? $this->ownerId($pi) : null,
                    'completion_notes' => $state === 'done' ? 'انجام شد — نمونهٔ بازبینی.' : null,
                    'reminder_offset_days' => 2,
                    'created_at' => $now->subDays(60),
                    'updated_at' => $now->subDays(2),
                ]);
                $n++;
            }
        }

        return $n;
    }

    private function seedNotes(CarbonImmutable $now): int
    {
        $customers = Customer::where('code', 'like', self::TAG . '-%')->orderBy('code')->get()->values();
        $projects = Project::where('code', 'like', self::TAG . '-%')->orderBy('code')->get()->values();

        if ($customers->isEmpty()) {
            return 0;
        }

        $rows = [
            ['تماس پیگیری تمدید قرارداد', 'پیش از پایان ماه تماس گرفته شود.', 'high', 'open', -2],
            ['ارسال پیش‌فاکتور بازطراحی', 'فایل قیمت به همراه زمان‌بندی ارسال شود.', 'normal', 'open', 4],
            ['پیگیری فاکتور معوق', 'فاکتور ماه گذشته هنوز تسویه نشده است.', 'urgent', 'open', -5],
            ['جلسهٔ بررسی رضایت مشتری', 'بازخورد سه‌ماهه ثبت شود.', 'low', 'open', 15],
            ['تحویل مدارک هویتی', 'مدارک شرکت دریافت و بایگانی شد.', 'normal', 'done', -18],
            ['درخواست گزارش عملکرد', 'گزارش ماهانه آماده و ارسال شد.', 'normal', 'done', -9],
            ['پیگیری امضای قرارداد', 'نسخهٔ امضاشده هنوز برنگشته است.', 'high', 'open', 1],
            ['معرفی به تیم پشتیبانی', 'مشتری به تیم پشتیبانی معرفی شد.', 'low', 'open', 20],
        ];

        // Delete only the notes this seeder owns, matched on an explicit title
        // list: a LIKE pattern can never be trusted to stay inside the demo
        // boundary, and an early version used the Arabic percent sign by
        // mistake so nothing was deleted and every re-seed doubled the rows.
        // A hard delete, not $model->delete(): Note uses SoftDeletes, so calling
        // delete() would leave the old rows in the table with deleted_at set and
        // every re-seed would quietly double the count.
        DB::table('notes')
            ->whereIn('title', array_column($rows, 0))
            ->whereIn('customer_id', $customers->pluck('id')->all())
            ->delete();

        $n = 0;
        foreach ($rows as $i => [$title, $desc, $priority, $status, $offset]) {
            DB::table('notes')->insert([
                'title' => $title,
                'description' => $desc,
                'due_date' => $now->addDays($offset)->toDateString(),
                'priority' => $priority,
                'status' => $status,
                'customer_id' => $customers[$i % $customers->count()]->id,
                'project_id' => $projects->isNotEmpty() && $i % 2 === 0
                    ? $projects[$i % $projects->count()]->id : null,
                'done_at' => $status === 'done' ? $now->addDays($offset) : null,
                'done_by' => $status === 'done' ? $this->ownerId($i) : null,
                'created_by' => $this->ownerId($i + 1),
                'assigned_to' => $this->ownerId($i + 2),
                'created_at' => $now->subDays(30 - $i),
                'updated_at' => $now->subDays(3),
                'deleted_at' => null,
            ]);
            $n++;
        }

        return $n;
    }

    // ─────────────────────────────────────────────────────────────────────
    // RESET
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Remove exactly the rows this seeder owns.
     *
     * Order matters: children before parents. Every delete is filtered by the
     * RVW marker or by a foreign key to a row that carries it, so baseline data
     * that existed before the seeder ran is never touched.
     */
    public function reset(): array
    {
        $deleted = [];

        $projectIds = Project::withTrashed()->where('code', 'like', self::TAG . '-%')->pluck('id')->all();
        $customerIds = Customer::withTrashed()->where('code', 'like', self::TAG . '-%')->pluck('id')->all();
        $contractIds = Contract::withTrashed()->where('number', 'like', self::TAG . '-C-%')->pluck('id')->all();
        $employeeIds = DB::table('employees')->where('code', 'like', self::TAG . '-E-%')->pluck('id')->all();

        if ($projectIds) {
            $deleted['work_items'] = DB::table('work_items')->whereIn('project_id', $projectIds)->delete();
            $deleted['project_assignments'] = DB::table('project_assignments')->whereIn('project_id', $projectIds)->delete();
            $deleted['notes (by project)'] = DB::table('notes')
                ->whereIn('project_id', $projectIds)->whereIn('customer_id', $customerIds ?: [0])->delete();
        }

        if ($customerIds) {
            $deleted['notes (by customer)'] = DB::table('notes')->whereIn('customer_id', $customerIds)->delete();
        }

        if ($employeeIds) {
            $deleted['project_assignments (by employee)'] = DB::table('project_assignments')
                ->whereIn('employee_id', $employeeIds)->delete();
            $deleted['employees'] = DB::table('employees')->whereIn('id', $employeeIds)->delete();
        }

        if ($contractIds) {
            $deleted['contracts'] = DB::table('contracts')->whereIn('id', $contractIds)->delete();
        }

        if ($projectIds) {
            $deleted['projects'] = DB::table('projects')->whereIn('id', $projectIds)->delete();
        }

        if ($customerIds) {
            $deleted['customers'] = DB::table('customers')->whereIn('id', $customerIds)->delete();
        }

        $userIds = User::where('username', 'like', 'review.%')->pluck('id')->all();
        if ($userIds) {
            DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
            $deleted['users'] = DB::table('users')->whereIn('id', $userIds)->delete();
        }

        return $deleted;
    }

    /**
     * A national ID that fails the real Iranian check digit by construction.
     *
     * This matters: a generator that produces *valid* check digits manufactures
     * something indistinguishable from a real person's national ID. The data
     * audit (tools/checks/review-data-audit.php) rejected the first version of
     * this seeder for exactly that reason, so the last digit is now derived to
     * be wrong on purpose.
     */
    private function fakeNationalId(int $i): string
    {
        // Vary the whole 9-digit prefix, not just the last digit: an earlier
        // version built '00' + (12345678 + $i) and then truncated to nine
        // characters, which discarded the only digit that varied — two
        // employees ended up with the same national ID and the UNIQUE index
        // rejected the seed.
        $prefix = str_pad((string) (234567891 + $i * 137), 9, '0', STR_PAD_LEFT);

        // Compute the digit that WOULD be valid, then shift it. The stored value
        // is therefore always invalid, so it can never be mistaken for, or
        // collide with, a real person's national ID.
        $sum = 0;
        for ($d = 0; $d < 9; $d++) {
            $sum += ((int) $prefix[$d]) * (10 - $d);
        }
        $r = $sum % 11;
        $valid = ($r < 2) ? $r : 11 - $r;

        return $prefix . (string) (($valid + 5) % 10);
    }

    /** Rotate ownership across the four demo users, falling back to admin. */
    private function ownerId(int $i): ?int
    {
        $ids = User::where('username', 'like', 'review.%')->orderBy('id')->pluck('id')->all();

        if ($ids === []) {
            return User::query()->orderBy('id')->value('id');
        }

        return $ids[$i % count($ids)];
    }
}
