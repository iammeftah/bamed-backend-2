<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER = 'super_admin';

    /** "role" is NOT fillable on purpose: it can only be set with forceFill (seeder). */
    protected $fillable = ['name', 'phone', 'password', 'active'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER;
    }

    /** Everybody except the super admin (the shop team). */
    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('role', '!=', self::ROLE_SUPER);
    }
}