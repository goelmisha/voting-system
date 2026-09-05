<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];
$stmt = $pdo->prepare("SELECT fingerprint_credential FROM voters WHERE id = ? LIMIT 1");
$stmt->execute([$vid]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);

$fp_credential = $voter['fingerprint_credential'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Verification - Voting</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fa; font-family: Arial, sans-serif; }
        .verify-card {
            background: #fff;
            border-radius: 12px;
            padding: 35px;
            max-width: 480px;
            margin: 60px auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            text-align: center;
        }
        .btn-custom {
            background-color: #28a745;
            color: #fff;
            font-weight: bold;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
            width: 100%;
        }
        .btn-custom:hover { background-color: #218838; color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <div class="verify-card">
        <h4 class="font-weight-bold mb-2">🖐️ Fingerprint Verification</h4>
        <p class="text-muted small mb-4">Scan your registered fingerprint to authorize your ballot submission.</p>

        <div id="verifyStatus" class="alert alert-info py-2 d-none"></div>

        <button id="verifyFpBtn" class="btn btn-custom mb-3">Touch Fingerprint Sensor</button>
        <a href="dashboard.php" class="text-muted small d-block">← Back to Dashboard</a>
    </div>
</div>

<script>
const storedCredential = "<?= htmlspecialchars($fp_credential); ?>";

document.getElementById('verifyFpBtn').addEventListener('click', async () => {
    const statusBox = document.getElementById('verifyStatus');
    statusBox.className = 'alert alert-info py-2 d-block';
    statusBox.innerText = "Scanning fingerprint...";

    if (!storedCredential) {
        statusBox.className = 'alert alert-warning py-2 d-block';
        statusBox.innerText = "No fingerprint registered for this account. Please register a fingerprint first.";
        document.getElementById('verifyFpBtn').disabled = true;
        return;
    }

    try {
        const challenge = new Uint8Array(32);
        window.crypto.getRandomValues(challenge);

        // Convert base64 credential back to Uint8Array
        const rawId = Uint8Array.from(atob(storedCredential), c => c.charCodeAt(0));

        const assertion = await navigator.credentials.get({
            publicKey: {
                challenge: challenge,
                allowCredentials: [{
                    id: rawId,
                    type: 'public-key',
                    transports: ['internal']
                }],
                userVerification: 'required',
                timeout: 60000
            }
        });

        if (assertion) {
            statusBox.className = 'alert alert-success py-2 d-block font-weight-bold';
            statusBox.innerText = "Fingerprint verified! Redirecting to ballot...";
            
            // Set session verification flag
            await fetch('verify_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ verified: true })
            });

            setTimeout(() => { window.location.href = "vote.php"; }, 1000);
        }
    } catch (err) {
        statusBox.className = 'alert alert-danger py-2 d-block';
        statusBox.innerText = "Verification failed: " + err.message;
    }
});
</script>
</body>
</html>
