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

class TenantAssignmentApiTest extends TestCase
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
            'id' => 'acme-assignment-tenant',
            'company_name' => 'Acme Catering',
            'plan' => 'starter',
            'enabled_features' => ['event_management', 'staff_assignment'],
        ]);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_it_creates_assignment_with_shift_window_fields(): void
    {
        $eventId = $this->createEvent('Morning Banquet', '2026-05-01');
        $staffId = $this->createStaff('Jordan', 'Crew');

        $response = $this->postJson('/api/tenant/assignments', [
            'event_id' => $eventId,
            'staff_id' => $staffId,
            'assignment_role' => 'Lead Server',
            'shift_start_at' => '2026-05-01 08:00:00',
            'shift_end_at' => '2026-05-01 12:00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.assignment_role', 'Lead Server')
            ->assertJsonPath('data.event.id', $eventId)
            ->assertJsonPath('data.staff.id', $staffId);

        $this->assertDatabaseHas('event_staff', [
            'event_id' => $eventId,
            'staff_id' => $staffId,
            'assignment_role' => 'Lead Server',
        ]);
    }

    public function test_it_allows_non_overlapping_same_day_assignments_for_same_staff(): void
    {
        $staffId = $this->createStaff('Taylor', 'Cook');
        $eventAId = $this->createEvent('Breakfast Event', '2026-05-02');
        $eventBId = $this->createEvent('Dinner Event', '2026-05-02');

        $this->postJson('/api/tenant/assignments', [
            'event_id' => $eventAId,
            'staff_id' => $staffId,
            'shift_start_at' => '2026-05-02 07:00:00',
            'shift_end_at' => '2026-05-02 10:00:00',
        ])->assertCreated();

        $this->postJson('/api/tenant/assignments', [
            'event_id' => $eventBId,
            'staff_id' => $staffId,
            'shift_start_at' => '2026-05-02 13:00:00',
            'shift_end_at' => '2026-05-02 16:00:00',
        ])->assertCreated();

        $this->assertDatabaseCount('event_staff', 2);
    }

    public function test_it_blocks_overlapping_shift_assignments_for_same_staff(): void
    {
        $staffId = $this->createStaff('Morgan', 'Server');
        $eventAId = $this->createEvent('Lunch Event', '2026-05-03');
        $eventBId = $this->createEvent('Brunch Event', '2026-05-03');

        $this->postJson('/api/tenant/assignments', [
            'event_id' => $eventAId,
            'staff_id' => $staffId,
            'shift_start_at' => '2026-05-03 10:00:00',
            'shift_end_at' => '2026-05-03 14:00:00',
        ])->assertCreated();

        $response = $this->postJson('/api/tenant/assignments', [
            'event_id' => $eventBId,
            'staff_id' => $staffId,
            'shift_start_at' => '2026-05-03 13:30:00',
            'shift_end_at' => '2026-05-03 15:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Staff member has a conflicting assignment for this timeslot.');

        $this->assertDatabaseCount('event_staff', 1);
    }

    private function createEvent(string $name, string $date): int
    {
        $clientId = \DB::table('clients')->insertGetId([
            'first_name' => 'Client',
            'last_name' => 'Sample',
            'email' => strtolower(str_replace(' ', '-', $name)).'@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return \DB::table('events')->insertGetId([
            'client_id' => $clientId,
            'event_name' => $name,
            'event_date' => $date,
            'location' => 'Metro Manila',
            'guest_count' => 25,
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStaff(string $firstName, string $lastName): int
    {
        return \DB::table('staff')->insertGetId([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName.'.'.$lastName).'@example.com',
            'position' => 'Kitchen',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureTenantTables(): void
    {
        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table): void {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('event_name');
                $table->date('event_date');
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->string('location');
                $table->unsignedInteger('guest_count')->default(1);
                $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table): void {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->string('position')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_staff')) {
            Schema::create('event_staff', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->string('assignment_role', 100)->nullable();
                $table->dateTime('shift_start_at')->nullable();
                $table->dateTime('shift_end_at')->nullable();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'staff_id']);
                $table->index(['staff_id', 'shift_start_at', 'shift_end_at']);
            });
        }
    }
}
