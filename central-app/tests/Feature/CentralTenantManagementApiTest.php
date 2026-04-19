<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CentralPermissions;
use App\Support\TenantRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CentralTenantManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep tests on landlord sqlite connection while allowing tenancy initialization.
        config()->set('tenancy.bootstrappers', []);

        $this->ensureTenantUserColumns();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        foreach (TenantRoles::all() as $roleName) {
            Role::findOrCreate($roleName, 'web');
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

    public function test_it_returns_tenant_edit_context_with_feature_catalog_and_users(): void
    {
        $this->actingAsCentralManager();

        $tenant = Tenant::create([
            'id' => 'acme-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'business',
            'enabled_features' => ['event_management', 'client_portal', 'staff_assignment', 'advanced_analytics', 'branding_controls'],
            'client_access' => true,
        ]);

        $tenant->domains()->create(['domain' => 'acme']);

        $tenantUser = User::query()->create([
            'name' => 'Tenant Staff',
            'username' => 'tenant.staff',
            'firstname' => 'Tenant',
            'lastname' => 'Staff',
            'email' => 'tenant.staff@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $tenantUser->syncRoles([TenantRoles::STAFF]);

        $response = $this->getJson('/api/central/tenants/acme-tenant');

        $response->assertOk()
            ->assertJsonPath('data.tenant.tenant_id', 'acme-tenant')
            ->assertJsonPath('data.tenant.plan', 'business')
            ->assertJsonPath('data.tenant.subdomain', 'acme')
            ->assertJsonPath('data.available_plans.0.key', 'free')
            ->assertJsonPath('data.feature_catalog.0.key', 'event_management')
            ->assertJsonPath('data.available_roles.0', TenantRoles::ADMIN)
            ->assertJsonFragment([
                'id' => $tenantUser->id,
                'username' => 'tenant.staff',
                'role' => TenantRoles::STAFF,
            ]);
    }

    public function test_it_updates_tenant_core_details_from_central_edit_page(): void
    {
        $this->actingAsCentralManager();

        $tenant = Tenant::create([
            'id' => 'acme-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'free',
            'enabled_features' => ['event_management'],
            'client_access' => false,
            'is_active' => true,
        ]);

        $tenant->domains()->create(['domain' => 'acme']);

        $response = $this->patchJson('/api/central/tenants/acme-tenant', [
            'company_name' => 'Acme Premium Catering',
            'subdomain' => 'acme-premium',
            'plan' => 'starter',
            'enabled_features' => ['event_management', 'client_portal'],
            'client_access' => true,
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company_name', 'Acme Premium Catering')
            ->assertJsonPath('data.subdomain', 'acme-premium')
            ->assertJsonPath('data.plan', 'starter')
            ->assertJsonPath('data.client_access', true)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.feature_flags.client_portal', true)
            ->assertJsonPath('data.feature_flags.advanced_analytics', false);

        $tenant->refresh();

        $this->assertSame('Acme Premium Catering', $tenant->getAttribute('company_name'));
        $this->assertSame('starter', $tenant->getAttribute('plan'));
        $this->assertSame(false, (bool) $tenant->getAttribute('is_active'));
        $this->assertSame('acme-premium', (string) $tenant->domains()->first()?->domain);
    }

    public function test_it_lists_and_updates_tenant_users_from_central_console(): void
    {
        $this->actingAsCentralManager();

        $tenant = Tenant::create([
            'id' => 'acme-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'starter',
            'enabled_features' => ['event_management', 'client_portal', 'staff_assignment'],
        ]);

        $tenant->domains()->create(['domain' => 'acme']);

        $tenantUser = User::query()->create([
            'name' => 'Tenant User',
            'username' => 'tenant.user',
            'firstname' => 'Tenant',
            'lastname' => 'User',
            'email' => 'tenant.user@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $tenantUser->syncRoles([TenantRoles::STAFF]);

        $listResponse = $this->getJson('/api/central/tenants/acme-tenant/users');

        $listResponse->assertOk()->assertJsonFragment([
            'id' => $tenantUser->id,
            'username' => 'tenant.user',
            'role' => TenantRoles::STAFF,
        ]);

        $updateResponse = $this->patchJson('/api/central/tenants/acme-tenant/users/'.$tenantUser->id, [
            'firstname' => 'Updated',
            'lastname' => 'Manager',
            'role' => TenantRoles::MANAGER,
            'is_active' => false,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.id', $tenantUser->id)
            ->assertJsonPath('data.firstname', 'Updated')
            ->assertJsonPath('data.lastname', 'Manager')
            ->assertJsonPath('data.role', TenantRoles::MANAGER)
            ->assertJsonPath('data.is_active', false);

        $tenantUser->refresh();

        $this->assertSame('Updated', $tenantUser->firstname);
        $this->assertSame('Manager', $tenantUser->lastname);
        $this->assertFalse((bool) $tenantUser->is_active);
        $this->assertSame(TenantRoles::MANAGER, $tenantUser->getRoleNames()->first());
    }

    private function ensureTenantUserColumns(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'firstname')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('firstname')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'lastname')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('lastname')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'mi')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('mi', 10)->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });
        }
    }
}
