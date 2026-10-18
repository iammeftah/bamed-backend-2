<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    private const COLORS = [
        'Blanc' => '#f3f3f1',
        'Noir' => '#111111',
        'Marine' => '#1f2a44',
        'Beige' => '#c8b79a',
        'Gris' => '#8a8a8a',
        'Anthracite' => '#3a3a3c',
        'Olive' => '#5b6340',
        'Bleu ciel' => '#a9c4de',
        'Sable' => '#d6c7a8',
        'Sauge' => '#a3b18a',
        'Rouge' => '#b3262e',
        'Bleu' => '#2f5da8',
    ];

    public function run(): void
    {
        $tops = ['S', 'M', 'L', 'XL', 'XXL'];
        $bottoms = ['38', '40', '42', '44'];
        $jeans = ['30', '32', '34', '36'];
        $shoes = ['40', '41', '42', '43', '44'];

        // id, name, brand, category slug, price, old price, days ago, featured, stock, sizes, [colour => photos]
        // Brand names must match BrandSeeder. Photo paths are placeholders: missing files fall back to the placeholder.
        $items = [
            ['oxford-shirt', 'Chemise Oxford', 'Polo Ralph Lauren', 'chemises', 490, null, 1, true, 34, $tops, ['Bleu ciel' => 3, 'Blanc' => 2]],
            ['linen-camp-shirt', 'Chemise Camp en Lin', 'Zara', 'chemises', 420, null, 2, false, 8, $tops, ['Sable' => 2, 'Sauge' => 2]],
            ['heavyweight-tee', 'T-shirt Épais', 'Adidas', 't-shirts', 220, null, 45, false, 64, $tops, ['Noir' => 2, 'Blanc' => 2, 'Gris' => 2]],
            ['logo-tee', 'T-shirt Logo', 'Tommy Hilfiger', 't-shirts', 260, 320, 20, false, 40, $tops, ['Blanc' => 2, 'Marine' => 2]],
            ['pique-polo', 'Polo Piqué', 'Lacoste', 'polos', 330, null, 3, false, 27, $tops, ['Marine' => 2, 'Blanc' => 2]],
            ['classic-polo', 'Polo Classique', 'Polo Ralph Lauren', 'polos', 540, null, 12, true, 18, $tops, ['Rouge' => 2, 'Marine' => 2]],
            ['tailored-chinos', 'Chino Ajusté', 'Zara', 'pantalons', 590, 720, 50, false, 3, $bottoms, ['Beige' => 3, 'Marine' => 2]],
            ['relaxed-trousers', 'Pantalon Fluide à Pinces', 'Zara', 'pantalons', 620, null, 4, false, 21, $bottoms, ['Noir' => 2, 'Beige' => 2]],
            ['track-pants', 'Pantalon de Survêtement', 'Puma', 'pantalons', 350, null, 6, false, 30, $bottoms, ['Noir' => 2, 'Gris' => 2]],
            ['straight-jeans', 'Jean Coupe Droite', 'Tommy Hilfiger', 'jeans', 690, 790, 15, false, 22, $jeans, ['Bleu' => 2, 'Noir' => 2]],
            ['wool-overshirt', 'Surchemise en Laine Mélangée', 'Lacoste', 'vestes', 890, null, 40, true, 15, $tops, ['Anthracite' => 2, 'Olive' => 2]],
            ['shell-jacket', 'Veste Coupe-Vent', 'The North Face', 'vestes', 1190, null, 5, true, 12, $tops, ['Noir' => 3, 'Rouge' => 2]],
            ['merino-crew-knit', 'Pull Mérinos Col Rond', 'Polo Ralph Lauren', 'maille', 640, 790, 55, true, 0, $tops, ['Marine' => 2, 'Gris' => 2]],
            ['pullover-hoodie', 'Hoodie à Capuche', 'Reebok', 'hoodies', 480, null, 8, false, 26, $tops, ['Gris' => 2, 'Noir' => 2]],
            ['court-sneakers', 'Sneakers Court', 'Adidas', 'sneakers', 850, null, 9, true, 14, $shoes, ['Blanc' => 3, 'Noir' => 2]],
            ['runner-sneakers', 'Sneakers Running', 'Puma', 'sneakers', 790, 950, 25, false, 10, $shoes, ['Gris' => 2, 'Rouge' => 2]],
            ['classic-trainers', 'Baskets Classiques', 'Reebok', 'sneakers', 720, null, 30, false, 9, $shoes, ['Blanc' => 2, 'Beige' => 2]],
        ];

        foreach ($items as $i => [$id, $name, $brandName, $slug, $price, $old, $days, $featured, $stock, $sizes, $colors]) {
            $categoryId = Category::where('slug', $slug)->value('id');
            if (! $categoryId) {
                throw new \RuntimeException("Run CategorySeeder first (missing category {$slug}).");
            }

            $brandId = Brand::where('slug', Str::slug($brandName))->value('id');
            if (! $brandId) {
                throw new \RuntimeException("Run BrandSeeder first (missing brand {$brandName}).");
            }

            $product = Product::firstOrNew(['sku' => 'BAM-'.(1001 + $i)]);
            $product->fill([
                'name' => $name,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'price' => $price,
                'old_price' => $old,
                'cost' => round($price * 0.52),
                'stock' => $stock,
                'low_stock_at' => 10,
                'sizes' => $sizes,
                'featured' => $featured,
                'active' => true,
            ]);
            $product->forceFill(['created_at' => now()->subDays($days)]);
            $product->save();

            $product->syncColors(array_map(
                fn (string $colorName, int $count) => $this->variant($id, $colorName, $count, $name),
                array_keys($colors),
                array_values($colors),
            ));
        }
    }

    /** @return array{name: string, hex: string, images: list<array{src: string, alt: string}>} */
    private function variant(string $productId, string $name, int $count, string $productName): array
    {
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'hex' => self::COLORS[$name],
            'images' => array_map(fn (int $n) => [
                'src' => "/images/products/{$productId}/{$slug}-{$n}.jpg",
                'alt' => "{$productName}, ".mb_strtolower($name).", photo {$n}",
            ], range(1, $count)),
        ];
    }
}