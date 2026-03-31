<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenantFeature extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'display_name', 'description', 'category'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            TenantRole::class,
            'role_features',
            'feature_id',
            'role_id',
            'id',
            'id'
        )
            ->using(RoleFeature::class)
            ->withPivot('is_enabled')
            ->withTimestamps();
    }
}
