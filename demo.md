# Demo Guide — Online Voting System

How to prep for and run an end-to-end demo (~8 minutes).

---

## ⚠️ Prep before demoing (5 min)

### 0. Baseline setup

```bash
composer install          # install PHPMailer etc.
php init_db.php           # create schema + default admins
php seed_real_data.php    # real Lok Sabha 2024 candidates + demo voters (safe to re-run)
php -S localhost:8000     # start the app
```

Open <http://localhost:8000>. Everything else (face models, Bootstrap, face-api.js, party logos) is vendored locally — the only internet dependency is email delivery.

### 1. Make the OTP land in an inbox you control

OTPs go out by **email only** — the Fast2SMS key is a placeholder, so SMS silently fails. Seeded voters have synthetic addresses (`arjun.singh1@gmail.com`…) whose inboxes you can't open. Point one voter at an inbox you can open:

```bash
sqlite3 voting_system.db "UPDATE voters SET email='you@example.com' WHERE email='arjun.singh1@gmail.com';"
```

(Alternative: log in as `jahnvikarnatac04@gmail.com` — it is the sending SMTP account, so the OTP lands in its own inbox.)

Seeded voter password for everyone: **`Voter@123`**

### 2. Make face verification actually match

All seeded voters have the generic `default.png` as their photo — face-api can't match against it. Log in as your demo voter and upload a real selfie via **Edit Profile** first. After that, live face verification works.

### 3. Camera permission

Keep the demo tab frontmost and pre-approve camera access so the face-verify screen doesn't stall on the browser prompt.

---

## 🎬 The demo flow (~8 min)

### Act 1 — Voter journey (the core)

1. Open `localhost:8000` → pick State + Lok Sabha constituency → note the portal is constituency-scoped
2. **Voter Login** → pause on the passkey-first screen ("Sign in with your fingerprint") → click **"Use Email & Password instead"** → log in with the seeded voter + `Voter@123`
3. Show the **2FA screen** → fetch the OTP from the inbox → enter it → logged in
4. **Pre-vote workflow**: the 3-step declaration gate before the dashboard unlocks
5. **Face verification**: webcam opens, live capture vs registered photo — the wow moment. Deliberately point the camera away once → "did not match" → then match → ballot unlocks
6. **Cast a vote**: candidates with real Lok Sabha 2024 names + party symbols → confirm → receipt
7. Try to vote again → **blocked** (double-vote prevention)

### Act 2 — Admin console

1. Scroll to the discreet officer section → `admin/login.php` → `admin` / `admin123`
2. Approve a `pending` voter → show that voter can now log in
3. Show **live results / turnout** for the constituency

### Act 3 — Passkeys (optional closer)

1. Logged in as voter → add a passkey (enrollment) → logout
2. Login screen → **fingerprint sign-in works** (localhost is a secure context, so WebAuthn runs fine under `php -S`) — including "a phone or tablet" cross-device approval if you want to flex

---

## One-liner narrative

> "Offline registration, online voting — every ballot behind fingerprint + OTP + face match, with a server-enforced one-person-one-vote and an audit trail."

---

## Quick end-to-end smoke test (2 min)

The shortest path to prove the whole pipeline works:

```bash
# 1. One-time prep
composer install
php init_db.php
php seed_real_data.php
sqlite3 voting_system.db "UPDATE voters SET email='you@example.com' WHERE email='arjun.singh1@gmail.com';"
php -S localhost:8000
```

Then in the browser:

1. Pick a constituency → **Voter Login** → email + `Voter@123`
2. Enter OTP from your inbox
3. Upload a selfie in **Edit Profile** (one-time, enables face match)
4. Complete the declaration → **face scan** → **vote** → try voting again (should be blocked)
5. `admin` / `admin123` at `admin/login.php` → check results show your vote

If all 5 pass, the app is demo-ready.
