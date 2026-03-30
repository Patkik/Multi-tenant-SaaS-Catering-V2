<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\TenantProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TenantController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $tenantProvisioningService)
    {
    }

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
}
