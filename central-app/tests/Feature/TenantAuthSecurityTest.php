<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantRuntimeAlias = 'tenant_runtime_auth_test';

    /** @var array<int, string> */
    private array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.runtime_connection', 'sqlite');
        config()->set('tenancy.runtime_connection_alias', $this->tenantRuntimeAlias);
    }

    protected function tearDown(): void
    {
        DB::disconnect($this->tenantRuntimeAlias);
        DB::purge($this->tenantRuntimeAlias);

        foreach ($this->tenantDatabasePaths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_tenant_login_requires_persisted_tenant_user_password_and_active_user(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('secure-login.localhost');

        DB::connection($this->tenantRuntimeAlias)->table('users')->insert([
            'name' => 'Casey Manager',
            'email' => 'casey@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'role' => 'manager',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('http://secure-login.localhost/auth/tenant/login', [
            'email' => 'casey@example.com',
            'password' => env('TENANT_AUTH_PASSWORD', 'tenant123!'),
            'role' => 'admin',
        ])
            ->assertSessionHasErrors('email');

        DB::connection($this->tenantRuntimeAlias)->table('users')
            ->where('email', 'casey@example.com')
            ->update(['is_active' => false]);

        $this->post('http://secure-login.localhost/auth/tenant/login', [
            'email' => 'casey@example.com',
            'password' => 'CorrectPass1!',
            'role' => 'admin',
        ])
            ->assertSessionHasErrors('email');
    }

    public function test_tenant_login_ignores_submitted_role_and_uses_persisted_role(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('role-proof.localhost');

        DB::connection($this->tenantRuntimeAlias)->table('users')->insert([
            'name' => 'Dana Staff',
            'email' => 'dana@example.com',
            'password' => Hash::make('RolePass1!'),
            'role' => 'cashier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('http://role-proof.localhost/auth/tenant/login', [
            'email' => 'dana@example.com',
            'password' => 'RolePass1!',
            'role' => 'admin',
        ])
            ->assertRedirect('http://role-proof.localhost/dashboard')
            ->assertSessionHas('tenant_role', 'cashier')
            ->assertSessionHas('tenant_authenticated_domain', (string) $tenant->domain);
    }

    public function test_tenant_login_accepts_normalized_input_for_legacy_mixed_case_stored_email(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('legacy-email.localhost');

        DB::connection($this->tenantRuntimeAlias)->table('users')->insert([
            'name' => 'Legacy User',
            'email' => 'Legacy.Mixed@Example.com',
            'password' => Hash::make('LegacyPass1!'),
            'role' => 'manager',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('http://legacy-email.localhost/auth/tenant/login', [
            'email' => 'legacy.mixed@example.com',
            'password' => 'LegacyPass1!',
            'role' => 'staff',
        ])
            ->assertRedirect('http://legacy-email.localhost/dashboard')
            ->assertSessionHas('tenant_authenticated_domain', (string) $tenant->domain)
            ->assertSessionHas('tenant_role', 'manager')
            ->assertSessionHas('tenant_user_email', 'Legacy.Mixed@Example.com');
    }

    public function test_tenant_resolution_fails_closed_when_same_host_is_ambiguous_by_port(): void
    {
        Tenant::factory()->create(['domain' => 'ambiguous.localhost:7001']);
        Tenant::factory()->create(['domain' => 'ambiguous.localhost:7002']);

        $this->get('http://ambiguous.localhost/auth/tenant/login')
            ->assertNotFound();

        $this->get('http://ambiguous.localhost:7002/auth/tenant/login')
            ->assertOk()
            ->assertSeeText('Tenant App Login');
    }

    public function test_registration_requires_server_otp_proof_and_accepts_valid_proof(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('otp-proof.localhost');

        $payload = [
            'first_name' => 'Alice',
            'middle_initial' => 'B',
            'last_name' => 'Cooper',
            'email' => 'alice@example.com',
            'phone' => '5551234567',
            'phone_format' => 'us',
            'role' => 'staff',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ];

        $this->post('http://otp-proof.localhost/auth/tenant/register', $payload)
            ->assertSessionHasErrors('otp');

        $this->withSession([
            'tenant_registration_otp_verified_email' => [
                'email' => 'alice@example.com',
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_domain' => (string) $tenant->domain,
                'verified_at' => now()->toIso8601String(),
            ],
        ])->post('http://otp-proof.localhost/auth/tenant/register', $payload)
            ->assertRedirect('http://otp-proof.localhost/dashboard');

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'role' => 'staff',
            'is_active' => 1,
        ], $this->tenantRuntimeAlias);
    }

    public function test_registration_duplicate_email_returns_explicit_error_and_does_not_overwrite(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('duplicate-proof.localhost');

        DB::connection($this->tenantRuntimeAlias)->table('users')->insert([
            'name' => 'Original User',
            'email' => 'alice@example.com',
            'password' => Hash::make('OriginalPass1!'),
            'role' => 'manager',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'first_name' => 'Alice',
            'middle_initial' => 'B',
            'last_name' => 'Cooper',
            'email' => 'alice@example.com',
            'phone' => '5551234567',
            'phone_format' => 'us',
            'role' => 'staff',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ];

        $this->withSession([
            'tenant_registration_otp_verified_email' => [
                'email' => 'alice@example.com',
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_domain' => (string) $tenant->domain,
                'verified_at' => now()->toIso8601String(),
            ],
        ])->post('http://duplicate-proof.localhost/auth/tenant/register', $payload)
            ->assertSessionHasErrors([
                'email' => 'An account with this email already exists.',
            ]);

        $this->assertSame(
            1,
            DB::connection($this->tenantRuntimeAlias)->table('users')
                ->where('email', 'alice@example.com')
                ->count()
        );

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'role' => 'manager',
        ], $this->tenantRuntimeAlias);
    }

    public function test_registration_session_role_uses_persisted_database_role_not_request_input(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('registration-role-proof.localhost');

        DB::connection($this->tenantRuntimeAlias)->unprepared(<<<'SQL'
            CREATE TRIGGER enforce_staff_role_on_insert
            AFTER INSERT ON users
            BEGIN
                UPDATE users SET role = 'staff' WHERE id = NEW.id;
            END;
        SQL);

        $payload = [
            'first_name' => 'Riley',
            'middle_initial' => 'Q',
            'last_name' => 'Cook',
            'email' => 'riley@example.com',
            'phone' => '5552221111',
            'phone_format' => 'us',
            'role' => 'manager',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ];

        $this->withSession([
            'tenant_registration_otp_verified_email' => [
                'email' => 'riley@example.com',
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_domain' => (string) $tenant->domain,
                'verified_at' => now()->toIso8601String(),
            ],
        ])->post('http://registration-role-proof.localhost/auth/tenant/register', $payload)
            ->assertRedirect('http://registration-role-proof.localhost/dashboard')
            ->assertSessionHas('tenant_role', 'staff')
            ->assertSessionHas('tenant_authenticated_domain', (string) $tenant->domain);

        $this->assertDatabaseHas('users', [
            'email' => 'riley@example.com',
            'role' => 'staff',
        ], $this->tenantRuntimeAlias);
    }

    public function test_registration_legacy_schema_without_role_column_keeps_safe_session_role(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('legacy-role-proof.localhost', false);

        $payload = [
            'first_name' => 'Sam',
            'middle_initial' => 'T',
            'last_name' => 'Lane',
            'email' => 'sam@example.com',
            'phone' => '5554443333',
            'phone_format' => 'us',
            'role' => 'manager',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ];

        $this->withSession([
            'tenant_registration_otp_verified_email' => [
                'email' => 'sam@example.com',
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_domain' => (string) $tenant->domain,
                'verified_at' => now()->toIso8601String(),
            ],
        ])->post('http://legacy-role-proof.localhost/auth/tenant/register', $payload)
            ->assertRedirect('http://legacy-role-proof.localhost/dashboard')
            ->assertSessionHas('tenant_role', 'staff')
            ->assertSessionHas('tenant_authenticated_domain', (string) $tenant->domain);

        $this->assertDatabaseHas('users', [
            'email' => 'sam@example.com',
            'is_active' => 1,
        ], $this->tenantRuntimeAlias);
    }

    public function test_tenant_dashboard_uses_deterministic_zeroed_metrics_in_production(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('dashboard-prod.localhost');
        $originalEnv = (string) config('app.env');
        config()->set('app.env', 'production');

        $response = $this->withSession([
            'tenant_authenticated_domain' => (string) $tenant->domain,
            'tenant_role' => 'staff',
            'tenant_user_email' => 'staff@example.com',
            'tenant_user_name' => 'Staff User',
        ])->get('http://dashboard-prod.localhost/dashboard');

        $response->assertOk();
        $response->assertViewHas('stats', static fn (array $stats): bool => $stats === [
            'orders_today' => 0,
            'orders_growth' => '+0%',
            'delivered_percent' => '0%',
            'delivered_count' => 0,
            'in_kitchen' => 0,
            'pending_pickup' => 0,
            'revenue' => '0',
            'active_staff' => 0,
            'pending_approvals' => 0,
            'assigned_tasks' => 0,
        ]);
        $response->assertViewHas('chart_data', static fn (array $chartData): bool => $chartData === [
            'revenue' => [0, 0, 0, 0, 0, 0, 0],
            'orders' => [0, 0, 0, 0, 0, 0, 0],
        ]);
        $response->assertViewHas('recent_orders', []);

        config()->set('app.env', $originalEnv);
    }

    public function test_otp_send_is_rate_limited_with_clear_message(): void
    {
        $this->createTenantWithTenantUserTable('otp-send-throttle.localhost');
        Mail::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('http://otp-send-throttle.localhost/auth/tenant/otp/send', [
                'email' => 'alice@example.com',
            ])->assertOk();
        }

        $this->postJson('http://otp-send-throttle.localhost/auth/tenant/otp/send', [
            'email' => 'alice@example.com',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', static fn (string $message): bool => str_contains($message, 'Too many verification code requests.'));
    }

    public function test_otp_verify_is_rate_limited_with_clear_message(): void
    {
        $tenant = $this->createTenantWithTenantUserTable('otp-verify-throttle.localhost');

        $session = [
            'tenant_registration_otp_challenge' => [
                'email' => 'alice@example.com',
                'otp_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_domain' => (string) $tenant->domain,
            ],
        ];

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->withSession($session)->postJson('http://otp-verify-throttle.localhost/auth/tenant/otp/verify', [
                'email' => 'alice@example.com',
                'code' => '000000',
            ])->assertStatus(422);
        }

        $this->withSession($session)->postJson('http://otp-verify-throttle.localhost/auth/tenant/otp/verify', [
            'email' => 'alice@example.com',
            'code' => '000000',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', static fn (string $message): bool => str_contains($message, 'Too many verification attempts.'));
    }

    public function test_otp_verify_rejects_challenge_replay_from_another_tenant_domain(): void
    {
        $sourceTenant = $this->createTenantWithTenantUserTable('otp-source.localhost');
        $this->createTenantWithTenantUserTable('otp-target.localhost');

        $this->withSession([
            'tenant_registration_otp_challenge' => [
                'email' => 'alice@example.com',
                'otp_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
                'tenant_id' => (int) $sourceTenant->getKey(),
                'tenant_domain' => (string) $sourceTenant->domain,
            ],
        ])->postJson('http://otp-target.localhost/auth/tenant/otp/verify', [
            'email' => 'alice@example.com',
            'code' => '123456',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'OTP challenge expired or invalid. Please request a new code.');
    }

    public function test_registration_rejects_verified_otp_proof_replay_from_another_tenant_domain(): void
    {
        $sourceTenant = $this->createTenantWithTenantUserTable('proof-source.localhost');
        $this->createTenantWithTenantUserTable('proof-target.localhost');

        $payload = [
            'first_name' => 'Alice',
            'middle_initial' => 'B',
            'last_name' => 'Cooper',
            'email' => 'alice@example.com',
            'phone' => '5551234567',
            'phone_format' => 'us',
            'role' => 'staff',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ];

        $this->withSession([
            'tenant_registration_otp_verified_email' => [
                'email' => 'alice@example.com',
                'tenant_id' => (int) $sourceTenant->getKey(),
                'tenant_domain' => (string) $sourceTenant->domain,
                'verified_at' => now()->toIso8601String(),
            ],
        ])->post('http://proof-target.localhost/auth/tenant/register', $payload)
            ->assertSessionHasErrors('otp');

        $this->assertDatabaseMissing('users', [
            'email' => 'alice@example.com',
        ], $this->tenantRuntimeAlias);
    }

    public function test_otp_send_returns_error_when_delivery_fails_and_log_context_is_sanitized(): void
    {
        $this->createTenantWithTenantUserTable('otp-log-safety.localhost');

        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP down'));
        Log::shouldReceive('warning')->once()->withArgs(
            static function (string $message, array $context): bool {
                if ($message !== 'Tenant registration OTP delivery failed.') {
                    return false;
                }

                return ! array_key_exists('code', $context)
                    && ! array_key_exists('email', $context)
                    && ! array_key_exists('reason', $context)
                    && array_key_exists('tenant_domain', $context)
                    && array_key_exists('error_type', $context);
            }
        );

        $this->postJson('http://otp-log-safety.localhost/auth/tenant/otp/send', [
            'email' => 'alice@example.com',
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Unable to send verification code right now. Please try again in a few moments.');
    }

    public function test_otp_send_returns_mock_code_in_local_mode_and_verifies_successfully(): void
    {
        $this->createTenantWithTenantUserTable('otp-mock-code.localhost');
        config()->set('tenancy.mock_otp_code', '123456');

        $this->postJson('http://otp-mock-code.localhost/auth/tenant/otp/send', [
            'email' => 'alice@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('mock_code', '123456');

        $this->postJson('http://otp-mock-code.localhost/auth/tenant/otp/verify', [
            'email' => 'alice@example.com',
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');
    }

    private function createTenantWithTenantUserTable(string $domain, bool $includeRoleColumn = true): Tenant
    {
        $tenantDatabasePath = storage_path('framework/testing/'.Str::uuid().'-tenant-auth.sqlite');

        $directory = dirname($tenantDatabasePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        touch($tenantDatabasePath);
        $this->tenantDatabasePaths[] = $tenantDatabasePath;

        $tenant = Tenant::factory()->create([
            'domain' => $domain,
            'database_name' => $tenantDatabasePath,
            'provisioning_status' => 'ready',
            'is_active' => true,
        ]);

        $runtimeConnectionConfig = config('database.connections.sqlite');
        $runtimeConnectionConfig['database'] = $tenantDatabasePath;

        config(["database.connections.{$this->tenantRuntimeAlias}" => $runtimeConnectionConfig]);
        DB::purge($this->tenantRuntimeAlias);

        if (! Schema::connection($this->tenantRuntimeAlias)->hasTable('users')) {
            Schema::connection($this->tenantRuntimeAlias)->create('users', function (Blueprint $table) use ($includeRoleColumn): void {
                $table->increments('id');
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                if ($includeRoleColumn) {
                    $table->string('role')->default('staff');
                }
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        return $tenant;
    }
}
