<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create central admin user
        User::firstOrCreate(
            ['email' => 'admin@central.local'],
            [
                'name' => 'Central Admin',
                'password' => Hash::make('central123!'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // Create additional test users
        User::firstOrCreate(
            ['email' => 'test@central.local'],
            [
                'name' => 'Test User',
                'password' => Hash::make('central123!'),
                'role' => 'user',
                'is_active' => true,
            ]
        );
    }
}
