<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    /** The table only has created_at. */
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'action',
        'entity',
        'entity_id',
        'summary',
    ];
}