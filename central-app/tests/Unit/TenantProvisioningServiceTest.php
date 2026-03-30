<?php

namespace Tests\Unit;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenantProvisioningException;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_state_persistence_failure_marks_tenant_failed_and_throws(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER block_ready_transition
            BEFORE UPDATE ON tenants
            WHEN NEW.provisioning_status = 'ready'
            BEGIN
                SELECT RAISE(FAIL, 'ready transition failed');
            END;
        SQL);

        $this->app->bind(TenantDatabaseProvisioner::class, static fn (): TenantDatabaseProvisioner => new class implements TenantDatabaseProvisioner
        {
            public function createDatabase(string $databaseName): void
            {
            }
        });

        /** @var TenantProvisioningService $service */
        $service = $this->app->make(TenantProvisioningService::class);

        try {
            $service->createTenant([
                'name' => 'Unit Tenant',
                'domain' => 'unit-tenant.localhost:8080',
                'database_name' => 'unit_tenant_db',
                'plan_code' => 'starter',
                'plan_entitlements' => ['starter'],
            ]);

            $this->fail('Expected TenantProvisioningException was not thrown.');
        } catch (TenantProvisioningException $exception) {
            $this->assertNotNull($exception->getPrevious());
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->where('database_name', 'unit_tenant_db')->firstOrFail();

        $this->assertSame('failed', $tenant->provisioning_status);
        $this->assertNotNull($tenant->provisioning_error);
        $this->assertStringContainsString('ready transition failed', (string) $tenant->provisioning_error);
        $this->assertNull($tenant->provisioned_at);
    }
}
