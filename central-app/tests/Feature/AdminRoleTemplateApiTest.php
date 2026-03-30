<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateFeature;
use App\Models\RoleTemplatePermission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TOKEN = 'test-central-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('central_admin.token', self::ADMIN_TOKEN);
    }

    public function test_role_template_endpoints_require_central_admin_token(): void
    {
        $tenant = Tenant::factory()->create();
        $feature = Feature::factory()->create([
            'name' => 'analytics',
        ]);
        $roleTemplate = RoleTemplate::factory()->create();

        $scenarios = [
            [
                'method' => 'GET',
                'uri' => '/api/admin/role-templates',
            ],
            [
                'method' => 'POST',
                'uri' => '/api/admin/role-templates',
                'payload' => [
                    'role_name' => 'EventSupervisor',
                    'permissions' => ['events.view'],
                    'feature_keys' => [$feature->name],
                ],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/admin/role-templates/__ROLE_TEMPLATE_ID__',
                'payload' => [
                    'description' => 'updated from matrix',
                ],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/admin/tenants/__TENANT_ID__/role-templates/__ROLE_TEMPLATE_ID__/apply',
            ],
        ];

        foreach ($scenarios as $scenario) {
            $uri = str_replace(
                ['__TENANT_ID__', '__ROLE_TEMPLATE_ID__'],
                [$tenant->id, $roleTemplate->id],
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

    public function test_role_template_endpoints_fail_closed_when_configured_token_is_unset_or_empty(): void
    {
        foreach ([null, ''] as $configuredToken) {
            config()->set('central_admin.token', $configuredToken);

            $response = $this->withHeaders([
                'X-Central-Admin-Key' => self::ADMIN_TOKEN,
            ])->getJson('/api/admin/role-templates');

            $response
                ->assertForbidden()
                ->assertJsonPath('message', 'Invalid central admin token.');
        }
    }

    public function test_role_template_list_endpoint_returns_data(): void
    {
        RoleTemplate::factory()->count(2)->create();

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/role-templates');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_create_persists_permissions_and_features(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);
        Feature::factory()->create([
            'name' => 'usage-monitoring',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => 'EventSupervisor',
            'description' => 'Role template for event supervisors',
            'permissions' => ['events.view', 'events.update', 'staff.assign'],
            'feature_keys' => ['analytics', 'usage-monitoring'],
        ]);

        $roleTemplateId = (string) $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'EventSupervisor')
            ->assertJsonPath('data.created_by_admin', 'central-admin-token')
            ->assertJsonCount(3, 'data.permissions')
            ->assertJsonCount(2, 'data.features');

        $this->assertDatabaseHas('role_template_permissions', [
            'role_template_id' => $roleTemplateId,
            'role_name' => 'EventSupervisor',
            'permission' => 'events.view',
        ]);

        $this->assertDatabaseHas('role_template_features', [
            'role_template_id' => $roleTemplateId,
            'role_name' => 'EventSupervisor',
            'feature_key' => 'analytics',
            'is_enabled' => true,
        ]);
    }

    public function test_update_replaces_nested_bindings(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);
        Feature::factory()->create([
            'name' => 'usage-monitoring',
        ]);

        $roleTemplate = RoleTemplate::factory()->create([
            'name' => 'EventSupervisor',
        ]);

        RoleTemplatePermission::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'EventSupervisor',
            'permission' => 'events.view',
        ]);
        RoleTemplateFeature::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'EventSupervisor',
            'feature_key' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->patchJson('/api/admin/role-templates/'.$roleTemplate->id, [
            'permissions' => ['staff.assign'],
            'feature_keys' => ['usage-monitoring'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.created_by_admin', 'central-admin-token')
            ->assertJsonCount(1, 'data.permissions')
            ->assertJsonCount(1, 'data.features')
            ->assertJsonPath('data.permissions.0.permission', 'staff.assign')
            ->assertJsonPath('data.features.0.feature_key', 'usage-monitoring');

        $this->assertDatabaseMissing('role_template_permissions', [
            'role_template_id' => $roleTemplate->id,
            'permission' => 'events.view',
        ]);
        $this->assertDatabaseMissing('role_template_features', [
            'role_template_id' => $roleTemplate->id,
            'feature_key' => 'analytics',
        ]);
    }

    public function test_update_allows_clearing_nullable_fields_with_explicit_null(): void
    {
        $roleTemplate = RoleTemplate::factory()->create([
            'description' => 'Initial description',
            'metadata' => ['source' => 'seed'],
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->patchJson('/api/admin/role-templates/'.$roleTemplate->id, [
            'description' => null,
            'metadata' => null,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.metadata', null);

        $this->assertDatabaseHas('role_templates', [
            'id' => $roleTemplate->id,
            'description' => null,
            'metadata' => null,
        ]);
    }

    public function test_apply_endpoint_returns_not_implemented_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $roleTemplate = RoleTemplate::factory()->create();

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenant->id.'/role-templates/'.$roleTemplate->id.'/apply');

        $response
            ->assertStatus(501)
            ->assertJsonPath('status', 'not_implemented')
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('role_template_id', $roleTemplate->id);
    }

    public function test_create_rejects_role_name_longer_than_100_characters(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => str_repeat('a', 101),
            'permissions' => ['events.view'],
            'feature_keys' => ['analytics'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_name']);
    }

    public function test_create_ignores_client_supplied_created_by_admin_value(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => 'KitchenSupervisor',
            'created_by_admin' => 'spoofed-user@example.com',
            'permissions' => ['events.view'],
            'feature_keys' => ['analytics'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created_by_admin', 'central-admin-token');
    }

    public function test_create_rejects_permission_without_dot_notation(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => 'OpsAssistant',
            'permissions' => ['eventsview'],
            'feature_keys' => ['analytics'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    }
}
