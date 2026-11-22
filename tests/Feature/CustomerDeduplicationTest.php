<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\CustomerWebsite;
use Tests\ViraTestCase;

/**
 * REQ-03.05 / REQ-03.06 / REQ-03.08 — duplicate prevention and search.
 *
 * §1 of the client document says the whole point of the CRM is to stop entering
 * the same customer twice. §3 says search must work on name, phone, national ID
 * and domain. Both depend on normalisation being right, and normalisation is
 * exactly the kind of code that looks correct and fails on real Persian data.
 */
final class CustomerDeduplicationTest extends ViraTestCase
{
    private \Illuminate\Database\Connection $db;

    private \Tests\Support\DatabaseHarness $harness;

    protected function setUp(): void
    {
        $this->freshDatabase();
    }

    /**
     * Each scenario gets its own database.
     *
     * Not pedantry: without this, a scenario that inserts a customer with a
     * given phone makes a *later* scenario see two matches instead of one, and
     * the failure looks like a bug in the deduplication code when it is really
     * leaked state. Tests that share a database silently become order-dependent.
     */
    private function freshDatabase(): void
    {
        $this->harness = \Tests\Support\DatabaseHarness::boot('cust')->migrate();
        $this->db = $this->harness->connection;
    }

    public function run(): void
    {
        $this->mobileNormalisation();
        $this->persianNormalisation();
        $this->searchKeyIsMaintained();
        $this->findsDuplicateByMobile();
        $this->findsDuplicateByNameVariant();
        $this->findsDuplicateByDomain();
        $this->findsDuplicateByNationalId();
        $this->doesNotFlagDistinctCustomers();
        $this->searchFindsByEveryDocumentedField();
    }

    private function mobileNormalisation(): void
    {
        foreach ([
            '09121234567',
            '۰۹۱۲۱۲۳۴۵۶۷',
            '0912 123 4567',
            '0912-123-4567',
            '+989121234567',
            '00989121234567',
            ' 0912.123.4567 ',
        ] as $input) {
            $this->assertSame(
                '09121234567',
                Customer::normalizeMobile($input),
                "normalising '{$input}'"
            );
        }

        $this->assertNull(Customer::normalizeMobile(''));
        $this->assertNull(Customer::normalizeMobile(null));
        $this->assertNull(Customer::normalizeMobile('هیچ عددی ندارد'));

        // A landline with an area code must not be mangled into a mobile.
        $this->assertSame('03132345678', Customer::normalizeMobile('031-3234-5678'));
    }

    private function persianNormalisation(): void
    {
        // Arabic yeh/kaf → Persian, ZWNJ → space, case folded.
        $this->assertSame(
            Customer::normalizePersianText('شرکت آریا'),
            Customer::normalizePersianText('شركت آريا'),
            'Arabic ك/ي must fold to Persian ک/ی'
        );

        $this->assertSame(
            Customer::normalizePersianText('نیم فاصله'),
            Customer::normalizePersianText("نیم\u{200c}فاصله"),
            'ZWNJ must not break matching'
        );

        $this->assertSame(
            Customer::normalizePersianText('گروه صنعتی البرز'),
            Customer::normalizePersianText('  گروه   صنعتی   البرز  '),
            'whitespace must collapse'
        );

        $this->assertSame('09121234567', Customer::normalizePersianText('۰۹۱۲۱۲۳۴۵۶۷'));
        $this->assertSame('eng co', Customer::normalizePersianText('ENG Co'));
    }

    private function searchKeyIsMaintained(): void
    {
        $this->freshDatabase();

        $customer = Customer::create([
            'type' => CustomerType::Legal,
            'company_name' => 'شركت بازرگاني آريا',
            'registration_number' => '۴۵۶۷۸۹',
            'mobile' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'status' => 'active',
        ]);

        $fresh = Customer::find($customer->id);

        $this->assertNotNull($fresh->search_key, 'search_key must be computed on save');
        $this->assertSame('09121234567', $fresh->normalized_mobile);
        $this->assertSame('456789', $fresh->normalized_national_id, 'registration number digits are normalised');
        $this->assertContains('شرکت بازرگانی', (string) $fresh->search_key, 'search key must contain folded company name');
        $this->assertSame('شرکت بازرگانی آریا', $fresh->display_name);
    }

    private function findsDuplicateByMobile(): void
    {
        $this->freshDatabase();

        Customer::create([
            'type' => CustomerType::Natural,
            'first_name' => 'رضا',
            'last_name' => 'محمدی',
            'mobile' => '09121234567',
            'status' => 'active',
        ]);

        // Deliberately a different name so the *mobile* signal is what fires.
        // (With an identical name both signals fire, which is correct but would
        // not prove the phone normalisation works.)
        $candidate = new Customer([
            'type' => CustomerType::Natural,
            'first_name' => 'نام',
            'last_name' => 'دیگر',
            'mobile' => '۰۹۱۲-۱۲۳-۴۵۶۷', // same number, different formatting
        ]);

        $candidates = Customer::duplicateCandidates($candidate);

        $this->assertCount(1, $candidates, 'the same mobile in Persian digits must be detected as a duplicate');
        $this->assertContains('شماره تماس یکسان', $this->reasonsText($candidates));
    }

