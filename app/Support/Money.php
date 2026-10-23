<?php

namespace App\Support;

class Money
{
    public static function format(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = explode('.', ltrim(bcadd($amount, '0', 2), '-'));

        return ($negative ? '-' : '').'$'.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction.' MXN';
    }
}
