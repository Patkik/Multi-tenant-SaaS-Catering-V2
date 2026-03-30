<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FeatureService
{
    public function isFeatureEnabled(Tenant $tenant, Feature $feature, ?Carbon $referenceTime = null): bool
    {
        return $this->resolveFeatureState($tenant, $feature, $referenceTime)['is_enabled'];
    }

    /**
     * @return array{feature_id:string, feature_name:string, is_enabled:bool, source:string}
     */
    public function resolveFeatureState(Tenant $tenant, Feature $feature, ?Carbon $referenceTime = null): array
    {
        $now = $referenceTime ?? now();
        $override = FeatureOverride::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->first();

        // 1) Active override always wins.
        if ($override !== null && $override->isActive($now)) {
            return [
                'feature_id' => (string) $feature->id,
                'feature_name' => $feature->name,
                'is_enabled' => $override->is_enabled,
                'source' => 'override',
            ];
        }

        // 2) If no active override, plan entitlement check decides deny.
        if ($feature->requires_plan !== null && ! $tenant->hasPlanEntitlement($feature->requires_plan)) {
            return [
                'feature_id' => (string) $feature->id,
                'feature_name' => $feature->name,
                'is_enabled' => false,
                'source' => 'plan',
            ];
        }

        // 3) Fallback to default catalog value.
        return [
            'feature_id' => (string) $feature->id,
            'feature_name' => $feature->name,
            'is_enabled' => $feature->default_enabled,
            'source' => 'default',
        ];
    }

    /**
     * @return Collection<int, array{feature_id:string, feature_name:string, is_enabled:bool, source:string}>
     */
    public function resolveEffectiveFeatures(Tenant $tenant): Collection
    {
        return Feature::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Feature $feature): array => $this->resolveFeatureState($tenant, $feature));
    }
}
