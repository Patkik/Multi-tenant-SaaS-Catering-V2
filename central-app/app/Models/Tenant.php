<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'domain',
        'database_name',
        'plan_code',
        'plan_entitlements',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_entitlements' => 'array',
        ];
    }

    /**
     * @return HasMany<FeatureOverride, $this>
     */
    public function featureOverrides(): HasMany
    {
        return $this->hasMany(FeatureOverride::class);
    }

    public function hasPlanEntitlement(string $requiredPlan): bool
    {
        $entitlements = $this->plan_entitlements ?? [];

        return in_array($requiredPlan, $entitlements, true);
    }
}
