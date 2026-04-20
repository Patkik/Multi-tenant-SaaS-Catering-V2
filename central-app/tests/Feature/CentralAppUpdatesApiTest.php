<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralAppUpdatesApiTest extends TestCase
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
        Cache::flush();
    }

    public function test_it_returns_disabled_update_state_when_repository_is_not_configured(): void
    {
        config()->set('services.app_updates.github_repository', '');

        $response = $this->getJson('/api/central/app-updates');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.update_available', false)
            ->assertJsonPath('data.current_version', (string) config('app.version'));
    }

    public function test_it_reports_update_availability_from_latest_github_release(): void
    {
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.cache_ttl', 300);

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'Version 2.1.0',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        $responseOne = $this->getJson('/api/central/app-updates');
        $responseTwo = $this->getJson('/api/central/app-updates');

        $responseOne->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.current_version', '2.0.2')
            ->assertJsonPath('data.latest_tag', 'v2.1.0')
            ->assertJsonPath('data.latest_version', '2.1.0')
            ->assertJsonPath('data.comparison_mode', 'semver')
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.release_url', 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0');

        $responseTwo->assertOk()
            ->assertJsonPath('data.update_available', true);

        Http::assertSentCount(1);
    }

    public function test_it_handles_github_connection_failures_gracefully(): void
    {
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::failedConnection(),
        ]);

        $response = $this->getJson('/api/central/app-updates');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.update_available', false);

        $this->assertStringStartsWith(
            'GitHub API is unreachable right now.',
            (string) $response->json('data.error')
        );
    }
}
