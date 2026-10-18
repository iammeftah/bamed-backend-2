<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public shape. Never exposes cost, stock or sku. Needs category and colors.images loaded. */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isNew = $this->created_at && $this->created_at->gt(now()->subDays(30));
        $tag = $isNew ? 'Nouveau' : ($this->old_price !== null ? 'Promo' : null);

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'brand' => $this->brand?->name,
            'brandId' => $this->brand?->slug,
            'category' => $this->category?->name ?? '',
            'type' => $this->category?->slug ?? '',
            'sizeScale' => $this->category?->size_scale ?? 'letters',
            'price' => $this->price,
            'oldPrice' => $this->when($this->old_price !== null, $this->old_price),
            'tag' => $this->when($tag !== null, $tag),
            'featured' => (bool) $this->featured,
            'addedAt' => $this->created_at ? $this->created_at->getTimestamp() * 1000 : 0,
            'colors' => $this->colorsPayload(),
            'sizes' => $this->sizes ?? [],
        ];
    }
}