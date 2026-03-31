<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tenant roles: admin, manager, staff, cashier
        $tenantRoles = ['admin', 'manager', 'staff', 'cashier'];
        
        foreach ($tenantRoles as $role) {
            User::create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@patcatering.com',
                'password' => Hash::make('password'),
                'role' => $role,
                'is_active' => true,
            ]);
        }
    }
}
