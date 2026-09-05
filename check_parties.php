<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS candidates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            party TEXT NOT NULL,
            photo TEXT DEFAULT 'default.png',
            votes_count INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Check existing candidate count
    $count = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();

    if ($count == 0) {
        // Insert sample test parties
        $stmt = $pdo->prepare("INSERT INTO candidates (name, party, photo, votes_count) VALUES (?, ?, 'default.png', 0)");
        $stmt->execute(['Candidate Alpha', 'Party A (Blue)']);
        $stmt->execute(['Candidate Beta', 'Party B (Green)']);
        $stmt->execute(['Candidate Gamma', 'Party C (Orange)']);

        echo "<h3 style='color:green;'>Created 3 test parties successfully!</h3>";
    } else {
        echo "<h3 style='color:blue;'>Found {$count} registered party/parties in database:</h3>";
    }

    // 3. Display current records
    $rows = $pdo->query("SELECT * FROM candidates")->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>";
    foreach ($rows as $r) {
        echo "<li>ID: {$r['id']} | Candidate: <strong>" . htmlspecialchars($r['name']) . "</strong> | Party: <strong>" . htmlspecialchars($r['party']) . "</strong></li>";
    }
    echo "</ul>";
    echo "<p><a href='voters/dashboard.php'>Go to Voter Dashboard</a> | <a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Database Error:</h3> " . $e->getMessage());
}
?>