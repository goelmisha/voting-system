<?php
/**
 * booth/enroll.php — Enrollment kiosk (paired stations only).
 * Screen 1: find citizen (EPIC keypad search / walk-in onboarding)
 * Screen 2: confirm + scan fingerprint on this phone's sensor
 * Screen 3: result, auto-return
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('BOOTHSTATION');
    session_start();
}
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['booth_station_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4b0082">
    <link rel="manifest" href="manifest.webmanifest">
    <title>Enroll Citizen — Booth Station</title>
    <style>
        :root { --primary: #4b0082; --accent: #7b1fa2; }
        * { -webkit-tap-highlight-color: transparent; box-sizing: border-box; }
        body { margin:0; background:#f4f1fa; font-family:Arial,sans-serif; min-height:100vh; user-select:none; }
        .topbar { background:var(--primary); color:#fff; padding:14px 18px;
                  display:flex; justify-content:space-between; align-items:center; }
        .topbar a { color:#fff; text-decoration:none; font-weight:bold; font-size:.9rem; }
        .wrap { max-width:440px; margin:0 auto; padding:18px; }
        .card { background:#fff; border-radius:16px; padding:24px 20px;
                box-shadow:0 6px 18px rgba(0,0,0,.07); margin-bottom:16px; }
        .screen { display:none; } .screen.active { display:block; }
        .big-icon { font-size:46px; text-align:center; }
        h3 { text-align:center; margin:6px 0 12px; }
        input[type=text], input[type=email], input[type=password] {
            width:100%; font-size:1.05rem; padding:14px; border:2px solid #ddd;
            border-radius:12px; margin-bottom:10px; }
        input.epic { font-size:1.4rem; text-align:center; letter-spacing:.15em; text-transform:uppercase; }
        .btn { display:block; width:100%; border:none; border-radius:12px; padding:16px;
               font-size:1.1rem; font-weight:bold; margin-top:10px; background:var(--primary);
               color:#fff; cursor:pointer; }
        .btn:disabled { background:#c9b8dc; }
        .btn.secondary { background:#eee; color:#444; }
        .btn.green { background:#28a745; }
        .result-row { border:1px solid #e5e0ef; border-radius:12px; padding:14px;
                      margin-top:10px; display:flex; justify-content:space-between;
                      align-items:center; gap:8px; }
        .result-row b { display:block; }
        .result-row small { color:#777; }
        .badge { font-size:.72rem; padding:3px 8px; border-radius:10px; font-weight:bold; }
        .b-approved { background:#d4edda; color:#1c7c3c; }
        .b-pending { background:#fff3cd; color:#8a6d00; }
        .b-rejected { background:#f8d7da; color:#8a1c26; }
        .chip { background:#f3e8ff; border:1px solid #d1c4e9; border-radius:20px;
                padding:10px 16px; font-weight:bold; color:var(--primary);
                text-align:center; margin:10px 0; font-size:1.05rem; }
        .status { min-height:26px; text-align:center; font-size:.95rem; margin-top:10px; }
        .ok { color:#1c7c3c; } .bad { color:#c62828; } .info { color:#555; }
        .linkish { background:none; border:none; color:var(--accent); font-weight:bold;
                   text-decoration:underline; cursor:pointer; font-size:.9rem; }
        .center { text-align:center; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="index.php">← Home</a>
    <h4 style="margin:0;font-size:1rem">🖐️ Enroll Citizen</h4>
    <span style="width:44px"></span>
</div>

<div class="wrap">

    <!-- SCREEN 1: find citizen -->
    <div class="screen active" id="screen-find">
        <div class="card">
            <div class="big-icon">🔎</div>
            <h3>Find Citizen</h3>
            <input type="text" class="epic" id="epicInput" placeholder="EPIC / Name" autocomplete="off">
            <button class="btn" id="searchBtn">Search Voter List</button>
            <div id="searchResults"></div>
            <div class="status" id="searchStatus"></div>
        </div>

        <div class="card">
            <h3 style="margin-top:0">New Walk-in Citizen</h3>
            <input type="text" id="obName" placeholder="Full name (as on ID) *">
            <input type="email" id="obEmail" placeholder="Email (login ID) *">
            <input type="text" id="obEpic" placeholder="Voter ID (EPIC) *" style="text-transform:uppercase">
            <input type="text" id="obMobile" placeholder="Mobile (optional)">
            <input type="password" id="obPass" placeholder="Temp password (min 6) *">
            <button class="btn green" id="onboardBtn">➕ Onboard &amp; Continue to Scan</button>
        </div>
    </div>

    <!-- SCREEN 2: confirm + scan -->
    <div class="screen" id="screen-scan">
        <div class="card center">
            <div class="big-icon">🖐️</div>
            <h3>Confirm &amp; Scan</h3>
            <div class="chip" id="armedChip">—</div>
            <div class="status" id="scanStatus"></div>
            <button class="btn" id="scanBtn" style="font-size:1.25rem">Arm &amp; Start Scan</button>
            <button class="btn secondary" id="cancelScanBtn">Cancel</button>
        </div>
    </div>

    <!-- SCREEN 3: result -->
    <div class="screen" id="screen-done">
        <div class="card center">
            <div class="big-icon" id="doneIcon">✅</div>
            <h3 id="doneTitle">Enrolled!</h3>
            <p id="doneMsg" class="info" style="font-size:.95rem"></p>
            <button class="btn" id="nextBtn">Enroll Next Citizen</button>
        </div>
    </div>

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

const b64url = {
    encode(buf) {
        const bytes = new Uint8Array(buf); let s = '';
        for (const b of bytes) s += String.fromCharCode(b);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    },
    decode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const bin = atob(str);
        return Uint8Array.from(bin, c => c.charCodeAt(0));
    }
};

function showScreen(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    window.scrollTo(0, 0);
}
function setStatus(el, msg, kind) {
    el.className = 'status ' + (kind || 'info');
    el.textContent = msg;
}

/* -------- Screen 1: search -------- */
const searchStatus = document.getElementById('searchStatus');
const resultsBox = document.getElementById('searchResults');

