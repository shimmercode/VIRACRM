<?php

namespace App\Support;

/**
 * Persian number-to-words, for "amount in words" on contracts and payslips.
 *
 * A payslip with figures only is a dispute waiting to happen; the client
 * document asks for official-looking PDFs, and Iranian financial documents
 * conventionally repeat the amount in words.
 */
final class PersianNumber
{
    private const ONES = [
        '', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه',
        'ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده',
    ];

    private const TENS = [
        '', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود',
    ];

    private const HUNDREDS = [
        '', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد',
    ];

    /** Groups of three, largest first. */
    private const SCALES = ['', ' هزار', ' میلیون', ' میلیارد', ' هزار میلیارد'];

    public static function toWords(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        if ($number < 0) {
            return 'منفی ' . self::toWords(-$number);
        }

        $groupIndex = 0;
        $parts = [];

        while ($number > 0) {
            $group = $number % 1000;
            if ($group > 0) {
                $text = self::groupToWords($group);
                $scale = self::SCALES[$groupIndex] ?? '';
                $parts[] = $text . $scale;
            }
            $number = intdiv($number, 1000);
            $groupIndex++;
        }

        return implode(' و ', array_reverse($parts));
    }

    private static function groupToWords(int $group): string
    {
        $words = [];
        $hundreds = intdiv($group, 100);
        $remainder = $group % 100;

        if ($hundreds > 0) {
            $words[] = self::HUNDREDS[$hundreds];
        }

        if ($remainder > 0) {
            if ($remainder < 20) {
                $words[] = self::ONES[$remainder];
            } else {
                $tens = intdiv($remainder, 10);
                $ones = $remainder % 10;
                $words[] = $ones > 0
                    ? self::TENS[$tens] . ' و ' . self::ONES[$ones]
                    : self::TENS[$tens];
            }
        }

        return implode(' و ', array_filter($words, fn ($w) => $w !== ''));
    }

    /** Amount in words with the currency unit appended. */
    public static function rialToWords(int $amount): string
    {
        return self::toWords($amount) . ' ریال';
    }
}
