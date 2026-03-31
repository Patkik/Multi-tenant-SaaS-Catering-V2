<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = env('DB_HOST', 'localhost');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

try {
    $pdo = new PDO("mysql:host={$host};dbname=tenant_patcatering", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY role");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Tenant Users in 'tenant_patcatering' Database:\n";
    echo str_repeat("=", 80) . "\n";
    printf("%-5s | %-20s | %-30s | %-10s | %-10s | %s\n", "ID", "Name", "Email", "Role", "Active", "Created At");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($users as $user) {
        printf("%-5d | %-20s | %-30s | %-10s | %-10s | %s\n", 
            $user['id'],
            $user['name'],
            $user['email'],
            $user['role'],
            $user['is_active'] ? 'Yes' : 'No',
            $user['created_at']
        );
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "Total users: " . count($users) . "\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
