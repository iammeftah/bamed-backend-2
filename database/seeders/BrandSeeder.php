<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // Logo files already in frontend/public/images/brands
        $brands = [
            'Lacoste' => 'lacoste.svg',
            'Polo Ralph Lauren' => 'polo-ralph-lauren.svg',
            'Tommy Hilfiger' => 'tommy-hilfiger.svg',
            'Zara' => 'zara.svg',
            'The North Face' => 'the-north-face.svg',
            'Adidas' => 'adidas.svg',
            'Puma' => 'puma.svg',
            'Reebok' => 'reebok.svg',
        ];

        foreach ($brands as $name => $file) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'logo' => "/images/brands/{$file}"],
            );
        }
    }
}