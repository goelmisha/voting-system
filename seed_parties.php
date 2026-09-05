<?php
require_once __DIR__ . '/db.php';

try {
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

   
    $count = (int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();

    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO candidates (name, party, photo, votes_count) VALUES (?, ?, 'default.png', 0)");
        $stmt->execute(['Rahul Sharma', 'Progressive Alliance']);
        $stmt->execute(['Priya Patel', 'National Democratic Front']);
        $stmt->execute(['Amit Verma', 'Independent']);
        echo "<h3 style='color:green;'>3 Sample Candidates Inserted Successfully!</h3>";
    } else {
        echo "<h3 style='color:blue;'>Candidates already exist in the database ({$count} found).</h3>";
    }
    $rows = $pdo->query("SELECT * FROM candidates")->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>";
    foreach ($rows as $row) {
        echo "<li>ID: {$row['id']} | Candidate: <strong>" . htmlspecialchars($row['name']) . "</strong> | Party: <strong>" . htmlspecialchars($row['party']) . "</strong></li>";
    }
    echo "</ul>";
echo '<p><a href="voters/dashboard.php">Go back to Voter Dashboard &rarr;</a></p>';
} catch (PDOException $e) {
    die("<h3 style='color:red;'>Database Error:</h3> " . $e->getMessage());
}
?>