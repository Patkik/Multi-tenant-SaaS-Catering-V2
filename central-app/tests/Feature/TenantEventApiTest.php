<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class TenantEventApiTest extends TestCase
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

        $this->tenant = Tenant::create([
            'id' => 'acme-test-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'free',
            'enabled_features' => ['event_management'],
        ]);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_it_creates_a_tenant_event_when_quota_is_available(): void
    {
        $response = $this->postJson('/api/tenant/events', [
            'event_name' => 'Corporate Lunch',
            'event_date' => '2026-04-21',
            'location' => 'Ortigas Center',
            'guest_count' => 60,
            'status' => 'pending',
            'quoted_total' => 56000,
            'client' => [
                'first_name' => 'Alex',
                'last_name' => 'Tan',
                'email' => 'alex@example.com',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.event_name', 'Corporate Lunch')
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.client.email', 'alex@example.com');

        $this->assertDatabaseHas('events', [
            'event_name' => 'Corporate Lunch',
            'status' => 'pending',
        ]);
    }

    public function test_it_rejects_event_creation_when_free_plan_quota_is_reached(): void
    {
        $clientId = \DB::table('clients')->insertGetId([
            'first_name' => 'Quota',
            'last_name' => 'Client',
            'email' => 'quota@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 10) as $index) {
            \DB::table('events')->insert([
                'client_id' => $clientId,
                'event_name' => 'Existing Event '.$index,
                'event_date' => '2026-04-0'.(($index % 9) + 1),
                'location' => 'Metro Manila',
                'guest_count' => 20,
                'status' => 'confirmed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->postJson('/api/tenant/events', [
            'event_name' => 'Overflow Event',
            'event_date' => '2026-04-22',
            'location' => 'BGC',
            'guest_count' => 80,
            'client' => [
                'first_name' => 'Casey',
                'last_name' => 'Lopez',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Free plan monthly active event limit reached.');

        $this->assertDatabaseMissing('events', [
            'event_name' => 'Overflow Event',
        ]);
    }

    public function test_it_lists_tenant_events_with_status_filter(): void
    {
        $clientId = \DB::table('clients')->insertGetId([
            'first_name' => 'List',
            'last_name' => 'Client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('events')->insert([
            [
                'client_id' => $clientId,
                'event_name' => 'Pending Event',
                'event_date' => '2026-05-02',
                'location' => 'Quezon City',
                'guest_count' => 25,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_id' => $clientId,
                'event_name' => 'Completed Event',
                'event_date' => '2026-05-03',
                'location' => 'Pasig',
                'guest_count' => 30,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/tenant/events?status=pending');

        $response->assertOk()
            ->assertJsonPath('data.data.0.event_name', 'Pending Event');
    }

    private function ensureTenantTables(): void
    {
        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table): void {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('address')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catering_packages')) {
            Schema::create('catering_packages', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('catering_package_id')->nullable()->constrained('catering_packages')->nullOnDelete();
                $table->string('event_name');
                $table->date('event_date');
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->string('location');
                $table->unsignedInteger('guest_count')->default(1);
                $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
                $table->decimal('quoted_total', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }
}
