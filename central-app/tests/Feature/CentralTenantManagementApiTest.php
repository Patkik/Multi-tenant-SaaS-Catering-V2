<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralTenantManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
    }

    public function test_it_requires_authentication_for_central_tenant_routes(): void
    {
        $response = $this->getJson('/api/central/tenants');

        $response->assertUnauthorized();
    }

    public function test_it_returns_forbidden_when_permission_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $response = $this->getJson('/api/central/tenants');

        $response->assertForbidden();
    }

    private function actingAsCentralManager(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            CentralPermissions::DASHBOARD_VIEW,
            CentralPermissions::PLANS_VIEW,
            CentralPermissions::TENANTS_VIEW,
            CentralPermissions::TENANTS_MANAGE,
        ]);

        Sanctum::actingAs($user, ['*']);
    }

    public function test_it_lists_tenants_for_central_management(): void
    {
        $this->actingAsCentralManager();

        $tenant = Tenant::create([
            'id' => 'acme-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'free',
            'enabled_features' => ['event_management'],
        ]);

        $tenant->domains()->create(['domain' => 'acme']);

        $response = $this->getJson('/api/central/tenants');

        $response->assertOk()
            ->assertJsonPath('data.data.0.tenant_id', 'acme-tenant')
            ->assertJsonPath('data.data.0.company_name', 'Acme Catering')
            ->assertJsonPath('data.data.0.full_domain', 'acme.localhost')
            ->assertJsonPath('data.data.0.client_access', false);
    }

    public function test_it_updates_tenant_plan_and_enabled_features(): void
    {
        $this->actingAsCentralManager();

        $tenant = Tenant::create([
            'id' => 'acme-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'free',
            'enabled_features' => ['event_management'],
        ]);

        $response = $this->patchJson('/api/central/tenants/acme-tenant/plan', [
            'plan' => 'business',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.plan', 'business')
            ->assertJsonPath('data.client_access', true)
            ->assertJsonPath('data.feature_flags.advanced_analytics', true)
            ->assertJsonPath('data.feature_flags.branding_controls', true);

        $tenant->refresh();

        $this->assertSame('business', $tenant->getAttribute('plan'));
    }

    public function test_it_updates_tenant_branding_metadata(): void
    {
        $this->actingAsCentralManager();

        $tenant = Tenant::create([
            'id' => 'acme-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'starter',
            'branding' => [
                'primary_color' => '#0B8F66',
            ],
        ]);

        $response = $this->patchJson('/api/central/tenants/acme-tenant/branding', [
            'company_name' => 'Acme Catering and Events',
            'primary_color' => '#1F4EAA',
            'logo_url' => 'https://example.com/acme-logo.png',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company_name', 'Acme Catering and Events')
            ->assertJsonPath('data.branding.primary_color', '#1F4EAA')
            ->assertJsonPath('data.branding.logo_url', 'https://example.com/acme-logo.png');

        $tenant->refresh();

        $this->assertSame('Acme Catering and Events', $tenant->getAttribute('company_name'));
        $this->assertSame('#1F4EAA', data_get($tenant->getAttribute('branding'), 'primary_color'));
    }
}
