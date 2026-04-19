<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * API health and documentation endpoint.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'name' => 'CaterPro API',
            'version' => '1.0.0',
            'environment' => app()->environment(),
            'endpoints' => [
                'health' => url('/'),
                'auth' => url('/api/user'),
                'tenant_onboarding' => url('/api/tenants/register'),
                'central_dashboard' => url('/api/central/dashboard'),
                'central_plans' => url('/api/central/plans'),
                'central_tenants' => url('/api/central/tenants'),
                'tenant_capabilities' => url('/api/tenant/capabilities'),
                'tenant_events' => url('/api/tenant/events'),
            ],
            'documentation' => 'API routes require either API authentication (Sanctum token) or multi-tenant subdomain routing.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
