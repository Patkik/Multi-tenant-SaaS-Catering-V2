<?php

namespace Tests\Feature;

use App\Contracts\TenantDatabaseProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminTenantProvisioningApiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TOKEN = 'test-central-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('central_admin.token', self::ADMIN_TOKEN);
        $this->bindFakeProvisioner();
    }

    public function test_token_is_required_for_tenant_creation(): void
    {
        $payload = $this->validPayload();

        $this->postJson('/api/admin/tenants', $payload)
            ->assertUnauthorized();

        $this->withHeaders([
            'X-Central-Admin-Key' => 'wrong-token',
        ])->postJson('/api/admin/tenants', $payload)
            ->assertForbidden();
    }

    public function test_successful_tenant_creation_returns_ready_payload(): void
    {
        $payload = $this->validPayload();

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.domain', $payload['domain'])
            ->assertJsonPath('data.database_name', $payload['database_name'])
            ->assertJsonPath('data.provisioning_status', 'ready')
            ->assertJsonPath('data.provisioning_error', null);

        $this->assertNotNull($response->json('data.provisioned_at'));

        $this->assertDatabaseHas('tenants', [
            'domain' => $payload['domain'],
            'database_name' => $payload['database_name'],
            'provisioning_status' => 'ready',
        ]);
    }

    public function test_provisioner_failure_returns_server_error_and_marks_tenant_failed(): void
    {
        $this->bindFakeProvisioner(static function (string $databaseName): void {
            throw new RuntimeException('Provisioning failure for '.$databaseName);
        });

        $payload = $this->validPayload([
            'domain' => 'failure.localhost:8080',
            'database_name' => 'tenant_failure_db',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants', $payload);

        $response
            ->assertStatus(500)
            ->assertJsonPath('message', 'Tenant provisioning failed.');

        $this->assertDatabaseHas('tenants', [
            'domain' => $payload['domain'],
            'database_name' => $payload['database_name'],
            'provisioning_status' => 'failed',
            'provisioning_error' => 'Provisioning failure for '.$payload['database_name'],
        ]);
    }

    public function test_database_name_collision_returns_server_error_and_marks_tenant_failed(): void
    {
        $this->bindFakeProvisioner(static function (string $databaseName): void {
            throw new RuntimeException(sprintf('Tenant database "%s" already exists.', $databaseName));
        });

        $payload = $this->validPayload([
            'domain' => 'collision.localhost:8080',
            'database_name' => 'tenant_collision_db',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants', $payload);

        $response
            ->assertStatus(500)
            ->assertJsonPath('message', 'Tenant provisioning failed.');

        $this->assertDatabaseHas('tenants', [
            'domain' => $payload['domain'],
            'database_name' => $payload['database_name'],
            'provisioning_status' => 'failed',
            'provisioning_error' => sprintf('Tenant database "%s" already exists.', $payload['database_name']),
        ]);
    }

    public function test_duplicate_database_name_and_domain_fail_validation(): void
    {
        $payload = $this->validPayload();

        $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants', $payload)
            ->assertCreated();

        $duplicateResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants', $payload);

        $duplicateResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain', 'database_name']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tenant One',
            'domain' => 'tenant-one.localhost:8080',
            'database_name' => 'tenant_one_db',
            'plan_code' => 'starter',
            'plan_entitlements' => ['starter'],
        ], $overrides);
    }

    private function bindFakeProvisioner(?callable $callback = null): void
    {
        $this->app->bind(TenantDatabaseProvisioner::class, function () use ($callback): TenantDatabaseProvisioner {
            return new class($callback) implements TenantDatabaseProvisioner
            {
                /**
                 * @var callable|null
                 */
                private $callback;

                public function __construct(?callable $callback)
                {
                    $this->callback = $callback;
                }

                public function createDatabase(string $databaseName): void
                {
                    if ($this->callback !== null) {
                        ($this->callback)($databaseName);
                    }
                }
            };
        });
    }
}
