<?php

namespace App\Support;

/**
 * Vietnamese amount-in-words for VND (mirrors resources/js/composables/useFormat.js).
 */
final class VndAmountInWords
{
    /** @var list<string> */
    private const ONES = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    public static function format(int|float|null $value): string
    {
        $n = (int) floor((float) ($value ?? 0));
        if ($n <= 0) {
            return '';
        }

        $scales = ['', ' nghìn', ' triệu', ' tỷ'];
        $groups = [];
        while ($n > 0) {
            array_unshift($groups, $n % 1000);
            $n = intdiv($n, 1000);
        }

        $total = count($groups);
        $words = '';
        foreach ($groups as $i => $g) {
            if ($g === 0) {
                continue;
            }
            $fromRight = $total - 1 - $i;
            $scale = $scales[$fromRight % 4] ?? '';
            $full = $i > 0;
            $words .= ' '.self::readGroup($g, $full).$scale;
        }

        $words = trim($words).' đồng';

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1);
    }

    private static function readGroup(int $n, bool $full): string
    {
        $tram = intdiv($n, 100);
        $chuc = intdiv($n % 100, 10);
        $donvi = $n % 10;
        $out = '';

        if ($tram > 0 || $full) {
            $out .= self::ONES[$tram].' trăm';
        }

        if ($chuc > 1) {
            $out .= ' '.self::ONES[$chuc].' mươi';
            if ($donvi === 1) {
                $out .= ' mốt';
            } elseif ($donvi === 5) {
                $out .= ' lăm';
            } elseif ($donvi > 0) {
                $out .= ' '.self::ONES[$donvi];
            }
        } elseif ($chuc === 1) {
            $out .= ' mười';
            if ($donvi === 5) {
                $out .= ' lăm';
            } elseif ($donvi > 0) {
                $out .= ' '.self::ONES[$donvi];
            }
        } elseif ($donvi > 0) {
            if ($tram > 0 || $full) {
                $out .= ' lẻ '.self::ONES[$donvi];
            } else {
                $out .= self::ONES[$donvi];
            }
        }

        return trim($out);
    }
}