async function search() {
    const q = document.getElementById('epicInput').value.trim();
    if (!q) { setStatus(searchStatus, 'Type an EPIC number or name.', 'bad'); return; }
    resultsBox.innerHTML = '';
    setStatus(searchStatus, 'Searching…', 'info');
    try {
        const r = await api('search_voter', { epic: q });
        setStatus(searchStatus, r.results.length ? 'Tap a citizen to enroll:' : 'No match — use the walk-in form below.', r.results.length ? 'ok' : 'info');
        r.results.forEach(v => {
            const div = document.createElement('div');
            div.className = 'result-row';
            div.innerHTML =
                '<span><b>' + v.fullname.replace(/</g, '&lt;') + '</b>' +
                '<small>EPIC ' + (v.voter_id_number || 'N/A') + ' · ' + v.passkey_count + ' passkey(s)</small></span>' +
                '<span style="text-align:right"><span class="badge b-' + v.status + '">' + v.status + '</span></span>';
            div.addEventListener('click', () => armAndConfirm(v.id, v.fullname, v.voter_id_number));
            resultsBox.appendChild(div);
        });
    } catch (err) { setStatus(searchStatus, err.message, 'bad'); }
}
document.getElementById('searchBtn').addEventListener('click', search);
document.getElementById('epicInput').addEventListener('keydown', e => { if (e.key === 'Enter') search(); });

/* -------- Arm + confirm screen -------- */
const scanStatus = document.getElementById('scanStatus');

