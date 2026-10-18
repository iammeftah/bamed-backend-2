<?php

namespace App\Support;

/** The fixed size scales (see section 2 of overview.md). A category picks one. */
class SizeScales
{
    public const DEFAULT = 'letters';

    /** @return array<string, list<string>> */
    public static function all(): array
    {
        return [
            'letters' => ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'],
            'pants' => ['38', '40', '42', '44', '46', '48', '50'],
            'jeans' => ['28', '29', '30', '31', '32', '33', '34', '36', '38', '40'],
            'shoes' => ['38', '39', '40', '41', '42', '43', '44', '45', '46'],
            'unique' => ['TU'],
        ];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> */
    public static function sizes(?string $scale): array
    {
        return self::all()[$scale ?? self::DEFAULT] ?? self::all()[self::DEFAULT];
    }
}