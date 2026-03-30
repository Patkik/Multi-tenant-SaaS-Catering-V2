<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TenantEffectiveFeatureController extends Controller
{
    public function __construct(private readonly FeatureService $featureService)
    {
    }

    public function index(Tenant $tenant): JsonResponse
    {
        Gate::authorize('admin.tenants.read-effective-features');

        return response()->json([
            'tenant_id' => $tenant->id,
            'data' => $this->featureService->resolveEffectiveFeatures($tenant),
        ]);
    }
}
