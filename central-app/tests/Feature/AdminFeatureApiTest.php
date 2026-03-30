<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_list_endpoint_returns_data(): void
    {
        Feature::factory()->count(2)->create();

        $response = $this->getJson('/api/admin/features');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_effective_features_endpoint_applies_override_precedence(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_entitlements' => ['pro'],
        ]);

        $feature = Feature::factory()->create([
            'name' => 'analytics',
            'default_enabled' => true,
            'requires_plan' => 'pro',
        ]);

        FeatureOverride::factory()->create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'is_enabled' => false,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->getJson('/api/admin/tenants/'.$tenant->id.'/effective-features');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.feature_name', 'analytics')
            ->assertJsonPath('data.0.is_enabled', false)
            ->assertJsonPath('data.0.source', 'override');
    }
}
