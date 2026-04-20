<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class TenantUserApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.bootstrappers', []);

        $this->withoutMiddleware([
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
            Authenticate::class,
            PermissionMiddleware::class,
        ]);

        $this->ensureTenantTables();
        $this->ensurePermissionTables();

        $this->tenant = Tenant::create([
            'id' => 'tenant-user-api-test',
            'company_name' => 'Tenant User API Test',
            'plan' => 'starter',
            'enabled_features' => ['event_management', 'staff_assignment'],
        ]);

        tenancy()->initialize($this->tenant);

        foreach (TenantRoles::all() as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_non_first_admin_cannot_create_admin_user(): void
    {
        $firstAdmin = User::query()->create([
            'name' => 'First Admin',
            'username' => 'first.admin',
            'firstname' => 'First',
            'lastname' => 'Admin',
            'email' => 'first.admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $firstAdmin->syncRoles([TenantRoles::ADMIN]);

        $manager = User::query()->create([
            'name' => 'Tenant Manager',
            'username' => 'tenant.manager',
            'firstname' => 'Tenant',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $manager->syncRoles([TenantRoles::MANAGER]);

        $response = $this
            ->actingAs($manager)
            ->postJson('/api/tenant/users', [
                'username' => 'new.admin',
                'firstname' => 'New',
                'lastname' => 'Admin',
                'email' => 'new.admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => TenantRoles::ADMIN,
                'is_active' => true,
            ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only the first admin account can assign the Admin role.');

        $this->assertDatabaseMissing('users', [
            'username' => 'new.admin',
        ]);
    }

    public function test_first_admin_can_create_admin_user(): void
    {
        $firstAdmin = User::query()->create([
            'name' => 'First Admin',
            'username' => 'first.admin',
            'firstname' => 'First',
            'lastname' => 'Admin',
            'email' => 'first.admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $firstAdmin->syncRoles([TenantRoles::ADMIN]);

        $response = $this
            ->actingAs($firstAdmin)
            ->postJson('/api/tenant/users', [
                'username' => 'new.admin',
                'firstname' => 'New',
                'lastname' => 'Admin',
                'email' => 'new.admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => TenantRoles::ADMIN,
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', TenantRoles::ADMIN);

        $this->assertDatabaseHas('users', [
            'username' => 'new.admin',
        ]);
    }

    public function test_auth_registration_allows_first_admin_bootstrap(): void
    {
        $response = $this->postJson('/api/tenant/auth/register', [
            'firstname' => 'Bootstrap',
            'lastname' => 'Admin',
            'email' => 'bootstrap.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => TenantRoles::ADMIN,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.role', TenantRoles::ADMIN)
            ->assertJsonPath('data.user.email', 'bootstrap.admin@example.com');

        $this->assertNotEmpty((string) $response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'email' => 'bootstrap.admin@example.com',
        ]);
    }

    public function test_auth_registration_allows_public_non_admin_when_first_admin_exists(): void
    {
        $firstAdmin = User::query()->create([
            'name' => 'First Admin',
            'username' => 'first.admin',
            'firstname' => 'First',
            'lastname' => 'Admin',
            'email' => 'first.admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $firstAdmin->syncRoles([TenantRoles::ADMIN]);

        $response = $this->postJson('/api/tenant/auth/register', [
            'firstname' => 'Public',
            'lastname' => 'User',
            'email' => 'public.user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => TenantRoles::STAFF,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.role', TenantRoles::STAFF);

        $this->assertNotEmpty((string) $response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'public.user@example.com',
        ]);
    }

    public function test_auth_registration_blocks_public_admin_role_when_first_admin_exists(): void
    {
        $firstAdmin = User::query()->create([
            'name' => 'First Admin',
            'username' => 'first.admin',
            'firstname' => 'First',
            'lastname' => 'Admin',
            'email' => 'first.admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $firstAdmin->syncRoles([TenantRoles::ADMIN]);

        $response = $this->postJson('/api/tenant/auth/register', [
            'firstname' => 'Public',
            'lastname' => 'Admin',
            'email' => 'public.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => TenantRoles::ADMIN,
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only the first admin account can assign the Admin role.');

        $this->assertDatabaseMissing('users', [
            'email' => 'public.admin@example.com',
        ]);
    }

    public function test_suspended_tenant_blocks_auth_login_with_exact_reason(): void
    {
        $this->tenant->setAttribute('is_active', false);
        $this->tenant->save();

        User::query()->create([
            'name' => 'Suspended Tenant User',
            'username' => 'suspended.user',
            'firstname' => 'Suspended',
            'lastname' => 'User',
            'email' => 'suspended.user@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/tenant/auth/login', [
            'identity' => 'suspended.user',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', EnsureTenantIsActive::suspensionMessage())
            ->assertJsonPath('reason_code', 'tenant_suspended')
            ->assertJsonPath('status', 'suspended');
    }

    public function test_capabilities_exposes_suspension_reason_for_frontend_toast(): void
    {
        $this->tenant->setAttribute('is_active', false);
        $this->tenant->save();

        $response = $this->getJson('/api/tenant/capabilities');

        $response
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.access_restriction_code', 'tenant_suspended')
            ->assertJsonPath('data.access_restriction_reason', EnsureTenantIsActive::suspensionMessage());
    }

    private function ensureTenantTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('username')->unique();
                $table->string('firstname')->nullable();
                $table->string('lastname')->nullable();
                $table->string('mi', 10)->nullable();
                $table->string('email')->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'firstname')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('firstname')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'lastname')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('lastname')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'mi')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('mi', 10)->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });
        }
    }

    private function ensurePermissionTables(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');

                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');

                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');

                $table->primary(['permission_id', 'role_id']);
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }
    }
}
