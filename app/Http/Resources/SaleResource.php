<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'at' => $this->created_at ? $this->created_at->getTimestamp() * 1000 : 0,
            'productId' => (string) ($this->product_id ?? ''),
            'productName' => $this->product_name,
            'categoryId' => (string) ($this->category_id ?? ''),
            'qty' => $this->qty,
            'unitPrice' => $this->unit_price,
            'unitCost' => $this->unit_cost,
            'total' => $this->total,
            'soldById' => (string) ($this->sold_by_id ?? ''),
            'soldByName' => $this->sold_by_name,
        ];
    }
}