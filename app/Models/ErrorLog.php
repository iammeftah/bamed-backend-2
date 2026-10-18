<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    /** The table only has created_at. */
    const UPDATED_AT = null;

    protected $fillable = [
        'class',
        'message',
        'location',
        'method',
        'path',
        'admin_id',
        'trace',
    ];
}