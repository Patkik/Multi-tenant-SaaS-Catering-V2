<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        Sanctum::actingAs($user, ['*']);
    }

    public function test_it_returns_central_dashboard_stats_and_plan_breakdown(): void
    {
        Tenant::create([
            'id' => 'free-tenant',
            'company_name' => 'Free Catering',
            'plan' => 'free',
        ]);

        Tenant::create([
            'id' => 'starter-tenant',
            'company_name' => 'Starter Catering',
            'plan' => 'starter',
        ]);

        Tenant::create([
            'id' => 'business-tenant',
            'company_name' => 'Business Catering',
            'plan' => 'business',
        ]);

        $response = $this->getJson('/api/central/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.total_tenants', 3)
            ->assertJsonPath('data.plan_breakdown.free.tenant_count', 1)
            ->assertJsonPath('data.plan_breakdown.starter.tenant_count', 1)
            ->assertJsonPath('data.plan_breakdown.business.tenant_count', 1)
            ->assertJsonPath('data.estimated_monthly_revenue', 1998);
    }
}
