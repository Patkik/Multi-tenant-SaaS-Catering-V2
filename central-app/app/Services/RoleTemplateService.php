<?php

namespace App\Services;

use App\Models\RoleTemplate;
use Illuminate\Support\Facades\DB;

class RoleTemplateService
{
    private const CREATED_BY_ADMIN_MARKER = 'central-admin-token';

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): RoleTemplate
    {
        /** @var RoleTemplate $roleTemplate */
        $roleTemplate = DB::transaction(function () use ($payload): RoleTemplate {
            $name = (string) $payload['role_name'];

            $roleTemplate = RoleTemplate::query()->create([
                'name' => $name,
                'description' => $payload['description'] ?? null,
                'is_system_default' => (bool) ($payload['is_system_default'] ?? false),
                'created_by_admin' => self::CREATED_BY_ADMIN_MARKER,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $this->replaceNestedBindings(
                $roleTemplate,
                $name,
                $payload['permissions'] ?? [],
                $payload['feature_keys'] ?? [],
            );

            return $roleTemplate;
        });

        return $roleTemplate->load(['permissions', 'features']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(RoleTemplate $roleTemplate, array $payload): RoleTemplate
    {
        /** @var RoleTemplate $roleTemplate */
        $roleTemplate = DB::transaction(function () use ($roleTemplate, $payload): RoleTemplate {
            $name = (string) ($payload['role_name'] ?? $roleTemplate->name);
            $description = array_key_exists('description', $payload)
                ? $payload['description']
                : $roleTemplate->description;
            $metadata = array_key_exists('metadata', $payload)
                ? $payload['metadata']
                : $roleTemplate->metadata;

            $roleTemplate->fill([
                'name' => $name,
                'description' => $description,
                'is_system_default' => $payload['is_system_default'] ?? $roleTemplate->is_system_default,
                'created_by_admin' => self::CREATED_BY_ADMIN_MARKER,
                'metadata' => $metadata,
            ]);
            $roleTemplate->save();

            $shouldReplacePermissions = array_key_exists('permissions', $payload) || array_key_exists('role_name', $payload);
            $shouldReplaceFeatures = array_key_exists('feature_keys', $payload) || array_key_exists('role_name', $payload);

            if ($shouldReplacePermissions) {
                $permissions = array_key_exists('permissions', $payload)
                    ? $payload['permissions']
                    : $roleTemplate->permissions()->pluck('permission')->all();

                $this->replacePermissions($roleTemplate, $name, $permissions);
            }

            if ($shouldReplaceFeatures) {
                $featureKeys = array_key_exists('feature_keys', $payload)
                    ? $payload['feature_keys']
                    : $roleTemplate->features()->pluck('feature_key')->all();

                $this->replaceFeatures($roleTemplate, $name, $featureKeys);
            }

            return $roleTemplate;
        });

        return $roleTemplate->load(['permissions', 'features']);
    }

    /**
     * @param list<string> $permissions
     * @param list<string> $featureKeys
     */
    private function replaceNestedBindings(RoleTemplate $roleTemplate, string $roleName, array $permissions, array $featureKeys): void
    {
        $this->replacePermissions($roleTemplate, $roleName, $permissions);
        $this->replaceFeatures($roleTemplate, $roleName, $featureKeys);
    }

    /**
     * @param list<string> $permissions
     */
    private function replacePermissions(RoleTemplate $roleTemplate, string $roleName, array $permissions): void
    {
        $roleTemplate->permissions()->delete();

        $rows = collect($permissions)
            ->map(fn (string $permission): array => [
                'role_name' => $roleName,
                'permission' => $permission,
            ])
            ->all();

        if ($rows !== []) {
            $roleTemplate->permissions()->createMany($rows);
        }
    }

    /**
     * @param list<string> $featureKeys
     */
    private function replaceFeatures(RoleTemplate $roleTemplate, string $roleName, array $featureKeys): void
    {
        $roleTemplate->features()->delete();

        $rows = collect($featureKeys)
            ->map(fn (string $featureKey): array => [
                'role_name' => $roleName,
                'feature_key' => $featureKey,
                'is_enabled' => true,
            ])
            ->all();

        if ($rows !== []) {
            $roleTemplate->features()->createMany($rows);
        }
    }
}
