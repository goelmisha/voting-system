<?php
// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON header
header('Content-Type: application/json; charset=utf-8');

// 1. Verify user authentication
if (!isset($_SESSION['vid'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized: Please log in first.'
    ]);
    exit();
}

// 2. Read incoming request payload (JSON or Form POST)
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$is_verified = false;

if (isset($data['verified']) && ($data['verified'] === true || $data['verified'] === 'true' || $data['verified'] === 1)) {
    $is_verified = true;
} elseif (isset($_POST['verified']) && ($_POST['verified'] === '1' || $_POST['verified'] === 'true')) {
    $is_verified = true;
}

// 3. Update session flag upon verification
if ($is_verified) {
    $_SESSION['face_verified'] = true;
    echo json_encode([
        'success' => true, 
        'message' => 'Biometric identity verified successfully.'
    ]);
    exit();
}

// 4. Handle invalid or missing payload
http_response_code(400);
echo json_encode([
    'success' => false, 
    'message' => 'Verification confirmation payload missing or invalid.'
]);
exit();