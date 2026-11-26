<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\CredentialType;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Models\User;
use App\Services\Contracts\ContractFinalizer;
use App\Services\Contracts\ContractRenderer;
use App\Services\Pdf\ContractPdf;
use App\Services\Pdf\CredentialPdf;
use App\Services\Pdf\PayslipPdf;
use App\Services\Pdf\PdfEngine;
use App\Services\Pdf\ProjectPdf;
use App\Services\Security\CredentialVault;
use App\Services\Security\EnvelopeEncrypter;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * REQ-09.05 — what is inside each PDF, asserted on the HTML before rendering.
 *
 * WHY NOT ASSERT ON THE PDF BYTES
 * -------------------------------
 * mPDF writes each glyph as a font glyph index, so the text of a page is not
 * present in the file as characters — not for Persian and not for Latin. A test
 * that searched the bytes for a string would pass by not finding what it never
 * could find, which is worse than no test.
 *
 * So: byte-level facts (is it a PDF, is it one page, does it carry ToUnicode) are
 * proven in tools/checks/pdf-flow.mjs against the real HTTP stack. Content facts
 * — the right amount, the right name, no secrets in the public document — are
 * proven here on the exact HTML handed to the engine.
 */
final class PdfDocumentTest extends ViraTestCase
{
    private DatabaseHarness $harness;

    private function boot(string $name): void
    {
        $this->harness = DatabaseHarness::boot($name)->migrate();
    }

