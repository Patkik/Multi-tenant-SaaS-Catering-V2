<?php

namespace Tests\Feature;

use App\Mail\SupportMessageMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->withoutMiddleware([
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        $this->ensureTenantTables();
        $this->ensurePermissionTables();

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

        Mail::assertSent(SupportMessageMail::class, function (SupportMessageMail $mail): bool {
            return $mail->source === 'tenant'
                && ($mail->payload['category'] ?? null) === 'feedback'
                && ($mail->metadata['workspace_name'] ?? null) === 'Tenant Support API Test';
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
}
