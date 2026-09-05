<?php
require_once __DIR__ . '/../db.php';

$admins = [
    [
        'name'     => 'Chief Election Officer',
        'email'    => 'chief_admin@example.com',
        'username' => 'superadmin',
        'password' => 'SuperSecret123!',
        'role'     => 'super_admin'
    ],
    [
        'name'     => 'Verification Officer 1',
        'email'    => 'officer1@example.com',
        'username' => 'officer1',
        'password' => 'OfficerPass123!',
        'role'     => 'admin'
    ],
    [
        'name'     => 'Ballot Patrol Officer',
        'email'    => 'patrol@example.com',
        'username' => 'patrolofficer',
        'password' => 'patrolofficer123',
        'role'     => 'admin'
    ]
];

try {
    // 1. Drop the outdated admins table
    $pdo->exec("DROP TABLE IF EXISTS admins;");

    // 2. Re-create the table with all required columns
    $pdo->exec("
        CREATE TABLE admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Insert all admin records with hashed passwords
    $stmt = $pdo->prepare("
        INSERT INTO admins (name, username, email, password, role) 
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($admins as $admin) {
        $hashedPassword = password_hash($admin['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $admin['name'],
            $admin['username'],
            $admin['email'],
            $hashedPassword,
            $admin['role']
        ]);
    }

    echo "<h3 style='color: green;'>Admins table rebuilt and seeded successfully!</h3>";
    echo "<ul>";
    foreach ($admins as $a) {
        echo "<li><strong>" . htmlspecialchars($a['name']) . " (" . htmlspecialchars($a['role']) . "):</strong> Username: <code>" . htmlspecialchars($a['username']) . "</code> | Password: <code>" . htmlspecialchars($a['password']) . "</code></li>";
    }
    echo "</ul>";
    echo "<p><a href='login.php'>Go to Admin Login &rarr;</a></p>";

} catch (PDOException $e) {
    die("<h3 style='color: red;'>Error updating admins:</h3> " . $e->getMessage());
}
?>