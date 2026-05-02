<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
    }

    public function test_it_logs_in_a_central_user_with_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@caterpro.local',
            'password' => Hash::make('password123'),
        ]);
        $user->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        $response = $this->postJson('/api/central/auth/login', [
            'email' => 'admin@caterpro.local',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'admin@caterpro.local');
    }

    public function test_it_rejects_login_for_user_without_central_permissions(): void
    {
        User::factory()->create([
            'email' => 'viewer@caterpro.local',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/central/auth/login', [
            'email' => 'viewer@caterpro.local',
            'password' => 'password123',
        ]);

        $response->assertForbidden();
    }

    public function test_it_returns_current_central_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/central/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }
}