<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralUserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $admin = User::factory()->create();
        $admin->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        Sanctum::actingAs($admin, ['*']);
    }

    public function test_it_updates_a_central_user_name_and_email(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'admin@caterpro.local',
        ]);

        $response = $this->patchJson('/api/central/users/'.$targetUser->id, [
            'name' => 'Central Operations Admin',
            'email' => 'ops-admin@caterpro.local',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.name', 'Central Operations Admin')
            ->assertJsonPath('data.email', 'ops-admin@caterpro.local');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Central Operations Admin',
            'email' => 'ops-admin@caterpro.local',
        ]);
    }

    public function test_it_rejects_duplicate_email_when_updating_a_central_user(): void
    {
        $targetUser = User::factory()->create([
            'email' => 'admin@caterpro.local',
        ]);

        User::factory()->create([
            'email' => 'existing@caterpro.local',
        ]);

        $response = $this->patchJson('/api/central/users/'.$targetUser->id, [
            'name' => 'Central Admin',
            'email' => 'existing@caterpro.local',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
