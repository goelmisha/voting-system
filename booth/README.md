# Booth Companion App

The dedicated booth phone runs this folder as an installable PWA:
pair once, then enroll citizens' fingerprints from a touch-first kiosk.

## Setup

1. **Pair the phone** (one time):
   - Admin console → **📱 Pair Booth Phone** → generate a one-time code.
   - On the phone, open `https://<your-domain>/booth/` → enter the code → **Pair This Phone**.
2. **Install as app**: Chrome menu → *Add to home screen*.
3. **Kiosk it**: Android Settings → Apps → Booth → Advanced → *Screen pin*, or use a kiosk launcher.

## Files

| File                 | Purpose                                            |
| -------------------- | -------------------------------------------------- |
| `api.php`            | JSON API — pairing, search/onboard, arm, WebAuthn  |
| `index.php`          | Kiosk home (pairing screen when unpaired)          |
| `enroll.php`         | 3-screen enrollment flow (find → scan → done)      |
| `manifest.webmanifest` / `sw.js` / `icons/` | PWA shell                   |

## Security model

- Station pairing issues a long-lived session (separate cookie name from admin sessions);
  the token hash lives in `booth_stations.token_hash` and can be revoked from the admin console.
- Arming a citizen expires after **120 s** (`admin_enroll_expires`) and auto-disarms —
  a scan can never land on the previous citizen.
- `register_finish` disarms immediately after one successful scan.
- The service worker caches only static shell assets; **all API calls require the network**.

## RP ID warning

Passkeys are bound to the hostname. Use a **stable HTTPS domain** (own domain behind
Cloudflare Tunnel) — a rotating `*.trycloudflare.com` URL invalidates every enrolled passkey.
