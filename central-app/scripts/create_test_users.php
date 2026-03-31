<?php
$db="test_tenant";
$host="127.0.0.1";
$u="root";
$p="";
try {
    $pdo=new PDO("mysql:host=$host", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("USE $db");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL UNIQUE,
        email_verified_at timestamp NULL,
        password varchar(255) NOT NULL,
        remember_token varchar(100) NULL,
        role varchar(50) NOT NULL DEFAULT 'staff',
        is_active boolean NOT NULL DEFAULT 1,
        created_at timestamp NULL,
        updated_at timestamp NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $roles=["admin", "manager", "staff", "cashier"];
    $now = date('Y-m-d H:i:s');
    
    foreach($roles as $role) {
        $name = ucfirst($role)." User";
        $email = $role."@test.local";
        $hash = password_hash("tenant123!", PASSWORD_BCRYPT);
        
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) continue;

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)");
        $stmt->execute([$name, $email, $hash, $role, $now, $now]);
        echo "Created {$email}\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
