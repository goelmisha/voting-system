<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['temp_fp_voter_id']) && !isset($_SESSION['vid'])) {
    header("Location: login.php");
    exit();
}

$target_voter_id = $_SESSION['temp_fp_voter_id'] ?? $_SESSION['vid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Fingerprint Enrollment - Online Voting System</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fa; font-family: Arial, sans-serif; }
        .fp-card {
            background: #fff;
            border-radius: 12px;
            padding: 35px;
            max-width: 480px;
            margin: 60px auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            text-align: center;
        }
        .fp-icon { font-size: 55px; color: var(--primary-color); margin-bottom: 15px; }
        .btn-custom {
            background-color: var(--primary-color);
            color: #fff;
            font-weight: bold;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
            width: 100%;
        }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <div class="fp-card">
        <div class="fp-icon">🖐️</div>
        <h4 class="font-weight-bold mb-2">Fingerprint Enrollment</h4>
        <p class="text-muted small mb-4">
            Scan your fingerprint using your device's biometric sensor (Touch ID, Windows Hello, or mobile scanner) to complete registration.
        </p>

        <div id="statusBox" class="alert alert-info py-2 d-none"></div>

        <button id="enrollBtn" class="btn btn-custom mb-3">Scan & Register Fingerprint</button>
        <a href="login.php" class="text-muted small d-block">Skip for now &rarr; Go to Login</a>
    </div>
</div>

<script>
document.getElementById('enrollBtn').addEventListener('click', async () => {
    const statusBox = document.getElementById('statusBox');
    statusBox.className = 'alert alert-info py-2 d-block';
    statusBox.innerText = "Please touch your device's fingerprint scanner...";

    if (!window.PublicKeyCredential) {
        statusBox.className = 'alert alert-danger py-2 d-block';
        statusBox.innerText = "WebAuthn / Biometrics not supported on this browser or device.";
        return;
    }

    try {
        const challenge = new Uint8Array(32);
        window.crypto.getRandomValues(challenge);

        const userId = new Uint8Array(16);
        window.crypto.getRandomValues(userId);

        const credential = await navigator.credentials.create({
            publicKey: {
                challenge: challenge,
                rp: { name: "Online Voting System", id: window.location.hostname },
                user: {
                    id: userId,
                    name: "voter_<?= $target_voter_id; ?>",
                    displayName: "Citizen Voter"
                },
                pubKeyCredParams: [{ alg: -7, type: "public-key" }, { alg: -257, type: "public-key" }],
                authenticatorSelection: {
                    authenticatorAttachment: "platform",
                    userVerification: "required"
                },
                timeout: 60000
            }
        });

        if (credential) {
            const rawIdBytes = new Uint8Array(credential.rawId);
            let binary = '';
            rawIdBytes.forEach(b => binary += String.fromCharCode(b));
            const credentialIdBase64 = btoa(binary);

            const response = await fetch('save_fingerprint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ credential_id: credentialIdBase64 })
            });

            const res = await response.json();
            if (res.success) {
                statusBox.className = 'alert alert-success py-2 d-block font-weight-bold';
                statusBox.innerText = "Fingerprint enrolled successfully! Redirecting to login...";
                setTimeout(() => { window.location.href = "login.php"; }, 1200);
            } else {
                statusBox.className = 'alert alert-danger py-2 d-block';
                statusBox.innerText = "Server Error: " + res.message;
            }
        }
    } catch (err) {
        statusBox.className = 'alert alert-danger py-2 d-block';
        statusBox.innerText = "Enrollment Cancelled or Failed: " + err.message;
    }
});
</script>
</body>
</html>
