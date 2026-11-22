<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Rial amount, stored and calculated as an integer.
 *
 * WHY: this is a CRM that produces contracts, invoices and payslips. Floating
 * point arithmetic on money produces 0.1+0.2 problems that surface as
 * one-rial discrepancies in a payslip the client signs. Iran has no minor
 * currency unit in practice, so every amount is a whole rial integer.
 *
 * RULES
 *  - never construct from a float without an explicit rounding decision;
 *  - never use arithmetic outside this class;
 *  - Persian/Arabic digits and thousands separators are accepted on input
 *    because users paste amounts from Word/Excel;
 *  - formatting is display-only.
 */
final class Money implements JsonSerializable
{
    private function __construct(public readonly int $amount)
    {
    }

    public static function fromRial(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse user input into a Money value.
     *
     * Accepts: "1,250,000" | "1250000" | "۱٬۲۵۰٬۰۰۰" | " 1250000 تومان"
     * Returns null when the input cannot be interpreted, so callers can raise a
     * validation error instead of silently storing 0.
     */
    public static function parse(?string $input): ?self
    {
        if ($input === null) {
            return null;
        }

        $raw = trim(self::toAsciiDigits($input));

        if ($raw === '') {
            return null;
        }

        // Drop currency words and invisible direction marks.
        $raw = trim(str_replace(
            ['تومان', 'ریال', '٬', '،', ' ', "\u{200c}", "\u{200f}", "\u{200e}"],
            ['', '', '', '', '', '', '', ''],
            $raw
        ));

        if ($raw === '' || $raw === '-') {
            return null;
        }

        // Grouping separators are only legitimate every three digits.
        // Treating "1,2,3" as 123 is how a typo becomes a wrong invoice total,
        // so an ill-formed grouping is rejected rather than guessed at.
        if (str_contains($raw, ',')) {
            if (! preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $raw)) {
                return null;
            }
            $raw = str_replace(',', '', $raw);
        }

        // Accept ASCII '.' or Arabic decimal separator '٫' as the decimal point.
        $raw = str_replace('٫', '.', $raw);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            return null;
        }

        if (str_contains($raw, '.')) {
            // Rial has no sub-unit; refuse to guess how to round a fractional value.
            [$whole, $fraction] = explode('.', $raw, 2);
            if (trim($fraction, '0') !== '') {
                return null;
            }
            $raw = $whole;
        }

        return new self((int) $raw);
    }

    /** Convert Persian/Arabic-Indic digits to ASCII digits. */
    public static function toAsciiDigits(string $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function multiplyBy(int $factor): self
    {
        return new self($this->amount * $factor);
    }

    /**
     * Multiply by a rational rate without losing precision.
     * Used for coefficients/percentages, e.g. 30% => rate(30, 100).
     * Rounds half away from zero, the convention used on Iranian payslips.
     */
    public function applyRate(int $numerator, int $denominator): self
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Rate denominator cannot be zero.');
        }

        return new self(self::divideRounded($this->amount * $numerator, $denominator));
    }

    /** Integer division rounding half away from zero. */
    public static function divideRounded(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        // NOTE: the parentheses around the ENTIRE xor expression are load-bearing.
        // '=' binds tighter than 'xor' in PHP, so writing
        //     $negative = $a < 0 xor $b < 0;
        // assigns ($a < 0) to $negative and throws away the rest of the expression.
        $negative = (($numerator < 0) xor ($denominator < 0));
        $sign = $negative ? -1 : 1;

        $numerator = abs($numerator);
        $denominator = abs($denominator);

        return $sign * intdiv($numerator * 2 + $denominator, $denominator * 2);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    /** "1,250,000" — Latin digits, for inputs and PDFs that need numeric clarity. */
    public function formatAscii(): string
    {
        return number_format($this->amount);
    }

    /**
     * "۱٬۲۵۰٬۰۰۰" — Persian digits and the Persian thousands separator (U+066C).
     *
     * The separator matters: an ASCII comma inside RTL text breaks the number
     * into segments that the bidi algorithm can reorder, so "1,250,000" can
     * render with the wrong grouping on screen. U+066C is a single strong
     * right-to-left-ish neutral that keeps the digits in one visual run.
     */
    public function formatPersian(): string
    {
        return self::toPersianDigits(
            str_replace(',', '٬', number_format($this->amount))
        );
    }

    /** Amount in words, e.g. "یک میلیون و دویست و پنجاه هزار ریال". */
    public function toPersianWords(): string
    {
        return PersianNumber::toWords($this->amount);
    }

    public static function toPersianDigits(string $value): string
    {
        return str_replace(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], $value);
    }

    public function __toString(): string
    {
        return $this->formatAscii();
    }

    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'formatted' => $this->formatPersian()];
    }
}
