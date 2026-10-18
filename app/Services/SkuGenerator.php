<?php

namespace App\Services;

use App\Models\Product;

class SkuGenerator
{
    /** Next free "BAM-1001", "BAM-1002"... */
    public function next(): string
    {
        $max = Product::query()
            ->where('sku', 'like', 'BAM-%')
            ->pluck('sku')
            ->map(fn (string $sku) => (int) preg_replace('/\D/', '', $sku))
            ->max() ?? 1000;

        return 'BAM-'.(max($max, 1000) + 1);
    }
}