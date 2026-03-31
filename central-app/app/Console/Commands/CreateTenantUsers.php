<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CreateTenantUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create-users {tenant : The tenant identifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create default users for a specific tenant with all roles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        
        $this->info("Creating users for tenant: {$tenantId}");
        
        // Get tenant database name
        $tenantDb = "tenant_{$tenantId}";
        
        // Check if tenant database exists
        try {
            $databases = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenantDb]);
            
            if (empty($databases)) {
                $this->error("Tenant database '{$tenantDb}' not found!");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error checking tenant database: " . $e->getMessage());
            return 1;
        }
        
        // Switch to tenant database
        DB::connection('mysql')->statement("USE {$tenantDb}");
        
        // Tenant roles
        $roles = ['admin', 'manager', 'staff', 'cashier'];
        
        // Create users for each role
        foreach ($roles as $role) {
            $email = $role . '@' . $tenantId . '.local';
            
            // Check if user already exists
            $userCount = DB::table('users')->where('email', $email)->count();
            
            if ($userCount > 0) {
                $this->warn("User with email {$email} already exists. Skipping...");
                continue;
            }
            
            DB::table('users')->insert([
                'name' => ucfirst($role) . ' User',
                'email' => $email,
                'password' => bcrypt('password'),
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->line("✓ Created {$role} user: {$email}");
        }
        
        $this->info('Users created successfully!');
        
        return 0;
    }
}
