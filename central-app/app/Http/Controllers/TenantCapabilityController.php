<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTenantIsActive;
use App\Services\AppUpdateService;
use App\Support\PlanFeatures;
use App\Support\TenantRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;

class TenantCapabilityController extends Controller
{
    public function __construct(
        private readonly AppUpdateService $appUpdateService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $plan = PlanFeatures::normalizePlan((string) ($tenant->getAttribute('plan') ?? 'free'));
        $enabledFeatures = $tenant->getAttribute('enabled_features');

        if (! is_array($enabledFeatures)) {
            $enabledFeatures = PlanFeatures::forPlan($plan);
        }

        $branding = $tenant->getAttribute('branding');
        $clientAccess = $tenant->getAttribute('client_access');

        if (! is_array($branding)) {
            $branding = [];
        }

        if (! is_bool($clientAccess)) {
            $clientAccess = PlanFeatures::supportsClientPortal($plan);
        }

        $subdomain = optional($tenant->domains->first())->domain;
        $baseDomain = (string) Arr::first(config('tenancy.central_domains', ['localhost']));
        $user = $request->user();
        $activeRole = $user ? TenantRoles::resolveFromUser($user) : null;
        $isActive = (bool) ($tenant->getAttribute('is_active') ?? true);

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->getTenantKey(),
                'company_name' => $tenant->getAttribute('company_name'),
                'subdomain' => $subdomain,
                'full_domain' => $subdomain ? sprintf('%s.%s', $subdomain, $baseDomain) : null,
                'app_version' => $this->appUpdateService->currentVersion(),
                'plan' => $plan,
                'plan_details' => PlanFeatures::detailsForPlan($plan),
                'enabled_features' => $enabledFeatures,
                'client_access' => $clientAccess,
                'branding' => [
                    'primary_color' => Arr::get($branding, 'primary_color', '#0B8F66'),
                    'logo_url' => Arr::get($branding, 'logo_url'),
                    'logo_path' => Arr::get($branding, 'logo_path'),
                ],
                'role_capabilities' => TenantRoles::moduleCapabilities(),
                'available_roles' => TenantRoles::all(),
                'active_role' => $activeRole,
                'is_authenticated' => (bool) $user,
                'is_active' => $isActive,
                'status' => $isActive ? 'active' : 'suspended',
                'access_restriction_reason' => $isActive ? null : EnsureTenantIsActive::suspensionMessage(),
                'access_restriction_code' => $isActive ? null : 'tenant_suspended',
            ],
        ]);
    }

    public function appUpdates(): JsonResponse
    {
        return response()->json([
            'data' => $this->appUpdateService->latestRelease(),
        ]);
    }

    public function applyAppUpdate(): JsonResponse
    {
        return response()->json([
            'data' => $this->appUpdateService->applyLatestRelease(),
        ]);
    }
}
