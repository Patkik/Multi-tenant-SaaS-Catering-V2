<?php

namespace Tests\Feature;

use App\Services\TenantProvisioningHealthCheckService;
use Tests\TestCase;

class TenancyHealthCheckCommandTest extends TestCase
{
    public function test_command_returns_success_when_health_check_passes(): void
    {
        $this->app->instance(TenantProvisioningHealthCheckService::class, new class extends TenantProvisioningHealthCheckService
        {
            public function evaluate(): array
            {
                return [
                    'ok' => true,
                    'connection' => 'mysql_provisioning',
                    'can_connect' => true,
                    'can_read_grants' => true,
                    'has_required_privilege' => true,
                    'required_privileges' => ['CREATE DATABASE', 'ALL PRIVILEGES', 'SUPER'],
                    'grants' => ["GRANT CREATE ON *.* TO 'provisioning'@'%"],
                    'errors' => [],
                ];
            }
        });

        $this->artisan('tenancy:health-check')
            ->expectsOutput('PASS: provisioning connection and privileges are healthy.')
            ->assertExitCode(0);
    }

    public function test_command_returns_failure_when_health_check_fails(): void
    {
        $this->app->instance(TenantProvisioningHealthCheckService::class, new class extends TenantProvisioningHealthCheckService
        {
            public function evaluate(): array
            {
                return [
                    'ok' => false,
                    'connection' => 'mysql_provisioning',
                    'can_connect' => true,
                    'can_read_grants' => true,
                    'has_required_privilege' => false,
                    'required_privileges' => ['CREATE DATABASE', 'ALL PRIVILEGES', 'SUPER'],
                    'grants' => ["GRANT SELECT ON central_app.* TO 'app'@'%"],
                    'errors' => ['Provisioning user grants are missing CREATE DATABASE, ALL PRIVILEGES, or SUPER on *.*.'],
                ];
            }
        });

        $this->artisan('tenancy:health-check')
            ->expectsOutput('FAIL: provisioning connection privileges are not sufficient.')
            ->assertExitCode(1);
    }
}