    private function findsDuplicateByNameVariant(): void
    {
        $this->freshDatabase();

        Customer::create([
            'type' => CustomerType::Legal,
            'company_name' => 'شرکت بازرگانی آریا',
            'status' => 'active',
        ]);

        $candidate = new Customer([
            'type' => CustomerType::Legal,
            'company_name' => 'شركت بازرگاني آريا', // Arabic letter forms
        ]);

        $candidates = Customer::duplicateCandidates($candidate);

        $this->assertGreaterThan(0, count($candidates), 'Arabic letter variants must still match');
        $this->assertContains('نام مشابه', $this->reasonsText($candidates));
    }

    private function findsDuplicateByDomain(): void
    {
        $this->freshDatabase();

        $existing = Customer::create([
            'type' => CustomerType::Legal,
            'company_name' => 'شرکت دامنه‌دار',
            'status' => 'active',
        ]);

        CustomerWebsite::create([
            'customer_id' => $existing->id,
            'url' => 'https://www.example-shop.ir/',
            'is_primary' => true,
        ]);

        $this->assertSame(
            'example-shop.ir',
            CustomerWebsite::where('customer_id', $existing->id)->value('domain'),
            'domain must be extracted and normalised from the URL'
        );

        $candidate = new Customer(['type' => CustomerType::Legal, 'company_name' => 'نام دیگر']);
        $candidate->setRelation('websites', collect([
            new CustomerWebsite(['url' => 'http://example-shop.ir/products', 'domain' => 'example-shop.ir']),
        ]));

        $candidates = Customer::duplicateCandidates($candidate);
        $this->assertGreaterThan(0, count($candidates), 'a shared domain must surface as a duplicate candidate');
        $this->assertContains('دامنه یکسان', $this->reasonsText($candidates));
    }

    private function findsDuplicateByNationalId(): void
    {
        $this->freshDatabase();

        Customer::create([
            'type' => CustomerType::Natural,
            'first_name' => 'مریم',
            'last_name' => 'حسینی',
            'national_id' => '0012345678',
            'status' => 'active',
        ]);

        $candidate = new Customer([
            'type' => CustomerType::Natural,
            'first_name' => 'نام متفاوت',
            'last_name' => 'نام خانوادگی متفاوت',
            'national_id' => '۰۰۱۲۳۴۵۶۷۸',
        ]);

        $candidates = Customer::duplicateCandidates($candidate);
        $this->assertGreaterThan(0, count($candidates), 'identical national ID must be caught even when names differ');
    }

    private function doesNotFlagDistinctCustomers(): void
    {
        $this->freshDatabase();

        Customer::create([
            'type' => CustomerType::Natural,
            'first_name' => 'علی',
            'last_name' => 'کریمی',
            'mobile' => '09121110000',
            'national_id' => '1111111111',
            'status' => 'active',
        ]);

        $candidate = new Customer([
            'type' => CustomerType::Natural,
            'first_name' => 'سارا',
            'last_name' => 'نوری',
            'mobile' => '09122220000',
            'national_id' => '2222222222',
        ]);

        $candidates = Customer::duplicateCandidates($candidate);

        // A false positive here trains users to ignore the warning entirely.
        $this->assertCount(0, $candidates, 'clearly different customers must not be flagged');
    }

    private function searchFindsByEveryDocumentedField(): void
    {
        $this->freshDatabase();

        $legal = Customer::create([
            'type' => CustomerType::Legal,
            'company_name' => 'شرکت فناوری سپهر',
            'mobile' => '02188776655',
            'status' => 'active',
        ]);
        CustomerWebsite::create(['customer_id' => $legal->id, 'url' => 'https://sepehr-tech.ir']);

        $natural = Customer::create([
            'type' => CustomerType::Natural,
            'first_name' => 'نیما',
            'last_name' => 'صادقی',
            'national_id' => '9876543210',
            'status' => 'active',
        ]);

        // §3: by name
        $this->assertSame(1, Customer::search('سپهر')->toBase()->count(), 'search by company name');
        // §3: by phone
        $this->assertSame(1, Customer::search('88776655')->toBase()->count(), 'search by phone');
        // §3: by national ID
        $this->assertSame(1, Customer::search('9876543210')->toBase()->count(), 'search by national ID');
        // §3: by domain
        $this->assertSame(1, Customer::search('sepehr-tech.ir')->toBase()->count(), 'search by domain');
        // Arabic-variant name search
        $this->assertSame(1, Customer::search('فناوري')->toBase()->count(), 'search folds Arabic yeh');

        $this->assertSame(0, Customer::search('هیچ‌چیز')->toBase()->count(), 'nonsense search returns nothing');
        // A blank term must not silently return everything as a "match" in the
        // UI, but at the query level it must not filter either.
        $this->assertSame(
            Customer::query()->toBase()->count(),
            Customer::search('   ')->toBase()->count(),
            'a whitespace-only search must not filter the result set'
        );
    }

    /**
     * Join every reason from every candidate into one string for assertions.
     *
     * @param  list<array{customer: Customer, reasons: list<string>}>  $candidates
     */
    private function reasonsText(array $candidates): string
    {
        $all = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate['reasons'] as $reason) {
                $all[] = $reason;
            }
        }

        return implode(' | ', $all);
    }
}
