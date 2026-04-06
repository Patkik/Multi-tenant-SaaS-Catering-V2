<?php

namespace Tests\Unit;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenantProvisioningException;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabasePath = storage_path('framework/testing/'.Str::uuid().'-tenant-provisioning-service-test.sqlite');
        File::ensureDirectoryExists(dirname($this->tenantDatabasePath));
    }

    protected function tearDown(): void
    {
        DB::disconnect((string) config('tenancy.runtime_connection_alias', 'tenant_runtime'));
        DB::purge((string) config('tenancy.runtime_connection_alias', 'tenant_runtime'));
        $this->deleteTenantDatabaseFile();

        parent::tearDown();
    }

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

        $this->app->bind(TenantDatabaseProvisioner::class, static fn (): TenantDatabaseProvisioner => new class() implements TenantDatabaseProvisioner
        {
            public function createDatabase(string $databaseName): void
            {
                if (! File::exists($databaseName)) {
                    File::put($databaseName, '');
                }
            }
        });

        /** @var TenantProvisioningService $service */
        $service = $this->app->make(TenantProvisioningService::class);

        try {
            $service->createTenant([
                'name' => 'Unit Tenant',
                'domain' => 'unit-tenant.localhost:8080',
                'database_name' => $this->tenantDatabasePath,
                'plan_code' => 'starter',
                'plan_entitlements' => ['starter'],
            ]);

            $this->fail('Expected TenantProvisioningException was not thrown.');
        } catch (TenantProvisioningException $exception) {
            $this->assertNotNull($exception->getPrevious());
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->where('database_name', $this->tenantDatabasePath)->firstOrFail();

        $this->assertSame('failed', $tenant->provisioning_status);
        $this->assertNotNull($tenant->provisioning_error);
        $this->assertStringContainsString('ready transition failed', (string) $tenant->provisioning_error);
        $this->assertNull($tenant->provisioned_at);
    }

    public function test_is_absolute_path_accepts_windows_drive_paths_with_either_slash_style(): void
    {
        $service = new TenantProvisioningService(new class() implements TenantDatabaseProvisioner
        {
            public function createDatabase(string $databaseName): void
            {
            }
        });

        $method = new \ReflectionMethod($service, 'isAbsolutePath');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'C:\\tenant\\migrations'));
        $this->assertTrue($method->invoke($service, 'C:/tenant/migrations'));
        $this->assertTrue($method->invoke($service, '\\\\server\\share\\migrations'));
        $this->assertFalse($method->invoke($service, 'database/migrations/tenant'));
    }

    public function test_normalize_migration_path_resolves_windows_style_absolute_path(): void
    {
        $service = new TenantProvisioningService(new class() implements TenantDatabaseProvisioner
        {
            public function createDatabase(string $databaseName): void
            {
            }
        });

        $method = new \ReflectionMethod($service, 'normalizeMigrationPath');
        $method->setAccessible(true);

        $migrationFile = database_path('migrations/0001_01_01_000000_create_users_table.php');
        $windowsForwardSlashPath = str_replace('\\', '/', $migrationFile);

        $resolvedPath = $method->invoke($service, $windowsForwardSlashPath);

        $this->assertIsString($resolvedPath);
        $this->assertNotSame('', $resolvedPath);
    }

    private function deleteTenantDatabaseFile(): void
    {
        clearstatcache(true, $this->tenantDatabasePath);

        if (! File::exists($this->tenantDatabasePath)) {
            return;
        }

        @unlink($this->tenantDatabasePath);
        clearstatcache(true, $this->tenantDatabasePath);

        if (File::exists($this->tenantDatabasePath)) {
            File::delete($this->tenantDatabasePath);
        }
    }
}
