<?php
/**
 * kiosk/_kiosk.php — shared phone-kiosk bootstrap.
 * ---------------------------------------------------------------
 * A booth kiosk is a scoped, locked-down session: it can only enroll
 * and verify citizens at ITS booth. It never gets admin powers, so the
 * existing admin/* guards already keep it out of the console.
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!defined('KIOSK_IDLE_SECONDS'))    define('KIOSK_IDLE_SECONDS', 300);   // auto-lock after 5 min idle
if (!defined('KIOSK_MAX_ATTEMPTS'))    define('KIOSK_MAX_ATTEMPTS', 5);     // unlock failures allowed...
if (!defined('KIOSK_ATTEMPT_WINDOW'))  define('KIOSK_ATTEMPT_WINDOW', 900); // ...within 15 minutes per IP
if (!defined('KIOSK_BALLOT_WINDOW'))   define('KIOSK_BALLOT_WINDOW', 120);  // vote within 2 min of fingerprint verify

/** Session keys that belong to a kiosk unlock. */
function kiosk_session_keys()
{
    return [
        'kiosk_booth_id', 'kiosk_booth_code', 'kiosk_booth_name',
        'kiosk_started', 'kiosk_last_seen',
        'kiosk_enroll_vid', 'kiosk_enroll_name',
        'kiosk_verify_vid', 'kiosk_verify_name',
        'booth_verified_vid', 'booth_verified_name', 'booth_verified_at',
    ];
}

/** Wipe all kiosk state (used on logout, timeout, and booth change). */
function kiosk_clear_session()
{
    foreach (kiosk_session_keys() as $k) {
        unset($_SESSION[$k]);
    }
    unset($_SESSION['wa_challenge']);
}

function kiosk_is_unlocked()
{
    return !empty($_SESSION['kiosk_booth_id']);
}

/** The unlocked booth as ['id','code','name'], or null. */
function kiosk_booth()
{
    if (!kiosk_is_unlocked()) {
        return null;
    }
    return [
        'id'   => (int)$_SESSION['kiosk_booth_id'],
        'code' => (string)($_SESSION['kiosk_booth_code'] ?? ''),
        'name' => (string)($_SESSION['kiosk_booth_name'] ?? ''),
    ];
}

function kiosk_touch()
{
    $_SESSION['kiosk_last_seen'] = time();
}

/**
 * Guard for kiosk pages: require an unlocked session and enforce the idle
 * timeout. Redirects to the kiosk login when either fails.
 */
function kiosk_require_unlocked()
{
    if (!kiosk_is_unlocked()) {
        header('Location: login.php');
        exit();
    }
    $last = (int)($_SESSION['kiosk_last_seen'] ?? 0);
    if ($last > 0 && (time() - $last) > KIOSK_IDLE_SECONDS) {
        kiosk_clear_session();
        header('Location: login.php?timeout=1');
        exit();
    }
    kiosk_touch();
}

function kiosk_client_ip()
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Count recent failed unlocks from this IP. */
function kiosk_recent_failures(PDO $pdo)
{
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM kiosk_auth_attempts
             WHERE ip_address = ? AND success = 0 AND created_at > datetime('now', ?)"
        );
        $stmt->execute([kiosk_client_ip(), '-' . (int)KIOSK_ATTEMPT_WINDOW . ' seconds']);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Record one unlock attempt (never throws). */
function kiosk_log_attempt(PDO $pdo, $booth_code, $success)
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO kiosk_auth_attempts (booth_code, ip_address, success) VALUES (?, ?, ?)"
        );
        $stmt->execute([$booth_code, kiosk_client_ip(), $success ? 1 : 0]);
    } catch (PDOException $e) {
        // Non-fatal.
    }
}

/* ---------------- ballot authorization ---------------- */

/**
 * The voter who just passed fingerprint verification at this booth, if that
 * verification is still inside the short ballot window. Returns
 * ['voter_id','verified_at','expires_in'] or null.
 *
 * This is the ONLY thing that authorizes a kiosk-ballot; it is set by
 * verify_finish and cleared the moment a vote is cast or the window lapses.
 */
