<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

class TenantRoles
{
    public const ADMIN = 'Admin';

    public const MANAGER = 'Manager';

    public const STAFF = 'Staff';

    public const CASHIER = 'Cashier';

    /**
     * @return array<string, array<int, string>>
     */
    public static function moduleCapabilities(): array
    {
        return [
            self::ADMIN => ['dashboard', 'clients', 'packages', 'events', 'payments', 'staff', 'assignments', 'analytics', 'branding', 'users', 'settings'],
            self::MANAGER => ['dashboard', 'clients', 'packages', 'events', 'payments', 'staff', 'assignments', 'analytics'],
            self::STAFF => ['dashboard', 'events', 'clients'],
            self::CASHIER => ['dashboard', 'payments', 'events'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ADMIN,
            self::MANAGER,
            self::STAFF,
            self::CASHIER,
        ];
    }

    public static function normalize(?string $role): string
    {
        if (! $role) {
            return self::ADMIN;
        }

        foreach (self::all() as $allowedRole) {
            if (strtolower($allowedRole) === strtolower(trim($role))) {
                return $allowedRole;
            }
        }

        return self::ADMIN;
    }

    public static function resolveFromUser(User $user): string
    {
        $roleName = $user->getRoleNames()->first();

        return self::normalize(is_string($roleName) ? $roleName : null);
    }

    public static function canAccessModule(string $role, string $module): bool
    {
        $normalizedRole = self::normalize($role);

        return in_array($module, self::moduleCapabilities()[$normalizedRole] ?? [], true);
    }
}