async function armAndConfirm(vid, name, epic) {
    setStatus(scanStatus, 'Arming…', 'info');
    try {
        await api('arm_voter', { vid });
        document.getElementById('armedChip').textContent = name + ' · EPIC ' + (epic || 'N/A');
        document.getElementById('scanBtn').disabled = false;
        setStatus(scanStatus, 'Verify the citizen, then start the scan.', 'info');
        showScreen('screen-scan');
    } catch (err) { setStatus(searchStatus, err.message, 'bad'); }
}

document.getElementById('onboardBtn').addEventListener('click', async () => {
    const btn = document.getElementById('onboardBtn');
    btn.disabled = true;
    try {
        const r = await api('onboard', {
            fullname: document.getElementById('obName').value.trim(),
            email: document.getElementById('obEmail').value.trim(),
            voter_id_number: document.getElementById('obEpic').value.trim(),
            mobile: document.getElementById('obMobile').value.trim(),
            password: document.getElementById('obPass').value
        });
        document.getElementById('obName').value = document.getElementById('obEmail').value =
        document.getElementById('obEpic').value = document.getElementById('obMobile').value =
        document.getElementById('obPass').value = '';
        const a = r.armed;
        document.getElementById('armedChip').textContent = a.name + ' · EPIC ' + (a.epic || 'N/A');
        document.getElementById('scanBtn').disabled = false;
        setStatus(scanStatus, 'Citizen onboarded. Verify identity, then scan.', 'ok');
        showScreen('screen-scan');
    } catch (err) {
        alert(err.message);
    } finally { btn.disabled = false; }
});

/* -------- WebAuthn scan -------- */
document.getElementById('scanBtn').addEventListener('click', async () => {
    const btn = document.getElementById('scanBtn');
    btn.disabled = true;

    if (!window.isSecureContext || !window.PublicKeyCredential) {
        setStatus(scanStatus, 'Needs HTTPS — open the booth URL over https://', 'bad');
        btn.disabled = false;
        return;
    }
    try {
        setStatus(scanStatus, 'Preparing enrollment…', 'info');
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
            publicKey.excludeCredentials = options.excludeCredentials.map(c => ({
                type: c.type, id: b64url.decode(c.id)
            }));
        }

        setStatus(scanStatus, 'Ask the citizen to scan now…', 'info');
        const credential = await navigator.credentials.create({ publicKey });

        setStatus(scanStatus, 'Verifying with server…', 'info');
        const res = await api('register_finish', {
            credential: {
                id: credential.id,
                rawId: b64url.encode(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: b64url.encode(credential.response.clientDataJSON),
                    attestationObject: b64url.encode(credential.response.attestationObject)
                }
            }
        });

        document.getElementById('doneIcon').textContent = '✅';
        document.getElementById('doneTitle').textContent = 'Enrolled!';
        document.getElementById('doneMsg').textContent =
            'Fingerprint registered for ' + (res.enrolled_name || 'the citizen') + '. Booth disarmed.';
        showScreen('screen-done');
    } catch (err) {
        setStatus(scanStatus, 'Failed: ' + err.message, 'bad');
        btn.disabled = false;
    }
});

document.getElementById('cancelScanBtn').addEventListener('click', async () => {
    try { await api('disarm'); } catch (e) {}
    showScreen('screen-find');
});

document.getElementById('nextBtn').addEventListener('click', () => {
    document.getElementById('epicInput').value = '';
    resultsBox.innerHTML = '';
    setStatus(searchStatus, '', 'info');
    showScreen('screen-find');
});

// Resume an armed session after a page reload (e.g. orientation change)
(async function resume() {
    try {
        const st = await api('booth_state');
        if (st.armed && st.armed.expires_in > 15) {
            document.getElementById('armedChip').textContent = st.armed.name + ' · EPIC ' + (st.armed.epic || 'N/A');
            setStatus(scanStatus, 'Resumed an armed citizen — scan to finish.', 'info');
            showScreen('screen-scan');
        }
    } catch (e) { /* not armed */ }
})();
</script>
</body>
</html>
