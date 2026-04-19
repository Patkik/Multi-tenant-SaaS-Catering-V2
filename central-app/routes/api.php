<?php

use App\Http\Controllers\CentralAuthController;
use App\Http\Controllers\CentralDashboardController;
use App\Http\Controllers\CentralInsightsController;
use App\Http\Controllers\CentralTenantController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TenantOnboardingController;
use App\Support\CentralPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HealthController::class);

Route::post('/tenants/register', [TenantOnboardingController::class, 'store']);

Route::prefix('/central/auth')->group(function () {
    Route::post('/login', [CentralAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [CentralAuthController::class, 'me']);
        Route::post('/logout', [CentralAuthController::class, 'logout']);
    });
});

Route::prefix('/central')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [CentralDashboardController::class, 'stats'])
        ->middleware('permission:'.CentralPermissions::DASHBOARD_VIEW);
    Route::get('/plans', [CentralDashboardController::class, 'plans'])
        ->middleware('permission:'.CentralPermissions::PLANS_VIEW);
    Route::get('/plans-pricing', [CentralInsightsController::class, 'plansPricing'])
        ->middleware('permission:'.CentralPermissions::PLANS_VIEW);
    Route::get('/users', [CentralInsightsController::class, 'users'])
        ->middleware('permission:'.CentralPermissions::DASHBOARD_VIEW);
    Route::patch('/users/{user}', [CentralInsightsController::class, 'updateUser'])
        ->middleware('permission:'.CentralPermissions::DASHBOARD_VIEW);
    Route::get('/revenue-analytics', [CentralInsightsController::class, 'revenueAnalytics'])
        ->middleware('permission:'.CentralPermissions::DASHBOARD_VIEW);
    Route::get('/system-health', [CentralInsightsController::class, 'systemHealth'])
        ->middleware('permission:'.CentralPermissions::DASHBOARD_VIEW);
    Route::get('/audit-logs', [CentralInsightsController::class, 'auditLogs'])
        ->middleware('permission:'.CentralPermissions::DASHBOARD_VIEW);
    Route::get('/tenants/subdomain-availability', [CentralTenantController::class, 'checkSubdomainAvailability'])
        ->middleware('permission:'.CentralPermissions::TENANTS_MANAGE);

    Route::get('/tenants', [CentralTenantController::class, 'index'])
        ->middleware('permission:'.CentralPermissions::TENANTS_VIEW);
    Route::patch('/tenants/{tenant}/plan', [CentralTenantController::class, 'updatePlan'])
        ->middleware('permission:'.CentralPermissions::TENANTS_MANAGE);
    Route::patch('/tenants/{tenant}/branding', [CentralTenantController::class, 'updateBranding'])
        ->middleware('permission:'.CentralPermissions::TENANTS_MANAGE);
    Route::patch('/tenants/{tenant}/status', [CentralTenantController::class, 'updateStatus'])
        ->middleware('permission:'.CentralPermissions::TENANTS_MANAGE);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
