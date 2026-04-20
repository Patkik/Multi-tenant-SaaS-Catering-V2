<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AppUpdateService;
use App\Services\CentralTenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CentralInsightsController extends Controller
{
    public function __construct(
        private readonly CentralTenantService $centralTenantService,
        private readonly AppUpdateService $appUpdateService,
    ) {
    }

    public function plansPricing(): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->plansPricingOverview(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->userDirectory($request->query('search')),
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        return response()->json([
            'data' => $this->centralTenantService->updateCentralUser($user, $validated),
        ]);
    }

    public function revenueAnalytics(): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->revenueAnalytics(),
        ]);
    }

    public function systemHealth(): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->systemHealthSnapshot(),
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

    public function syncAppVersion(): JsonResponse
    {
        return response()->json([
            'data' => $this->appUpdateService->syncCurrentVersion(),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->auditTimeline(
                $request->query('search'),
                $request->query('type'),
                $request->query('actor'),
            ),
        ]);
    }
}

