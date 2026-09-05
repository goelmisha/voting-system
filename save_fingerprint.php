<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$voter_id = $_SESSION['temp_fp_voter_id'] ?? $_SESSION['vid'] ?? null;

if (!$voter_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$credential_id = trim($input['credential_id'] ?? '');

if (empty($credential_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid credential received.']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE voters SET fingerprint_credential = ? WHERE id = ?");
    $stmt->execute([$credential_id, $voter_id]);

    unset($_SESSION['temp_fp_voter_id']);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
