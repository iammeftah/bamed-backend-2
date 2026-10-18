<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    /** The table only has created_at. */
    const UPDATED_AT = null;

    protected $fillable = ['method', 'route', 'status', 'duration_ms', 'ip', 'admin_id'];
}