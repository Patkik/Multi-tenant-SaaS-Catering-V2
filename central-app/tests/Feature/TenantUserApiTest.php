<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            ->assertJsonPath('data.app_version', (string) config('app.version'))
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.access_restriction_code', 'tenant_suspended')
            ->assertJsonPath('data.access_restriction_reason', EnsureTenantIsActive::suspensionMessage());
    }

    public function test_tenant_app_updates_endpoint_returns_release_status(): void
    {
        Cache::flush();
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.apply_command', '');

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'Version 2.1.0',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        $response = $this->getJson('/api/tenant/app-updates');

        $response->assertOk()
            ->assertJsonPath('data.current_version', '2.0.2')
            ->assertJsonPath('data.latest_version', '2.1.0')
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.can_apply', false);
    }

    public function test_tenant_app_updates_apply_returns_manual_required_without_command(): void
    {
        Cache::flush();
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.apply_command', '');

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'Version 2.1.0',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        Process::fake();

        $response = $this->postJson('/api/tenant/app-updates/apply');

        $response->assertOk()
            ->assertJsonPath('data.status', 'manual_required')
            ->assertJsonPath('data.release.update_available', true)
            ->assertJsonPath('data.release.can_apply', false);

        Process::assertNothingRan();
    }

    public function test_tenant_app_updates_apply_runs_configured_command(): void
    {
        Cache::flush();
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.apply_command', 'php artisan about');

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'Version 2.1.0',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        Process::fake([
            'php artisan about' => Process::result('Tenant update completed'),
        ]);

        $response = $this->postJson('/api/tenant/app-updates/apply');

        $response->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.release.can_apply', true)
            ->assertJsonPath('data.output', 'Tenant update completed');

        Process::assertRan('php artisan about');
    }

    public function test_authenticated_user_can_update_own_profile_fields(): void
    {
        $user = User::query()->create([
            'name' => 'Profile User',
            'username' => 'profile.user',
            'firstname' => 'Profile',
            'lastname' => 'User',
            'email' => 'profile.user@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->syncRoles([TenantRoles::STAFF]);

        $response = $this
            ->actingAs($user)
            ->patchJson('/api/tenant/auth/profile', [
                'firstname' => 'Updated',
                'lastname' => 'Member',
                'mi' => 'Q',
                'email' => 'updated.member@example.com',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.firstname', 'Updated')
            ->assertJsonPath('data.user.lastname', 'Member')
            ->assertJsonPath('data.user.email', 'updated.member@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firstname' => 'Updated',
            'lastname' => 'Member',
            'mi' => 'Q',
            'email' => 'updated.member@example.com',
        ]);
    }

    public function test_authenticated_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Avatar User',
            'username' => 'avatar.user',
            'firstname' => 'Avatar',
            'lastname' => 'User',
            'email' => 'avatar.user@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->syncRoles([TenantRoles::STAFF]);

        $uploadResponse = $this
            ->actingAs($user)
            ->patch('/api/tenant/auth/profile', [
                'avatar_file' => UploadedFile::fake()->createWithContent(
                    'avatar.png',
                    (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2cyB0AAAAASUVORK5CYII='),
                ),
            ], [
                'Accept' => 'application/json',
            ]);

        $uploadResponse
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertNotNull($user->avatar_url);
        Storage::disk('public')->assertExists((string) $user->avatar_path);

        $storedAvatarPath = (string) $user->avatar_path;

        $removeResponse = $this
            ->actingAs($user)
            ->patchJson('/api/tenant/auth/profile', [
                'remove_avatar' => true,
            ]);

        $removeResponse
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', null);

        $user->refresh();
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->avatar_url);
        Storage::disk('public')->assertMissing($storedAvatarPath);
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

        if (! Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('avatar_path')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'avatar_url')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('avatar_url')->nullable();
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
