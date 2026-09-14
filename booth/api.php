<?php
/**
 * booth/api.php
 * ---------------------------------------------------------------
 * JSON API for the Booth Companion PWA (dedicated station phone).
 *
 * Stations pair once with a one-time code (generated from
 * admin/verify_pair.php) and then call these endpoints with a
 * long-lived station session — so the phone never gets logged out
 * mid-election-day by PHP session expiry on the admin side.
 *
 * Enrollment itself reuses the battle-tested server pieces:
 *   - wa_store_challenge / wa_verify_registration (includes/webauthn.php)
 *   - the admin_enroll_vid arming contract (admin/enroll_voter.php)
 *
 * Actions:
 *   pair_begin                  {code}                  -> pair this phone
 *   ping                        -                       -> keepalive + state
 *   booth_state                 -                       -> mode + armed target
 *   logout_station              -                       -> forget station token
 *   search_voter                {epic | q}              -> find citizens
 *   onboard                     {fullname,email,...}    -> walk-in onboarding + arm
 *   arm_voter                   {vid}                   -> arm booth for a voter
 *   disarm                      -                       -> clear armed target
 *   register_begin              -                       -> WebAuthn create options
 *   register_finish             {credential,label}      -> verify + store passkey
 * ---------------------------------------------------------------
 */

/* ---- JSON-only output guard ---------------------------------------------
 * PHP notices/warnings/fatals used to be able to leak into the response body,
 * which the phone sees as "Server error (200)" because JSON.parse fails.
 * Now: display_errors off, every stray byte captured in a buffer, and a
 * shutdown handler converts ANY un-rendered/fatal state into clean JSON.
 */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_name('BOOTHSTATION');
    session_start();
}

ob_start(); // capture anything that tries to print before the JSON goes out

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/webauthn.php';

header('Content-Type: application/json; charset=utf-8');

function booth_json(array $data, int $status = 200): void
{
    // Discard every buffered byte (notices, warnings, BOMs) so the body is pure JSON.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

register_shutdown_function(function () {
    $err = error_get_last();
    $fatal = $err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    $neverRendered = ob_get_level() > 0; // flow ended without reaching booth_json()
    if (!$fatal && !$neverRendered) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
    }
    $detail = $fatal
        ? ($err['message'] . ' in ' . basename($err['file']) . ':' . $err['line'])
        : 'no handler produced a response (empty body)';
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $detail,
        'code'    => 'PHP_FATAL',
    ]);
});

function booth_err(string $message, int $status = 400, string $code = ''): void
{
    booth_json(['success' => false, 'message' => $message, 'code' => $code], $status);
}

/** Auto-disarm the armed enrollment target once the scan window expires. */
function booth_auto_disarm(): void
{
    if (!empty($_SESSION['admin_enroll_vid']) && !empty($_SESSION['admin_enroll_expires'])
        && time() > (int)$_SESSION['admin_enroll_expires']) {
        booth_disarm();
    }
}

function booth_disarm(): void
{
    unset($_SESSION['admin_enroll_vid'], $_SESSION['admin_enroll_name'], $_SESSION['admin_enroll_expires']);
}

