<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'size_scale', 'image'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** How many products use this category (a category in use can't be deleted). */
    public function productsCount(): int
    {
        return $this->products()->count();
    }
}