<?php

namespace Tests\Feature;

use App\Mail\SupportMessageMail;
use App\Models\User;
use App\Support\CentralPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralSupportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (CentralPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
    }

    public function test_it_sends_a_central_support_request_by_email(): void
    {
        Mail::fake();

        config()->set('support.central_recipient', 'central-support@example.com');

        $user = User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'admin@caterpro.local',
            'password' => Hash::make('password123'),
        ]);
        $user->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/central/support', [
            'category' => 'bug',
            'subject' => 'Dashboard totals look wrong',
            'message' => 'The revenue total on the dashboard is missing the latest paid invoices.',
            'contact_name' => 'Central Admin',
            'contact_email' => 'admin@caterpro.local',
            'page_path' => '/central/dashboard',
            'user_role' => 'Central Admin',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.message', 'Your support request has been sent to the central team.');

        $this->assertDatabaseHas('support_messages', [
            'source' => 'central',
            'subject' => 'Dashboard totals look wrong',
            'workspace_name' => 'Central Platform',
        ]);

        Mail::assertSent(SupportMessageMail::class, function (SupportMessageMail $mail): bool {
            return $mail->source === 'central'
                && ($mail->payload['category'] ?? null) === 'bug'
                && ($mail->supportMetadata['workspace_name'] ?? null) === 'Central Platform';
        });
    }

    public function test_it_requires_authentication_for_central_support_requests(): void
    {
        $response = $this->postJson('/api/central/support', [
            'category' => 'feedback',
            'subject' => 'Feature request',
            'message' => 'Please add a shortcut for exporting reports.',
        ]);

        $response->assertUnauthorized();
    }

    public function test_it_lists_tenant_support_submissions_for_central_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'admin@caterpro.local',
            'password' => Hash::make('password123'),
        ]);
        $user->givePermissionTo(CentralPermissions::DASHBOARD_VIEW);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/central/support', [
            'category' => 'feedback',
            'subject' => 'Central feedback sample',
            'message' => 'This is a feedback sample created from the central workspace.',
            'contact_name' => 'Central Admin',
            'contact_email' => 'admin@caterpro.local',
            'page_path' => '/central/support',
            'user_role' => 'Central Admin',
        ])->assertOk();

        \Illuminate\Support\Facades\DB::table('support_messages')->insert([
            'source' => 'tenant',
            'category' => 'bug',
            'subject' => 'Tenant issue sample',
            'message' => 'Tenant could not load the bookings board after a recent update.',
            'contact_name' => 'Tenant Manager',
            'contact_email' => 'tenant@example.com',
            'workspace_name' => 'Demo Tenant',
            'workspace_id' => 'tenant-demo',
            'tenant_id' => 'tenant-demo',
            'page_path' => '/bookings',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/central/support/submissions?source=tenant');

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.source', 'tenant')
            ->assertJsonPath('data.items.0.subject', 'Tenant issue sample');
    }
}
