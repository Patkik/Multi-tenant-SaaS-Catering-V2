<?php

namespace Tests\Feature;

use Tests\TestCase;

class CentralDashboardUiTest extends TestCase
{
    public function test_dashboard_route_renders_central_command_ui(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSeeText('Central Command')
            ->assertSeeText('Recent Template Applications');
    }
}
