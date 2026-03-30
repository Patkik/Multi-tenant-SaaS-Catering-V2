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

    private const ADMIN_TOKEN = 'test-central-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('central_admin.token', self::ADMIN_TOKEN);
    }

    public function test_admin_endpoints_require_central_admin_token(): void
    {
        $tenant = Tenant::factory()->create();
        $feature = Feature::factory()->create();

        $scenarios = [
            [
                'method' => 'GET',
                'uri' => '/api/admin/features',
            ],
            [
                'method' => 'POST',
                'uri' => '/api/admin/features',
                'payload' => [
                    'name' => 'new-feature',
                    'category' => 'Core',
                    'default_enabled' => true,
                ],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/admin/features/__FEATURE_ID__',
                'payload' => [
                    'description' => 'updated',
                ],
            ],
            [
                'method' => 'GET',
                'uri' => '/api/admin/tenants/__TENANT_ID__/effective-features',
            ],
        ];

        foreach ($scenarios as $scenario) {
            $uri = str_replace(
                ['__FEATURE_ID__', '__TENANT_ID__'],
                [$feature->id, $tenant->id],
                $scenario['uri'],
            );

            $payload = $scenario['payload'] ?? [];

            $this->flushHeaders();
            $this->json($scenario['method'], $uri, $payload)
                ->assertUnauthorized();

            $this->flushHeaders();
            $this->withHeaders([
                'X-Central-Admin-Key' => 'wrong-token',
            ])->json($scenario['method'], $uri, $payload)
                ->assertForbidden();
        }
    }

    public function test_admin_endpoints_fail_closed_when_configured_token_is_unset_or_empty(): void
    {
        foreach ([null, ''] as $configuredToken) {
            config()->set('central_admin.token', $configuredToken);

            $response = $this->withHeaders([
                'X-Central-Admin-Key' => self::ADMIN_TOKEN,
            ])->getJson('/api/admin/features');

            $response
                ->assertForbidden()
                ->assertJsonPath('message', 'Invalid central admin token.');
        }
    }

    public function test_feature_list_endpoint_returns_data(): void
    {
        Feature::factory()->count(2)->create();

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/features');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_feature_create_and_update_endpoints_allow_authorized_token(): void
    {
        $feature = Feature::factory()->create();

        $createResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/features', [
            'name' => 'tenant-alerts',
            'description' => 'Centralized alerts for tenants',
            'category' => 'Admin',
            'default_enabled' => true,
            'requires_plan' => null,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'tenant-alerts');

        $updateResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->patchJson('/api/admin/features/'.$feature->id, [
            'description' => 'updated from test',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.description', 'updated from test');
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

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/tenants/'.$tenant->id.'/effective-features');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.feature_name', 'analytics')
            ->assertJsonPath('data.0.is_enabled', false)
            ->assertJsonPath('data.0.source', 'override');
    }
}
