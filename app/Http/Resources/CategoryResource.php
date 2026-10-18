<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Admin shape. createdAt is in epoch milliseconds.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'sizeScale' => $this->size_scale,
            'image' => $this->image,
            'createdAt' => $this->created_at ? $this->created_at->timestamp * 1000 : 0,
        ];
    }
}