<?php

use App\Http\Controllers\Api\Admin\FeatureController;
use App\Http\Controllers\Api\Admin\RoleTemplateController;
use App\Http\Controllers\Api\Admin\TenantController;
use App\Http\Controllers\Api\Admin\TenantEffectiveFeatureController;
use App\Http\Controllers\Api\Admin\TenantFeatureOverrideController;
use App\Http\Controllers\Api\Admin\ApplyRoleTemplateToTenantController;
use App\Http\Controllers\Api\Internal\UsageCaptureController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['central.admin', 'throttle:60,1'])->group(function (): void {
    Route::get('/features', [FeatureController::class, 'index']);
    Route::get('/features/{feature}', [FeatureController::class, 'show']);
    Route::post('/features', [FeatureController::class, 'store']);
    Route::patch('/features/{feature}', [FeatureController::class, 'update']);

    Route::get('/role-templates', [RoleTemplateController::class, 'index']);
    Route::post('/role-templates', [RoleTemplateController::class, 'store']);
    Route::patch('/role-templates/{roleTemplate}', [RoleTemplateController::class, 'update']);

    Route::post('/tenants', [TenantController::class, 'store']);
    Route::get('/tenants/{tenant}/effective-features', [TenantEffectiveFeatureController::class, 'index']);
    Route::get('/tenants/{tenant}/feature-overrides', [TenantFeatureOverrideController::class, 'index']);
    Route::put('/tenants/{tenant}/feature-overrides/{feature}', [TenantFeatureOverrideController::class, 'upsert']);
    Route::delete('/tenants/{tenant}/feature-overrides/{feature}', [TenantFeatureOverrideController::class, 'destroy']);
    Route::get('/tenants/{tenant}/monitoring', [TenantController::class, 'monitoring']);
    Route::post('/tenants/{tenant}/role-templates/{roleTemplate}/apply', ApplyRoleTemplateToTenantController::class);
});

Route::prefix('internal')->middleware(['internal.usage', 'throttle:120,1'])->group(function (): void {
    Route::post('/usage/capture', UsageCaptureController::class);
});
