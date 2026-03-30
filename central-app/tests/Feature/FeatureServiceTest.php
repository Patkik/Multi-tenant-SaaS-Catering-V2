<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\Tenant;
use App\Services\FeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_enabled_when_no_override_and_plan_allows(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_entitlements' => ['pro'],
        ]);

        $feature = Feature::factory()->create([
            'default_enabled' => true,
            'requires_plan' => 'pro',
        ]);

        $service = app(FeatureService::class);

        $this->assertTrue($service->isFeatureEnabled($tenant, $feature));
    }

    public function test_active_deny_override_disables_feature(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_entitlements' => ['pro'],
        ]);

        $feature = Feature::factory()->create([
            'default_enabled' => true,
            'requires_plan' => 'pro',
        ]);

        FeatureOverride::factory()->create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'is_enabled' => false,
            'expires_at' => now()->addDay(),
        ]);

        $service = app(FeatureService::class);

        $this->assertFalse($service->isFeatureEnabled($tenant, $feature));
    }

    public function test_expired_override_is_ignored(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_entitlements' => ['pro'],
        ]);

        $feature = Feature::factory()->create([
            'default_enabled' => true,
            'requires_plan' => 'pro',
        ]);

        FeatureOverride::factory()->create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'is_enabled' => false,
            'expires_at' => now()->subMinute(),
        ]);

        $service = app(FeatureService::class);

        $this->assertTrue($service->isFeatureEnabled($tenant, $feature));
    }

    public function test_plan_deny_disables_feature_without_override(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_entitlements' => ['starter'],
        ]);

        $feature = Feature::factory()->create([
            'default_enabled' => true,
            'requires_plan' => 'pro',
        ]);

        $service = app(FeatureService::class);

        $resolved = $service->resolveFeatureState($tenant, $feature);

        $this->assertFalse($resolved['is_enabled']);
        $this->assertSame('plan', $resolved['source']);
    }
}
