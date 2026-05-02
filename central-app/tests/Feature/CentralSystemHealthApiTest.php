<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralSystemHealthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        Sanctum::actingAs($user, ['*']);
    }

    public function test_it_reports_health_for_configured_local_services(): void
    {
        config([
            'queue.default' => 'database',
            'cache.default' => 'file',
            'filesystems.default' => 'local',
            'mail.default' => 'log',
        ]);

        $response = $this->getJson('/api/central/system-health');

        $response->assertOk();

        $serviceNames = collect($response->json('data.service_health', []))
            ->pluck('name')
            ->values();

        $this->assertTrue($serviceNames->contains('Landlord DB'));
        $this->assertTrue($serviceNames->contains('Database Queue'));
        $this->assertTrue($serviceNames->contains('File Cache'));
        $this->assertTrue($serviceNames->contains('Local Storage'));
        $this->assertTrue($serviceNames->contains('Log Mailer'));

        $this->assertFalse($serviceNames->contains('S3 Storage'));
        $this->assertFalse($serviceNames->contains('Redis Cache'));

        $resourceLabels = collect($response->json('data.resource_usage', []))
            ->pluck('label')
            ->values();

        $this->assertTrue($resourceLabels->contains('CPU'));
        $this->assertTrue($resourceLabels->contains('Memory'));
        $this->assertTrue($resourceLabels->contains('Disk landlord'));
        $this->assertTrue($resourceLabels->contains('Disk tenant DBs'));
        $this->assertFalse($resourceLabels->contains('Queue throughput'));
    }
}
