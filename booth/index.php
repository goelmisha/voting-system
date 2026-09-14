<?php
/**
 * booth/index.php — Booth Companion PWA (kiosk home).
 * Pairs once via a one-time code from admin/verify_pair.php, then acts
 * as the touch-first enrollment station home screen.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('BOOTHSTATION');
    session_start();
}
require_once __DIR__ . '/../db.php';

$paired = false;
$station_name = '';
if (!empty($_SESSION['booth_station_id'])) {
    $stmt = $pdo->prepare("SELECT name, mode FROM booth_stations WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION['booth_station_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paired = true;
        $station_name = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4b0082">
    <link rel="manifest" href="manifest.webmanifest">
    <title>Booth Station — Online Voting System</title>
    <style>
        :root { --primary: #4b0082; --accent: #7b1fa2; }
        * { -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0; background: #f4f1fa; font-family: Arial, sans-serif;
            display: flex; flex-direction: column; min-height: 100vh; user-select: none;
        }
        .topbar {
            background: var(--primary); color: #fff; padding: 14px 18px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .topbar h4 { margin: 0; font-size: 1rem; }
        .wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card {
            background: #fff; border-radius: 16px; padding: 32px 26px; width: 100%;
            max-width: 420px; box-shadow: 0 8px 24px rgba(0,0,0,.08); text-align: center;
        }
        .big-icon { font-size: 52px; }
        .btn {
            display: block; width: 100%; border: none; border-radius: 12px;
            padding: 18px; font-size: 1.15rem; font-weight: bold; margin-top: 14px;
            background: var(--primary); color: #fff; cursor: pointer;
        }
        .btn:active { background: var(--accent); }
        .btn.secondary { background: #eee; color: #444; }
        input.code {
            width: 100%; box-sizing: border-box; font-size: 1.6rem; text-align: center;
            letter-spacing: .35em; text-transform: uppercase; padding: 14px;
            border: 2px solid #ddd; border-radius: 12px; margin-top: 16px;
        }
        .status { min-height: 24px; font-size: .95rem; margin-top: 12px; }
        .ok { color: #1c7c3c; } .bad { color: #c62828; }
        .offline-banner {
            display: none; background: #c62828; color: #fff; text-align: center;
            padding: 8px; font-weight: bold; font-size: .9rem;
        }
        .tile {
            background: #fff; border-radius: 16px; padding: 26px 18px; text-align: center;
            box-shadow: 0 6px 18px rgba(0,0,0,.07); border-top: 5px solid var(--primary);
            display: block; text-decoration: none; color: #222; margin-bottom: 16px;
        }
        .tile .emoji { font-size: 44px; }
        .tile h3 { margin: 8px 0 4px; }
        .tile p { margin: 0; color: #777; font-size: .85rem; }
        .tiles { max-width: 420px; margin: 0 auto; width: 100%; }
    </style>
</head>
<body>

<div class="offline-banner" id="offlineBanner">⚠️ OFFLINE — booth actions unavailable</div>

<div class="topbar">
    <h4>🗳️ Booth Station</h4>
    <?php if ($paired): ?><small><?= htmlspecialchars($station_name); ?></small><?php endif; ?>
</div>

<div class="wrap">
<?php if (!$paired): ?>
    <!-- First-run pairing -->
    <div class="card">
        <div class="big-icon">📱</div>
        <h3>Pair this phone</h3>
        <p class="text-muted" style="font-size:.9rem">
            Ask the election admin for the one-time code shown in
            <strong>Admin → Pair Booth Phone</strong>, then enter it below.
        </p>
        <form id="pairForm">
            <input class="code" id="pairCode" maxlength="8" autocomplete="off"
                   placeholder="••••••••" inputmode="latin" required>
            <button class="btn" type="submit">Pair This Phone</button>
        </form>
        <div class="status" id="pairStatus"></div>
    </div>
<?php else: ?>
    <!-- Station home -->
    <div class="tiles">
        <a class="tile" href="enroll.php">
            <div class="emoji">🖐️</div>
            <h3>Enroll Citizen</h3>
            <p>Search / onboard a citizen and scan their fingerprint</p>
        </a>
        <a class="tile" href="#" onclick="alert('Voting kiosk mode ships in Phase 3');return false;">
            <div class="emoji">🗳️</div>
            <h3>Voting Kiosk</h3>
            <p>Election-day mode — coming online with Phase 3</p>
        </a>
        <button class="btn secondary" id="unpairBtn">Unpair this station</button>
    </div>
<?php endif; ?>
</div>

<script>
const API = 'api.php';

async function api(action, extra = {}) {
    let res;
    try {
        res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...extra })
        });
    } catch (e) {
        throw new Error('Network error — check the phone\'s internet connection.');
    }
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch (e) {
        // Server replied with HTML/warnings — surface the real content.
        const snippet = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
        throw new Error('Bad server response (' + res.status + '): ' + (snippet || 'empty body'));
    }
    if (!res.ok || data.success === false) {
        throw new Error(data.message || ('Server error ' + res.status));
    }
    return data;
}

const banner = document.getElementById('offlineBanner');
function net() { banner.style.display = navigator.onLine ? 'none' : 'block'; }
window.addEventListener('online', net);
window.addEventListener('offline', net);
net();

<?php if (!$paired): ?>
document.getElementById('pairForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const status = document.getElementById('pairStatus');
    status.className = 'status';
    status.textContent = 'Pairing…';
    try {
        const code = document.getElementById('pairCode').value.trim();
        const r = await api('pair_begin', { code });
        status.className = 'status ok';
        status.textContent = '✅ Paired as ' + r.name + ' — loading…';
        setTimeout(() => window.location.reload(), 800);
    } catch (err) {
        status.className = 'status bad';
        status.textContent = err.message;
    }
});
<?php else: ?>
// Keepalive heartbeat + offline visibility
async function ping() {
    try { await api('ping'); net(); } catch (e) { banner.style.display = 'block'; }
}
setInterval(ping, 60000);

document.getElementById('unpairBtn').addEventListener('click', async () => {
    if (!confirm('Unpair this phone from the election system?')) return;
    try { await api('logout_station'); } catch (e) {}
    window.location.reload();
});
<?php endif; ?>

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
}
</script>
</body>
</html>
