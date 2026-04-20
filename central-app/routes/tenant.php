<?php

declare(strict_types=1);

use App\Http\Controllers\TenantCapabilityController;
use App\Http\Controllers\TenantClientController;
use App\Http\Controllers\TenantPackageController;
use App\Http\Controllers\TenantPaymentController;
use App\Http\Controllers\TenantStaffController;
use App\Http\Controllers\TenantAssignmentController;
use App\Http\Controllers\TenantAnalyticsController;
use App\Http\Controllers\TenantBrandingController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantEventController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::prefix('/api/tenant')->group(function () {
        Route::get('/capabilities', TenantCapabilityController::class);
        Route::get('/auth/registration-policy', [TenantAuthController::class, 'registrationPolicy']);
        Route::post('/auth/login', [TenantAuthController::class, 'login'])->middleware('tenant.active');
        Route::post('/auth/register', [TenantAuthController::class, 'register'])->middleware('tenant.active');

        Route::middleware(['auth:sanctum', 'tenant.active'])->group(function () {
            Route::get('/auth/me', [TenantAuthController::class, 'me']);
            Route::post('/auth/logout', [TenantAuthController::class, 'logout']);

            Route::middleware(['permission:clients.view'])->group(function () {
                Route::get('/clients', [TenantClientController::class, 'index']);
            });

            Route::middleware(['permission:clients.manage'])->group(function () {
                Route::post('/clients', [TenantClientController::class, 'store']);
                Route::patch('/clients/{client}', [TenantClientController::class, 'update']);
                Route::delete('/clients/{client}', [TenantClientController::class, 'destroy']);
            });

            Route::middleware(['permission:packages.view'])->group(function () {
                Route::get('/packages', [TenantPackageController::class, 'index']);
            });

            Route::middleware(['permission:packages.manage'])->group(function () {
                Route::post('/packages', [TenantPackageController::class, 'store']);
                Route::patch('/packages/{package}', [TenantPackageController::class, 'update']);
                Route::delete('/packages/{package}', [TenantPackageController::class, 'destroy']);
            });

            Route::middleware(['tenant.feature:event_management', 'permission:events.view'])->group(function () {
                Route::get('/events', [TenantEventController::class, 'index']);
            });

            Route::middleware(['tenant.feature:event_management', 'permission:events.manage'])->group(function () {
                Route::post('/events', [TenantEventController::class, 'store']);
                Route::patch('/events/{event}/status', [TenantEventController::class, 'updateStatus']);
            });

            Route::middleware(['permission:payments.view'])->group(function () {
                Route::get('/payments', [TenantPaymentController::class, 'index']);
            });

            Route::middleware(['permission:payments.manage'])->group(function () {
                Route::post('/payments', [TenantPaymentController::class, 'store']);
                Route::patch('/payments/{payment}', [TenantPaymentController::class, 'update']);
                Route::delete('/payments/{payment}', [TenantPaymentController::class, 'destroy']);
            });

            Route::middleware(['tenant.feature:staff_assignment', 'permission:staff.view'])->group(function () {
                Route::get('/staff', [TenantStaffController::class, 'index']);
                Route::get('/assignments', [TenantAssignmentController::class, 'index']);
            });

            Route::middleware(['tenant.feature:staff_assignment', 'permission:staff.manage'])->group(function () {
                Route::post('/staff', [TenantStaffController::class, 'store']);
                Route::patch('/staff/{staff}', [TenantStaffController::class, 'update']);
                Route::delete('/staff/{staff}', [TenantStaffController::class, 'destroy']);
                Route::post('/assignments', [TenantAssignmentController::class, 'store']);
                Route::delete('/assignments/{assignment}', [TenantAssignmentController::class, 'destroy']);
            });

            Route::middleware(['tenant.feature:advanced_analytics', 'permission:analytics.view'])->group(function () {
                Route::get('/analytics', TenantAnalyticsController::class);
            });

            Route::middleware(['tenant.feature:branding_controls', 'permission:branding.manage'])->group(function () {
                Route::get('/branding', [TenantBrandingController::class, 'show']);
                Route::patch('/branding', [TenantBrandingController::class, 'update']);
            });

            Route::middleware(['permission:dashboard.view'])->group(function () {
                Route::get('/app-updates', [TenantCapabilityController::class, 'appUpdates']);
            });

            Route::middleware(['permission:settings.manage'])->group(function () {
                Route::post('/app-updates/apply', [TenantCapabilityController::class, 'applyAppUpdate']);
            });

            Route::middleware(['permission:settings.manage'])->group(function () {
                Route::get('/settings', [TenantSettingsController::class, 'show']);
                Route::patch('/settings', [TenantSettingsController::class, 'update']);
                Route::get('/users', [TenantUserController::class, 'index']);
                Route::post('/users', [TenantUserController::class, 'store']);
                Route::patch('/users/{member}', [TenantUserController::class, 'update']);
                Route::delete('/users/{member}', [TenantUserController::class, 'destroy']);
            });
        });
    });
});
