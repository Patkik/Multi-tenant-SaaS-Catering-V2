<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
{
    private const SUSPENSION_MESSAGE = 'Access denied: this tenant workspace is suspended in the central app. Access returns once the central admin restores this tenant.';

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required for this endpoint.',
            ], 400);
        }

        $isActive = $tenant->getAttribute('is_active');

        if ($isActive === null || filter_var($isActive, FILTER_VALIDATE_BOOL) !== false) {
            return $next($request);
        }

        return response()->json([
            'message' => self::SUSPENSION_MESSAGE,
            'status' => 'suspended',
            'reason_code' => 'tenant_suspended',
        ], 403);
    }

    public static function suspensionMessage(): string
    {
        return self::SUSPENSION_MESSAGE;
    }
}
