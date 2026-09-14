<?php
/**
 * admin/enroll_voter.php
 * ---------------------------------------------------------------
 * Booth Biometric Enrollment console (ADMIN ONLY).
 *
 * Voters cannot self-register biometrics from the public site for
 * security reasons — the election admin pre-enrolls each voter at
 * the booth: the admin picks (or onboards) the citizen, the citizen
 * scans their fingerprint/face on the booth device, and the passkey
 * is bound to THAT voter's account (never to the admin).
 *
 * Flow:
 *   1. Existing voter  -> arm via ?vid=N
 *      Walk-in citizen -> create record via POST (booth onboarding),
 *                         then auto-armed for scanning.
 *   2. "Start Scan" calls the shared webauthn_options.php API;
 *      wa_target_voter() resolves the armed admin_enroll_vid, so
 *      enrollment targets the voter.
 *   3. On success the API disarms the session automatically.
 * ---------------------------------------------------------------
 */
session_start();
require_once __DIR__ . '/../db.php';

// Admin gate: election staff only — never reachable by voters or the public
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$voter = null;
$error = '';

/* ---------------- Walk-in onboarding (POST) ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booth_onboard'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $epic     = strtoupper(trim($_POST['voter_id_number'] ?? ''));
    $mobile   = trim($_POST['mobile'] ?? '');
    $password = (string)($_POST['booth_password'] ?? '');
    $status   = ($_POST['booth_status'] ?? 'approved') === 'pending' ? 'pending' : 'approved';

    // Validate
    if ($fullname === '' || $email === '' || $epic === '') {
        $error = 'Full name, Email and Voter ID (EPIC) are all required for booth onboarding.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Temporary password must be at least 6 characters.';
    } else {
        try {
            // Uniqueness pre-check (email + EPIC), case-insensitive for email
            $dup = $pdo->prepare("SELECT id, email, voter_id_number FROM voters WHERE LOWER(email) = ? OR UPPER(voter_id_number) = ? LIMIT 1");
            $dup->execute([$email, $epic]);
            $existing_row = $dup->fetch(PDO::FETCH_ASSOC);

            if ($existing_row) {
                if (strcasecmp($existing_row['email'], $email) === 0) {
                    $error = 'A voter with this email already exists (#' . (int)$existing_row['id'] . '). Select them from the list instead.';
                } else {
                    $error = 'A voter with this Voter ID (EPIC) already exists (#' . (int)$existing_row['id'] . '). Select them from the list instead.';
                }
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO voters (fullname, email, voter_id_number, mobile, address, password, photo, document_proof, status, has_voted)
                     VALUES (?, ?, ?, ?, '', ?, 'default.png', '', ?, 0)"
                );
                $insert->execute([
                    $fullname,
                    $email,
                    $epic,
                    $mobile !== '' ? $mobile : null,
                    password_hash($password, PASSWORD_DEFAULT),
                    $status,
                ]);
                $new_id = (int)$pdo->lastInsertId();

                // Flash the handed-over credentials, then arm the booth for scanning
                $_SESSION['booth_flash'] = [
                    'kind' => 'success',
                    'html' => '✅ Citizen <strong>' . htmlspecialchars($fullname) . '</strong> onboarded at the booth (Record #' . $new_id . ', ' .
                              ucfirst($status) . ').<br>Hand over these login credentials: <strong>' . htmlspecialchars($email) .
                              '</strong> / <code>' . htmlspecialchars($password) . '</code>' .
                              '<br><small class="text-muted">Now scan their fingerprint below to finish biometric enrollment.</small>',
                ];
                header('Location: enroll_voter.php?vid=' . $new_id);
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Database error during onboarding: ' . $e->getMessage();
        }
    }

    // On validation failure, fall through and re-render with $error
    if (!empty($_POST['fullname'])) { /* keep entered values via $_POST in the form */ }
}

/* ---------------- Flash message (after onboarding redirect) ---------------- */

$flash_html = '';
if (isset($_SESSION['booth_flash'])) {
    $flash = $_SESSION['booth_flash'];
    unset($_SESSION['booth_flash']);
    $flash_html = '<div class="alert alert-' . htmlspecialchars($flash['kind'] ?? 'info') . ' py-2">'
                . ($flash['html'] ?? '') . '</div>';
}

