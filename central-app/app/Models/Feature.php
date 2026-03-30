<?php

namespace App\Models;

use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'category',
        'default_enabled',
        'requires_plan',
        'deprecated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_enabled' => 'boolean',
            'deprecated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FeatureOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(FeatureOverride::class);
    }
}
