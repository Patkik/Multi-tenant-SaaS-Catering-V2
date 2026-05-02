<?php

namespace App\Http\Middleware;

use App\Support\PlanFeatures;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureTenantFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required for this endpoint.',
            ], 400);
        }

        $plan = (string) ($tenant->getAttribute('plan') ?? 'free');
        $enabledFeatures = $tenant->getAttribute('enabled_features');

        if (! is_array($enabledFeatures)) {
            $enabledFeatures = PlanFeatures::forPlan($plan);
        }

        if (! in_array($feature, $enabledFeatures, true)) {
            return response()->json([
                'message' => 'Your current subscription plan does not include this feature.',
                'feature' => $feature,
                'plan' => $plan,
            ], 403);
        }

        return $next($request);
    }
}
