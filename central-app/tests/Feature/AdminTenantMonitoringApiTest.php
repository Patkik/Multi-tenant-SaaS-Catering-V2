<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateApplication;
use App\Models\RoleTemplatePermission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TOKEN = 'test-central-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('central_admin.token', self::ADMIN_TOKEN);
    }

    public function test_monitoring_endpoint_requires_central_admin_token(): void
    {
        $tenant = Tenant::factory()->create();

        $this->flushHeaders();
        $this->getJson('/api/admin/tenants/'.$tenant->id.'/monitoring')
            ->assertUnauthorized();

        $this->flushHeaders();
        $this->withHeaders([
            'X-Central-Admin-Key' => 'wrong-token',
        ])->getJson('/api/admin/tenants/'.$tenant->id.'/monitoring')
            ->assertForbidden();
    }

    public function test_monitoring_endpoint_returns_stable_payload_shape(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Northwind Catering',
            'domain' => 'northwind.localhost:8080',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/tenants/'.$tenant->id.'/monitoring');

        $response
            ->assertOk()
            ->assertJsonPath('tenant_domain', 'northwind.localhost:8080')
            ->assertJsonPath('tenant_name', 'Northwind Catering')
            ->assertJsonPath('admin_name', null)
            ->assertJsonPath('users_total', 0)
            ->assertJsonPath('active_roles', [])
            ->assertJsonPath('usage_snapshot_summary', null)
            ->assertJsonStructure([
                'tenant_domain',
                'tenant_name',
                'admin_name',
                'users_total',
                'active_roles',
                'active_features_count',
                'deactivated_features_count',
                'usage_snapshot_summary',
            ]);
    }

    public function test_monitoring_endpoint_reports_active_and_deactivated_feature_counts(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_entitlements' => ['starter'],
        ]);

        $alwaysOnFeature = Feature::factory()->create([
            'name' => 'analytics',
            'default_enabled' => true,
            'requires_plan' => null,
        ]);
        $premiumFeature = Feature::factory()->create([
            'name' => 'priority-support',
            'default_enabled' => true,
            'requires_plan' => 'pro',
        ]);
        $optInFeature = Feature::factory()->create([
            'name' => 'advanced-audit',
            'default_enabled' => false,
            'requires_plan' => null,
        ]);

        FeatureOverride::factory()->create([
            'tenant_id' => $tenant->id,
            'feature_id' => $optInFeature->id,
            'is_enabled' => true,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/tenants/'.$tenant->id.'/monitoring');

        $response
            ->assertOk()
            ->assertJsonPath('active_features_count', 2)
            ->assertJsonPath('deactivated_features_count', 1);
    }

    public function test_monitoring_endpoint_uses_latest_applied_application_for_active_roles(): void
    {
        $tenant = Tenant::factory()->create();

        $olderTemplate = RoleTemplate::factory()->create();
        RoleTemplatePermission::factory()->create([
            'role_template_id' => $olderTemplate->id,
            'role_name' => 'OlderRole',
            'permission' => 'events.view',
        ]);
        RoleTemplateApplication::query()->create([
            'tenant_id' => $tenant->id,
            'role_template_id' => $olderTemplate->id,
            'strategy' => 'merge',
            'status' => RoleTemplateApplication::STATUS_APPLIED,
            'requested_by_admin' => 'old-admin@central.test',
            'applied_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $latestAppliedTemplate = RoleTemplate::factory()->create();
        RoleTemplatePermission::factory()->create([
            'role_template_id' => $latestAppliedTemplate->id,
            'role_name' => 'KitchenManager',
            'permission' => 'events.view',
        ]);
        RoleTemplatePermission::factory()->create([
            'role_template_id' => $latestAppliedTemplate->id,
            'role_name' => 'KitchenManager',
            'permission' => 'events.update',
        ]);
        RoleTemplateApplication::query()->create([
            'tenant_id' => $tenant->id,
            'role_template_id' => $latestAppliedTemplate->id,
            'strategy' => 'replace',
            'status' => RoleTemplateApplication::STATUS_APPLIED,
            'requested_by_admin' => 'applied-admin@central.test',
            'applied_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        RoleTemplateApplication::query()->create([
            'tenant_id' => $tenant->id,
            'role_template_id' => $latestAppliedTemplate->id,
            'strategy' => 'replace',
            'status' => RoleTemplateApplication::STATUS_QUEUED,
            'requested_by_admin' => 'latest-admin@central.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/tenants/'.$tenant->id.'/monitoring');

        $response
            ->assertOk()
            ->assertJsonPath('admin_name', 'latest-admin@central.test')
            ->assertJsonPath('active_roles.0', 'KitchenManager')
            ->assertJsonCount(1, 'active_roles');
    }
}
