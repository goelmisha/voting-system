<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// If already logged in and verified, redirect to voter dashboard
if (isset($_SESSION['vid']) && isset($_SESSION['status']) && strtolower(trim($_SESSION['status'])) === 'approved' && !isset($_SESSION['temp_login_voter'])) {
    header('Location: voters/dashboard.php');
    exit();
}

$alert_message = "";
$current_step = isset($_SESSION['temp_login_voter']) ? 'otp_step' : 'cred_step';

// Handle cancel / re-entering credentials
if (isset($_GET['action']) && $_GET['action'] === 'cancel_login') {
    unset($_SESSION['temp_login_voter'], $_SESSION['login_otp'], $_SESSION['login_otp_expiry']);
    header('Location: login.php');
    exit();
}

/**
 * Helper: Dispatch Live Email OTP via Gmail SMTP
 */
function send_login_email_otp($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jahnvikarnatac04@gmail.com';
        // Paste your 16-character Google App Password here (no spaces)
        $mail->Password   = 'zwtczjgjkqnulyrc';  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('jahnvikarnatac04@gmail.com', 'Online Voting System');
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Voter Login 2FA OTP Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 500px;'>
                <h3 style='color: blueviolet; margin-top: 0;'>Voter Portal Authentication</h3>
                <p>Hello <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
                <p>Your Two-Factor Authentication (2FA) login code is:</p>
                <div style='font-size: 26px; font-weight: bold; letter-spacing: 6px; background: #f3f3f3; display: inline-block; padding: 10px 20px; border-radius: 6px; color: #222; margin: 10px 0;'>{$otp}</div>
                <p style='color: #777; font-size: 13px; margin-top: 15px;'>This code will expire in 5 minutes. Do not share this with anyone.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Login Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Helper: Dispatch Live Mobile OTP via Fast2SMS
 */
function send_login_sms_otp($mobile_number, $otp) {
    $apiKey = "YOUR_FAST2SMS_API_KEY"; // Enter your Fast2SMS API Key

    $clean_mobile = preg_replace('/[^0-9]/', '', $mobile_number);
    if (strlen($clean_mobile) === 12 && substr($clean_mobile, 0, 2) === '91') {
        $clean_mobile = substr($clean_mobile, 2);
    }

    $fields = [
        "variables_values" => $otp,
        "route"            => "otp",
        "numbers"          => $clean_mobile,
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($fields),
        CURLOPT_HTTPHEADER     => [
            "authorization: " . $apiKey,
            "content-type: application/json"
        ],
        CURLOPT_TIMEOUT        => 5
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
    return true;
}


// STEP 1: Verify Credentials & Dispatch Live Login OTP

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $alert_message = "Please enter both your email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM voters WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $voter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($voter) {
                // Verify password against hash, with fallback for plain-text entries
                $password_valid = password_verify($password, $voter['password']) || ($password === $voter['password']);

                if ($password_valid) {
                    $status = strtolower(trim($voter['status'] ?? 'pending'));

                    if ($status === 'pending') {
                        $alert_message = "Your citizenship verification is pending admin review. Please wait for approval.";
                    } elseif ($status === 'rejected') {
                        $alert_message = "Your registration was rejected during verification. Please contact support.";
                    } elseif ($status === 'approved') {
                        // Generate 6-Digit OTP & 5-minute Expiry
                        $generated_otp = (string)random_int(100000, 999999);
                        $_SESSION['login_otp']        = $generated_otp;
                        $_SESSION['login_otp_expiry'] = time() + (5 * 60);

                        // Stage profile data in temporary session
                        $_SESSION['temp_login_voter'] = [
                            'vid'       => (int)$voter['id'],
                            'name'      => $voter['fullname'],
                            'email'     => $voter['email'],
                            'id_number' => $voter['voter_id_number'],
                            'mobile'    => $voter['mobile'] ?? '',
                            'image'     => $voter['photo'] ?? 'default.png',
                            'voting'    => ((int)$voter['has_voted'] === 1) ? 'yes' : 'no',
                            'status'    => 'approved'
                        ];

                        // Send Live OTP to Email and Mobile
                        send_login_email_otp($voter['email'], $voter['fullname'], $generated_otp);
                        if (!empty($voter['mobile'])) {
                            send_login_sms_otp($voter['mobile'], $generated_otp);
                        }

                        header('Location: login.php');
                        exit();
                    } else {
                        $alert_message = "Your account status is currently inactive.";
                    }
                } else {
                    $alert_message = "Invalid email or password. Please try again.";
                }
            } else {
                $alert_message = "Invalid email or password. Please try again.";
            }
        } catch (PDOException $e) {
            $alert_message = "Database error: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------
// STEP 2: Validate Login OTP & Authorize Session
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp_btn'])) {
    $entered_otp = trim($_POST['otp'] ?? '');

    if (empty($entered_otp)) {
        $alert_message = "Please enter the 6-digit OTP code.";
    } elseif (!isset($_SESSION['login_otp']) || !isset($_SESSION['login_otp_expiry'])) {
        $alert_message = "OTP session expired. Please log in again.";
    } elseif (time() > $_SESSION['login_otp_expiry']) {
        unset($_SESSION['temp_login_voter'], $_SESSION['login_otp'], $_SESSION['login_otp_expiry']);
        $alert_message = "OTP has expired. Please re-enter your credentials.";
    } elseif ($entered_otp !== (string)$_SESSION['login_otp']) {
        $alert_message = "Invalid OTP entered. Please check your inbox/messages.";
    } else {
        // Authenticate citizen session
        $voter = $_SESSION['temp_login_voter'];
        $_SESSION['vid']       = $voter['vid'];
        $_SESSION['name']      = $voter['name'];
        $_SESSION['email']     = $voter['email'];
        $_SESSION['id_number'] = $voter['id_number'];
        $_SESSION['image']     = $voter['image'];
        $_SESSION['voting']    = $voter['voting'];
        $_SESSION['status']    = 'approved';

        // Clear temporary staging session
        unset($_SESSION['temp_login_voter'], $_SESSION['login_otp'], $_SESSION['login_otp_expiry']);

        header('Location: voters/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Login - Online Voting System</title>

    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css"> 

    <style>
        :root {
            --primary-color: blueviolet;
            --primary-hover: #701eb8;
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .header {
            background-color: var(--primary-color);
            color: #ffffff;
            height: 9vh;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .voter-login-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: bold;
            width: 100%;
        }

        .btn-custom:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }

        .otp-input {
            letter-spacing: 8px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
        }

        footer {
            text-align: center;
            padding: 20px 0;
            color: #777;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
            background-color: #fff;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="container-fluid header">
        <h3 class="m-0 font-weight-bold">Online Voting System</h3>
    </div>

    <!-- Login Container -->
    <div class="container mt-2 mb-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="voter-login-card">

                    <?php if ($current_step === 'cred_step'): ?>
                        <!-- Step 1: Voter Credentials -->
                        <h4 class="text-center font-weight-bold mb-3">Voter Login</h4>
                        <p class="text-center text-muted small mb-4">Enter credentials to receive your 2FA login OTP</p>

                        <?php if (!empty($alert_message)): ?>
                            <div class="alert alert-warning py-2 text-center" role="alert">
                                <small class="font-weight-bold"><?= htmlspecialchars($alert_message); ?></small>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label"><strong>Email Address:</strong></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="Enter your registered email" required>
                            </div>

                            <div class="form-group mb-4">
                                <label for="password" class="form-label"><strong>Password:</strong></label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            </div>

                            <button type="submit" class="btn btn-custom mt-2" name="login">Proceed to OTP Verification &rarr;</button>
                        </form>

                        <div class="text-center mt-3">
                            <small class="text-muted">Haven't registered yet? <a href="register.php" style="color: blueviolet; font-weight: bold;">Register Here</a></small>
                            <br>
                            <a href="index.php" class="text-muted small mt-2 d-inline-block">← Back to Portal</a>
                        </div>

                    <?php else: ?>
                        <!-- Step 2: 2FA Login OTP Screen (Live Real OTP) -->
                        <h4 class="text-center font-weight-bold mb-2">Two-Factor Authentication</h4>
                        <p class="text-center text-muted small mb-3">
                            An OTP has been dispatched to <strong><?= htmlspecialchars($_SESSION['temp_login_voter']['email'] ?? ''); ?></strong>.
                        </p>

                        <?php if (!empty($alert_message)): ?>
                            <div class="alert alert-danger py-2 text-center" role="alert">
                                <small class="font-weight-bold"><?= htmlspecialchars($alert_message); ?></small>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="form-group mb-4">
                                <label for="otp" class="text-center d-block"><strong>Enter 6-Digit OTP:</strong></label>
                                <input type="text" class="form-control otp-input" name="otp" id="otp" maxlength="6" placeholder="000000" autofocus required>
                            </div>

                            <button type="submit" name="verify_otp_btn" class="btn btn-custom mb-2">Verify & Login to Dashboard</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="login.php?action=cancel_login" class="text-muted small">&larr; Back to Login / Re-enter</a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>