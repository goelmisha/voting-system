<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Drop old mismatched table
    $pdo->exec("DROP TABLE IF EXISTS candidates;");
    $pdo->exec("DROP TABLE IF EXISTS groups;");

    // 2. Re-create candidates table with proper columns
    $pdo->exec("
        CREATE TABLE candidates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            party TEXT NOT NULL,
            email TEXT,
            mobile TEXT,
            address TEXT,
            photo TEXT DEFAULT 'default.png',
            votes_count INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Re-create groups table with compatible structure
    $pdo->exec("
        CREATE TABLE groups (
            gid INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT,
            mobile TEXT,
            address TEXT,
            image TEXT DEFAULT 'default.png',
            total_vote INTEGER DEFAULT 0
        );
    ");

    echo "<h3 style='color:green;'>Candidates and Groups tables repaired successfully!</h3>";
    echo "<p><a href='admin/register_group.php'>Go back to Register Party / Candidate &rarr;</a></p>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Failed to repair table:</h3> " . $e->getMessage());
}
?>