<?php
require_once __DIR__ . '/../db.php';

$admins = [
    [
        'name' => 'Chief Election Officer',
        'email' => 'chief_admin@example.com',
        'username' => 'superadmin',
        'password' => 'SuperSecret123!',
        'role' => 'super_admin'
    ],
    [
        'name' => 'Verification Officer 1',
        'email' => 'officer1@example.com',
        'username' => 'officer1',
        'password' => 'OfficerPass123!',
        'role' => 'admin'

    ],
     [
        'name' => 'ballot patrol officer',
        'email' => 'patrol@example.com',
        'username' => 'patrolofficer',
        'password' => 'patrolofficer123',
        'role' => 'admin'

    ],

];

try {
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO admins (name, email, username, password, role) 
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($admins as $admin) {
        $hashedPassword = password_hash($admin['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $admin['name'],
            $admin['email'],
            $admin['username'],
            $hashedPassword,
            $admin['role']
        ]);
    }

    echo "<h3>Admins registered successfully.</h3>";
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>