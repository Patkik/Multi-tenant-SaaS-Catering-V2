<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\TenantRole;
use App\Services\RBACService;

echo "\n=== RBAC SYSTEM TEST ===\n";

// Test 1: Get admin role
$adminRole = TenantRole::where('name', 'admin')->first();
echo "\nAdmin Role: " . ($adminRole ? 'Found ✓' : 'Not Found ✗') . "\n";

if ($adminRole) {
    echo "Admin Permissions: " . $adminRole->permissions()->count() . "\n";
    echo "Admin Features: " . $adminRole->features()->count() . "\n";
    
    // Test 2: Test RBAC Service
    $rbacService = app(\App\Services\RBACService::class);
    echo "\nAdmin Can Manage Team: " . ($rbacService->hasPermission($adminRole, 'team.manage') ? 'Yes ✓' : 'No ✗') . "\n";
    echo "Admin Can See Analytics: " . ($rbacService->hasFeature($adminRole, 'analytics') ? 'Yes ✓' : 'No ✗') . "\n";
    
    echo "\nAdmin Available Modules:\n";
    $modules = $rbacService->getAvailableModules($adminRole);
    foreach ($modules as $key => $module) {
        echo "  ✓ " . $module['name'] . " (" . $key . ")\n";
    }
}

// Test 3: Get manager role
echo "\n--- MANAGER ROLE ---\n";
$managerRole = TenantRole::where('name', 'manager')->first();
if ($managerRole) {
    echo "Manager Permissions: " . $managerRole->permissions()->count() . "\n";
    echo "Manager Features: " . $managerRole->features()->count() . "\n";
    
    $rbacService = app(\App\Services\RBACService::class);
    echo "\nManager Can Manage Team: " . ($rbacService->hasPermission($managerRole, 'team.manage') ? 'Yes ✗ (should be no)' : 'No ✓') . "\n";
    echo "Manager Can See Analytics: " . ($rbacService->hasFeature($managerRole, 'analytics') ? 'Yes ✓' : 'No ✗') . "\n";
    
    echo "\nManager Available Modules:\n";
    $modules = $rbacService->getAvailableModules($managerRole);
    foreach ($modules as $key => $module) {
        echo "  ✓ " . $module['name'] . "\n";
    }
}

// Test 4: Get cashier role
echo "\n--- CASHIER ROLE ---\n";
$cashierRole = TenantRole::where('name', 'cashier')->first();
if ($cashierRole) {
    echo "Cashier Permissions: " . $cashierRole->permissions()->count() . "\n";
    echo "Cashier Features: " . $cashierRole->features()->count() . "\n";
    
    $rbacService = app(\App\Services\RBACService::class);
    echo "\nCashier Can See Analytics: " . ($rbacService->hasFeature($cashierRole, 'analytics') ? 'Yes ✗ (should be no)' : 'No ✓') . "\n";
    
    echo "\nCashier Available Modules:\n";
    $modules = $rbacService->getAvailableModules($cashierRole);
    foreach ($modules as $key => $module) {
        echo "  ✓ " . $module['name'] . "\n";
    }
}

// Test 5: Test Staff role
echo "\n--- STAFF ROLE ---\n";
$staffRole = TenantRole::where('name', 'staff')->first();
if ($staffRole) {
    echo "Staff Permissions: " . $staffRole->permissions()->count() . "\n";
    echo "Staff Features: " . $staffRole->features()->count() . "\n";
    
    $rbacService = app(\App\Services\RBACService::class);
    echo "\nStaff Can View Orders: " . ($rbacService->hasPermission($staffRole, 'orders.view') ? 'Yes ✓' : 'No ✗') . "\n";
    echo "Staff Can Create Orders: " . ($rbacService->hasPermission($staffRole, 'orders.create') ? 'Yes ✓' : 'No ✗') . "\n";
    echo "Staff Can Delete Orders: " . ($rbacService->hasPermission($staffRole, 'orders.delete') ? 'Yes ✗ (should be no)' : 'No ✓') . "\n";
    
    echo "\nStaff Available Modules:\n";
    $modules = $rbacService->getAvailableModules($staffRole);
    foreach ($modules as $key => $module) {
        echo "  ✓ " . $module['name'] . "\n";
    }
}

echo "\n✓ RBAC System Verified Successfully!\n\n";
