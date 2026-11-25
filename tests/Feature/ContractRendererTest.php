<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\User;
use App\Services\Contracts\ContractFinalizer;
use App\Services\Contracts\ContractRenderer;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * REQ-04.05–04.08 — contract rendering, finalisation and renewal.
 *
 * THE ASSERTION THAT MATTERS MOST
 * -------------------------------
 * `unknownTokenIsRefusedRatherThanRendered` and
 * `templateBodyIsNeverEvaluatedAsCode` are the security tests. A contract body is
 * text an administrator types; if it were ever run through Blade or `eval`, that
 * permission would be remote code execution. These tests exist so the next
 * developer who thinks "let's just support a little logic in templates" has to
 * delete a failing assertion first.
 *
 * The rest guard the promises a finalised contract makes: a number, a stored
 * body, a snapshot, and a renewal that never rewrites the original.
 */
final class ContractRendererTest extends ViraTestCase
{
    private DatabaseHarness $harness;

    private function boot(string $name): void
    {
        $this->harness = DatabaseHarness::boot($name)->migrate();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'مدیر قرارداد',
            'username' => 'contracts_' . uniqid(),
            'password' => Hash::make('Secret-Passw0rd-123'),
            'is_superadmin' => true,
            'is_active' => true,
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'type' => 'legal',
            'company_name' => 'شرکت نمونه',
            'national_id' => '10101010101',
            'economic_code' => '411111111111',
            'address' => 'تهران، خیابان نمونه',
            'status' => 'active',
        ]);
    }

    private function makeContract(array $attrs = []): Contract
    {
        return Contract::create(array_merge([
            'customer_id' => $this->makeCustomer()->id,
            'title' => 'قرارداد نگهداری سالانه',
            'amount' => 12_000_000,
            'start_date' => '2026-03-21',
            'end_date' => '2027-03-20',
            'status' => 'draft',
            'description' => 'شرح مختصر قرارداد',
        ], $attrs));
    }

    public function run(): void
    {
        $this->rendersWhitelistedTokensInPersian();
        $this->unknownTokenIsRefusedRatherThanRendered();
        $this->templateBodyIsNeverEvaluatedAsCode();
        $this->finalizeStoresBodyNumberAndSnapshot();
        $this->finalizeWithoutTemplateUsesTheDescription();
        $this->finalizeWithoutAnyTextIsRefused();
        $this->finalizeTwiceIsRefused();
        $this->renewCopiesTermsAndLeavesOriginalUntouched();
        $this->renewRequiresAFinalisedContract();
        $this->amountInWordsIsCorrect();
    }

    private function rendersWhitelistedTokensInPersian(): void
    {
        $this->boot('ctr_render');
        $contract = $this->makeContract(['party_one_name' => 'ویرا', 'party_two_name' => 'شرکت نمونه']);

        $body = 'قرارداد {{contract.title}} بین {{party.one_name}} و {{party.two_name}} '
            . 'به مبلغ {{contract.amount}} از {{contract.start_date}} آغاز می‌شود.';

        $out = (new ContractRenderer())->render($body, $contract);

        $this->assertTrue(str_contains($out, 'قرارداد نگهداری سالانه'), 'title token resolved');
        $this->assertTrue(str_contains($out, 'ویرا'), 'party one resolved');
        $this->assertTrue(str_contains($out, 'شرکت نمونه'), 'party two resolved');
        $this->assertTrue(! str_contains($out, '{{'), 'no unresolved placeholder remains');
        // Jalali, not Gregorian: 2026-03-21 is 1405/01/01.
        $this->assertTrue(str_contains($out, '۱۴۰۵'), 'the start date is rendered in Jalali');
        $this->assertTrue(! str_contains($out, '2026-03-21'), 'no raw Gregorian date leaks into the document');
    }

    private function unknownTokenIsRefusedRatherThanRendered(): void
    {
        $this->boot('ctr_unknown');
        $contract = $this->makeContract();

        $this->assertThrows(\RuntimeException::class, function () use ($contract) {
            (new ContractRenderer())->render('شماره: {{contact.number}}', $contract);
        }, 'A typo in a placeholder must be an error, not a literal {{...}} left in the contract.');

        $unknown = (new ContractRenderer())->unknownTokens('{{contract.title}} و {{foo.bar}}');
        $this->assertCount(1, $unknown);
        $this->assertSame('foo.bar', $unknown[0]);
    }

    /**
     * If a template body were ever compiled, this input would execute. It must
     * come out as literal text.
     */
    private function templateBodyIsNeverEvaluatedAsCode(): void
    {
        $this->boot('ctr_rce');
        $contract = $this->makeContract();

        /*
         * The marker is built at runtime so it does not appear in this source
         * file's payload string — otherwise `str_contains($out, 'PWNED')` would
         * find the word in the INPUT and fail for the wrong reason. That is
         * exactly what happened on the first run.
         */
        $marker = 'EXEC' . 'UTED';
        $payload = '{{contract.title}} <?php echo "' . $marker . '"; ?> {{ 7*7 }}';

        $out = (new ContractRenderer())->render($payload, $contract);

        $this->assertTrue(str_contains($out, '<?php'), 'PHP tags are stored as literal text, not executed');
        $this->assertTrue(! str_contains($out, '>' . $marker) && ! str_contains($out, $marker . '<'),
            'the echo was not executed');
        $this->assertTrue(str_contains($out, '7*7'), 'the template language has no arithmetic');
    }

    private function finalizeStoresBodyNumberAndSnapshot(): void
    {
        $this->boot('ctr_finalize');
        $user = $this->makeUser();
        $contract = $this->makeContract();

        $template = ContractTemplate::create([
            'title' => 'قالب استاندارد',
            'body' => 'قرارداد {{contract.title}} — مبلغ {{contract.amount}}',
            'version' => 1,
            'is_active' => true,
            'is_default' => true,
        ]);

        $finalized = (new ContractFinalizer(new ContractRenderer()))
            ->finalize($contract, $user, $template, 'نهایی‌سازی اولیه');

        $this->assertNotNull($finalized->finalized_at, 'finalized_at is set');
        $this->assertSame(ContractStatus::Active, $finalized->status,
            'finalising moves the contract to active');
        $this->assertTrue(str_starts_with((string) $finalized->number, 'VIRA-C-1405-'),
            'the number is year-scoped and derived from the start date, got ' . $finalized->number);
        $this->assertTrue(str_contains((string) $finalized->rendered_body, 'قرارداد نگهداری سالانه'),
            'the body is rendered and stored');

        $versions = \App\Models\ContractVersion::query()->where('contract_id', $contract->id)->get();
        $this->assertCount(1, $versions, 'one version snapshot is written');
        $this->assertSame('نهایی‌سازی اولیه', $versions->first()->reason);
        $this->assertSame('قرارداد نگهداری سالانه', $versions->first()->snapshot['title'] ?? null,
            'the snapshot holds the whole contract, not only the body');
    }

    private function finalizeWithoutTemplateUsesTheDescription(): void
    {
        $this->boot('ctr_desc');
        $user = $this->makeUser();
        $contract = $this->makeContract(['description' => 'متن توافقی طرفین']);

        $finalized = (new ContractFinalizer(new ContractRenderer()))->finalize($contract, $user);

        $this->assertSame('متن توافقی طرفین', $finalized->rendered_body,
            'with no template the description becomes the document, rather than an empty final body');
    }

    private function finalizeWithoutAnyTextIsRefused(): void
    {
        $this->boot('ctr_empty');
        $user = $this->makeUser();
        $contract = $this->makeContract(['description' => null]);

        $this->assertThrows(\RuntimeException::class, function () use ($contract, $user) {
            (new ContractFinalizer(new ContractRenderer()))->finalize($contract, $user);
        }, 'A contract with no text must not be marked finalised.');
    }

    private function finalizeTwiceIsRefused(): void
    {
        $this->boot('ctr_twice');
        $user = $this->makeUser();
        $contract = $this->makeContract();
        $finalizer = new ContractFinalizer(new ContractRenderer());

        $finalized = $finalizer->finalize($contract, $user);

        $this->assertThrows(\RuntimeException::class, function () use ($finalizer, $finalized, $user) {
            $finalizer->finalize($finalized, $user);
        }, 'Re-finalising would overwrite the frozen body and issue a second number.');
    }

    private function renewCopiesTermsAndLeavesOriginalUntouched(): void
    {
        $this->boot('ctr_renew');
        $user = $this->makeUser();
        $contract = $this->makeContract();
        $finalizer = new ContractFinalizer(new ContractRenderer());

        $original = $finalizer->finalize($contract, $user);
        $originalBody = $original->rendered_body;
        $originalNumber = $original->number;

        $renewal = $finalizer->renew($original, $user, [
            'title' => 'قرارداد نگهداری سالانه — تمدید',
            'amount' => 15_000_000,
            'start_date' => '2027-03-21',
            'end_date' => '2028-03-20',
        ], 'تمدید یک‌ساله');

        $this->assertSame(ContractStatus::Draft, $renewal->status,
            'a renewal starts as a draft, not an obligation');
        $this->assertSame((int) $original->id, (int) $renewal->renewed_from_id);
        $this->assertSame(15_000_000, (int) $renewal->amount);
        $this->assertNull($renewal->rendered_body, 'the renewal re-renders its own body when it is finalised');
        $this->assertNotSame($originalNumber, $renewal->number, 'the renewal gets its own number');

        $fresh = $original->fresh();
        $this->assertSame($originalBody, $fresh->rendered_body,
            'THE ORIGINAL BODY IS UNTOUCHED — it is what the parties performed under');
        $this->assertSame($originalNumber, $fresh->number, 'the original keeps its number');
        $this->assertSame(ContractStatus::Renewed, $fresh->status,
            'the original is marked renewed so it stops appearing as live');

        $versions = \App\Models\ContractVersion::query()->where('contract_id', $original->id)->count();
        $this->assertSame(2, $versions, 'the renewal writes a second snapshot on the original, explaining it');
    }

    private function renewRequiresAFinalisedContract(): void
    {
        $this->boot('ctr_renew_draft');
        $user = $this->makeUser();
        $contract = $this->makeContract();

        $this->assertThrows(\RuntimeException::class, function () use ($contract, $user) {
            (new ContractFinalizer(new ContractRenderer()))->renew($contract, $user, [], 'x');
        }, 'A draft cannot be renewed; it was never an obligation.');
    }

    private function amountInWordsIsCorrect(): void
    {
        $this->boot('ctr_words');
        $renderer = new ContractRenderer();

        $cases = [
            0 => 'صفر ریال',
            1_000 => 'یک هزار ریال',
            12_000_000 => 'دوازده میلیون ریال',
            /* "صد", not "یکصد": normal Persian speech writes 100 without the
             * leading "یک" when it is inside a larger number. The first draft of
             * this expectation said یکصد and failed against correct output. */
            123_456_789 => 'صد و بیست و سه میلیون و چهارصد و پنجاه و شش هزار و هفتصد و هشتاد و نه ریال',
        ];

        foreach ($cases as $amount => $expected) {
            /* One contract per case: the renderer reads the stored amount, so
             * reusing one row would silently test the last amount four times. */
            $contract = $this->makeContract(['amount' => $amount]);
            $out = $renderer->render('{{contract.amount_words}}', $contract);
            $this->assertSame($expected, $out, 'amount in words for ' . $amount);
        }
    }
}
