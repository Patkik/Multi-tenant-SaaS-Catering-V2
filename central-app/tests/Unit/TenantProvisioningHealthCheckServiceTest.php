<?php

namespace Tests\Unit;

use App\Services\TenantProvisioningHealthCheckService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantProvisioningHealthCheckServiceTest extends TestCase
{
    public function test_health_check_passes_when_grants_include_global_all_privileges(): void
    {
        config()->set('tenancy.provisioning_connection', 'mysql_provisioning');

        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['ok' => 1]]);
        $connection->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER()')->andReturn([
            (object) ['Grants for provisioning@%' => "GRANT ALL PRIVILEGES ON *.* TO 'provisioning'@'%'"],
        ]);

        DB::shouldReceive('connection')->once()->with('mysql_provisioning')->andReturn($connection);

        $service = $this->app->make(TenantProvisioningHealthCheckService::class);
        $result = $service->evaluate();

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['has_required_privilege']);
    }

    public function test_health_check_fails_when_all_privileges_are_schema_scoped_only(): void
    {
        config()->set('tenancy.provisioning_connection', 'mysql_provisioning');

        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['ok' => 1]]);
        $connection->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER()')->andReturn([
            (object) ['Grants for provisioning@%' => "GRANT ALL PRIVILEGES ON central_app.* TO 'provisioning'@'%'"],
        ]);

        DB::shouldReceive('connection')->once()->with('mysql_provisioning')->andReturn($connection);

        $service = $this->app->make(TenantProvisioningHealthCheckService::class);
        $result = $service->evaluate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['has_required_privilege']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_health_check_passes_when_grants_include_create_privilege(): void
    {
        config()->set('tenancy.provisioning_connection', 'mysql_provisioning');

        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['ok' => 1]]);
        $connection->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER()')->andReturn([
            (object) ['Grants for provisioning@%' => "GRANT CREATE, SELECT ON *.* TO 'provisioning'@'%'"],
        ]);

        DB::shouldReceive('connection')->once()->with('mysql_provisioning')->andReturn($connection);

        $service = $this->app->make(TenantProvisioningHealthCheckService::class);
        $result = $service->evaluate();

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['has_required_privilege']);
        $this->assertTrue($result['can_connect']);
        $this->assertTrue($result['can_read_grants']);
        $this->assertSame([], $result['errors']);
    }

    public function test_health_check_fails_when_required_privileges_are_missing(): void
    {
        config()->set('tenancy.provisioning_connection', 'mysql_provisioning');

        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['ok' => 1]]);
        $connection->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER()')->andReturn([
            (object) ['Grants for app@%' => "GRANT SELECT, INSERT, UPDATE ON central_app.* TO 'app'@'%'"],
        ]);

        DB::shouldReceive('connection')->once()->with('mysql_provisioning')->andReturn($connection);

        $service = $this->app->make(TenantProvisioningHealthCheckService::class);
        $result = $service->evaluate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['has_required_privilege']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_health_check_fails_when_grant_includes_global_create_view_only(): void
    {
        config()->set('tenancy.provisioning_connection', 'mysql_provisioning');

        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['ok' => 1]]);
        $connection->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER()')->andReturn([
            (object) ['Grants for provisioning@%' => "GRANT CREATE VIEW ON *.* TO 'provisioning'@'%'"],
        ]);

        DB::shouldReceive('connection')->once()->with('mysql_provisioning')->andReturn($connection);

        $service = $this->app->make(TenantProvisioningHealthCheckService::class);
        $result = $service->evaluate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['has_required_privilege']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_health_check_fails_when_grant_includes_global_create_routine_only(): void
    {
        config()->set('tenancy.provisioning_connection', 'mysql_provisioning');

        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['ok' => 1]]);
        $connection->shouldReceive('select')->once()->with('SHOW GRANTS FOR CURRENT_USER()')->andReturn([
            (object) ['Grants for provisioning@%' => "GRANT CREATE ROUTINE ON *.* TO 'provisioning'@'%'"],
        ]);

        DB::shouldReceive('connection')->once()->with('mysql_provisioning')->andReturn($connection);

        $service = $this->app->make(TenantProvisioningHealthCheckService::class);
        $result = $service->evaluate();

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['has_required_privilege']);
        $this->assertNotEmpty($result['errors']);
    }
}
