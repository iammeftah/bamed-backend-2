<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'brand_id',
        'category_id',
        'price',
        'old_price',
        'cost',
        'stock',
        'low_stock_at',
        'sizes',
        'featured',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'old_price' => 'float',
            'cost' => 'float',
            'stock' => 'integer',
            'low_stock_at' => 'integer',
            'sizes' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('position');
    }

    /** Replaces every colour (and its photos) with the given list, keeping the order. */
    public function syncColors(array $colors): void
    {
        ProductColor::where('product_id', $this->id)->delete();

        foreach (array_values($colors) as $i => $c) {
            $color = $this->colors()->create([
                'name' => trim($c['name']),
                'hex' => strtolower($c['hex']),
                'position' => $i,
            ]);

            foreach (array_values($c['images'] ?? []) as $n => $img) {
                $color->images()->create([
                    'src' => $img['src'],
                    'alt' => $img['alt'] ?? '',
                    'position' => $n,
                ]);
            }
        }
    }

    /** Every photo URL of this product, read from the database (not from loaded relations). */
    public function imageUrls(): array
    {
        return ProductImage::query()
            ->whereIn('product_color_id', ProductColor::where('product_id', $this->id)->select('id'))
            ->pluck('src')
            ->all();
    }

    /** Colours with their photos, in the shape the frontend expects. Needs colors.images loaded. */
    public function colorsPayload(): array
    {
        return $this->colors->map(fn (ProductColor $c) => [
            'name' => $c->name,
            'hex' => $c->hex,
            'images' => $c->images->map(fn (ProductImage $i) => [
                'src' => $i->src,
                'alt' => $i->alt,
            ])->values()->all(),
        ])->values()->all();
    }
}