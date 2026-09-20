# Booth Companion App — Plan

A dedicated Android smartphone acts as the **central booth station**: election staff use it to
enroll citizens' fingerprints (passkeys) before polling, and on election day citizens scan on it
to authenticate and vote.

**Decisions made**

| Decision           | Choice                                                                 |
| ------------------ | ---------------------------------------------------------------------- |
| App form           | Installable PWA (fullscreen, add-to-home-screen) — no app store        |
| Fingerprint sensor | The phone's built-in sensor via WebAuthn (no USB scanners)             |
| Passkey home       | Passkeys live on the booth phone; citizens scan there to vote          |

## Why a PWA works here

The existing stack already does everything the app needs:

- `admin/enroll_voter.php` — the arm-booth → scan → disarm flow (the core enrollment UX)
- `webauthn_options.php` — `register_begin/finish`, `login_begin/finish`, `vote_begin/finish`
- `includes/webauthn.php` — tunnel/HTTPS-aware RP ID handling (Cloudflare-ready)

The companion app is a **repackaging, not a rewrite**: a touch-first kiosk UI + installability +
a long-lived "station" session so the phone never dies mid-election-day on a logged-out redirect.

## Architecture

```
┌─────────────────────┐   HTTPS (Cloudflare tunnel → stable domain)   ┌──────────────────┐
│  Booth phone (PWA)  │ ────────────────────────────────────────────▶ │  Existing PHP app │
│  Chrome, fullscreen │   booth/api.php (station-token auth)          │  + SQLite         │
│  platform passkeys  │ ◀──────────────────────────────────────────── │  webauthn_options │
└─────────────────────┘   JSON options / results                      └──────────────────┘
```

- One PHP session per station. The phone authenticates **once** with admin credentials and
  receives a long-lived **station token**; the PWA sends it on every API call.
- Enrollment calls reuse `register_begin`/`register_finish` unchanged: the booth API sets
  `$_SESSION['admin_enroll_vid']` (same arming mechanism as today) so
  `wa_target_voter()` resolves the citizen.

## Critical gotcha — RP ID stability

Passkeys are cryptographically bound to the RP ID (hostname). If the booth enrolls citizens
today on `xyz.trycloudflare.com` and the tunnel URL changes tomorrow, **every passkey breaks**.

→ Pin a stable hostname (own domain behind Cloudflare Tunnel, e.g. `booth.yourdomain.org`)
**before** enrolling anyone. `includes/webauthn.php` already trusts `X-Forwarded-Proto`/CF headers.

## Phases

### Phase 1 — Station pairing + PWA skeleton

Files:

```
booth/
├── manifest.webmanifest    # name, fullscreen display, icons, portrait
├── sw.js                   # cache static shell only; API always network
├── api.php                 # booth JSON endpoints, station-token auth
├── index.php               # kiosk home (mode switch: Enroll / Voting open)
├── enroll.php              # touch-first enrollment console
├── vote_kiosk.php          # election-day flow (Phase 3)
└── js/booth.js             # b64url helpers, fetch wrapper, keepalive ping
```

DB (add to `init_db.php` / idempotent upgrades in `db.php`):

```sql
CREATE TABLE booth_stations (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,             -- e.g. "Booth 12 — Sector 4"
    token_hash    TEXT NOT NULL,             -- password_hash() of the station token
    active        INTEGER DEFAULT 1,
    created_at    TEXT DEFAULT (datetime('now')),
    last_seen_at  TEXT
);
```

Admin side (`admin/dashboard.php`): "Pair a booth station" card → generates a one-time
pairing code, admin enters it on the phone (`booth/?pair=CODE`) → long-lived token stored in
the PWA (IndexedDB), hashed in DB. Revoke/list stations from the same card.

### Phase 2 — Enrollment kiosk (replaces desktop `enroll_voter.php` at the booth)

`booth/enroll.php`, big touch targets, 3 screens:

1. **Find citizen** — numeric EPIC keypad → `booth_search_voter`; or **Onboard walk-in** form
   (same fields/validation as today's POST handler, moved into `api.php` as JSON).
2. **Arm + scan** — shows citizen name/EPIC/photo confirm chip → `register_begin` →
   `navigator.credentials.create()` → the Android prompt targets *this phone's* fingerprint
   → `register_finish` → auto-disarm (existing behavior).
3. **Result** — enrolled ✅ / already-enrolled passkey list, auto-return to screen 1.

Safety rails:

- **Auto-disarm timer**: if no scan within 60s, the booth API clears `admin_enroll_vid`.
- Confirm chip before every scan so a scan is never saved to the wrong citizen.
- Polls closed → enrollment screen hidden (mode flag on the station row or a settings table).

### Phase 3 — Election-day kiosk mode

`booth/vote_kiosk.php`: admin toggles "Polls open" → citizen flow on the phone:

1. Type EPIC (or browse by name) → `booth_login_begin` (new action): resolves the voter's
   `passkeys.credential_id` list and returns `allowCredentials` so the Android prompt is scoped
   to that one citizen's passkey — no passkey-picker browsing.
2. Citizen scans → existing `login_finish` verifies → pre-vote workflow →
   `vote_begin`/`vote_finish` gate → ballot on the phone.
3. After ballot, auto logout back to the EPIC screen (30s timeout).

Face verification stays on (server-side gate already enforced); it runs on the same phone camera.
Admin can disable it per station later if booth-side policy allows.

### Phase 4 — Hardening

- Rate-limit per-station API calls + failed-scan attempts (log to `biometric_logs` with
  `station_id`).
- Station token rotation + "last seen" heartbeat (`booth_ping`) with auto-expiry.
- Audit view in admin dashboard: enrollments & verifications per station.
- Offline behavior: SW caches the shell, API calls fail visibly — show a red "OFFLINE" banner,
  never queue biometric actions offline.

## Phone setup checklist (deployment)

- [ ] Dedicated Google account / work profile; no personal apps
- [ ] Full-disk encryption + remote wipe (Find My Device) enabled
- [ ] PWA installed via "Add to home screen"; Android **screen pinning** or a kiosk launcher
- [ ] Auto-brightness, "keep screen on" while charging, charging dock/cradle
- [ ] Stable hostname configured (see RP ID gotcha) and tunnel running as a service
- [ ] Wi-Fi + backup 4G hotspot tested

## Out of scope (explicitly)

- USB-OTG fingerprint scanners and server-side template matching (a future fork; the
  `passkeys` table schema would need a sibling `fp_templates` table — noted, not built)
- iOS booth devices (Safari PWA restrictions around kiosk use)
