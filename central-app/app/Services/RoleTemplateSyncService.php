<?php

namespace App\Services;

use App\Models\RoleTemplate;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RoleTemplateSyncService
{
    public const STRATEGY_MERGE = 'merge';

    public const STRATEGY_REPLACE = 'replace';

    /**
     * @return array{connection:string,strategy:string,role_name:string,permission_count:int,feature_count:int}
     */
    public function syncRoleTemplate(Tenant $tenant, RoleTemplate $roleTemplate, string $strategy): array
    {
        if (! in_array($strategy, [self::STRATEGY_MERGE, self::STRATEGY_REPLACE], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported role template apply strategy: %s', $strategy));
        }

        $connectionName = $this->resolveTenantRuntimeConnection($tenant);
        $this->ensureTenantRuntimeTables($connectionName);

        $roleName = (string) $roleTemplate->name;
        $permissions = $roleTemplate->permissions()
            ->pluck('permission')
            ->map(static fn (mixed $permission): string => (string) $permission)
            ->unique()
            ->values()
            ->all();

        $features = $roleTemplate->features()
            ->get(['feature_key', 'is_enabled'])
            ->map(static fn (object $feature): array => [
                'feature_key' => (string) $feature->feature_key,
                'is_enabled' => (bool) $feature->is_enabled,
            ]);

        $now = now();

        DB::connection($connectionName)->transaction(function () use ($connectionName, $strategy, $roleName, $permissions, $features, $now): void {
            DB::connection($connectionName)->table('tenant_roles')->upsert([
                [
                    'id' => (string) Str::uuid(),
                    'role_name' => $roleName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['role_name'], ['updated_at']);

            if ($strategy === self::STRATEGY_REPLACE) {
                DB::connection($connectionName)->table('tenant_role_permissions')
                    ->where('role_name', $roleName)
                    ->delete();

                DB::connection($connectionName)->table('tenant_role_features')
                    ->where('role_name', $roleName)
                    ->delete();
            }

            if ($permissions !== []) {
                $permissionRows = collect($permissions)
                    ->map(static fn (string $permission): array => [
                        'id' => (string) Str::uuid(),
                        'role_name' => $roleName,
                        'permission' => $permission,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::connection($connectionName)->table('tenant_role_permissions')
                    ->upsert($permissionRows, ['role_name', 'permission'], ['updated_at']);
            }

            if ($features->isNotEmpty()) {
                $featureRows = $features
                    ->map(static fn (array $feature): array => [
                        'id' => (string) Str::uuid(),
                        'role_name' => $roleName,
                        'feature_key' => $feature['feature_key'],
                        'is_enabled' => $feature['is_enabled'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::connection($connectionName)->table('tenant_role_features')
                    ->upsert($featureRows, ['role_name', 'feature_key'], ['is_enabled', 'updated_at']);
            }
        });

        return [
            'connection' => $connectionName,
            'strategy' => $strategy,
            'role_name' => $roleName,
            'permission_count' => count($permissions),
            'feature_count' => $features->count(),
        ];
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

    private function ensureTenantRuntimeTables(string $connectionName): void
    {
        $schema = Schema::connection($connectionName);

        if (! $schema->hasTable('tenant_roles')) {
            $schema->create('tenant_roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('role_name', 100)->unique();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('tenant_role_permissions')) {
            $schema->create('tenant_role_permissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('role_name', 100);
                $table->string('permission');
                $table->timestamps();

                $table->unique(['role_name', 'permission']);
                $table->index('role_name');
            });
        }

        if (! $schema->hasTable('tenant_role_features')) {
            $schema->create('tenant_role_features', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('role_name', 100);
                $table->string('feature_key');
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->unique(['role_name', 'feature_key']);
                $table->index('role_name');
                $table->index('feature_key');
            });
        }
    }
}
