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
    
    // Check if tenant exists
    $check = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
    $check->execute(['patcatering.localhost:8000']);
    
    if ($check->rowCount() > 0) {
        echo "Tenant 'patcatering' already exists. Skipping...\n";
        exit(0);
    }
    
    // Generate UUIDs for the tenant
    $tenantId = generate_uuid();
    
    // Insert tenant record
    $insert = $pdo->prepare(
        "INSERT INTO tenants (id, name, domain, database_name, plan_code, plan_entitlements, provisioning_status, provisioning_error, provisioned_at, created_at, updated_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $now = date('Y-m-d H:i:s');
    
    $insert->execute([
        $tenantId,
        'Pat Catering',
        'patcatering.localhost:8000',
        'tenant_patcatering',
        null,
        json_encode([]),
        'ready',
        null,
        $now,
        $now,
        $now,
    ]);
    
    echo "✓ Created tenant record in central database\n";
    echo "  ID: {$tenantId}\n";
    echo "  Domain: patcatering.localhost:8000\n";
    echo "  Database: tenant_patcatering\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}

function generate_uuid(): string {
    // Generate a random UUID v4
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
