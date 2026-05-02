<?php

namespace Tests\Feature;

use App\Mail\SupportMessageMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class TenantSupportApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.bootstrappers', []);

        $tenantDatabasePath = database_path('tenant_support_api_test.sqlite');

        File::delete($tenantDatabasePath);
        File::put($tenantDatabasePath, '');

        config()->set('database.connections.tenant_template.database', $tenantDatabasePath);
        config()->set('database.connections.tenant.database', $tenantDatabasePath);
        config()->set('tenancy.database.central_connection', 'landlord');
        config()->set('database.default', 'tenant');
        DB::purge('tenant');

        $this->withoutMiddleware([
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        $this->ensureTenantTables();
        $this->ensurePermissionTables();
        $this->ensureLandlordSupportTables();

        $this->tenant = Tenant::create([
            'id' => 'tenant-support-api-test',
            'company_name' => 'Tenant Support API Test',
            'plan' => 'starter',
            'enabled_features' => ['event_management'],
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

    public function test_it_sends_a_tenant_support_request_by_email(): void
    {
        Mail::fake();

        config()->set('support.tenant_recipient', 'tenant-support@example.com');

        $user = User::query()->create([
            'name' => 'Tenant Manager',
            'username' => 'tenant.manager',
            'firstname' => 'Tenant',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->syncRoles([TenantRoles::MANAGER]);

        $response = $this->actingAs($user)->postJson('/api/tenant/support', [
            'category' => 'feedback',
            'subject' => 'Could the bookings page show color tags?',
            'message' => 'The bookings board would be easier to scan with more visible status labels.',
            'contact_name' => 'Tenant Manager',
            'contact_email' => 'manager@example.com',
            'page_path' => '/bookings',
            'user_role' => TenantRoles::MANAGER,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.message', 'Your support request has been sent to the tenant support inbox.');

        $this->assertDatabaseHas('support_messages', [
            'source' => 'tenant',
            'subject' => 'Could the bookings page show color tags?',
            'workspace_name' => 'Tenant Support API Test',
        ], 'landlord');

        Mail::assertSent(SupportMessageMail::class, function (SupportMessageMail $mail): bool {
            return $mail->source === 'tenant'
                && ($mail->payload['category'] ?? null) === 'feedback'
                && ($mail->supportMetadata['workspace_name'] ?? null) === 'Tenant Support API Test';
        });
    }

    private function ensureTenantTables(): void
    {
        Schema::connection('tenant')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('mi', 10)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('avatar_path')->nullable();
            $table->string('avatar_url')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

    }

    private function ensurePermissionTables(): void
    {
        Schema::connection('tenant')->create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('tenant')->create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
            $table->index(['model_id', 'model_type']);
        });
    }

    private function ensureLandlordSupportTables(): void
    {
        if (Schema::connection('landlord')->hasTable('support_messages')) {
            return;
        }

        Schema::connection('landlord')->create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 20);
            $table->string('category', 40);
            $table->string('subject', 120);
            $table->text('message');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('workspace_name')->nullable();
            $table->string('workspace_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('page_path')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('user_role')->nullable();
            $table->string('tenant_domain')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }
}
