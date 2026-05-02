<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_tenant_with_domain_and_plan_metadata(): void
    {
        $response = $this->postJson('/api/tenants/register', [
            'company_name' => 'Acme Catering',
            'subdomain' => 'acme',
            'plan' => 'free',
            'admin' => [
                'username' => 'acmeadmin',
                'lastname' => 'Owner',
                'mi' => 'Q',
                'firstname' => 'Ariel',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'acme.' . config('tenancy.central_domains')[0])
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.company_name', 'Acme Catering');

        $this->assertDatabaseHas('tenants', [
            'id' => $response->json('data.tenant_id'),
        ]);

        $this->assertDatabaseHas('domains', [
            'domain' => 'acme',
            'tenant_id' => $response->json('data.tenant_id'),
        ]);

        $tenant = Tenant::findOrFail($response->json('data.tenant_id'));

        $this->assertFalse((bool) $tenant->getAttribute('client_access'));
    }

    public function test_it_rejects_duplicate_subdomains(): void
    {
        $payload = [
            'company_name' => 'Acme Catering',
            'subdomain' => 'acme',
            'plan' => 'free',
            'admin' => [
                'username' => 'acmeadmin',
                'lastname' => 'Owner',
                'mi' => 'Q',
                'firstname' => 'Ariel',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ];

        $this->postJson('/api/tenants/register', $payload)->assertCreated();

        $response = $this->postJson('/api/tenants/register', array_merge($payload, [
            'company_name' => 'Acme Catering 2',
            'admin' => array_merge($payload['admin'], ['username' => 'acmeadmin2']),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subdomain']);
    }
}
