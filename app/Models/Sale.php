<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    /** The table only has created_at. */
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'product_name',
        'category_id',
        'qty',
        'unit_price',
        'unit_cost',
        'total',
        'sold_by_id',
        'sold_by_name',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'float',
            'unit_cost' => 'float',
            'total' => 'float',
        ];
    }
}