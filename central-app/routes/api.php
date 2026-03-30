<?php

use App\Http\Controllers\Api\Admin\FeatureController;
use App\Http\Controllers\Api\Admin\TenantEffectiveFeatureController;
use App\Http\Controllers\Api\Admin\ApplyRoleTemplateToTenantController;
use App\Http\Controllers\Api\Admin\RoleTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['central.admin', 'throttle:60,1'])->group(function (): void {
    Route::get('/features', [FeatureController::class, 'index']);
    Route::post('/features', [FeatureController::class, 'store']);
    Route::patch('/features/{feature}', [FeatureController::class, 'update']);

    Route::get('/role-templates', [RoleTemplateController::class, 'index']);
    Route::post('/role-templates', [RoleTemplateController::class, 'store']);
    Route::patch('/role-templates/{roleTemplate}', [RoleTemplateController::class, 'update']);

    Route::get('/tenants/{tenant}/effective-features', [TenantEffectiveFeatureController::class, 'index']);
    Route::post('/tenants/{tenant}/role-templates/{roleTemplate}/apply', ApplyRoleTemplateToTenantController::class);
});
