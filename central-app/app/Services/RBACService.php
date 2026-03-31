<?php

namespace App\Services;

use App\Models\TenantRole;
use Illuminate\Support\Collection;

class RBACService
{
    /**
     * Check if a role has a specific permission
     */
    public function hasPermission(?TenantRole $role, string $permissionName): bool
    {
        if (!$role) {
            return false;
        }

        return $role->hasPermission($permissionName);
    }

    /**
     * Check if a role can access a feature
     */
    public function hasFeature(?TenantRole $role, string $featureName): bool
    {
        if (!$role) {
            return false;
        }

        return $role->hasFeature($featureName);
    }

    /**
     * Get all features accessible by a role (where is_enabled = true)
     */
    public function getEnabledFeatures(?TenantRole $role): Collection
    {
        if (!$role) {
            return collect();
        }

        return $role
            ->features()
            ->wherePivot('is_enabled', true)
            ->pluck('name');
    }

    /**
     * Get available module list based on role's enabled features
     * Maps database features to frontend module display
     */
    public function getAvailableModules(?TenantRole $role): array
    {
        if (!$role) {
            return [];
        }

        $enabledFeatures = $this->getEnabledFeatures($role);

        // Feature-to-module mapping
        $moduleMap = [
            'orders' => ['name' => 'Orders', 'icon' => 'shopping-cart', 'route' => 'orders'],
            'clients' => ['name' => 'Clients', 'icon' => 'users', 'route' => 'clients'],
            'kitchen_board' => ['name' => 'Kitchen Board', 'icon' => 'chef-hat', 'route' => 'kitchen'],
            'calendar' => ['name' => 'Calendar', 'icon' => 'calendar', 'route' => 'calendar'],
            'analytics' => ['name' => 'Analytics', 'icon' => 'bar-chart-2', 'route' => 'analytics'],
            'team_management' => ['name' => 'Team', 'icon' => 'users-cog', 'route' => 'team'],
        ];

        $modules = [
            // Dashboard is always visible
            'dashboard' => ['name' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],
        ];

        // Add modules based on enabled features
        foreach ($enabledFeatures as $feature) {
            if (isset($moduleMap[$feature])) {
                $modules[$feature] = $moduleMap[$feature];
            }
        }

        return $modules;
    }

    /**
     * Get all permissions for a role
     */
    public function getPermissions(?TenantRole $role): Collection
    {
        if (!$role) {
            return collect();
        }

        return $role->permissions()->pluck('name');
    }

    /**
     * Check if role has any of the given permissions
     */
    public function hasAnyPermission(?TenantRole $role, array $permissionNames): bool
    {
        if (!$role) {
            return false;
        }

        $userPermissions = $this->getPermissions($role);

        foreach ($permissionNames as $permissionName) {
            if ($userPermissions->contains($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if role has all of the given permissions
     */
    public function hasAllPermissions(?TenantRole $role, array $permissionNames): bool
    {
        if (!$role) {
            return false;
        }

        $userPermissions = $this->getPermissions($role);

        foreach ($permissionNames as $permissionName) {
            if (!$userPermissions->contains($permissionName)) {
                return false;
            }
        }

        return true;
    }
}
