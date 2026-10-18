<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Same list as sampleCategories in frontend/lib/products.ts
        $categories = [
            ['name' => 'Hoodies', 'size_scale' => 'letters', 'description' => 'Hoodies et sweats à capuche, coupes oversize et classiques.'],
            ['name' => 'T-shirts', 'size_scale' => 'letters', 'description' => 'Basiques et graphiques en coton, du quotidien.'],
            ['name' => 'Jeans', 'size_scale' => 'jeans', 'description' => 'Coupes droites, baggy et ajustées, tailles W28 à W40.'],
            ['name' => 'Pantalons', 'size_scale' => 'pants', 'description' => 'Cargos, joggings et pantalons fluides.', 'image' => '/images/categories/trousers.jpg'],
            ['name' => 'Vestes', 'size_scale' => 'letters', 'description' => 'Vestes, coupe-vents et surchemises de saison.', 'image' => '/images/categories/outerwear.jpg'],
            ['name' => 'Sneakers', 'size_scale' => 'shoes', 'description' => 'Sneakers et baskets, pointures 38 à 46.'],
            ['name' => 'Chemises', 'size_scale' => 'letters', 'description' => 'Oxford, lin, coupes classiques et décontractées.', 'image' => '/images/categories/shirts.jpg'],
            ['name' => 'Polos', 'size_scale' => 'letters', 'description' => 'Piqué et maille légère.'],
            ['name' => 'Maille', 'size_scale' => 'letters', 'description' => 'Pulls et mailles fines.', 'image' => '/images/categories/knitwear.jpg'],
            ['name' => 'Accessoires', 'size_scale' => 'unique', 'description' => 'Casquettes, sacs et accessoires en taille unique.'],
        ];

        foreach ($categories as $c) {
            Category::updateOrCreate(
                ['slug' => Str::slug($c['name'])],
                $c + ['image' => null],
            );
        }
    }
}