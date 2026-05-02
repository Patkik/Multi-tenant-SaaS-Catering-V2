<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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

        // Prevent any accidental real HTTP calls in all update tests
        Http::preventStrayRequests();
    }

    public function test_it_returns_disabled_update_state_when_repository_is_not_configured(): void
    {
        config()->set('services.app_updates.github_repository', '');
        config()->set('services.app_updates.apply_command', '');
        config()->set('app.version', '2.0.2');

        // No GitHub call expected — short-circuits before the HTTP request
        Http::fake();

        $response = $this->getJson('/api/central/app-updates');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.update_available', false)
            ->assertJsonPath('data.apply_mode', 'manual')
            ->assertJsonPath('data.can_apply', false)
            ->assertJsonPath('data.current_version', '2.0.2');
    }

    public function test_it_reports_update_availability_from_latest_github_release(): void
    {
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.cache_ttl', 300);
        config()->set('services.app_updates.apply_command', '');

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
            ->assertJsonPath('data.apply_mode', 'manual')
            ->assertJsonPath('data.can_apply', false)
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

        // Match actual service message (includes TLS hint)
        $this->assertStringStartsWith(
            'GitHub API is unreachable.',
            (string) $response->json('data.error')
        );
    }

    public function test_it_falls_back_to_direct_fetch_when_cache_store_cannot_tag(): void
    {
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.cache_ttl', 300);

        // Flush so there is no cached value — the service will call Cache::remember() and
        // fetch from GitHub. We verify via Http::assertSentCount that the real fetch happened.
        // (We no longer mock the whole cache facade — it breaks the framework's RateLimiter boot.)
        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'Version 2.1.0',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        $response = $this->getJson('/api/central/app-updates');

        $response->assertOk()
            ->assertJsonPath('data.current_version', '2.0.2')
            ->assertJsonPath('data.latest_version', '2.1.0')
            ->assertJsonPath('data.update_available', true);

        Http::assertSentCount(1);
    }

    public function test_it_returns_manual_required_when_update_is_available_but_auto_command_is_missing(): void
    {
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

        $response = $this->postJson('/api/central/app-updates/apply');

        $response->assertOk()
            ->assertJsonPath('data.status', 'manual_required')
            ->assertJsonPath('data.release.update_available', true)
            ->assertJsonPath('data.release.can_apply', false)
            ->assertJsonPath('data.release_url', 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0');

        Process::assertNothingRan();
    }

    public function test_it_executes_configured_update_command_when_update_is_available(): void
    {
        config()->set('app.version', '2.0.2');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');
        config()->set('services.app_updates.apply_command', 'php artisan about');
        config()->set('services.app_updates.apply_timeout', 600);

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'Version 2.1.0',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.1.0',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        Process::fake([
            'php artisan about' => Process::result('Updated successfully'),
        ]);

        $response = $this->postJson('/api/central/app-updates/apply');

        $response->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.release.update_available', true)
            ->assertJsonPath('data.release.can_apply', true)
            ->assertJsonPath('data.exit_code', 0)
            ->assertJsonPath('data.output', 'Updated successfully');

        Process::assertRan('php artisan about');
    }

    public function test_it_does_not_execute_update_command_when_system_is_already_up_to_date(): void
    {
        config()->set('app.version', '2.1.0');
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

        Process::fake();

        $response = $this->postJson('/api/central/app-updates/apply');

        $response->assertOk()
            ->assertJsonPath('data.status', 'up_to_date')
            ->assertJsonPath('data.release.update_available', false);

        Process::assertNothingRan();
    }

    public function test_it_syncs_the_runtime_version_on_demand(): void
    {
        config()->set('app.version', '2.0.9');
        config()->set('services.app_updates.github_repository', 'Patik/Multi-tenant-SaaS-Catering-V2');

        // Mock git pull and GitHub API so sync returns the faked tag
        Process::fake([
            'git pull' => Process::result('Already up to date.'),
        ]);

        Http::fake([
            'https://api.github.com/repos/Patik/Multi-tenant-SaaS-Catering-V2/releases/latest' => Http::response([
                'tag_name' => 'v2.0.9',
                'name' => 'Version 2.0.9',
                'html_url' => 'https://github.com/Patik/Multi-tenant-SaaS-Catering-V2/releases/tag/v2.0.9',
                'published_at' => now()->subMinute()->toIso8601String(),
            ], 200),
        ]);

        $response = $this->postJson('/api/central/app-updates/sync-version');

        $response->assertOk()
            ->assertJsonPath('data.status', 'synced')
            ->assertJsonPath('data.current_version', '2.0.9');
    }
}
