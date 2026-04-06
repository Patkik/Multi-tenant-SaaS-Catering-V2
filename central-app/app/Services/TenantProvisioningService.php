<?php

namespace App\Services;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenantProvisioningException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TenantProvisioningService
{
    public function __construct(private readonly TenantDatabaseProvisioner $tenantDatabaseProvisioner)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws TenantProvisioningException
     */
    public function createTenant(array $payload): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = DB::transaction(function () use ($payload): Tenant {
            return Tenant::query()->create([
                'name' => $payload['name'],
                'domain' => $payload['domain'],
                'database_name' => $payload['database_name'],
                'plan_code' => $payload['plan_code'] ?? null,
                'plan_entitlements' => $payload['plan_entitlements'] ?? [],
                'provisioning_status' => 'provisioning',
                'provisioning_error' => null,
                'provisioned_at' => null,
            ]);
        });

        try {
            $this->tenantDatabaseProvisioner->createDatabase((string) $tenant->database_name);
            $this->runTenantMigrations($tenant);
        } catch (Throwable $exception) {
            try {
                DB::transaction(function () use ($tenant, $exception): void {
                    $tenant->forceFill([
                        'provisioning_status' => 'failed',
                        'provisioning_error' => $this->normalizeErrorMessage($exception),
                        'provisioned_at' => null,
                    ])->save();
                });
            } catch (Throwable) {
                // Best-effort fallback only; preserve the original createDatabase failure signal.
            }

            throw new TenantProvisioningException('Tenant provisioning failed.', previous: $exception);
        }

        try {
            DB::transaction(function () use ($tenant): void {
                $tenant->forceFill([
                    'provisioning_status' => 'ready',
                    'provisioning_error' => null,
                    'provisioned_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            try {
                DB::transaction(function () use ($tenant, $exception): void {
                    $tenant->forceFill([
                        'provisioning_status' => 'failed',
                        'provisioning_error' => $this->normalizeErrorMessage($exception),
                        'provisioned_at' => null,
                    ])->save();
                });
            } catch (Throwable) {
                // Best-effort fallback only; preserve the original ready-state failure signal.
            }

            throw new TenantProvisioningException('Tenant provisioning failed.', previous: $exception);
        }

        return $tenant->fresh();
    }

    private function runTenantMigrations(Tenant $tenant): void
    {
        $connectionName = $this->resolveTenantRuntimeConnection($tenant);
        $migrationPaths = $this->resolveTenantMigrationPaths();

        $exitCode = Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => $migrationPaths,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Tenant migrations failed for database "%s": %s',
                (string) $tenant->database_name,
                trim(Artisan::output())
            ));
        }
    }

    private function resolveTenantRuntimeConnection(Tenant $tenant): string
    {
        $runtimeConnection = (string) config('tenancy.runtime_connection', config('database.default'));
        $runtimeConnectionAlias = (string) config('tenancy.runtime_connection_alias', 'tenant_runtime');

        $runtimeConnectionConfig = config("database.connections.{$runtimeConnection}");

        if (! is_array($runtimeConnectionConfig)) {
            throw new RuntimeException(sprintf('Runtime tenant connection "%s" is not configured.', $runtimeConnection));
        }

        $runtimeConnectionConfig['database'] = (string) $tenant->database_name;

        config(["database.connections.{$runtimeConnectionAlias}" => $runtimeConnectionConfig]);

        DB::purge($runtimeConnectionAlias);
        DB::connection($runtimeConnectionAlias)->getPdo();

        return $runtimeConnectionAlias;
    }

    private function normalizeErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            return 'Unknown tenant database provisioning error.';
        }

        return mb_substr($message, 0, 2000);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTenantMigrationPaths(): array
    {
        $configuredPaths = config('tenancy.tenant_migration_paths');

        if (is_array($configuredPaths)) {
            $resolvedConfiguredPaths = array_values(array_filter(array_map(
                fn (mixed $path): ?string => $this->normalizeMigrationPath($path),
                $configuredPaths
            )));

            if ($resolvedConfiguredPaths !== []) {
                return $resolvedConfiguredPaths;
            }
        }

        $tenantDirectories = [
            database_path('migrations/tenant'),
            database_path('migrations/tenants'),
        ];

        $resolvedTenantDirectories = array_values(array_filter(array_map(
            fn (string $path): ?string => $this->normalizeMigrationPath($path),
            $tenantDirectories
        )));

        if ($resolvedTenantDirectories !== []) {
            return $resolvedTenantDirectories;
        }

        $explicitTenantMigrations = [
            database_path('migrations/0001_01_01_000000_create_users_table.php'),
            database_path('migrations/2026_03_31_001000_add_role_and_is_active_to_users_table.php'),
            database_path('migrations/2026_03_31_001001_create_tenant_rbac_tables.php'),
        ];

        $resolvedExplicitMigrations = array_values(array_filter(array_map(
            fn (string $path): ?string => $this->normalizeMigrationPath($path),
            $explicitTenantMigrations
        )));

        if ($resolvedExplicitMigrations === []) {
            throw new RuntimeException('No tenant migration paths were found for tenant provisioning.');
        }

        return $resolvedExplicitMigrations;
    }

    private function normalizeMigrationPath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            return null;
        }

        $absolutePath = $this->isAbsolutePath($trimmedPath) ? $trimmedPath : base_path($trimmedPath);
        $normalizedAbsolutePath = $this->normalizeFilesystemPath($absolutePath);

        if (! file_exists($normalizedAbsolutePath)) {
            return null;
        }

        $normalizedBasePath = $this->normalizeFilesystemPath(base_path());

        return $this->pathStartsWith($normalizedAbsolutePath, $normalizedBasePath.DIRECTORY_SEPARATOR)
            ? ltrim(mb_substr($normalizedAbsolutePath, mb_strlen($normalizedBasePath)), DIRECTORY_SEPARATOR)
            : $normalizedAbsolutePath;
    }

    private function isAbsolutePath(string $path): bool
    {
        $hasWindowsDrivePrefix = strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');

        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || $hasWindowsDrivePrefix;
    }

    private function normalizeFilesystemPath(string $path): string
    {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));

        return rtrim($normalizedPath, DIRECTORY_SEPARATOR);
    }

    private function pathStartsWith(string $path, string $prefix): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_starts_with(mb_strtolower($path), mb_strtolower($prefix));
        }

        return str_starts_with($path, $prefix);
    }
}
