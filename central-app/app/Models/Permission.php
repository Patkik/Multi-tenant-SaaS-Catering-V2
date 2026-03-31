<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'display_name', 'description', 'category'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantRole::class,
            'role_permissions',
            'permission_id',
            'role_id',
            'id',
            'id'
        )
            ->using(RolePermission::class)
            ->withTimestamps();
    }
}
