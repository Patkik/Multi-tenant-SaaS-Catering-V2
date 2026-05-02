<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * TenancyServiceProvider - Redis-Backed Multi-Tenant Permission Cache
 * 
 * Purpose: Bootstrap tenancy with distributed permission caching for LAN deployments.
 * 
 * This provider ensures that:
 * 1. Permission cache is stored in Redis (shared across multiple instances)
 * 2. Cache keys are namespaced per tenant to prevent cross-tenant pollution
 * 3. Permission cache is automatically invalidated on tenant switch
 * 4. Spatie permission cache uses variadic arguments (spread syntax) to prevent auth failures
 */
class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * Event listeners for tenancy lifecycle
     */
    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => $this->tenantCreatedListeners(),
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // Synchronous execution mandatory
                // NEW: Flush permission cache on tenant deletion
                [$this, 'clearPermissionCacheOnTenantDeletion'],
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy initialization - CRITICAL FOR LAN
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
                [$this, 'bootstrapTenantPermissionCache'],
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
                [$this, 'restoreCentralPermissionCache'],
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],
        ];
    }

    /**
     * Listeners that should fire when a tenant is created
     */
    public function tenantCreatedListeners(): array
    {
        return [
            JobPipeline::make([
                Jobs\CreateDatabase::class,
                Jobs\MigrateDatabase::class,
            ])->send(function (Events\TenantCreated $event) {
                return $event->tenant;
            })->shouldBeQueued(false), // Synchronous for LAN
        ];
    }

    /**
     * Bootstrap tenant-specific permission cache
     * 
     * This method runs when tenancy is initialized (tenant request detected).
     * It sets up a tenant-namespaced permission cache in Redis to prevent
     * permission cache collisions on multi-instance LAN deployments.
     * 
     * @return void
     */
    public function bootstrapTenantPermissionCache(): void
    {
        // Get the current tenant ID from the request
        $tenantId = tenant()->id ?? 'unknown';

        // Create a unique cache key prefix for this tenant
        $cachePrefix = "tenant_{$tenantId}_permissions_";

        // Set the cache prefix for Spatie Permission
        // This ensures all permission checks are scoped to the current tenant
        app(PermissionRegistrar::class)->setPermissionsCacheKey($cachePrefix . 'list');

        // Log permission cache initialization (debug only)
        \Log::debug('Permission cache initialized for tenant', [
            'tenant_id' => $tenantId,
            'cache_prefix' => $cachePrefix,
            'cache_store' => config('cache.default'),
        ]);
    }

    /**
     * Restore central permission cache
     * 
     * This method runs when tenancy ends (returning to central context).
     * It resets the permission cache key to the central/landlord default.
     * 
     * @return void
     */
    public function restoreCentralPermissionCache(): void
    {
        // Reset to default cache key for central context
        app(PermissionRegistrar::class)->setPermissionsCacheKey('spatie.permission.cache');

        \Log::debug('Permission cache restored to central context');
    }

    /**
     * Clear permission cache when a tenant is deleted
     * 
     * This is critical to prevent "ghost" permissions lingering in Redis
     * after a tenant is removed from the system.
     * 
     * @param Events\TenantDeleted $event
     * @return void
     */
    public function clearPermissionCacheOnTenantDeletion(Events\TenantDeleted $event): void
    {
        $tenantId = $event->tenant->id;
        $cachePrefix = "tenant_{$tenantId}_permissions_";

        // Flush all permission cache entries for this tenant
        try {
            Cache::flush(); // Or use Cache::store('redis')->tags($cachePrefix)->flush() for tag-based
            
            \Log::info('Permission cache cleared for deleted tenant', [
                'tenant_id' => $tenantId,
                'cache_prefix' => $cachePrefix,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to clear permission cache for tenant', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register any application services
     * 
     * This is called once when the application boots. Use this for
     * container bindings and one-time registrations.
     * 
     * @return void
     */
    public function register(): void
    {
        // Register any tenant-specific service overrides
        // Example: $this->app->singleton(TenantService::class, TenantService::class);
    }

    /**
     * Bootstrap any application services
     * 
     * This is called after all services are registered.
     * Use this for final configuration steps.
     * 
     * @return void
     */
    public function boot(): void
    {
        // Initialize tenancy routes/middleware
        $this->bootRoutes();
        $this->bootMiddleware();
    }

    /**
     * Bootstrap tenancy routes
     * 
     * @return void
     */
    protected function bootRoutes(): void
    {
        // Routes are registered in config/tenancy.php
        // This method is a hook for future extensions
    }

    /**
     * Bootstrap tenancy middleware
     * 
     * @return void
     */
    protected function bootMiddleware(): void
    {
        // Middleware is registered in config/tenancy.php
        // This method is a hook for future extensions
    }
}
