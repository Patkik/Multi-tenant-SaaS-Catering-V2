<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CentralTenantUiFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_tenant_creation_form(): void
    {
        $response = $this->withSession(['central_authenticated' => true])->get('/');

        $response
            ->assertOk()
            ->assertSeeText('Create Tenant')
            ->assertSee('name="database_name"', false)
            ->assertSeeText('Create Tenant + Database');
    }

    public function test_tenant_create_route_uses_provisioning_service(): void
    {
        $tenant = Tenant::factory()->make([
            'id' => (string) Str::uuid(),
            'name' => 'Northwind Catering',
            'database_name' => 'tenant_northwind',
            'provisioning_status' => 'ready',
        ]);

        $this->mock(TenantProvisioningService::class, function ($mock) use ($tenant): void {
            $mock->shouldReceive('createTenant')
                ->once()
                ->andReturn($tenant);
        });

        $response = $this->withSession(['central_authenticated' => true])->post(
            route('tenants.create'),
            [
                'name' => 'Northwind Catering',
                'domain' => 'northwind.localhost:8080',
                'database_name' => 'tenant_northwind',
                'plan_code' => 'starter',
                'plan_entitlements' => 'starter,analytics',
            ],
            [
                'HTTP_HOST' => 'localhost',
            ]
        );

        $response
            ->assertRedirect('/')
            ->assertSessionHas('tenant_create_success');
    }

    public function test_tenant_app_preview_renders_without_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Bluebird Kitchen',
            'domain' => 'bluebird.localhost:8080',
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'bluebird.localhost:8080'])
            ->get(route('tenant.app.preview', ['tenant' => $tenant->id]))
            ->assertRedirect('http://bluebird.localhost:8080/auth/tenant/login');
    }

    public function test_tenant_app_preview_redirects_to_specific_tenant_domain(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Bluebird Kitchen',
            'domain' => 'bluebird.localhost:8080',
        ]);

        $this->get(route('tenant.app.preview', ['tenant' => $tenant->id]))
            ->assertRedirect('http://bluebird.localhost:8080/auth/tenant/login');
    }
}
