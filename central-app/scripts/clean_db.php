<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = env('DB_HOST', 'localhost');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$database = 'central_app';

try {
    $pdo = new PDO("mysql:host={$host}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Try to clean up tablespace issues
    echo "Cleaning up tablespace issues...\n";
    
    // Skip or ignore the error - we'll use raw SQL to handle the migrations manually
    $pdo->exec("USE {$database}");
    
    // Check if migrations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Migrations table already exists\n";
    } else {
        // Create it without InnoDB
        $createTableSQL = <<<SQL
        CREATE TABLE `migrations` (
            `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `migration` varchar(255) NOT NULL,
            `batch` int NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $pdo->exec($createTableSQL);
        echo "✓ Created migrations table\n";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