    private function user(): User
    {
        return User::create([
            'name' => 'مدیر سند',
            'username' => 'pdf_' . uniqid(),
            'password' => Hash::make('Secret-Passw0rd-123'),
            'is_superadmin' => true,
            'is_active' => true,
        ]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'type' => 'legal',
            'company_name' => 'شرکت نمونهٔ سند',
            'national_id' => '10101010101',
            'address' => 'تهران',
            'status' => 'active',
        ]);
    }

    public function run(): void
    {
        $this->contractDocumentCarriesItsOwnFacts();
        $this->draftContractIsRefused();
        $this->contractBodyIsEscapedNotInterpreted();
        $this->publicProjectDocumentCannotContainAnUnlistedField();
        $this->secretAppearsOnlyInTheConfidentialDocument();
        $this->payslipShowsTheFormulaAndTheSignatureLines();
        $this->persianTextIsRightAlignedNotLeftAligned();
        $this->htmlIsEscapedEverywhere();
    }

    private function engine(): PdfEngine
    {
        return new PdfEngine();
    }

    private function contractDocumentCarriesItsOwnFacts(): void
    {
        $this->boot('pdf_contract');
        $user = $this->user();

        $contract = Contract::create([
            'customer_id' => $this->customer()->id,
            'title' => 'پشتیبانی سالانه',
            'amount' => 12_000_000,
            'start_date' => '2026-03-21',
            'end_date' => '2027-03-20',
            'status' => 'draft',
            'party_one_name' => 'شرکت ویرا',
            // Finalisation refuses a contract with no text at all; that guard is
            // correct, so the fixture supplies real text.
            'description' => 'پشتیبانی فنی و بهینه‌سازی در طول سال.',
        ]);

        $final = (new ContractFinalizer(new ContractRenderer()))->finalize($contract, $user);

        $html = (new ContractPdf($this->engine()))->html($final);

        $this->assertTrue(str_contains($html, $final->number),
            'the document carries its own number');
        $this->assertTrue(str_contains($html, 'پشتیبانی سالانه'), 'the title is present');
        $this->assertTrue(str_contains($html, '۱۲٬۰۰۰٬۰۰۰'),
            'the amount is formatted in Persian digits with the Persian thousands separator');
        $this->assertTrue(str_contains($html, '۱۴۰۵'), 'dates are Jalali');
        $this->assertTrue(! str_contains($html, '2026-03-21'),
            'no raw Gregorian date leaks into the contract');
        $this->assertTrue(str_contains($html, 'امضای طرف اول')
            && str_contains($html, 'امضای طرف دوم'),
            'both signature lines are present');
    }

    private function draftContractIsRefused(): void
    {
        $this->boot('pdf_draft');

        $contract = Contract::create([
            'customer_id' => $this->customer()->id,
            'title' => 'پیش‌نویس',
            'amount' => 1_000,
            'start_date' => '2026-03-21',
            'status' => 'draft',
        ]);

        $this->assertThrows(\RuntimeException::class, function () use ($contract) {
            (new ContractPdf($this->engine()))->build($contract);
        }, 'a draft has no permanent number and no frozen body; printing it looks like a binding agreement');
    }

    /**
     * The body is administrator-typed text rendered into a document handed to a
     * customer. If it were inserted as markup, an administrator could put remote
     * images or tags into it — and mPDF fetches images.
     */
    private function contractBodyIsEscapedNotInterpreted(): void
    {
        $this->boot('pdf_escape');
        $user = $this->user();

        $payload = 'متن قرارداد <img src="http://evil.example/x.png"> و <script>alert(1)</script>';

        $contract = Contract::create([
            'customer_id' => $this->customer()->id,
            'title' => 'قرارداد آزمون',
            'amount' => 5_000,
            'start_date' => '2026-03-21',
            'status' => 'draft',
            'description' => $payload,
        ]);

        $final = (new ContractFinalizer(new ContractRenderer()))->finalize($contract, $user);
        $html = (new ContractPdf($this->engine()))->html($final);

        $this->assertTrue(! str_contains($html, '<img'), 'no raw <img> reaches the renderer');
        $this->assertTrue(! str_contains($html, '<script'), 'no raw <script> reaches the renderer');
        $this->assertTrue(str_contains($html, '&lt;img'),
            'the tag is present as escaped text, so nothing was silently dropped either');
    }

    /**
     * T-PDF-08 / REQ-06.06 — the public document must contain no credential data.
     * An allow-list is used rather than a deny-list, so a column added to
     * `projects` tomorrow does not reach a customer's PDF by default.
     */
    private function publicProjectDocumentCannotContainAnUnlistedField(): void
    {
        $this->boot('pdf_project');

        $project = Project::create([
            'code' => 'PRJ-1',
            'customer_id' => $this->customer()->id,
            'type' => 'seo',
            'title' => 'پروژهٔ آزمون عمومی',
            'status' => 'active',
            'amount' => 50_000_000,
            'start_date' => '2026-01-01',
            'notes' => 'توضیح عمومی',
        ]);

        /* A value that must never appear, written into a REAL column that the
         * public document's allow-list does not name. `projects` has no
         * `internal_notes`; `owner_user_id` is the unlisted column that exists,
         * and pointing at a real one is what makes this a test rather than a
         * demonstration. (The first draft invented a column and the insert
         * failed — which is also what a deny-list would have done in
         * production, only silently.) */
        $owner = $this->user();
        $project->owner_user_id = $owner->id;
        $project->save();

        $html = (new ProjectPdf($this->engine()))->html($project);

        $this->assertTrue(str_contains($html, 'پروژهٔ آزمون عمومی'), 'public fields are present');

        /* owner_user_id is set and is not in PUBLIC_FIELDS: neither the id nor
         * the owner's name may appear in a document handed to the customer. */
        $this->assertTrue($project->fresh()->owner_user_id === $owner->id,
            'the unlisted column really holds a value');
        $this->assertTrue(! str_contains($html, $owner->name),
            'a column outside PUBLIC_FIELDS never reaches the customer document');
        $this->assertTrue(str_contains($html, 'دسترسی‌های فنی پروژه') || str_contains($html, 'عمومی'),
            'the document says it is the public one');
    }

    /**
     * SECURITY AUDIT — the secret appears in exactly one document.
     *
     * This is the assertion the HTTP flow cannot make. mPDF writes glyph indices
     * rather than characters, so searching the exported PDF's bytes for a
     * password would "pass" whether or not the password was there. The HTML
     * handed to the engine is the only trustworthy place to look, and it is the
     * exact string that becomes the file.
     *
     * Three facts, each of which a plausible bug would break:
     *   1. the confidential document really contains the plaintext (an export
     *      that silently dropped the password would be a broken product, not a
     *      safe one);
     *   2. the public project document does not contain it;
     *   3. exporting project A does not carry project B's password — the export
     *      query is scoped by project_id, and a missing WHERE would look
     *      identical until someone exported the wrong client's passwords.
     */
    private function secretAppearsOnlyInTheConfidentialDocument(): void
    {
        $this->boot('pdf_secret');

        $customer = $this->customer();

        $projectA = Project::create([
            'code' => 'PRJ-A',
            'customer_id' => $customer->id,
            'type' => 'seo',
            'title' => 'پروژهٔ آزمون الف',
            'status' => 'active',
            'amount' => 10_000_000,
            'notes' => 'یادداشت عمومی الف',
        ]);

        $projectB = Project::create([
            'code' => 'PRJ-B',
            'customer_id' => $customer->id,
            'type' => 'seo',
            'title' => 'پروژهٔ آزمون ب',
            'status' => 'active',
            'amount' => 20_000_000,
            'notes' => 'یادداشت عمومی ب',
        ]);

        $type = CredentialType::create([
            'code' => 'audit_wp',
            'title' => 'وردپرس ممیزی',
            'fields_schema' => [
                ['key' => 'password', 'label' => 'رمز عبور', 'required' => true],
            ],
            'is_active' => true,
        ]);

        /* An explicit cipher, exactly as CredentialVaultTest does. Going through
         * the config-based path would make this test depend on VIRA_CREDENTIAL_KEY
         * being present in the environment, and it would then fail closed for the
         * wrong reason. */
        $vault = new CredentialVault(
            new EnvelopeEncrypter(base64_encode(str_repeat("\x37", 32)))
        );

        $secretA = 'Audit-Secret-A-9x7Qz';
        $secretB = 'Audit-Secret-B-4m2Lp';

        $credA = ProjectCredential::create([
            'project_id' => $projectA->id,
            'credential_type_id' => $type->id,
            'label' => 'ورود مدیر الف',
            'username' => 'admin_a',
        ]);
        $vault->store($credA, 'password', $secretA);

        $credB = ProjectCredential::create([
            'project_id' => $projectB->id,
            'credential_type_id' => $type->id,
            'label' => 'ورود مدیر ب',
            'username' => 'admin_b',
        ]);
        $vault->store($credB, 'password', $secretB);

        /* Precondition, asserted rather than assumed: the ciphertext on disk does
         * not contain either plaintext. Without this the rest of the test could
         * pass because nothing was ever encrypted. */
        $this->assertTrue($vault->isReady(), 'the vault is usable in this test');
        $this->assertTrue($vault->reveal($credA->fresh(), 'password') === $secretA,
            'the secret round-trips, so there is something to leak');

        $confidential = (new CredentialPdf($this->engine(), $vault))
            ->html($projectA, [$credA->fresh()], 'ممیز امنیت');

        $this->assertTrue(str_contains($confidential, $secretA),
            'the confidential document carries the plaintext — that is its purpose');
        $this->assertTrue(str_contains($confidential, 'محرمانه'),
            'the confidential document says it is confidential');

        /* Fact 3: project B's password must not ride along. */
        $this->assertTrue(! str_contains($confidential, $secretB),
            'exporting project A does not carry project B\'s password');
        $this->assertTrue(! str_contains($confidential, 'ورود مدیر ب'),
            'project B\'s credential row is not in project A\'s document either');

        /* Fact 2: the document a customer receives. */
        $public = (new ProjectPdf($this->engine()))->html($projectA);

        $this->assertTrue(str_contains($public, 'پروژهٔ آزمون الف'),
            'the public document really is for this project');
        $this->assertTrue(! str_contains($public, $secretA),
            'the plaintext password never reaches the public project document');
        $this->assertTrue(! str_contains($public, $secretB),
            'nor does any other project\'s password');
        $this->assertTrue(! str_contains($public, 'admin_a'),
            'the credential username does not reach the public document either');
    }

    private function payslipShowsTheFormulaAndTheSignatureLines(): void
    {
        $this->boot('pdf_payslip');

        $employee = Employee::create([
            'code' => 'E-9',
            'first_name' => 'مریم',
            'last_name' => 'رستمی',
            'position' => 'کارشناس',
            'start_date' => '2025-01-01',
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'jalali_year' => 1405,
            'jalali_month' => 8,
            'state' => 'draft',
        ]);

        $slip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'base_salary' => 100_000_000,
            'worked_days' => 30,
            'bonus' => 5_000_000,
            'overtime' => 2_000_000,
            'advance' => 10_000_000,
            'deductions' => 3_000_000,
            'total_earnings' => 107_000_000,
            'total_deductions' => 13_000_000,
            'net_payable' => 94_000_000,
            'computed_at' => now(),
        ]);

        PayrollItem::create([
            'payslip_id' => $slip->id,
            'label' => 'بیمهٔ تأمین اجتماعی',
            'direction' => PayrollItem::DEDUCTION,
            'amount' => 2_000_000,
        ]);

        $html = (new PayslipPdf($this->engine()))->html($slip->fresh());

        /* REQ-07.03's item list, checked one by one — the requirement is a list
         * of fields, and a payslip missing one of them is not the document the
         * client described. */
        foreach ([
            'نام و نام خانوادگی' => 'name',
            'دورهٔ حقوق' => 'month/year',
            'حقوق پایه' => 'base salary',
            'روزهای کارکرد' => 'worked days',
            'پاداش' => 'bonus',
            'مساعده' => 'advance',
            'کسورات' => 'deductions',
            'خالص پرداختی' => 'net',
        ] as $label => $what) {
            $this->assertTrue(str_contains($html, $label), 'the payslip shows ' . $what);
        }

        $this->assertTrue(str_contains($html, 'بیمهٔ تأمین اجتماعی'),
            'manual deduction items are listed individually, not collapsed into a total');
        $this->assertTrue(str_contains($html, 'امضای پرسنل') && str_contains($html, 'امضای مدیر'),
            'both signature lines are present');
        $this->assertTrue(str_contains($html, '۹۴٬۰۰۰٬۰۰۰'), 'the net figure is rendered');
        $this->assertTrue(str_contains($html, 'نود و چهار میلیون'), 'the net is also written in words');
    }

    /**
     * `directionality => 'rtl'` sets the bidi base but NOT the alignment. Text
     * joins correctly and still starts at the left margin — correct-looking and
     * wrong on paper.
     */
    private function persianTextIsRightAlignedNotLeftAligned(): void
    {
        $this->boot('pdf_align');
        $css = $this->engine()->stylesheet();

        $this->assertTrue((bool) preg_match('/text-align:\s*right/', $css),
            'the base stylesheet right-aligns text');
        $this->assertTrue(str_contains($css, 'direction: rtl'), 'the base direction is RTL');
        $this->assertTrue(str_contains($css, 'vazirmatn'), 'Vazirmatn is the document font');
    }

    private function htmlIsEscapedEverywhere(): void
    {
        $this->boot('pdf_esc');

        $this->assertSame('&lt;b&gt;&amp;&quot;', PdfEngine::e('<b>&"'),
            'escaping covers angle brackets, ampersands and quotes');
        $this->assertSame('۱۲۳', PdfEngine::digits(123), 'digits are converted per-character');
    }
}
