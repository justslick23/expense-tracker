<?php

namespace App\Support;

class Money
{
    public static function parse(string $value): int
    {
        $v = str_replace([',', ' '], '', trim($value));
        [$whole, $frac] = array_pad(explode('.', ltrim($v, '-'), 2), 2, '');
        $n = (int) $whole * 100 + (int) str_pad($frac, 2, '0');

        return str_starts_with($v, '-') ? -$n : $n;
    }

    public static function format(int $minor, string $symbol = 'M'): string
    {
        $abs = abs($minor);

        return ($minor < 0 ? '-' : '').$symbol.' '.number_format(intdiv($abs, 100)).'.'.sprintf('%02d', $abs % 100);
    }
}