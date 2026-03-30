<?php

namespace App\Models;

use Database\Factories\RoleTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleTemplate extends Model
{
    /** @use HasFactory<RoleTemplateFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_system_default',
        'created_by_admin',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<RoleTemplatePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RoleTemplatePermission::class);
    }

    /**
     * @return HasMany<RoleTemplateFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(RoleTemplateFeature::class);
    }
}
