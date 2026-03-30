<?php

use App\Http\Controllers\Api\Admin\FeatureController;
use App\Http\Controllers\Api\Admin\TenantEffectiveFeatureController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::get('/features', [FeatureController::class, 'index']);
    Route::post('/features', [FeatureController::class, 'store']);
    Route::patch('/features/{feature}', [FeatureController::class, 'update']);

    Route::get('/tenants/{tenant}/effective-features', [TenantEffectiveFeatureController::class, 'index']);
});
