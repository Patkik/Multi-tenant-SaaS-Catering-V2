<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'clients.view',
            'clients.manage',
            'packages.view',
            'packages.manage',
            'events.view',
            'events.manage',
            'payments.view',
            'payments.manage',
            'staff.view',
            'staff.manage',
            'analytics.view',
            'branding.manage',
            'settings.manage',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $manager = Role::query()->firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $staff = Role::query()->firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $cashier = Role::query()->firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);

        $admin->syncPermissions($permissions);

        $manager->syncPermissions([
            'dashboard.view',
            'clients.view',
            'clients.manage',
            'packages.view',
            'packages.manage',
            'events.view',
            'events.manage',
            'payments.view',
            'payments.manage',
            'staff.view',
            'staff.manage',
            'analytics.view',
        ]);

        $staff->syncPermissions([
            'dashboard.view',
            'events.view',
            'events.manage',
            'clients.view',
        ]);

        $cashier->syncPermissions([
            'dashboard.view',
            'payments.view',
            'payments.manage',
            'events.view',
        ]);

        $this->seedDefaultUsers($admin, $manager, $staff, $cashier);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedDefaultUsers(Role $admin, Role $manager, Role $staff, Role $cashier): void
    {
        $tenant = tenant();
        $adminData = is_object($tenant) ? $tenant->getAttribute('admin') : [];

        if (! is_array($adminData)) {
            $adminData = [];
        }

        $defaultPassword = (string) env('TENANT_DEMO_PASSWORD', 'password123');

        $adminUser = User::query()->updateOrCreate(
            ['username' => (string) ($adminData['username'] ?? 'admin')],
            [
                'name' => trim(((string) ($adminData['firstname'] ?? 'Admin')).' '.((string) ($adminData['lastname'] ?? 'User'))),
                'firstname' => (string) ($adminData['firstname'] ?? 'Admin'),
                'lastname' => (string) ($adminData['lastname'] ?? 'User'),
                'mi' => isset($adminData['mi']) ? (string) $adminData['mi'] : null,
                'email' => isset($adminData['email']) ? (string) $adminData['email'] : null,
                'password' => isset($adminData['password_hash'])
                    ? (string) $adminData['password_hash']
                    : Hash::make((string) ($adminData['password'] ?? $defaultPassword)),
                'is_active' => true,
            ],
        );

        $adminUser->syncRoles([$admin->name]);

        $this->seedTenantRoleUser('manager', 'Manager', 'User', $defaultPassword, $manager->name);
        $this->seedTenantRoleUser('staff', 'Staff', 'User', $defaultPassword, $staff->name);
        $this->seedTenantRoleUser('cashier', 'Cashier', 'User', $defaultPassword, $cashier->name);
    }

    private function seedTenantRoleUser(string $username, string $firstname, string $lastname, string $password, string $role): void
    {
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $firstname.' '.$lastname,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => null,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );

        $user->syncRoles([TenantRoles::normalize($role)]);
    }
}
