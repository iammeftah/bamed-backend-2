<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreBrandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'label' => $this->name,
            'logo' => $this->logo,
            'productCount' => (int) ($this->active_products_count ?? 0),
        ];
    }
}