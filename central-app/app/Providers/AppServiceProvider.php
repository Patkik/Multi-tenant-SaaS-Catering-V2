<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin.features.read', static fn (?Authenticatable $user): bool => true);
        Gate::define('admin.features.write', static fn (?Authenticatable $user): bool => true);
        Gate::define('admin.tenants.read-effective-features', static fn (?Authenticatable $user): bool => true);
    }
}
