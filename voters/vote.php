<?php
session_start();
require_once __DIR__ . '/../db.php';

// 1. Check if voter is logged in
if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];

// 2. Biometric Verification Gate
// Ensure face verification has been completed in the active session
if (!isset($_SESSION['face_verified']) || $_SESSION['face_verified'] !== true) {
    echo '<script>
        alert("Biometric verification required before casting your vote.");
        window.location.href = "face_verify.php";
    </script>';
    exit();
}

// 3. Process the vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['vote_btn']) || isset($_POST['cast_vote_btn']))) {
    $candidate_id = (int)($_POST['gid'] ?? $_POST['candidate_id'] ?? 0);

    if ($candidate_id <= 0) {
        echo '<script>
            alert("Invalid party or candidate selection.");
            window.location.href = "dashboard.php";
        </script>';
        exit();
    }

    try {
        // Fetch current live voter record to verify status and prevent race conditions
        $check_stmt = $pdo->prepare("SELECT status, has_voted, voting FROM voters WHERE id = ? LIMIT 1");
        $check_stmt->execute([$vid]);
        $voter = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voter) {
            header("Location: ../logout.php");
            exit();
        }

        $status = strtolower(trim($voter['status'] ?? 'pending'));
        $has_voted = ((int)($voter['has_voted'] ?? 0) === 1) || (strtolower(trim($voter['voting'] ?? '')) === 'yes');

        // Check verification approval
        if ($status !== 'approved') {
            echo '<script>
                alert("Your account is pending admin verification. You cannot vote yet.");
                window.location.href = "dashboard.php";
            </script>';
            exit();
        }

        // Check if already voted
        if ($has_voted) {
            echo '<script>
                alert("You have already voted! Multiple votes are not allowed.");
                window.location.href = "dashboard.php";
            </script>';
            exit();
        }

        // Begin transaction to guarantee atomic updates
        $pdo->beginTransaction();

        // 1. Update candidate / group vote count
        try {
            $stmt_party = $pdo->prepare("UPDATE candidates SET votes_count = votes_count + 1 WHERE id = ?");
            $stmt_party->execute([$candidate_id]);
        } catch (PDOException $e) {
            $stmt_party = $pdo->prepare("UPDATE groups SET total_vote = total_vote + 1 WHERE id = ? OR gid = ?");
            $stmt_party->execute([$candidate_id, $candidate_id]);
        }

        // 2. Mark voter as voted in the database
        $stmt_voter = $pdo->prepare("UPDATE voters SET has_voted = 1, voting = 'yes' WHERE id = ?");
        $stmt_voter->execute([$vid]);

        // Commit transaction
        $pdo->commit();

        // Update session tracking and reset biometric verification flag
        $_SESSION['voting'] = 'yes';
        $_SESSION['has_voted'] = 1;
        unset($_SESSION['face_verified']);

        echo '<script>
            alert("Vote cast successfully! Thank you for participating.");
            window.location.href = "dashboard.php";
        </script>';
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo '<script>
            alert("Voting process failed due to a server error. Please try again.");
            window.location.href = "dashboard.php";
        </script>';
        exit();
    }
} else {
    // Redirect if accessed directly via GET
    header("Location: dashboard.php");
    exit();
}