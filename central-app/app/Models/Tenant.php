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
        'provisioning_status',
        'provisioning_error',
        'provisioned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_entitlements' => 'array',
            'provisioned_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FeatureOverride, $this>
     */
    public function featureOverrides(): HasMany
    {
        return $this->hasMany(FeatureOverride::class);
    }

    /**
     * @return HasMany<RoleTemplateApplication, $this>
     */
    public function roleTemplateApplications(): HasMany
    {
        return $this->hasMany(RoleTemplateApplication::class);
    }

    /**
     * @return HasMany<TenantContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(TenantContact::class);
    }

    /**
     * @return HasMany<UsageSnapshot, $this>
     */
    public function usageSnapshots(): HasMany
    {
        return $this->hasMany(UsageSnapshot::class);
    }

    /**
     * @return HasMany<RBACChangeAudit, $this>
     */
    public function rbacAudits(): HasMany
    {
        return $this->hasMany(RBACChangeAudit::class);
    }

    public function hasPlanEntitlement(string $requiredPlan): bool
    {
        $entitlements = $this->plan_entitlements ?? [];

        return in_array($requiredPlan, $entitlements, true);
    }
}
