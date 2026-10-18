<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Admin shape (StockProduct). Needs colors.images loaded. */
class StockProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brandId' => $this->brand_id ? (string) $this->brand_id : null,
            'categoryId' => (string) $this->category_id,
            'colors' => $this->colorsPayload(),
            'price' => $this->price,
            'oldPrice' => $this->old_price,
            'cost' => $this->cost,
            'stock' => $this->stock,
            'lowStockAt' => $this->low_stock_at,
            'sizes' => $this->sizes ?? [],
            'active' => (bool) $this->active,
            'createdAt' => $this->created_at ? $this->created_at->getTimestamp() * 1000 : 0,
        ];
    }
}