function kiosk_verified_voter()
{
    $vid = !empty($_SESSION['booth_verified_vid']) ? (int)$_SESSION['booth_verified_vid'] : 0;
    $at  = !empty($_SESSION['booth_verified_at']) ? (int)$_SESSION['booth_verified_at'] : 0;
    if ($vid <= 0 || $at <= 0) {
        return null;
    }
    $age = time() - $at;
    if ($age < 0 || $age > KIOSK_BALLOT_WINDOW) {
        return null;
    }
    return [
        'voter_id'   => $vid,
        'verified_at'=> $at,
        'expires_in' => KIOSK_BALLOT_WINDOW - $age,
    ];
}

/** Drop any pending ballot authorization. */
function kiosk_clear_ballot_auth()
{
    unset(
        $_SESSION['booth_verified_vid'],
        $_SESSION['booth_verified_name'],
        $_SESSION['booth_verified_at']
    );
}

/* ---------------- CSRF ---------------- */

/** Per-session CSRF token for kiosk state-changing POSTs (e.g. casting a vote). */
function kiosk_csrf_token()
{
    if (empty($_SESSION['kiosk_csrf'])) {
        $_SESSION['kiosk_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['kiosk_csrf'];
}

function kiosk_csrf_check($token)
{
    return !empty($_SESSION['kiosk_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['kiosk_csrf'], $token);
}

/* ---------------- ballot eligibility ---------------- */

/**
 * Decide whether a citizen may cast a ballot at this booth.
 *
 * Rules (beyond the fingerprint verification itself):
 *   - account approved
 *   - has not already voted
 *   - HAS a constituency assigned
 *   - that constituency matches the booth's constituency
 *
 * Returns ['ok'=>bool, 'reason'=>string, 'voter'=>array|null,
 *          'constituency'=>string, 'booth_constituency'=>string].
 */
function kiosk_ballot_eligibility(PDO $pdo, int $voter_id, ?int $booth_id)
{
    $stmt = $pdo->prepare(
        "SELECT id, fullname, status, has_voted, voting, constituency FROM voters WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$voter_id]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voter) {
        return ['ok' => false, 'reason' => 'Citizen record not found.', 'voter' => null,
                'constituency' => '', 'booth_constituency' => ''];
    }

    $status    = strtolower(trim($voter['status'] ?? 'pending'));
    $has_voted = ((int)($voter['has_voted'] ?? 0) === 1)
              || (strtolower(trim($voter['voting'] ?? '')) === 'yes');
    $vconst    = trim((string)($voter['constituency'] ?? ''));

    $bconst = '';
    if ($booth_id !== null) {
        $b = $pdo->prepare("SELECT constituency FROM booths WHERE id = ? LIMIT 1");
        $b->execute([$booth_id]);
        $bconst = trim((string)($b->fetchColumn() ?: ''));
    }

    $reason = '';
    if ($status !== 'approved') {
        $reason = "This citizen's account is not approved to vote.";
    } elseif ($has_voted) {
        $reason = 'This citizen has already voted.';
    } elseif ($vconst === '') {
        $reason = 'No constituency is assigned to this citizen.';
    } elseif ($bconst === '') {
        $reason = 'This booth has no constituency configured.';
    } elseif (strcasecmp($vconst, $bconst) !== 0) {
        $reason = "This citizen's constituency (" . $vconst . ') does not match this booth (' . $bconst . ').';
    }

    return [
        'ok'                 => $reason === '',
        'reason'             => $reason,
        'voter'              => $voter,
        'constituency'       => $vconst,
        'booth_constituency' => $bconst,
        'has_voted'          => $has_voted,
    ];
}

/** Candidates standing in a constituency, in ballot order. */
function kiosk_candidates(PDO $pdo, string $constituency): array
{
    if (trim($constituency) === '') {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT id, name, party, photo, votes_count FROM candidates
         WHERE constituency = ? ORDER BY id ASC"
    );
    $stmt->execute([$constituency]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
