<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantBrandingRequest;
use App\Http\Requests\UpdateTenantPlanRequest;
use App\Models\Tenant;
use App\Services\CentralTenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class CentralTenantController extends Controller
{
    public function __construct(private readonly CentralTenantService $centralTenantService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        $search = trim((string) $request->query('search', ''));
        $plan = trim((string) $request->query('plan', ''));
        $status = trim((string) $request->query('status', ''));

        return response()->json([
            'data' => $this->centralTenantService->listTenants($perPage, [
                'search' => $search,
                'plan' => $plan,
                'status' => $status,
            ]),
        ]);
    }

    public function updatePlan(UpdateTenantPlanRequest $request, Tenant $tenant): JsonResponse
    {
        $updatedTenant = $this->centralTenantService->updatePlan($tenant, (string) $request->validated('plan'));

        return response()->json([
            'message' => 'Tenant plan updated successfully.',
            'data' => $this->centralTenantService->tenantPayload($updatedTenant),
        ]);
    }

    public function updateBranding(UpdateTenantBrandingRequest $request, Tenant $tenant): JsonResponse
    {
        $updatedTenant = $this->centralTenantService->updateBranding($tenant, $request->validated());

        return response()->json([
            'message' => 'Tenant branding updated successfully.',
            'data' => $this->centralTenantService->tenantPayload($updatedTenant),
        ]);
    }

    public function updateStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $updatedTenant = $this->centralTenantService->updateStatus($tenant, (bool) $validated['is_active']);

        return response()->json([
            'message' => 'Tenant status updated successfully.',
            'data' => $this->centralTenantService->tenantPayload($updatedTenant),
        ]);
    }

    public function checkSubdomainAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $subdomain = Str::lower((string) $validated['subdomain']);
        $baseDomain = (string) Arr::first(config('tenancy.central_domains', ['127.0.0.1']));
        $fullDomain = $subdomain.'.'.$baseDomain;

        $isReserved = in_array($subdomain, config('tenancy.central_domains', []), true)
            || in_array($fullDomain, config('tenancy.central_domains', []), true);

        $exists = Domain::query()->whereIn('domain', [$subdomain, $fullDomain])->exists();

        return response()->json([
            'data' => [
                'subdomain' => $subdomain,
                'available' => ! $isReserved && ! $exists,
                'reason' => $isReserved ? 'reserved' : ($exists ? 'taken' : null),
            ],
        ]);
    }
}
