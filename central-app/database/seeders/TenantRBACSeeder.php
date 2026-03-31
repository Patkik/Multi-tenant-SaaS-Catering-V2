<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\TenantFeature;
use App\Models\TenantRole;
use Illuminate\Database\Seeder;

class TenantRBACSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Orders
            ['name' => 'orders.view', 'display_name' => 'View Orders', 'category' => 'orders', 'description' => 'View all orders'],
            ['name' => 'orders.create', 'display_name' => 'Create Orders', 'category' => 'orders', 'description' => 'Create new orders'],
            ['name' => 'orders.update', 'display_name' => 'Update Orders', 'category' => 'orders', 'description' => 'Update existing orders'],
            ['name' => 'orders.delete', 'display_name' => 'Delete Orders', 'category' => 'orders', 'description' => 'Delete orders'],
            ['name' => 'orders.manage', 'display_name' => 'Manage Orders', 'category' => 'orders', 'description' => 'Full order management'],

            // Clients
            ['name' => 'clients.view', 'display_name' => 'View Clients', 'category' => 'clients', 'description' => 'View all clients'],
            ['name' => 'clients.create', 'display_name' => 'Create Clients', 'category' => 'clients', 'description' => 'Create new clients'],
            ['name' => 'clients.update', 'display_name' => 'Update Clients', 'category' => 'clients', 'description' => 'Update client information'],
            ['name' => 'clients.delete', 'display_name' => 'Delete Clients', 'category' => 'clients', 'description' => 'Delete clients'],
            ['name' => 'clients.manage', 'display_name' => 'Manage Clients', 'category' => 'clients', 'description' => 'Full client management'],

            // Analytics
            ['name' => 'analytics.view', 'display_name' => 'View Analytics', 'category' => 'analytics', 'description' => 'View analytics and reports'],

            // Team
            ['name' => 'team.manage', 'display_name' => 'Manage Team', 'category' => 'team', 'description' => 'Manage team members and roles'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'category' => 'reports', 'description' => 'View reports and exports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'category' => 'reports', 'description' => 'Export reports to files'],

            // Kitchen Board
            ['name' => 'kitchen.view', 'display_name' => 'View Kitchen Board', 'category' => 'kitchen', 'description' => 'View kitchen board'],
            ['name' => 'kitchen.update', 'display_name' => 'Update Kitchen Board', 'category' => 'kitchen', 'description' => 'Update kitchen board status'],
        ];

        $permissionMap = [];
        foreach ($permissions as $perm) {
            $permissionMap[$perm['name']] = Permission::create($perm);
        }

        // Create features
        $features = [
            ['name' => 'orders', 'display_name' => 'Orders', 'category' => 'core', 'description' => 'Order management module'],
            ['name' => 'clients', 'display_name' => 'Clients', 'category' => 'core', 'description' => 'Client management module'],
            ['name' => 'analytics', 'display_name' => 'Analytics', 'category' => 'advanced', 'description' => 'Analytics and dashboards'],
            ['name' => 'team_management', 'display_name' => 'Team Management', 'category' => 'admin', 'description' => 'Team and role management'],
            ['name' => 'reports', 'display_name' => 'Reports', 'category' => 'advanced', 'description' => 'Reports and exports'],
            ['name' => 'kitchen_board', 'display_name' => 'Kitchen Board', 'category' => 'core', 'description' => 'Kitchen board management'],
            ['name' => 'calendar', 'display_name' => 'Calendar', 'category' => 'core', 'description' => 'Event calendar'],
        ];

        $featureMap = [];
        foreach ($features as $feat) {
            $featureMap[$feat['name']] = TenantFeature::create($feat);
        }

        // Create roles and assign permissions/features
        $roles = ['admin', 'manager', 'staff', 'cashier'];
        $roleDisplayNames = [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff Member',
            'cashier' => 'Cashier',
        ];

        foreach ($roles as $roleName) {
            $role = TenantRole::create([
                'name' => $roleName,
                'display_name' => $roleDisplayNames[$roleName],
                'description' => 'Role: ' . ucfirst($roleName),
            ]);

            // Assign permissions based on role
            switch ($roleName) {
                case 'admin':
                    // Admin has all permissions
                    $role->permissions()->attach(array_values($permissionMap));
                    // Admin has all features enabled
                    foreach ($featureMap as $feature) {
                        $role->features()->attach($feature->id, ['is_enabled' => true]);
                    }
                    break;

                case 'manager':
                    // Manager has most permissions except team management
                    $managerPerms = [
                        'orders.view', 'orders.create', 'orders.update',
                        'clients.view', 'clients.create', 'clients.update',
                        'analytics.view',
                        'reports.view', 'reports.export',
                        'kitchen.view', 'kitchen.update',
                    ];
                    foreach ($managerPerms as $permName) {
                        $role->permissions()->attach($permissionMap[$permName]);
                    }
                    // Manager has most features except team_management
                    $managerFeatures = ['orders', 'clients', 'analytics', 'reports', 'kitchen_board', 'calendar'];
                    foreach ($managerFeatures as $featName) {
                        $role->features()->attach($featureMap[$featName]->id, ['is_enabled' => true]);
                    }
                    break;

                case 'staff':
                    // Staff has basic order/kitchen permissions
                    $staffPerms = [
                        'orders.view', 'orders.create',
                        'clients.view',
                        'kitchen.view', 'kitchen.update',
                    ];
                    foreach ($staffPerms as $permName) {
                        $role->permissions()->attach($permissionMap[$permName]);
                    }
                    // Staff can access core features except team and analytics
                    $staffFeatures = ['orders', 'clients', 'kitchen_board', 'calendar'];
                    foreach ($staffFeatures as $featName) {
                        $role->features()->attach($featureMap[$featName]->id, ['is_enabled' => true]);
                    }
                    break;

                case 'cashier':
                    // Cashier has minimal permissions - orders only
                    $cashierPerms = ['orders.view', 'orders.create'];
                    foreach ($cashierPerms as $permName) {
                        $role->permissions()->attach($permissionMap[$permName]);
                    }
                    // Cashier can access order and calendar only
                    $cashierFeatures = ['orders', 'calendar'];
                    foreach ($cashierFeatures as $featName) {
                        $role->features()->attach($featureMap[$featName]->id, ['is_enabled' => true]);
                    }
                    break;
            }
        }

        $this->command->info('RBAC seeding completed: ' . count($permissionMap) . ' permissions, ' . count($featureMap) . ' features, ' . count($roles) . ' roles.');
    }
}
