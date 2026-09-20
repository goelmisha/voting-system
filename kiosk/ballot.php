<?php
/**
 * kiosk/ballot.php
 * ---------------------------------------------------------------
 * The ballot for the citizen who JUST passed fingerprint verification
 * at this booth. Only reachable while that verification is still inside
 * the short ballot window (KIOSK_BALLOT_WINDOW), and only if the
 * citizen is eligible at THIS booth (approved, not yet voted, and their
 * constituency matches the booth's).
 *
 * The citizen picks on this device and submits to kiosk/vote.php.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/_kiosk.php';
kiosk_require_unlocked();

$booth = kiosk_booth();

// Must have a live fingerprint verification.
$auth = kiosk_verified_voter();
if ($auth === null) {
    header('Location: index.php?expired=1');
    exit();
}

$voter_id = (int)$auth['voter_id'];
$el       = kiosk_ballot_eligibility($pdo, $voter_id, $booth['id']);
$voter    = $el['voter'];
$name     = $voter['fullname'] ?? ('Voter #' . $voter_id);

$candidates = $el['ok'] ? kiosk_candidates($pdo, $el['constituency']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>Ballot — <?= htmlspecialchars($booth['name']); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; padding-bottom: 40px; }
        .header {
            background-color: var(--primary-color); color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 20;
        }
        .booth-chip {
            background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35);
            border-radius: 20px; padding: 4px 14px; font-size: .85rem; font-weight: bold;
        }
        .ballot-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 12px;
            padding: 22px; margin-top: 22px; box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            border-top: 5px solid #1f7a3f;
        }
        .candidate {
            display: flex; align-items: center; gap: 14px;
            border: 2px solid #e6e6ef; border-radius: 12px; padding: 14px; margin-bottom: 10px;
            cursor: pointer; transition: border-color .15s, background .15s;
        }
        .candidate:hover { border-color: #c9b8dc; background: #faf7ff; }
        .candidate input { width: 22px; height: 22px; flex: 0 0 auto; }
        .candidate.selected { border-color: var(--primary-color); background: #f3e8ff; }
        .cand-name { font-weight: bold; font-size: 1.05rem; }
        .cand-party { color: #555; font-size: .9rem; }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
        .btn-custom:disabled { background-color: #c9b8dc; cursor: not-allowed; }
        .voter-chip {
            background: #f3e8ff; border: 1px solid #d1c4e9; border-radius: 20px;
            padding: 6px 16px; font-weight: bold; color: var(--primary-color); display: inline-block;
        }
        .timer-pill { background: #fff3cd; border: 1px solid #ffe08a; border-radius: 20px; padding: 4px 12px; font-size: .85rem; font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <div class="font-weight-bold">🗳️ Ballot</div>
        <span class="booth-chip">📍 <?= htmlspecialchars($booth['name']); ?> · <?= htmlspecialchars($booth['code']); ?></span>
    </div>
    <a href="index.php?mode=clear" class="btn btn-light btn-sm font-weight-bold">Cancel</a>
</div>

<div class="container" style="max-width: 720px;">

    <div class="text-center mt-3">
        <div class="voter-chip mb-2"><?= htmlspecialchars($name); ?></div>
        <div class="timer-pill" id="timer" data-remaining="<?= (int)$auth['expires_in']; ?>">
            Verification valid for <?= (int)$auth['expires_in']; ?>s
        </div>
    </div>

    <?php if (!$el['ok']): ?>

        <div class="ballot-card">
            <div class="alert alert-warning mb-0 text-center">
                <strong>No ballot available at this booth.</strong><br>
                <span class="small"><?= htmlspecialchars($el['reason']); ?></span>
            </div>
            <a href="index.php?mode=clear" class="btn btn-outline-secondary w-100 mt-3">Back to search</a>
        </div>

    <?php elseif (empty($candidates)): ?>

        <div class="ballot-card">
            <div class="alert alert-info mb-0 text-center small">
                No candidates are configured for the constituency
                <strong><?= htmlspecialchars($el['constituency']); ?></strong>.
                Ask election staff to seed candidates for this constituency.
            </div>
            <a href="index.php?mode=clear" class="btn btn-outline-secondary w-100 mt-3">Back to search</a>
        </div>

    <?php else: ?>

        <div class="ballot-card">
            <h5 class="font-weight-bold mb-1">Parliamentary Constituency</h5>
            <p class="text-muted small mb-3"><?= htmlspecialchars($el['constituency']); ?></p>

            <form method="POST" action="vote.php" id="ballotForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(kiosk_csrf_token()); ?>">

                <?php foreach ($candidates as $c): ?>
                    <label class="candidate" data-candidate>
                        <input type="radio" name="candidate_id" value="<?= (int)$c['id']; ?>" required>
                        <div>
                            <div class="cand-name"><?= htmlspecialchars($c['name']); ?></div>
                            <div class="cand-party"><?= htmlspecialchars($c['party']); ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>

                <button type="submit" id="submitBtn" class="btn btn-custom w-100 py-3 mt-3" disabled>
                    ✅ Confirm &amp; Cast Vote
                </button>
                <p class="text-center text-muted small mt-2 mb-0">
                    Your choice is secret. Records show only that you voted, never who for.
                </p>
            </form>
        </div>

    <?php endif; ?>
</div>

<script>
// Highlight the chosen candidate and enable submission.
document.querySelectorAll('[data-candidate]').forEach(function (label) {
    label.addEventListener('click', function () {
        document.querySelectorAll('[data-candidate]').forEach(l => l.classList.remove('selected'));
        label.classList.add('selected');
        const btn = document.getElementById('submitBtn');
        if (btn) btn.disabled = false;
    });
});

const form = document.getElementById('ballotForm');
if (form) {
    form.addEventListener('submit', function (e) {
        const chosen = document.querySelector('input[name="candidate_id"]:checked');
        if (!chosen) {
            e.preventDefault();
            return;
        }
        const name = chosen.closest('[data-candidate]').querySelector('.cand-name').innerText;
        if (!window.confirm('Cast your vote for ' + name + '?\n\nThis cannot be changed or undone.')) {
            e.preventDefault();
        }
    });
}

// Ballot authorization countdown — verification expires quickly.
(function () {
    const el = document.getElementById('timer');
    if (!el) return;
    let left = parseInt(el.dataset.remaining, 10) || 0;
    const tick = setInterval(function () {
        left -= 1;
        el.innerText = left > 0 ? ('Verification valid for ' + left + 's') : 'Verification expired';
        if (left <= 0) {
            clearInterval(tick);
            window.location.href = 'index.php?expired=1';
        }
    }, 1000);
})();
</script>
</body>
</html>
