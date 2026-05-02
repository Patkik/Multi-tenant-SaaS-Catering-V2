<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterTenantRequest;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;

class TenantOnboardingController extends Controller
{
    public function store(RegisterTenantRequest $request, TenantProvisioningService $tenantProvisioningService): JsonResponse
    {
        $tenant = $tenantProvisioningService->provision($request->validated());

        return response()->json([
            'message' => 'Tenant registered successfully.',
            'data' => $tenant,
        ], 201);
    }
}
