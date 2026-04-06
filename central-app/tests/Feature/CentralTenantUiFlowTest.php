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

    public function test_dashboard_links_to_tenant_creation_form(): void
    {
        $dashboardResponse = $this->withSession(['central_authenticated' => true])->get('/');

        $dashboardResponse
            ->assertOk()
            ->assertSeeText('New Tenant')
            ->assertSee(route('central.tenants'), false);

        $tenantsResponse = $this->withSession(['central_authenticated' => true])->get(route('central.tenants'));

        $tenantsResponse
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

    public function test_tenant_create_normalizes_localhost_8080_domain_to_active_app_port(): void
    {
        $tenant = Tenant::factory()->make([
            'id' => (string) Str::uuid(),
            'name' => 'Hello Catering',
            'database_name' => 'tenant_hello',
            'provisioning_status' => 'ready',
        ]);

        $this->mock(TenantProvisioningService::class, function ($mock) use ($tenant): void {
            $mock->shouldReceive('createTenant')
                ->once()
                ->withArgs(static fn (array $payload): bool => ($payload['domain'] ?? null) === 'hello.localhost:8000')
                ->andReturn($tenant);
        });

        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'localhost:8000',
                'SERVER_PORT' => '8000',
            ])
            ->withSession(['central_authenticated' => true])
            ->post(route('tenants.create'), [
                'name' => 'Hello Catering',
                'domain' => 'hello.localhost:8080',
                'database_name' => 'tenant_hello',
                'plan_code' => 'starter',
                'plan_entitlements' => 'starter',
            ]);

        $response
            ->assertRedirect('/')
            ->assertSessionHas('tenant_create_success');
    }

    public function test_tenant_create_preserves_explicit_non_8080_localhost_port(): void
    {
        $tenant = Tenant::factory()->make([
            'id' => (string) Str::uuid(),
            'name' => 'Custom Port Catering',
            'database_name' => 'tenant_custom_port',
            'provisioning_status' => 'ready',
        ]);

        $this->mock(TenantProvisioningService::class, function ($mock) use ($tenant): void {
            $mock->shouldReceive('createTenant')
                ->once()
                ->withArgs(static fn (array $payload): bool => ($payload['domain'] ?? null) === 'custom.localhost:3001')
                ->andReturn($tenant);
        });

        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'localhost:8000',
                'SERVER_PORT' => '8000',
            ])
            ->withSession(['central_authenticated' => true])
            ->post(route('tenants.create'), [
                'name' => 'Custom Port Catering',
                'domain' => 'custom.localhost:3001',
                'database_name' => 'tenant_custom_port',
                'plan_code' => 'starter',
                'plan_entitlements' => 'starter',
            ]);

        $response
            ->assertRedirect('/')
            ->assertSessionHas('tenant_create_success');
    }

    public function test_tenant_preview_links_normalize_legacy_localhost_8080_domain_to_active_port(): void
    {
        Tenant::factory()->create([
            'name' => 'Legacy Preview Catering',
            'domain' => 'legacy-preview.localhost:8080',
        ]);

        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'localhost:8000',
                'SERVER_PORT' => '8000',
            ])
            ->withSession(['central_authenticated' => true])
            ->get(route('central.tenants'));

        $response
            ->assertOk()
            ->assertSee('http://legacy-preview.localhost:8000/auth/tenant/login', false)
            ->assertDontSee('http://legacy-preview.localhost:8080/auth/tenant/login', false);
    }

    public function test_central_login_accepts_ipv6_loopback_as_localhost_equivalent(): void
    {
        $this->withServerVariables([
            'HTTP_HOST' => '[::1]:8000',
            'SERVER_PORT' => '8000',
        ])->get('/auth/central/login')
            ->assertOk()
            ->assertSeeText('Central App Login');
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

    public function test_central_can_update_tenant_plan_and_activation_status(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_code' => 'starter',
            'is_active' => true,
        ]);

        $response = $this->withSession(['central_authenticated' => true])->patch(
            route('central.tenants.update', $tenant),
            [
                'plan_code' => 'enterprise',
                'is_active' => '0',
            ],
            [
                'HTTP_HOST' => 'localhost',
            ]
        );

        $response
            ->assertRedirect(route('central.tenants'))
            ->assertSessionHas('success');

        $tenant->refresh();

        $this->assertSame('enterprise', $tenant->plan_code);
        $this->assertFalse((bool) $tenant->is_active);
    }

    public function test_deactivated_tenant_preview_redirects_to_login_with_deactivation_flash(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'bluebird.localhost:8080',
            'is_active' => false,
        ]);

        $response = $this->get(route('tenant.app.preview', ['tenant' => $tenant->id]));

        $response
            ->assertRedirect('http://bluebird.localhost:8080/auth/tenant/login')
            ->assertSessionHas('tenant_deactivated', true);
    }

    public function test_deactivated_tenant_registration_is_blocked_with_deactivation_flash(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'bluebird.localhost',
            'is_active' => false,
        ]);

        $this->get('http://bluebird.localhost/auth/tenant/register')
            ->assertRedirect('/auth/tenant/login')
            ->assertSessionHas('tenant_deactivated', true);

        $this->post('http://bluebird.localhost/auth/tenant/register', [
                'first_name' => 'Alice',
                'middle_initial' => 'B',
                'last_name' => 'Cooper',
                'email' => 'alice@example.com',
                'phone' => '5551234567',
                'phone_format' => 'us',
                'role' => 'staff',
                'password' => 'ValidPass1!',
                'password_confirmation' => 'ValidPass1!',
            ])
            ->assertRedirect('/auth/tenant/login')
            ->assertSessionHas('tenant_deactivated', true);
    }

    public function test_tenant_update_requires_central_authentication(): void
    {
        $tenant = Tenant::factory()->create();

        $this->patch(
            route('central.tenants.update', $tenant),
            [
                'plan_code' => 'growth',
                'is_active' => '1',
            ],
            [
                'HTTP_HOST' => 'localhost',
            ]
        )->assertRedirect(route('auth.central.login'));
    }

    public function test_central_tenant_update_rejects_plan_outside_allowlist(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_code' => 'starter',
        ]);

        $response = $this->withSession(['central_authenticated' => true])->patch(
            route('central.tenants.update', $tenant),
            [
                'plan_code' => 'custom-tier',
                'is_active' => '1',
            ],
            [
                'HTTP_HOST' => 'localhost',
            ]
        );

        $response->assertSessionHasErrors('plan_code');

        $tenant->refresh();
        $this->assertSame('starter', $tenant->plan_code);
    }
}
