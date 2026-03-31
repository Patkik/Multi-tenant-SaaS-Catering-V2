<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantRole extends Model
{
    /** @use HasFactory<\Database\Factories\TenantRoleFactory> */
    use HasFactory, HasUuids;

    protected $table = 'tenant_roles';

    protected $fillable = ['name', 'display_name', 'description', 'is_default', 'is_protected'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_protected' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id',
            'id',
            'id'
        )
            ->using(RolePermission::class)
            ->withTimestamps();
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantFeature::class,
            'role_features',
            'role_id',
            'feature_id',
            'id',
            'id'
        )
            ->using(RoleFeature::class)
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function roleFeatures(): HasMany
    {
        return $this->hasMany(RoleFeature::class, 'role_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()
            ->where('name', $permissionName)
            ->exists();
    }

    public function hasFeature(string $featureName): bool
    {
        return $this->features()
            ->where('name', $featureName)
            ->where('is_enabled', true)
            ->exists();
    }
}
