<?php
/**
 * admin/verify_pair.php — Pair Booth Phone (ADMIN ONLY).
 * Generates a one-time pairing code that the booth phone enters once;
 * the phone then holds a long-lived station session.
 */
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

/* ---------------- Actions ---------------- */

if (isset($_GET['gen'])) {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no I, L, O, 0, 1
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $name = trim((string)($_GET['name'] ?? '')) ?: ('Booth ' . date('j M'));
    $ins = $pdo->prepare(
        "INSERT INTO booth_stations (name, pairing_token_hash, pairing_active, pairing_created_at)
         VALUES (?, ?, 1, datetime('now'))"
    );
    $ins->execute([$name, hash('sha256', $code)]);
    $_SESSION['pair_flash'] = ['kind' => 'success', 'msg' => $code, 'name' => $name];
    header("Location: verify_pair.php");
    exit();
}

if (isset($_GET['revoke'])) {
    $sid = (int)$_GET['revoke'];
    $pdo->prepare("UPDATE booth_stations SET active = 0, pairing_active = 0, token_hash = '' WHERE id = ?")
        ->execute([$sid]);
    $_SESSION['pair_flash'] = ['kind' => 'danger', 'msg' => 'Station #' . $sid . ' revoked.', 'name' => ''];
    header("Location: verify_pair.php");
    exit();
}

$flash = $_SESSION['pair_flash'] ?? null;
unset($_SESSION['pair_flash']);

/* ---------------- Data ---------------- */

$stations = $pdo->query(
    "SELECT * FROM booth_stations WHERE active = 1 ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pair Booth Phone - Admin - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; }
        .header {
            background-color: var(--primary-color); color: #fff; height: 9vh;
            display: flex; align-items: center; justify-content: space-between; padding: 0 30px;
        }
        .booth-card {
            background: #fff; border-radius: 12px; padding: 30px; max-width: 760px;
            margin: 40px auto; border: 1px solid #e0e0e0; border-top: 5px solid var(--primary-color);
            box-shadow: 0 6px 20px rgba(0,0,0,.06);
        }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
        .code-box {
            font-size: 2.4rem; letter-spacing: .3em; text-align: center; font-weight: bold;
            background: #f3e8ff; border: 2px dashed var(--primary-color); border-radius: 12px;
            padding: 18px 10px 18px 22px; color: var(--primary-color); user-select: all;
        }
    </style>
</head>
<body>

<div class="container-fluid header">
    <h4 class="m-0 font-weight-bold">📱 Pair Booth Phone — Election Staff Only</h4>
    <a href="dashboard.php" class="btn btn-light btn-sm font-weight-bold">← Admin Dashboard</a>
</div>

<div class="container">
    <div class="booth-card">

        <div class="text-center mb-4">
            <div style="font-size:44px">📱</div>
            <h4 class="font-weight-bold mb-1">Pair a Booth Station Phone</h4>
            <p class="text-muted small mb-0">
                Generate a one-time code, then enter it on the booth phone at
                <code>/booth/</code>. The code works once and never expires the station.
            </p>
        </div>

        <?php if ($flash): ?>
            <?php if ($flash['kind'] === 'success'): ?>
                <div class="alert alert-success py-2 text-center">
                    New pairing code for <strong><?= htmlspecialchars($flash['name']); ?></strong> — enter this on the phone:
                    <div class="code-box mt-2"><?= htmlspecialchars($flash['msg']); ?></div>
                    <small class="text-muted d-block mt-2">Shown only once. Works until used or replaced.</small>
                </div>
            <?php else: ?>
                <div class="alert alert-<?= htmlspecialchars($flash['kind']); ?> py-2 text-center"><?= htmlspecialchars($flash['msg']); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="GET" action="verify_pair.php" class="border rounded p-3 bg-light mb-4">
            <label class="font-weight-bold small">1. Generate a one-time pairing code</label>
            <div class="form-row">
                <div class="col-7">
                    <input type="text" name="name" class="form-control" placeholder="Station name (e.g. Booth 12 — Sector 4)">
                </div>
                <div class="col-5">
                    <button type="submit" name="gen" value="1" class="btn btn-custom btn-block">Generate Code</button>
                </div>
            </div>
        </form>

        <label class="font-weight-bold small">2. Paired &amp; pending stations</label>
        <?php if (empty($stations)): ?>
            <div class="alert alert-secondary text-center small mb-0">No booth stations yet — generate a code above.</div>
        <?php else: ?>
            <?php foreach ($stations as $s): ?>
                <div class="border rounded p-2 px-3 mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>📱 <?= htmlspecialchars($s['name']); ?></strong>
                        <br><small class="text-muted">
                            Created <?= htmlspecialchars(date('j M Y', strtotime($s['created_at']))); ?>
                            · Last seen <?= $s['last_seen_at'] ? htmlspecialchars(date('j M, H:i', strtotime($s['last_seen_at']))) : 'never'; ?>
                        </small>
                    </div>
                    <div class="text-right">
                        <?php if ((int)$s['pairing_active'] === 1): ?>
                            <span class="badge badge-warning px-2 py-1">Awaiting pairing</span>
                        <?php elseif (!empty($s['token_hash'])): ?>
                            <span class="badge badge-success px-2 py-1">Paired</span>
                        <?php else: ?>
                            <span class="badge badge-secondary px-2 py-1">Idle</span>
                        <?php endif; ?>
                        <a href="verify_pair.php?revoke=<?= (int)$s['id']; ?>"
                           class="btn btn-outline-danger btn-sm ml-2"
                           onclick="return confirm('Revoke station <?= htmlspecialchars($s['name']); ?>? The phone must pair again.')">
                            Revoke
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
