<?php

namespace Tests\Feature;

use App\Jobs\ApplyRoleTemplateToTenantJob;
use App\Models\Feature;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateApplication;
use App\Models\RoleTemplateFeature;
use App\Models\RoleTemplatePermission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class AdminRoleTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TOKEN = 'test-central-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('central_admin.token', self::ADMIN_TOKEN);
    }

    public function test_role_template_endpoints_require_central_admin_token(): void
    {
        $tenant = Tenant::factory()->create();
        $feature = Feature::factory()->create([
            'name' => 'analytics',
        ]);
        $roleTemplate = RoleTemplate::factory()->create();

        $scenarios = [
            [
                'method' => 'GET',
                'uri' => '/api/admin/role-templates',
            ],
            [
                'method' => 'POST',
                'uri' => '/api/admin/role-templates',
                'payload' => [
                    'role_name' => 'EventSupervisor',
                    'permissions' => ['events.view'],
                    'feature_keys' => [$feature->name],
                ],
            ],
            [
                'method' => 'PATCH',
                'uri' => '/api/admin/role-templates/__ROLE_TEMPLATE_ID__',
                'payload' => [
                    'description' => 'updated from matrix',
                ],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/admin/tenants/__TENANT_ID__/role-templates/__ROLE_TEMPLATE_ID__/apply',
            ],
        ];

        foreach ($scenarios as $scenario) {
            $uri = str_replace(
                ['__TENANT_ID__', '__ROLE_TEMPLATE_ID__'],
                [$tenant->id, $roleTemplate->id],
                $scenario['uri'],
            );

            $payload = $scenario['payload'] ?? [];

            $this->flushHeaders();
            $this->json($scenario['method'], $uri, $payload)
                ->assertUnauthorized();

            $this->flushHeaders();
            $this->withHeaders([
                'X-Central-Admin-Key' => 'wrong-token',
            ])->json($scenario['method'], $uri, $payload)
                ->assertForbidden();
        }
    }

    public function test_role_template_endpoints_fail_closed_when_configured_token_is_unset_or_empty(): void
    {
        foreach ([null, ''] as $configuredToken) {
            config()->set('central_admin.token', $configuredToken);

            $response = $this->withHeaders([
                'X-Central-Admin-Key' => self::ADMIN_TOKEN,
            ])->getJson('/api/admin/role-templates');

            $response
                ->assertForbidden()
                ->assertJsonPath('message', 'Invalid central admin token.');
        }
    }

    public function test_role_template_list_endpoint_returns_data(): void
    {
        RoleTemplate::factory()->count(2)->create();

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->getJson('/api/admin/role-templates');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_create_persists_permissions_and_features(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);
        Feature::factory()->create([
            'name' => 'usage-monitoring',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => 'EventSupervisor',
            'description' => 'Role template for event supervisors',
            'permissions' => ['events.view', 'events.update', 'staff.assign'],
            'feature_keys' => ['analytics', 'usage-monitoring'],
        ]);

        $roleTemplateId = (string) $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'EventSupervisor')
            ->assertJsonPath('data.created_by_admin', 'central-admin-token')
            ->assertJsonCount(3, 'data.permissions')
            ->assertJsonCount(2, 'data.features');

        $this->assertDatabaseHas('role_template_permissions', [
            'role_template_id' => $roleTemplateId,
            'role_name' => 'EventSupervisor',
            'permission' => 'events.view',
        ]);

        $this->assertDatabaseHas('role_template_features', [
            'role_template_id' => $roleTemplateId,
            'role_name' => 'EventSupervisor',
            'feature_key' => 'analytics',
            'is_enabled' => true,
        ]);
    }

    public function test_update_replaces_nested_bindings(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);
        Feature::factory()->create([
            'name' => 'usage-monitoring',
        ]);

        $roleTemplate = RoleTemplate::factory()->create([
            'name' => 'EventSupervisor',
        ]);

        RoleTemplatePermission::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'EventSupervisor',
            'permission' => 'events.view',
        ]);
        RoleTemplateFeature::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'EventSupervisor',
            'feature_key' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->patchJson('/api/admin/role-templates/'.$roleTemplate->id, [
            'permissions' => ['staff.assign'],
            'feature_keys' => ['usage-monitoring'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.created_by_admin', 'central-admin-token')
            ->assertJsonCount(1, 'data.permissions')
            ->assertJsonCount(1, 'data.features')
            ->assertJsonPath('data.permissions.0.permission', 'staff.assign')
            ->assertJsonPath('data.features.0.feature_key', 'usage-monitoring');

        $this->assertDatabaseMissing('role_template_permissions', [
            'role_template_id' => $roleTemplate->id,
            'permission' => 'events.view',
        ]);
        $this->assertDatabaseMissing('role_template_features', [
            'role_template_id' => $roleTemplate->id,
            'feature_key' => 'analytics',
        ]);
    }

    public function test_update_allows_clearing_nullable_fields_with_explicit_null(): void
    {
        $roleTemplate = RoleTemplate::factory()->create([
            'description' => 'Initial description',
            'metadata' => ['source' => 'seed'],
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->patchJson('/api/admin/role-templates/'.$roleTemplate->id, [
            'description' => null,
            'metadata' => null,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.metadata', null);

        $this->assertDatabaseHas('role_templates', [
            'id' => $roleTemplate->id,
            'description' => null,
            'metadata' => null,
        ]);
    }

    public function test_apply_endpoint_returns_accepted_and_queues_application_job(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $roleTemplate = RoleTemplate::factory()->create();

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenant->id.'/role-templates/'.$roleTemplate->id.'/apply', [
            'strategy' => 'merge',
            'requested_by_admin' => 'ops-admin@central.test',
        ]);

        $applicationId = (string) $response->json('data.id');

        $response
            ->assertAccepted()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.role_template_id', $roleTemplate->id)
            ->assertJsonPath('data.strategy', 'merge')
            ->assertJsonPath('data.status', RoleTemplateApplication::STATUS_QUEUED)
            ->assertJsonPath('data.requested_by_admin', 'ops-admin@central.test');

        $this->assertDatabaseHas('role_template_applications', [
            'id' => $applicationId,
            'tenant_id' => $tenant->id,
            'role_template_id' => $roleTemplate->id,
            'status' => RoleTemplateApplication::STATUS_QUEUED,
            'strategy' => 'merge',
        ]);

        Queue::assertPushed(ApplyRoleTemplateToTenantJob::class, function (ApplyRoleTemplateToTenantJob $job) use ($applicationId): bool {
            return $job->roleTemplateApplicationId === $applicationId;
        });
    }

    public function test_apply_endpoint_is_idempotent_when_idempotency_key_is_replayed(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $roleTemplate = RoleTemplate::factory()->create();
        $idempotencyKey = 'apply-template-key-001';

        $firstResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenant->id.'/role-templates/'.$roleTemplate->id.'/apply', [
            'strategy' => 'replace',
            'idempotency_key' => $idempotencyKey,
            'requested_by_admin' => 'idempotent-admin@central.test',
        ]);

        $secondResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenant->id.'/role-templates/'.$roleTemplate->id.'/apply', [
            'strategy' => 'replace',
            'idempotency_key' => $idempotencyKey,
            'requested_by_admin' => 'different-admin@central.test',
        ]);

        $firstResponse->assertAccepted();
        $secondResponse->assertAccepted();

        $firstApplicationId = (string) $firstResponse->json('data.id');
        $secondApplicationId = (string) $secondResponse->json('data.id');

        $this->assertSame($firstApplicationId, $secondApplicationId);
        $this->assertSame(1, RoleTemplateApplication::query()->where('idempotency_key', $idempotencyKey)->count());

        Queue::assertPushed(ApplyRoleTemplateToTenantJob::class, 1);
    }

    public function test_apply_endpoint_scopes_idempotency_key_by_target_context(): void
    {
        Queue::fake();

        $idempotencyKey = 'apply-template-key-context-scope';

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $templateA = RoleTemplate::factory()->create();
        $templateB = RoleTemplate::factory()->create();

        $firstResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenantA->id.'/role-templates/'.$templateA->id.'/apply', [
            'strategy' => 'merge',
            'idempotency_key' => $idempotencyKey,
        ]);

        $differentTenantResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenantB->id.'/role-templates/'.$templateA->id.'/apply', [
            'strategy' => 'merge',
            'idempotency_key' => $idempotencyKey,
        ]);

        $differentTemplateResponse = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/tenants/'.$tenantA->id.'/role-templates/'.$templateB->id.'/apply', [
            'strategy' => 'merge',
            'idempotency_key' => $idempotencyKey,
        ]);

        $firstResponse->assertAccepted();
        $differentTenantResponse->assertAccepted();
        $differentTemplateResponse->assertAccepted();

        $firstApplicationId = (string) $firstResponse->json('data.id');
        $differentTenantApplicationId = (string) $differentTenantResponse->json('data.id');
        $differentTemplateApplicationId = (string) $differentTemplateResponse->json('data.id');

        $this->assertNotSame($firstApplicationId, $differentTenantApplicationId);
        $this->assertNotSame($firstApplicationId, $differentTemplateApplicationId);
        $this->assertNotSame($differentTenantApplicationId, $differentTemplateApplicationId);

        $this->assertSame(3, RoleTemplateApplication::query()->where('idempotency_key', $idempotencyKey)->count());

        Queue::assertPushed(ApplyRoleTemplateToTenantJob::class, 3);
    }

    public function test_create_rejects_role_name_longer_than_100_characters(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => str_repeat('a', 101),
            'permissions' => ['events.view'],
            'feature_keys' => ['analytics'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_name']);
    }

    public function test_create_ignores_client_supplied_created_by_admin_value(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => 'KitchenSupervisor',
            'created_by_admin' => 'spoofed-user@example.com',
            'permissions' => ['events.view'],
            'feature_keys' => ['analytics'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created_by_admin', 'central-admin-token');
    }

    public function test_create_rejects_permission_without_dot_notation(): void
    {
        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $response = $this->withHeaders([
            'X-Central-Admin-Key' => self::ADMIN_TOKEN,
        ])->postJson('/api/admin/role-templates', [
            'role_name' => 'OpsAssistant',
            'permissions' => ['eventsview'],
            'feature_keys' => ['analytics'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_apply_job_propagates_merge_and_replace_to_tenant_runtime_tables(): void
    {
        config()->set('tenancy.runtime_connection', 'sqlite');
        config()->set('tenancy.runtime_connection_alias', 'tenant_runtime_test');

        $tenantDatabasePath = storage_path('framework/testing/'.Str::uuid().'-tenant-runtime.sqlite');
        if (! is_dir(dirname($tenantDatabasePath))) {
            mkdir(dirname($tenantDatabasePath), 0777, true);
        }
        touch($tenantDatabasePath);

        $tenant = Tenant::factory()->create([
            'database_name' => $tenantDatabasePath,
        ]);

        Feature::factory()->create([
            'name' => 'analytics',
        ]);

        $roleTemplate = RoleTemplate::factory()->create([
            'name' => 'EventSupervisor',
        ]);

        RoleTemplatePermission::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'EventSupervisor',
            'permission' => 'events.view',
        ]);
        RoleTemplateFeature::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'EventSupervisor',
            'feature_key' => 'analytics',
            'is_enabled' => true,
        ]);

        $mergeApplication = RoleTemplateApplication::query()->create([
            'tenant_id' => $tenant->id,
            'role_template_id' => $roleTemplate->id,
            'strategy' => 'merge',
            'status' => RoleTemplateApplication::STATUS_QUEUED,
        ]);

        $mergeJob = new ApplyRoleTemplateToTenantJob($mergeApplication->id);
        $mergeJob->handle($this->app->make(\App\Services\RoleTemplateSyncService::class));

        $this->assertTrue(Schema::connection('tenant_runtime_test')->hasTable('tenant_roles'));
        $this->assertTrue(Schema::connection('tenant_runtime_test')->hasTable('tenant_role_permissions'));
        $this->assertTrue(Schema::connection('tenant_runtime_test')->hasTable('tenant_role_features'));

        $this->assertDatabaseHas('tenant_roles', [
            'role_name' => 'EventSupervisor',
        ], 'tenant_runtime_test');
        $this->assertDatabaseHas('tenant_role_permissions', [
            'role_name' => 'EventSupervisor',
            'permission' => 'events.view',
        ], 'tenant_runtime_test');
        $this->assertDatabaseHas('tenant_role_features', [
            'role_name' => 'EventSupervisor',
            'feature_key' => 'analytics',
            'is_enabled' => 1,
        ], 'tenant_runtime_test');

        DB::connection('tenant_runtime_test')->table('tenant_role_permissions')->insert([
            'id' => (string) Str::uuid(),
            'role_name' => 'EventSupervisor',
            'permission' => 'legacy.permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant_runtime_test')->table('tenant_role_features')->insert([
            'id' => (string) Str::uuid(),
            'role_name' => 'EventSupervisor',
            'feature_key' => 'legacy-feature',
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $replaceApplication = RoleTemplateApplication::query()->create([
            'tenant_id' => $tenant->id,
            'role_template_id' => $roleTemplate->id,
            'strategy' => 'replace',
            'status' => RoleTemplateApplication::STATUS_QUEUED,
        ]);

        $replaceJob = new ApplyRoleTemplateToTenantJob($replaceApplication->id);
        $replaceJob->handle($this->app->make(\App\Services\RoleTemplateSyncService::class));

        $this->assertDatabaseMissing('tenant_role_permissions', [
            'role_name' => 'EventSupervisor',
            'permission' => 'legacy.permission',
        ], 'tenant_runtime_test');
        $this->assertDatabaseMissing('tenant_role_features', [
            'role_name' => 'EventSupervisor',
            'feature_key' => 'legacy-feature',
        ], 'tenant_runtime_test');

        $this->assertSame(RoleTemplateApplication::STATUS_APPLIED, $mergeApplication->fresh()->status);
        $this->assertSame(RoleTemplateApplication::STATUS_APPLIED, $replaceApplication->fresh()->status);

        DB::disconnect('tenant_runtime_test');
        DB::purge('tenant_runtime_test');
        @unlink($tenantDatabasePath);
    }

    public function test_apply_job_marks_application_failed_when_tenant_connection_fails(): void
    {
        config()->set('tenancy.runtime_connection', 'sqlite');
        config()->set('tenancy.runtime_connection_alias', 'tenant_runtime_fail_test');

        $tenant = Tenant::factory()->create([
            'database_name' => storage_path('framework/testing/missing/'.Str::uuid().'/tenant-runtime.sqlite'),
        ]);

        $roleTemplate = RoleTemplate::factory()->create([
            'name' => 'OpsAssistant',
        ]);

        RoleTemplatePermission::factory()->create([
            'role_template_id' => $roleTemplate->id,
            'role_name' => 'OpsAssistant',
            'permission' => 'ops.view',
        ]);

        $application = RoleTemplateApplication::query()->create([
            'tenant_id' => $tenant->id,
            'role_template_id' => $roleTemplate->id,
            'strategy' => 'merge',
            'status' => RoleTemplateApplication::STATUS_QUEUED,
        ]);

        $job = new ApplyRoleTemplateToTenantJob($application->id);

        try {
            $job->handle($this->app->make(\App\Services\RoleTemplateSyncService::class));
            $this->fail('Expected tenant runtime connection failure.');
        } catch (Throwable) {
            $this->assertSame(RoleTemplateApplication::STATUS_FAILED, $application->fresh()->status);
            $this->assertNotNull($application->fresh()->error_message);
        } finally {
            DB::disconnect('tenant_runtime_fail_test');
            DB::purge('tenant_runtime_fail_test');
        }
    }
}
