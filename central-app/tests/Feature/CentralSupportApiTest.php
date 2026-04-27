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

        Mail::assertSent(SupportMessageMail::class, function (SupportMessageMail $mail): bool {
            return $mail->source === 'central'
                && ($mail->payload['category'] ?? null) === 'bug'
                && ($mail->metadata['workspace_name'] ?? null) === 'Central Platform';
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
}
