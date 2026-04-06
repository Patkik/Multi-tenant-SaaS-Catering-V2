<?php

namespace Tests\Unit;

use App\Contracts\TenantDatabaseProvisioner;
use App\Services\TenantAccountRegistrationService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TenantRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabasePath = storage_path('framework/testing/'.Str::uuid().'-tenant-registration-service-test.sqlite');
        File::ensureDirectoryExists(dirname($this->tenantDatabasePath));
    }

    protected function tearDown(): void
    {
        DB::disconnect((string) config('tenancy.runtime_connection_alias', 'tenant_runtime'));
        DB::purge((string) config('tenancy.runtime_connection_alias', 'tenant_runtime'));
        $this->deleteTenantDatabaseFile();

        parent::tearDown();
    }

    public function test_registration_is_persisted_in_tenant_database_not_central_database(): void
    {
        $this->app->bind(TenantDatabaseProvisioner::class, static fn (): TenantDatabaseProvisioner => new class() implements TenantDatabaseProvisioner
        {
            public function createDatabase(string $databaseName): void
            {
                if (! File::exists($databaseName)) {
                    File::put($databaseName, '');
                }
            }
        });

        /** @var TenantProvisioningService $tenantProvisioningService */
        $tenantProvisioningService = $this->app->make(TenantProvisioningService::class);

        $tenant = $tenantProvisioningService->createTenant([
            'name' => 'Registration Service Test Tenant',
            'domain' => 'registration-service.localhost:8080',
            'database_name' => $this->tenantDatabasePath,
            'plan_code' => 'starter',
            'plan_entitlements' => ['starter'],
        ]);

        /** @var TenantAccountRegistrationService $registrationService */
        $registrationService = $this->app->make(TenantAccountRegistrationService::class);

        $result = $registrationService->register($tenant, [
            'email' => 'jane@example.test',
            'password' => 'Str0ng!Pass',
            'role' => 'staff',
            'full_name' => 'Jane Q. Cook',
        ]);

        $this->assertSame('active', $result['status']);

        $tenantConnection = (string) config('tenancy.runtime_connection_alias', 'tenant_runtime');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.test',
            'role' => 'staff',
            'is_active' => 1,
        ], $tenantConnection);

        $this->assertDatabaseMissing('users', [
            'email' => 'jane@example.test',
        ]);
    }

    public function test_registration_duplicate_email_check_is_case_insensitive_and_insert_is_normalized(): void
    {
        $this->app->bind(TenantDatabaseProvisioner::class, static fn (): TenantDatabaseProvisioner => new class() implements TenantDatabaseProvisioner
        {
            public function createDatabase(string $databaseName): void
            {
                if (! File::exists($databaseName)) {
                    File::put($databaseName, '');
                }
            }
        });

        /** @var TenantProvisioningService $tenantProvisioningService */
        $tenantProvisioningService = $this->app->make(TenantProvisioningService::class);

        $tenant = $tenantProvisioningService->createTenant([
            'name' => 'Registration Email Normalization Tenant',
            'domain' => 'registration-normalization.localhost:8080',
            'database_name' => $this->tenantDatabasePath,
            'plan_code' => 'starter',
            'plan_entitlements' => ['starter'],
        ]);

        /** @var TenantAccountRegistrationService $registrationService */
        $registrationService = $this->app->make(TenantAccountRegistrationService::class);

        $registrationService->register($tenant, [
            'email' => 'MixedCase.User@Example.Test',
            'password' => 'Str0ng!Pass',
            'role' => 'staff',
            'full_name' => 'Mixed Case',
        ]);

        $tenantConnection = (string) config('tenancy.runtime_connection_alias', 'tenant_runtime');

        $this->assertDatabaseHas('users', [
            'email' => 'mixedcase.user@example.test',
        ], $tenantConnection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $registrationService->register($tenant, [
            'email' => 'MIXEDCASE.USER@EXAMPLE.TEST',
            'password' => 'Another1!Pass',
            'role' => 'manager',
            'full_name' => 'Duplicate Case',
        ]);
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