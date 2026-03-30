<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\TenantProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Models\RoleTemplateApplication;
use App\Models\Tenant;
use App\Models\UsageSnapshot;
use App\Services\FeatureService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly FeatureService $featureService,
    ) {}

    public function store(StoreTenantRequest $request): JsonResponse
    {
        Gate::authorize('admin.tenants.create');

        try {
            $tenant = $this->tenantProvisioningService->createTenant($request->validated());
        } catch (TenantProvisioningException) {
            return response()->json([
                'message' => 'Tenant provisioning failed.',
            ], 500);
        }

        return response()->json([
            'data' => $tenant,
        ], 201);
    }

    public function monitoring(Tenant $tenant): JsonResponse
    {
        Gate::authorize('admin.tenants.monitoring');

        $effectiveFeatures = $this->featureService->resolveEffectiveFeatures($tenant);
        $activeFeaturesCount = $effectiveFeatures->where('is_enabled', true)->count();
        $deactivatedFeaturesCount = $effectiveFeatures->where('is_enabled', false)->count();

        $latestApplication = $tenant->roleTemplateApplications()->latest()->first();

        $latestAppliedApplication = $tenant->roleTemplateApplications()
            ->where('status', RoleTemplateApplication::STATUS_APPLIED)
            ->latest('applied_at')
            ->latest()
            ->with(['roleTemplate.permissions'])
            ->first();

        $latestSnapshot = $tenant->usageSnapshots()
            ->latest('captured_at')
            ->latest()
            ->first();

        $latestDailySnapshot = $tenant->usageSnapshots()
            ->where('window_type', UsageSnapshot::WINDOW_DAILY)
            ->latest('captured_at')
            ->latest()
            ->first();

        $activeRoles = $this->extractActiveRoles($latestAppliedApplication);

        return response()->json([
            'tenant_domain' => $tenant->domain,
            'tenant_name' => $tenant->name,
            'admin_name' => $latestApplication?->requested_by_admin,
            'users_total' => $latestSnapshot?->users_total ?? 0,
            'active_roles' => $activeRoles,
            'active_features_count' => $activeFeaturesCount,
            'deactivated_features_count' => $deactivatedFeaturesCount,
            'usage_snapshot_summary' => $latestDailySnapshot === null ? null : [
                'captured_at' => $latestDailySnapshot->captured_at?->toIso8601String(),
                'storage_mb' => $latestDailySnapshot->storage_mb,
                'orders_count' => $latestDailySnapshot->orders_count,
                'metadata' => $latestDailySnapshot->metadata,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractActiveRoles(?RoleTemplateApplication $application): array
    {
        if ($application === null || $application->roleTemplate === null) {
            return [];
        }

        /** @var Collection<int, string> $roleNames */
        $roleNames = $application->roleTemplate->permissions
            ->pluck('role_name')
            ->filter(static fn ($roleName): bool => is_string($roleName) && $roleName !== '')
            ->map(static fn (string $roleName): string => $roleName)
            ->unique()
            ->values();

        return $roleNames->all();
    }
}