/** Current paired station row, or null. */
function booth_station($pdo): ?array
{
    if (empty($_SESSION['booth_station_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM booth_stations WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION['booth_station_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        // Station was revoked server-side — kill the local session.
        booth_disarm();
        unset($_SESSION['booth_station_id'], $_SESSION['booth_station_name']);
        return null;
    }
    $upd = $pdo->prepare("UPDATE booth_stations SET last_seen_at = datetime('now') WHERE id = ?");
    $upd->execute([(int)$row['id']]);
    return $row;
}

function booth_require_station($pdo): array
{
    booth_auto_disarm();
    $station = booth_station($pdo);
    if (!$station) {
        booth_err('Station not paired or revoked. Pair this phone again.', 401, 'NOT_PAIRED');
    }
    return $station;
}

/** Armed-citizen payload for the kiosk UI (null when nothing is armed). */
function booth_armed_payload($pdo): ?array
{
    if (empty($_SESSION['admin_enroll_vid'])) {
        return null;
    }
    $vid = (int)$_SESSION['admin_enroll_vid'];
    $stmt = $pdo->prepare("SELECT id, fullname, email, voter_id_number, status FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voter) {
        booth_disarm();
        return null;
    }
    return [
        'vid'        => $vid,
        'name'       => $voter['fullname'],
        'email'      => $voter['email'],
        'epic'       => $voter['voter_id_number'],
        'status'     => $voter['status'],
        'expires_in' => max(0, (int)($_SESSION['admin_enroll_expires'] ?? 0) - time()),
    ];
}

/** Passkey credential IDs already enrolled for a voter (for excludeCredentials). */
function booth_existing_creds($pdo, int $voter_id): array
{
    $stmt = $pdo->prepare("SELECT credential_id FROM passkeys WHERE voter_id = ?");
    $stmt->execute([$voter_id]);
    return array_map(fn($r) => $r['credential_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ---------------- dispatch ---------------- */

/* ---- Diagnostics endpoint: open /booth/api.php?health=1 in the phone
 * browser to inspect the server environment without the app. ------------- */
if (isset($_GET['health'])) {
    $db_ok = false;
    $booth_table = false;
    try {
        $pdo->query("SELECT 1");
        $db_ok = true;
        $booth_table = (bool)$pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='booth_stations'"
        )->fetchColumn();
    } catch (Throwable $t) {
    }
    booth_json([
        'ok'             => $db_ok && $booth_table,
        'php'            => PHP_VERSION,
        'rp_id'          => wa_rp_id(),
        'host'           => wa_host_header(),
        'https'          => wa_is_https(),
        'secure_context' => wa_secure_context_ok(),
        'db_ok'          => $db_ok,
        'booth_table'    => $booth_table,
    ]);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    $body = [];
}
$action = trim((string)($body['action'] ?? ''));

try {
switch ($action) {

    /* ---------------- Pairing (no station required) ---------------- */

    case 'pair_begin': {
        $code = strtoupper(preg_replace('/\s+/', '', (string)($body['code'] ?? '')));
        if ($code === '') {
            booth_err('Enter the pairing code shown in the admin console.');
        }
        $stmt = $pdo->prepare("SELECT * FROM booth_stations WHERE pairing_active = 1 AND active = 1");
        $stmt->execute();
        $matched = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!empty($row['pairing_token_hash']) && hash_equals($row['pairing_token_hash'], hash('sha256', $code))) {
                $matched = $row;
                break;
            }
        }
        if (!$matched) {
            booth_err('Invalid or expired pairing code. Generate a new one from the admin console.', 403, 'BAD_CODE');
        }
        // Issue the long-lived station token (session-backed; token hash stored).
        $token = bin2hex(random_bytes(32));
        $upd = $pdo->prepare(
            "UPDATE booth_stations
             SET token_hash = ?, pairing_token_hash = '', pairing_active = 0, last_seen_at = datetime('now')
             WHERE id = ?"
        );
        $upd->execute([password_hash($token, PASSWORD_DEFAULT), (int)$matched['id']]);

        session_regenerate_id(true);
        $_SESSION['booth_station_id']   = (int)$matched['id'];
        $_SESSION['booth_station_name'] = $matched['name'];

        booth_json(['success' => true, 'station_id' => (int)$matched['id'], 'name' => $matched['name']]);
    }

    case 'ping': {
        $station = booth_require_station($pdo);
        booth_json([
            'success' => true,
            'mode'    => $station['mode'] ?: 'enroll',
            'name'    => $station['name'],
            'armed'   => booth_armed_payload($pdo),
        ]);
    }

    case 'booth_state': {
        $station = booth_require_station($pdo);
        booth_json([
            'success' => true,
            'mode'    => $station['mode'] ?: 'enroll',
            'name'    => $station['name'],
            'armed'   => booth_armed_payload($pdo),
        ]);
    }

    case 'logout_station': {
        $station = booth_station($pdo);
        if ($station) {
            $upd = $pdo->prepare("UPDATE booth_stations SET token_hash = '' WHERE id = ?");
            $upd->execute([(int)$station['id']]);
        }
        booth_disarm();
        $_SESSION = [];
        session_destroy();
        booth_json(['success' => true]);
    }

    /* ---------------- Voter discovery + onboarding ---------------- */

    case 'search_voter': {
        booth_require_station($pdo);
        $q = trim((string)($body['epic'] ?? $body['q'] ?? ''));
        if ($q === '') {
            booth_err('Type an EPIC number or name to search.');
        }
        $like = '%' . strtoupper($q) . '%';
        $stmt = $pdo->prepare(
            "SELECT id, fullname, email, voter_id_number, mobile, status,
                    (SELECT COUNT(*) FROM passkeys p WHERE p.voter_id = voters.id) AS passkey_count
             FROM voters
             WHERE UPPER(voter_id_number) LIKE ? OR UPPER(fullname) LIKE ?
             ORDER BY fullname ASC LIMIT 8"
        );
        $stmt->execute([$like, $like]);
        booth_json(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    case 'onboard': {
        booth_require_station($pdo);
        $fullname = trim((string)($body['fullname'] ?? ''));
        $email    = strtolower(trim((string)($body['email'] ?? '')));
        $epic     = strtoupper(trim((string)($body['voter_id_number'] ?? '')));
        $mobile   = trim((string)($body['mobile'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $status   = (($body['status'] ?? 'approved') === 'pending') ? 'pending' : 'approved';

        if ($fullname === '' || $email === '' || $epic === '') {
            booth_err('Full name, Email and Voter ID (EPIC) are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            booth_err('Please enter a valid email address.');
        } elseif (strlen($password) < 6) {
            booth_err('Temporary password must be at least 6 characters.');
        } else {
            $dup = $pdo->prepare("SELECT id, email, voter_id_number FROM voters WHERE LOWER(email) = ? OR UPPER(voter_id_number) = ? LIMIT 1");
            $dup->execute([$email, $epic]);
            $existing = $dup->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                booth_err('Already exists: ' . ($existing['email'] === $email ? 'email' : 'EPIC') . ' (#' . (int)$existing['id'] . '). Search and select them instead.', 409, 'DUPLICATE');
            }
            $insert = $pdo->prepare(
                "INSERT INTO voters (fullname, email, voter_id_number, mobile, address, password, photo, document_proof, status, has_voted)
                 VALUES (?, ?, ?, ?, '', ?, 'default.png', '', ?, 0)"
            );
            $insert->execute([
                $fullname, $email, $epic,
                $mobile !== '' ? $mobile : null,
                password_hash($password, PASSWORD_DEFAULT),
                $status,
            ]);
            $vid = (int)$pdo->lastInsertId();

            // Arm straight away so the scan step follows on the same screen.
            $_SESSION['admin_enroll_vid']     = $vid;
            $_SESSION['admin_enroll_name']    = $fullname;
            $_SESSION['admin_enroll_expires'] = time() + 120;

            booth_json(['success' => true, 'voter_id' => $vid, 'armed' => booth_armed_payload($pdo)]);
        }
    }

    case 'arm_voter': {
        booth_require_station($pdo);
        $vid = (int)($body['vid'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, fullname, email, voter_id_number, status FROM voters WHERE id = ? LIMIT 1");
        $stmt->execute([$vid]);
        $voter = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voter) {
            booth_err('Voter record not found.', 404, 'NOT_FOUND');
        }
        $_SESSION['admin_enroll_vid']     = (int)$voter['id'];
        $_SESSION['admin_enroll_name']    = $voter['fullname'];
        $_SESSION['admin_enroll_expires'] = time() + 120;
        booth_json(['success' => true, 'armed' => booth_armed_payload($pdo)]);
    }

    case 'disarm': {
        booth_require_station($pdo);
        booth_disarm();
        booth_json(['success' => true]);
    }

    /* ---------------- WebAuthn enrollment (reuses server core) ---------------- */

    case 'register_begin': {
        $station = booth_require_station($pdo);
        $vid = (int)($_SESSION['admin_enroll_vid'] ?? 0);
        if ($vid <= 0) {
            booth_err('No citizen armed. Select or onboard a citizen first.', 409, 'NOT_ARMED');
        }
        if (!wa_secure_context_ok()) {
            booth_err('WebAuthn needs HTTPS (or localhost). Open the booth URL over https://', 403, 'INSECURE_CONTEXT');
        }
        $stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ? LIMIT 1");
        $stmt->execute([$vid]);
        $voter = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voter) {
            booth_err('Armed citizen no longer exists.', 404, 'NOT_FOUND');
        }
        $challenge = wa_store_challenge('register', $vid);
        booth_json([
            'success' => true,
            'options' => [
                'challenge' => $challenge,
                'rp'        => ['name' => 'Online Voting System', 'id' => wa_rp_id()],
                'user'      => [
                    'id'          => wa_b64url_encode(wa_user_handle($vid)),
                    'name'        => $voter['email'],
                    'displayName' => $voter['fullname'],
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],
                    ['type' => 'public-key', 'alg' => -257],
                ],
                'timeout'     => 120000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'residentKey'      => 'required',
                    'userVerification' => 'required',
                ],
                'excludeCredentials' => array_map(
                    fn($id) => ['type' => 'public-key', 'id' => $id],
                    booth_existing_creds($pdo, $vid)
                ),
            ],
        ]);
    }

    case 'register_finish': {
        $station = booth_require_station($pdo);
        $vid = (int)($_SESSION['admin_enroll_vid'] ?? 0);
        if ($vid <= 0) {
            booth_err('Enrollment window expired. Select the citizen again.', 409, 'NOT_ARMED');
        }
        try {
            $cred = wa_verify_registration($body['credential'] ?? [], $vid);

            $dup = $pdo->prepare("SELECT id FROM passkeys WHERE credential_id = ? LIMIT 1");
            $dup->execute([$cred['credential_id']]);
            if ($dup->fetch()) {
                booth_err('This passkey is already registered.');
            }

            $label = trim((string)($body['label'] ?? ''));
            if ($label === '') {
                $label = 'Booth PWA · ' . ($station['name'] ?? 'Station');
            }
            $insert = $pdo->prepare(
                "INSERT INTO passkeys (voter_id, credential_id, public_key, alg, sign_count, device_label)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([$vid, $cred['credential_id'], $cred['pem'], $cred['alg'], $cred['sign_count'], $label]);

            $name = (string)($_SESSION['admin_enroll_name'] ?? '');
            booth_disarm(); // one scan = one enrollment; never leave the booth armed

            booth_json([
                'success'       => true,
                'enrolled_vid'  => $vid,
                'enrolled_name' => $name,
            ]);
        } catch (Exception $e) {
            booth_err('Enrollment failed: ' . $e->getMessage(), 400, 'VERIFY_FAILED');
        }
    }

    default:
        booth_err('Unknown action: ' . htmlspecialchars($action));
}
} catch (Throwable $e) {
    booth_err('Server error: ' . $e->getMessage(), 500, 'EXCEPTION');
}
