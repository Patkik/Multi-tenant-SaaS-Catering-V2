<?php

namespace App\Providers;

use App\Contracts\TenantDatabaseProvisioner;
use App\Services\MySqlTenantDatabaseProvisioner;
use App\Services\TenantProvisioningHealthCheckService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TenantDatabaseProvisioner::class, MySqlTenantDatabaseProvisioner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ((bool) config('tenancy.startup_health_check_enabled', false)) {
            /** @var TenantProvisioningHealthCheckService $healthCheckService */
            $healthCheckService = $this->app->make(TenantProvisioningHealthCheckService::class);
            $result = $healthCheckService->evaluate();

            if (! $result['ok']) {
                $message = sprintf(
                    'Tenant provisioning health check failed on startup for connection "%s". Errors: %s',
                    $result['connection'],
                    implode('; ', $result['errors'])
                );

                Log::warning($message, $result);

                if ((bool) config('tenancy.health_check_fail_fast', false)) {
                    throw new RuntimeException($message.' Configure DB_PROVISIONING_* credentials with CREATE DATABASE (or ALL PRIVILEGES / SUPER).');
                }
            }
        }

        Gate::define('admin.features.read', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.features.write', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.create', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.read-effective-features', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.monitoring', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.overrides.read', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.overrides.write', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.contacts.read', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.tenants.contacts.write', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.role-templates.read', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.role-templates.write', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.role-templates.apply', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
        Gate::define('admin.audit.rbac.read', static fn (?Authenticatable $user): bool => (bool) request()->attributes->get('central_admin_authenticated', false));
    }
}
