<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Support\JalaliDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * REQ-04.05 / 04.06 — turn a template and a contract into a document.
 *
 * WHY A WHITELIST AND NOT A TEMPLATE ENGINE
 * -----------------------------------------
 * The obvious implementation is `str_replace` over an array, or worse, Blade's
 * `render()` on stored template text. The second is remote code execution: a
 * template body is *content* an administrator types, and rendering it as code
 * means anyone with `contracts.finalize` can execute PHP on the server.
 *
 * So placeholders are `{{token}}`, matched against a fixed list, and an unknown
 * token is an error — not a silent passthrough, and not an evaluation.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * -----------------------------
 * No loops, no conditionals, no arithmetic. §4 lists a contract body with
 * parties, dates, amount and an optional description. A template language is a
 * product of its own, and inventing one would be scope nobody asked for.
 */
final class ContractRenderer
{
    /**
     * Available placeholders, with Persian labels for the editor's helper panel.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            'contract.number' => 'شمارهٔ قرارداد',
            'contract.title' => 'عنوان قرارداد',
            'contract.type' => 'نوع قرارداد',
            'contract.amount' => 'مبلغ (با جداکننده و واحد)',
            'contract.amount_words' => 'مبلغ به حروف',
            'contract.start_date' => 'تاریخ شروع (جلالی)',
            'contract.end_date' => 'تاریخ پایان (جلالی)',
            'contract.description' => 'توضیحات',
            'customer.name' => 'نام مشتری',
            'customer.national_id' => 'شناسه یا کد ملی مشتری',
            'customer.address' => 'نشانی مشتری',
            'party.one_name' => 'نام طرف اول',
            'party.two_name' => 'نام طرف دوم',
            'party.one_address' => 'نشانی طرف اول',
            'party.two_address' => 'نشانی طرف دوم',
            'today' => 'تاریخ امروز (جلالی)',
        ];
    }

    /**
     * Render a body for a contract.
     *
     * @throws RuntimeException when the body contains a token that is not in
     *                          the whitelist.
     */
    public function render(string $body, Contract $contract): string
    {
        $values = $this->valuesFor($contract);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            function (array $m) use ($values): string {
                $token = $m[1];

                if (! array_key_exists($token, $values)) {
                    throw new RuntimeException(
                        'جای‌نگهدار ناشناخته در قالب: {{' . $token . '}} — فهرست جای‌نگهدارهای مجاز در راهنمای قالب آمده است.'
                    );
                }

                return $values[$token];
            },
            $body
        );
    }

    /** Validate a body's tokens without a contract; used when saving a template. */
    public function unknownTokens(string $body): array
    {
        $known = array_keys(self::tokens());
        $found = [];

        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $body, $m);

        foreach ($m[1] as $token) {
            if (! in_array($token, $known, true)) {
                $found[$token] = true;
            }
        }

        return array_keys($found);
    }

    /** Every token resolved to its display string for this contract. */
    private function valuesFor(Contract $contract): array
    {
        $contract->loadMissing(['customer', 'contractType']);
        $customer = $contract->customer;

        $date = function ($value): string {
            if ($value === null) {
                return '—';
            }

            $carbon = $value instanceof \DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value);

            return JalaliDate::fromCarbon($carbon)->formatPersian();
        };

        return [
            'contract.number' => $contract->number ?: '—',
            'contract.title' => (string) $contract->title,
            'contract.type' => $contract->contractType?->title ?? '—',
            'contract.amount' => Money::fromRial((int) $contract->amount)->formatPersian(),
            'contract.amount_words' => $this->amountInWords((int) $contract->amount),
            'contract.start_date' => $date($contract->start_date),
            'contract.end_date' => $date($contract->end_date),
            'contract.description' => (string) ($contract->description ?? ''),
            'customer.name' => $customer?->display_name ?? '—',
            'customer.national_id' => $customer?->national_id
                ?? ($customer?->economic_code ?? '—'),
            'customer.address' => $customer?->address ?? '—',
            'party.one_name' => $contract->party_one_name ?: '—',
            'party.two_name' => $contract->party_two_name ?: '—',
            'party.one_address' => $contract->party_one_address ?: '—',
            'party.two_address' => $contract->party_two_address ?: '—',
            'today' => JalaliDate::fromCarbon(CarbonImmutable::now())->formatPersian(),
        ];
    }

    /**
     * Amount in Persian words.
     *
     * Deliberately bounded: a wrong "به حروف" on a contract is worse than none,
     * so anything the writer is not sure of falls back to digits rather than
     * producing a confident wrong phrase.
     */
    private function amountInWords(int $amount): string
    {
        if ($amount === 0) {
            return 'صفر ریال';
        }

        if ($amount < 0 || $amount >= 1_000_000_000_000) {
            return Money::fromRial($amount)->formatPersian();
        }

        $ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
        $teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
        $tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
        $hundreds = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
        $scales = ['', ' هزار', ' میلیون', ' میلیارد'];
        $and = ' و ';

        $three = static function (int $n) use ($ones, $teens, $tens, $hundreds, $and): string {
            $parts = [];
            $h = intdiv($n, 100);
            $rest = $n % 100;

            if ($h > 0) {
                $parts[] = $hundreds[$h];
            }

            if ($rest >= 10 && $rest < 20) {
                $parts[] = $teens[$rest - 10];
            } else {
                $t = intdiv($rest, 10);
                $o = $rest % 10;

                if ($t > 0) {
                    $parts[] = $tens[$t];
                }

                if ($o > 0) {
                    $parts[] = $ones[$o];
                }
            }

            return implode($and, array_filter($parts));
        };

        $groups = [];
        $remaining = $amount;

        while ($remaining > 0) {
            $groups[] = $remaining % 1000;
            $remaining = intdiv($remaining, 1000);
        }

        $words = [];

        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }

            $words[] = $three($groups[$i]) . $scales[$i];
        }

        return implode($and, $words) . ' ریال';
    }
}
