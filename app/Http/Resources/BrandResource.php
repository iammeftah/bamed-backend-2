<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'productCount' => (int) ($this->products_count ?? 0),
            'createdAt' => $this->created_at ? $this->created_at->getTimestamp() * 1000 : 0,
        ];
    }
}