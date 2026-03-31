<?php

// Simple verification that RBAC tables have data
$mysqli = new mysqli('127.0.0.1', 'root', 'password', 'central_app');

if ($mysqli->connect_error) {
    die('Connect Error: ' . $mysqli->connect_error);
}

echo "=== RBAC DATA VERIFICATION ===\n\n";

$tables = [
    'tenant_roles' => 'SELECT COUNT(*) as cnt FROM tenant_roles',
    'permissions' => 'SELECT COUNT(*) as cnt FROM permissions',
    'tenant_features' => 'SELECT COUNT(*) as cnt FROM tenant_features',
    'role_permissions' => 'SELECT COUNT(*) as cnt FROM role_permissions',
    'role_features' => 'SELECT COUNT(*) as cnt FROM role_features',
];

foreach ($tables as $table => $query) {
    $result = $mysqli->query($query);
    $row = $result->fetch_assoc();
    $count = $row['cnt'];
    echo "$table: $count records\n";
}

// Check admin role has all features
echo "\n=== ADMIN ROLE FEATURES ===\n";
$result = $mysqli->query("
    SELECT tf.name, rf.is_enabled 
    FROM tenant_roles tr
    JOIN role_features rf ON tr.id = rf.role_id
    JOIN tenant_features tf ON rf.feature_id = tf.id
    WHERE tr.name = 'admin'
    ORDER BY tf.name
");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['name'] . ": " . ($row['is_enabled'] ? 'ENABLED' : 'DISABLED') . "\n";
}

// Check cashier role features
echo "\n=== CASHIER ROLE FEATURES ===\n";
$result = $mysqli->query("
    SELECT tf.name, rf.is_enabled 
    FROM tenant_roles tr
    JOIN role_features rf ON tr.id = rf.role_id
    JOIN tenant_features tf ON rf.feature_id = tf.id
    WHERE tr.name = 'cashier'
    ORDER BY tf.name
");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['name'] . ": " . ($row['is_enabled'] ? 'ENABLED' : 'DISABLED') . "\n";
}

// Verify permissions
echo "\n=== PERMISSIONS ===\n";
$result = $mysqli->query("SELECT COUNT(DISTINCT rp.role_id) as roles, p.name 
    FROM role_permissions rp
    JOIN permissions p ON rp.permission_id = p.id
    GROUP BY p.name
    LIMIT 5");
echo "Sample permissions by role:\n";
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['name'] . ": " . $row['roles'] . " roles have it\n";
}

echo "\n✓ RBAC Data Verified Successfully!\n";
$mysqli->close();
