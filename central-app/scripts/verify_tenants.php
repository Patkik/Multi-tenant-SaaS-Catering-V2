<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = env('DB_HOST', 'localhost');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

try {
    $pdo = new PDO("mysql:host={$host};dbname=central_app", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT id, name, domain, database_name, provisioning_status FROM tenants");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Tenants in Central Database:\n";
    echo str_repeat("=", 100) . "\n";
    printf("%-36s | %-20s | %-30s | %-20s | %s\n", "ID", "Name", "Domain", "Database", "Status");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($tenants as $tenant) {
        printf("%-36s | %-20s | %-30s | %-20s | %s\n", 
            $tenant['id'],
            $tenant['name'],
            $tenant['domain'],
            $tenant['database_name'],
            $tenant['provisioning_status']
        );
    }
    
    echo str_repeat("=", 100) . "\n";
    echo "Total tenants: " . count($tenants) . "\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
