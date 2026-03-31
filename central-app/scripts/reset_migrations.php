<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = env('DB_HOST', 'localhost');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$database = 'central_app';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$database}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Resetting migrations...\n";
    
    // Drop all tables except migrations to reset state
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if ($table !== 'migrations') {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    }
    
    // Clear migrations table
    $pdo->exec("DELETE FROM migrations");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "✓ Database reset for fresh migrations\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
