<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = env('DB_HOST', 'localhost');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host={$host}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $database = 'tenant_patcatering';
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$database}'");
    if ($stmt->rowCount() == 0) {
        echo "ERROR: Tenant database '{$database}' does not exist.\n";
        exit(1);
    }
    
    // Switch to tenant database
    $pdo->exec("USE {$database}");
    
    echo "Connected to database: {$database}\n";
    
    // Create users table if it doesn't exist
    $createTableSQL = <<<SQL
    CREATE TABLE IF NOT EXISTS `users` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `name` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL UNIQUE,
        `email_verified_at` timestamp NULL,
        `password` varchar(255) NOT NULL,
        `remember_token` varchar(100) NULL,
        `role` varchar(50) NOT NULL DEFAULT 'staff',
        `is_active` boolean NOT NULL DEFAULT 1,
        `created_at` timestamp NULL,
        `updated_at` timestamp NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    
    $pdo->exec($createTableSQL);
    echo "✓ Users table ready\n\n";
    
    // Define roles
    $roles = ['admin', 'manager', 'staff', 'cashier'];
    $insertedCount = 0;
    $now = date('Y-m-d H:i:s');
    
    foreach ($roles as $role) {
        $name = ucfirst($role) . ' User';
        $email = $role . '@patcatering.local';
        $password_hash = password_hash('password', PASSWORD_BCRYPT);
        
        // Check if user exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            echo "⊘ User {$email} already exists. Skipping...\n";
            continue;
        }
        
        // Insert user
        $insert = $pdo->prepare(
            "INSERT INTO users (name, email, password, role, is_active, email_verified_at, created_at, updated_at) 
             VALUES (?, ?, ?, ?, 1, ?, ?, ?)"
        );
        $insert->execute([$name, $email, $password_hash, $role, $now, $now, $now]);
        
        echo "✓ Created user: {$email}\n";
        $insertedCount++;
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Successfully created {$insertedCount} tenant users for patcatering!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "LOGIN CREDENTIALS FOR PATCATERING TENANT:\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($roles as $role) {
        printf("  %-15s | Email: %-25s | Password: password\n", ucfirst($role), $role . '@patcatering.local');
    }
    echo str_repeat("-", 60) . "\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
