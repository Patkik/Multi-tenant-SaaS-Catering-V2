<?php

namespace App\Http\Controllers;

use App\Services\CentralTenantService;
use Illuminate\Http\JsonResponse;

class CentralDashboardController extends Controller
{
    public function __construct(private readonly CentralTenantService $centralTenantService)
    {
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->dashboardStats(),
        ]);
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => $this->centralTenantService->plansCatalog(),
        ]);
    }
}
