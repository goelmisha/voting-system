<?php
/**
 * kiosk/login.php
 * ---------------------------------------------------------------
 * Unlocks the phone kiosk for ONE booth (code + PIN). This is a
 * scoped credential, deliberately separate from admin login: a booth
 * operator can enroll/verify at their station but can never reach the
 * admin console or any voter's ballot.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/_kiosk.php';

// Already unlocked → straight to the kiosk.
if (kiosk_is_unlocked()) {
    header('Location: index.php');
    exit();
}

$error     = '';
$lockout   = false;
$timeout   = isset($_GET['timeout']);

// Rate-limit before doing anything else.
$failures = kiosk_recent_failures($pdo);
if ($failures >= KIOSK_MAX_ATTEMPTS) {
    $lockout = true;
    $error = 'Too many failed unlock attempts from this device. Please wait and try again.';
}

if (!$lockout && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_btn'])) {
    $code = strtoupper(trim($_POST['booth_code'] ?? ''));
    $pin  = (string)($_POST['booth_pin'] ?? '');

    if ($code === '' || $pin === '') {
        $error = 'Please enter both the booth code and the PIN.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM booths WHERE UPPER(code) = ? AND active = 1 LIMIT 1");
            $stmt->execute([$code]);
            $booth = $stmt->fetch(PDO::FETCH_ASSOC);

            $pin_ok = $booth && (password_verify($pin, $booth['pin_hash']) || $pin === $booth['pin_hash']);

            if ($pin_ok) {
                kiosk_log_attempt($pdo, $code, true);

                session_regenerate_id(true);
                kiosk_clear_session(); // never inherit stale state across unlocks
                $_SESSION['kiosk_booth_id']   = (int)$booth['id'];
                $_SESSION['kiosk_booth_code'] = (string)$booth['code'];
                $_SESSION['kiosk_booth_name'] = (string)$booth['name'];
                $_SESSION['kiosk_started']    = time();
                kiosk_touch();

                header('Location: index.php');
                exit();
            }

            kiosk_log_attempt($pdo, $code, false);
            $error = 'Invalid booth code or PIN.';
        } catch (PDOException $e) {
            $error = 'Database error while unlocking the kiosk.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Booth Kiosk — Unlock</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body {
            background-color: #f8f9fc; font-family: Arial, sans-serif;
            min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between;
        }
        .header {
            background-color: var(--primary-color); color: #fff; height: 9vh;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .unlock-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 12px;
            padding: 35px; max-width: 420px; margin: 50px auto;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08); border-top: 5px solid var(--primary-color);
        }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
        .kiosk-icon { font-size: 48px; }
    </style>
</head>
<body>

<div class="container-fluid header">
    <h3 class="m-0 font-weight-bold">🖐️ Booth Biometric Kiosk</h3>
</div>

<main class="container">
    <div class="unlock-card">
        <div class="text-center mb-3">
            <div class="kiosk-icon">📍</div>
            <h4 class="font-weight-bold mb-1">Unlock This Booth</h4>
            <p class="text-muted small mb-0">
                Enter this polling station's booth code and PIN to enroll or verify fingerprints on this phone.
            </p>
        </div>

        <?php if ($timeout && !$error): ?>
            <div class="alert alert-info py-2 text-center small">This kiosk was locked after inactivity. Please unlock again.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-<?= $lockout ? 'warning' : 'danger' ?> py-2 text-center small"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group mb-3">
                <label for="booth_code"><strong>Booth code</strong></label>
                <input type="text" name="booth_code" id="booth_code" class="form-control"
                       placeholder="e.g. BOOTH-001" required autofocus autocapitalize="characters"
                       value="<?= htmlspecialchars($_POST['booth_code'] ?? ''); ?>">
            </div>
            <div class="form-group mb-4">
                <label for="booth_pin"><strong>Booth PIN</strong></label>
                <input type="password" name="booth_pin" id="booth_pin" class="form-control"
                       placeholder="••••••" required inputmode="numeric">
            </div>
            <button type="submit" name="unlock_btn" class="btn btn-custom w-100 py-2" <?= $lockout ? 'disabled' : ''; ?>>
                Unlock Kiosk
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="../admin/login.php" class="text-muted small">Election staff → Admin console</a>
        </div>
    </div>
</main>

</body>
</html>
