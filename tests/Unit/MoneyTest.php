<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\PersianNumber;
use Tests\ViraTestCase;

/**
 * REQ-04.01 / REQ-07.02 — financial amounts must be exact.
 *
 * This suite exists because a real bug was found here: `($a < 0) xor ($b < 0)`
 * without wrapping parentheses zeroes the result (PHP gives '=' higher
 * precedence than 'xor'). Every rate-based money calculation would have
 * produced 0 rial. These assertions pin that behaviour down.
 */
final class MoneyTest extends ViraTestCase
{
    public function run(): void
    {
        $this->integerArithmetic();
        $this->noFloatDrift();
        $this->roundingHalfAwayFromZero();
        $this->rates();
        $this->parsingUserInput();
        $this->rejectsAmbiguousInput();
        $this->persianDigits();
        $this->words();
    }

    private function integerArithmetic(): void
    {
        $this->assertSame(300, Money::fromRial(500)->subtract(Money::fromRial(200))->amount);
        $this->assertSame(750, Money::fromRial(500)->add(Money::fromRial(250))->amount);
        $this->assertSame(3000, Money::fromRial(1000)->multiplyBy(3)->amount);
        $this->assertTrue(Money::zero()->isZero());
        $this->assertTrue(Money::fromRial(-5)->isNegative());
        $this->assertTrue(Money::fromRial(7)->equals(Money::fromRial(7)));
    }

    private function noFloatDrift(): void
    {
        // 0.1 + 0.2 !== 0.3 in floating point; integers must not drift.
        $sum = Money::zero();
        for ($i = 0; $i < 10; $i++) {
            $sum = $sum->add(Money::fromRial(10));
        }
        $this->assertSame(100, $sum->amount, 'accumulating 10×10 must be exactly 100');
    }

    private function roundingHalfAwayFromZero(): void
    {
        $this->assertSame(3, Money::divideRounded(5, 2));
        $this->assertSame(4, Money::divideRounded(7, 2));
        $this->assertSame(0, Money::divideRounded(1, 3));
        $this->assertSame(1, Money::divideRounded(2, 3));
        $this->assertSame(-3, Money::divideRounded(-5, 2));
        $this->assertSame(-3, Money::divideRounded(5, -2));
        $this->assertSame(3, Money::divideRounded(-5, -2));
        $this->assertSame(0, Money::divideRounded(-1, 3), 'half away from zero must not bias negatives');
    }

    private function rates(): void
    {
        $this->assertSame(300000, Money::fromRial(1000000)->applyRate(30, 100)->amount);
        $this->assertSame(330000, Money::fromRial(1000000)->applyRate(33, 100)->amount);
        $this->assertSame(25000, Money::fromRial(1000003)->applyRate(25, 1000)->amount);

        // Large values must stay exact (int64 range is far beyond any real contract).
        $large = Money::fromRial(9_000_000_000);
        $this->assertSame(4_500_000_000, $large->applyRate(50, 100)->amount);

        $this->assertThrows(\InvalidArgumentException::class, fn () => Money::fromRial(1)->applyRate(1, 0));
        $this->assertThrows(\InvalidArgumentException::class, fn () => Money::divideRounded(1, 0));
    }

    private function parsingUserInput(): void
    {
        $this->assertSame(1250000, Money::parse('1250000')?->amount);
        $this->assertSame(2500000, Money::parse('2,500,000')?->amount);
        $this->assertSame(1250000, Money::parse('۱٬۲۵۰٬۰۰۰')?->amount, 'Persian digits + Arabic thousands separator');
        $this->assertSame(1250000, Money::parse('۱۲۵۰۰۰۰')?->amount);
        $this->assertSame(98000, Money::parse(' 98000 تومان ')?->amount, 'currency word is stripped');
        $this->assertSame(5000, Money::parse('5000 ریال')?->amount);
        $this->assertSame(0, Money::parse('0')?->amount);
        $this->assertSame(-1500, Money::parse('-1,500')?->amount);
    }

    private function rejectsAmbiguousInput(): void
    {
        $this->assertNull(Money::parse('abc'));
        $this->assertNull(Money::parse(''));
        $this->assertNull(Money::parse(null));
        $this->assertNull(Money::parse('12.5'), 'fractional rial must not be silently rounded');
        $this->assertNull(Money::parse('1,2,3'), 'malformed grouping');
    }

    private function persianDigits(): void
    {
        $this->assertSame('1250000', Money::toAsciiDigits('۱۲۵۰۰۰۰'));
        $this->assertSame('1250000', Money::toAsciiDigits('١٢٥٠٠٠٠'));
        $this->assertSame('۱۲۵۰۰۰۰', Money::toPersianDigits('1250000'));
        $this->assertSame('1,250,000', Money::fromRial(1250000)->formatAscii());

        // Non-digit Persian text must pass through untouched.
        $this->assertSame('قرارداد سئو', Money::toAsciiDigits('قرارداد سئو'));
    }

    private function words(): void
    {
        $this->assertSame('صفر', PersianNumber::toWords(0));
        $this->assertSame('هفت', PersianNumber::toWords(7));
        $this->assertSame('بیست', PersianNumber::toWords(20));
        $this->assertSame('بیست و یک', PersianNumber::toWords(21));
        $this->assertSame('یکصد', PersianNumber::toWords(100));
        $this->assertSame('یکصد و یک', PersianNumber::toWords(101));
        $this->assertSame('یک هزار', PersianNumber::toWords(1000));
        $this->assertSame('یک هزار و دویست و پنجاه', PersianNumber::toWords(1250));
        $this->assertSame('یک میلیون و دویست و پنجاه هزار', PersianNumber::toWords(1250000));
        $this->assertSame('منفی پنج', PersianNumber::toWords(-5));

        $this->assertContains('میلیون', PersianNumber::toWords(123456789));
        $this->assertContains('میلیارد', PersianNumber::toWords(2000000000));
        $this->assertSame('یک هزار ریال', PersianNumber::rialToWords(1000));
    }
}
