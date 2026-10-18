<?php

namespace App\Support;

class Phone
{
    /**
     * Turns a Moroccan number typed in any common way into +212XXXXXXXXX.
     * An invalid number is returned cleaned but unchanged, so validation can reject it.
     */
    public static function normalize(?string $input): string
    {
        $p = preg_replace('/[\s\-().]/', '', (string) $input);

        if (str_starts_with($p, '00212')) {
            return '+'.substr($p, 2);
        }

        if (str_starts_with($p, '212')) {
            return '+'.$p;
        }

        if (preg_match('/^0[5-7]\d{8}$/', $p)) {
            return '+212'.substr($p, 1);
        }

        return $p;
    }
}