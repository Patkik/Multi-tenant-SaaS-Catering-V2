<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertTenantFeatureOverrideRequest;
use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\RBACChangeAudit;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TenantFeatureOverrideController extends Controller
{
    public function index(Tenant $tenant): JsonResponse
    {
        Gate::authorize('admin.tenants.overrides.read');

        $overrides = FeatureOverride::query()
            ->where('tenant_id', $tenant->id)
            ->with('feature:id,name,category')
            ->orderByDesc('set_at')
            ->get();

        return response()->json([
            'data' => $overrides,
        ]);
    }

    public function upsert(UpsertTenantFeatureOverrideRequest $request, Tenant $tenant, Feature $feature): JsonResponse
    {
        Gate::authorize('admin.tenants.overrides.write');

        $payload = $request->validated();

        /** @var FeatureOverride $override */
        $override = FeatureOverride::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_id' => $feature->id,
            ],
            [
                'is_enabled' => (bool) $payload['is_enabled'],
                'reason' => $payload['reason'] ?? null,
                'set_by_admin' => 'central-admin-token',
                'set_at' => now(),
                'expires_at' => $payload['expires_at'] ?? null,
            ],
        );

        RBACChangeAudit::query()->create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'central_admin_token',
            'actor_id' => null,
            'action' => 'feature_override.upserted',
            'resource_type' => 'feature_override',
            'resource_id' => $override->id,
            'before_state' => null,
            'after_state' => [
                'feature_id' => $feature->id,
                'is_enabled' => (bool) $override->is_enabled,
                'reason' => $override->reason,
                'expires_at' => optional($override->expires_at)->toIso8601String(),
            ],
            'metadata' => null,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $override->fresh(['feature:id,name,category']),
        ]);
    }

    public function destroy(Tenant $tenant, Feature $feature): JsonResponse
    {
        Gate::authorize('admin.tenants.overrides.write');

        $deleted = FeatureOverride::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->delete();

        if ($deleted > 0) {
            RBACChangeAudit::query()->create([
                'tenant_id' => $tenant->id,
                'actor_type' => 'central_admin_token',
                'actor_id' => null,
                'action' => 'feature_override.reverted',
                'resource_type' => 'feature',
                'resource_id' => $feature->id,
                'before_state' => null,
                'after_state' => null,
                'metadata' => null,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Feature override reverted.',
        ]);
    }
}