/* ---------------- Arm a booth enrollment target ---------------- */

$vid = isset($_GET['vid']) ? (int)$_GET['vid'] : 0;
if ($vid > 0) {
    $stmt = $pdo->prepare("SELECT id, fullname, email, voter_id_number, status FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($voter) {
        // Bind this enrollment strictly to the chosen citizen for this session.
        // The WebAuthn API verifies the challenge against THIS id and disarms
        // itself after one successful scan.
        $_SESSION['admin_enroll_vid']  = (int)$voter['id'];
        $_SESSION['admin_enroll_name'] = $voter['fullname'];
    } else {
        $error = 'Voter record #' . $vid . ' was not found.';
        unset($_SESSION['admin_enroll_vid'], $_SESSION['admin_enroll_name']);
    }
} else {
    unset($_SESSION['admin_enroll_vid'], $_SESSION['admin_enroll_name']);
}

// Passkeys already registered for the selected voter
$existing = [];
if ($voter) {
    try {
        $stmt = $pdo->prepare("SELECT credential_id, device_label, created_at FROM passkeys WHERE voter_id = ? ORDER BY id DESC");
        $stmt->execute([(int)$voter['id']]);
        $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $existing = [];
    }
}

// All citizens for the pick-a-voter dropdown
$voters_list = $pdo->query("SELECT id, fullname, email, voter_id_number, status FROM voters ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);

function booth_status_badge(array $v): string
{
    $s = strtolower(trim($v['status'] ?? 'pending'));
    if ($s === 'approved') return '<span class="badge badge-success px-2 py-1">Approved</span>';
    if ($s === 'rejected') return '<span class="badge badge-danger px-2 py-1">Rejected</span>';
    return '<span class="badge badge-warning px-2 py-1">Pending</span>';
}

// Re-fill the onboarding form after a validation error
$old = [
    'fullname' => htmlspecialchars($_POST['fullname'] ?? ''),
    'email'    => htmlspecialchars($_POST['email'] ?? ''),
    'epic'     => htmlspecialchars($_POST['voter_id_number'] ?? ''),
    'mobile'   => htmlspecialchars($_POST['mobile'] ?? ''),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booth Biometric Enrollment - Admin - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; }
        .header {
            background-color: var(--primary-color);
            color: #ffffff;
            height: 9vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .booth-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 720px;
            margin: 40px auto;
            border: 1px solid #e0e0e0;
            border-top: 5px solid var(--primary-color);
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
        .btn-custom:disabled { background-color: #c9b8dc; cursor: not-allowed; }
        .passkey-row { display: flex; justify-content: space-between; align-items: center; }
        .voter-chip {
            background: #f3e8ff; border: 1px solid #d1c4e9; border-radius: 20px;
            padding: 6px 16px; font-weight: bold; color: var(--primary-color); display: inline-block;
        }
        .step-label { font-weight: bold; font-size: .85rem; color: #333; display: block; margin-bottom: .5rem; }
    </style>
</head>
<body>

<div class="container-fluid header">
    <h4 class="m-0 font-weight-bold">🗳️ Booth Biometric Enrollment — Election Staff Only</h4>
    <a href="dashboard.php" class="btn btn-light btn-sm font-weight-bold">← Admin Dashboard</a>
</div>

<div class="container">
    <div class="booth-card">

        <div class="text-center mb-4">
            <div style="font-size: 44px;">🖐️</div>
            <h4 class="font-weight-bold mb-1">Register Biometrics / Enroll Voter</h4>
            <p class="text-muted small mb-0">
                Voters cannot self-register biometrics online for security reasons.
                The election admin pre-enrolls them at the booth: select or onboard the citizen,
                then have them scan on this device.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 text-center"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?= $flash_html; ?>

        <div class="row">
            <!-- Step 1A: pick an existing citizen -->
            <div class="col-md-6 mb-3">
                <form method="GET" action="enroll_voter.php" class="border rounded p-3 h-100 bg-light">
                    <span class="step-label">1A. Existing Voter — Select Citizen</span>
                    <?php if (empty($voters_list)): ?>
                        <div class="alert alert-warning py-2 small text-center mb-2">No voters registered yet — use <strong>1B. Walk-in Onboarding</strong>.</div>
                    <?php else: ?>
                        <select name="vid" class="form-control mb-2" required>
                            <option value="" disabled selected>-- Select registered voter --</option>
                            <?php foreach ($voters_list as $v): ?>
                                <option value="<?= (int)$v['id']; ?>">
                                    <?= htmlspecialchars($v['fullname']); ?>
                                    (<?= htmlspecialchars($v['voter_id_number'] ?? 'No EPIC'); ?>) — <?= htmlspecialchars(ucfirst(strtolower(trim($v['status'] ?? 'pending')))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-block font-weight-bold">Arm Booth for Selected Voter</button>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-2">Arming binds the next fingerprint scan strictly to the selected citizen's account.</small>
                </form>
            </div>

            <!-- Step 1B: onboard a NEW walk-in citizen -->
            <div class="col-md-6 mb-3">
                <form method="POST" action="enroll_voter.php" class="border rounded p-3 h-100 bg-light" id="onboardForm">
                    <span class="step-label">1B. New Walk-in Citizen — Booth Onboarding</span>
                    <input type="hidden" name="booth_onboard" value="1">
                    <div class="form-group mb-2">
                        <input type="text" name="fullname" class="form-control form-control-sm" placeholder="Full name (as on ID) *" value="<?= $old['fullname']; ?>" required>
                    </div>
                    <div class="form-group mb-2">
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="Email (login ID) *" value="<?= $old['email']; ?>" required>
                    </div>
                    <div class="form-group mb-2">
                        <input type="text" name="voter_id_number" class="form-control form-control-sm" placeholder="Voter ID (EPIC number) *" value="<?= $old['epic']; ?>" required>
                    </div>
                    <div class="form-group mb-2">
                        <input type="text" name="mobile" class="form-control form-control-sm" placeholder="Mobile (optional, for OTP)" value="<?= $old['mobile']; ?>">
                    </div>
                    <div class="form-row mb-2">
                        <div class="col-7">
                            <input type="text" name="booth_password" class="form-control form-control-sm" placeholder="Temp password *" minlength="6" required>
                        </div>
                        <div class="col-5">
                            <select name="booth_status" class="form-control form-control-sm" title="Booth-verified citizens are approved on the spot">
                                <option value="approved" selected>Approved now</option>
                                <option value="pending">Keep pending</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-custom btn-block btn-sm font-weight-bold">➕ Onboard Citizen &amp; Arm Booth</button>
                    <small class="text-muted d-block mt-2">Creates the voter record (ID verified in person by you), then jumps straight to the scan step.</small>
                </form>
            </div>
        </div>

        <?php if ($voter): ?>
            <!-- Step 2: Enrollment console for the armed citizen -->
            <hr>
            <div class="text-center mb-3">
                <span class="step-label">2. Scan on This Booth Device</span>
                <div class="voter-chip mb-2">
                    <?= htmlspecialchars($voter['fullname']); ?> · <?= htmlspecialchars($voter['email']); ?>
                </div>
                <div>EPIC: <span class="badge badge-info px-2 py-1"><?= htmlspecialchars($voter['voter_id_number'] ?? 'N/A'); ?></span>
                     Status: <?= booth_status_badge($voter); ?></div>
            </div>

            <div id="statusBox" class="alert alert-info py-2 text-center d-none"></div>

            <button id="enrollBtn" class="btn btn-custom btn-block py-2 mb-3">
                🖐️ Start Scan &amp; Register Biometric for <?= htmlspecialchars($voter['fullname']); ?>
            </button>

            <?php if (!empty($existing)): ?>
                <label class="font-weight-bold small text-dark">Already enrolled devices for this citizen:</label>
                <?php foreach ($existing as $pk): ?>
                    <div class="border rounded p-2 px-3 mb-2 passkey-row">
                        <div>
                            <strong class="small">🔑 <?= htmlspecialchars($pk['device_label'] ?: 'Fingerprint device'); ?></strong>
                            <br><small class="text-muted">Added <?= htmlspecialchars(date('j M Y', strtotime($pk['created_at']))); ?></small>
                        </div>
                        <span class="badge badge-success">Active</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning py-2 text-center small">No biometrics registered yet for this citizen.</div>
            <?php endif; ?>
        <?php else: ?>
            <hr>
            <div class="alert alert-secondary text-center small mb-0">
                No booth target armed. Select an existing voter (1A) or onboard a walk-in citizen (1B) to begin.
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
const b64url = {
    encode(buf) {
        const bytes = new Uint8Array(buf);
        let s = '';
        for (const b of bytes) s += String.fromCharCode(b);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    },
    decode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const bin = atob(str);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
    }
};

async function api(action, extra = {}) {
    const res = await fetch('../webauthn_options.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra })
    });
    let data;
    try { data = await res.json(); } catch (e) { throw new Error('Server error (' + res.status + ').'); }
    if (!res.ok || data.success === false) throw new Error(data.message || ('Server error ' + res.status));
    return data;
}

function show(msg, kind) {
    const box = document.getElementById('statusBox');
    box.className = 'alert alert-' + (kind || 'info') + ' py-2 text-center d-block';
    box.innerText = msg;
}

function deviceLabel() {
    const ua = navigator.userAgent;
    let os = 'device';
    if (/Windows/.test(ua)) os = 'Windows';
    else if (/Mac OS X/.test(ua)) os = 'macOS';
    else if (/Android/.test(ua)) os = 'Android';
    else if (/iPhone|iPad/.test(ua)) os = 'iOS';
    else if (/Linux/.test(ua)) os = 'Linux';
    let br = 'browser';
    if (/Edg\//.test(ua)) br = 'Edge';
    else if (/Chrome\//.test(ua)) br = 'Chrome';
    else if (/Firefox\//.test(ua)) br = 'Firefox';
    else if (/Safari\//.test(ua)) br = 'Safari';
    return 'Booth: ' + os + ' · ' + br;
}

const enrollBtn = document.getElementById('enrollBtn');
if (enrollBtn) {
    enrollBtn.addEventListener('click', async () => {
        enrollBtn.disabled = true;

        if (!window.isSecureContext || !window.PublicKeyCredential) {
            show('Biometrics require a secure context — open the admin panel over HTTPS (e.g. the Cloudflare tunnel URL) or http://localhost.', 'danger');
            enrollBtn.disabled = false;
            return;
        }

        try {
            show('Preparing enrollment for the selected citizen…', 'info');
            const { options } = await api('register_begin');

            const publicKey = {
                challenge: b64url.decode(options.challenge),
                rp: options.rp,
                user: { ...options.user, id: b64url.decode(options.user.id) },
                pubKeyCredParams: options.pubKeyCredParams,
                timeout: options.timeout,
                attestation: options.attestation,
                authenticatorSelection: options.authenticatorSelection
            };
            if (options.excludeCredentials.length) {
                publicKey.excludeCredentials = options.excludeCredentials.map(c => ({ type: c.type, id: b64url.decode(c.id) }));
            }

            show('Ask the citizen to scan now — fingerprint, face unlock, or "Use another device" for a phone/security key.', 'primary');
            const credential = await navigator.credentials.create({ publicKey });

            show('Verifying with the server…', 'info');
            const res = await api('register_finish', {
                credential: {
                    id: credential.id,
                    rawId: b64url.encode(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: b64url.encode(credential.response.clientDataJSON),
                        attestationObject: b64url.encode(credential.response.attestationObject)
                    }
                },
                label: deviceLabel()
            });

            show('✅ Biometric enrolled successfully for ' + (res.enrolled_name || 'the voter') + '! Booth disarmed.', 'success');
            setTimeout(() => { window.location.reload(); }, 1500);
        } catch (err) {
            show('Enrollment cancelled or failed: ' + err.message, 'danger');
            enrollBtn.disabled = false;
        }
    });
}

// Suggest a strong-ish temp password for walk-ins (admin can still edit it)
(function () {
    const pwInput = document.querySelector('input[name="booth_password"]');
    const form = document.getElementById('onboardForm');
    if (pwInput && form) {
        form.addEventListener('submit', () => {
            if (!pwInput.value) {
                pwInput.value = 'Vote@' + Math.floor(1000 + Math.random() * 9000);
            }
        });
    }
})();
</script>
</body>
</html>
