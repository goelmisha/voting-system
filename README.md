# Online Voting System — Lok Sabha Portal

A PHP + SQLite web application that simulates a secure national (Lok Sabha) election portal with biometric voter verification, two-factor authentication, and an election-commission admin console.

> **Educational / demo project.** It demonstrates authentication and ballot-integrity patterns and is **not** certified for real elections.

## Features

### Voter portal
- **Constituency selection** — pick a State/UT and Lok Sabha constituency (all 543 PC labels bundled) before entering the portal
- **Passkey-first login** — sign in with a fingerprint / WebAuthn passkey (cross-device "a phone or tablet" approval supported), with email + password fallback
- **Two-factor authentication** — 6-digit OTP sent over Gmail SMTP (email) and Fast2SMS (mobile), valid for 5 minutes
- **Pre-vote workflow** — a 3-step declaration screen before the dashboard unlocks
- **Face verification gate** — live webcam capture matched against the registered photo using face-api.js 128-d descriptors; the match decision, threshold, one-time nonce, snapshot, and IP are handled/audited server-side
- **Ballot integrity** — one vote per voter enforced with a live re-check + transaction, plus an anonymous `votes` audit trail
- **Party symbols & logos** — real Indian national/state party logos with ECI-style symbol rendering

### Admin console (`/admin`)
- Role-based accounts (`super_admin` / `admin`)
- Approve or reject voter registrations
- Manage candidates per constituency, view live results and turnout

## Tech stack

| Layer      | Choice                                                        |
| ---------- | ------------------------------------------------------------- |
| Backend    | PHP 8 (PDO), no framework                                     |
| Database   | SQLite (`voting_system.db`)                                   |
| UI         | Bootstrap 5 (bundled locally), vanilla JS                     |
| Face match | face-api.js (client) + server-side distance check             |
| Passkeys   | Dependency-free server-side WebAuthn (`includes/webauthn.php`), ES256 + RS256 |
| Email/SMS  | PHPMailer (Gmail SMTP), Fast2SMS REST API                     |

## Requirements

- PHP ≥ 8.0 with extensions: `pdo_sqlite`, `openssl`, `curl`, `mbstring`
- Composer
- A browser with WebAuthn + camera access (passkey login and face verification require **localhost or HTTPS**)

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Initialise the database (creates tables + default admins)
php init_db.php

# 3. Seed realistic demo data (optional but recommended)
#    Real Lok Sabha 2024 candidates per constituency + synthetic demo voters
php seed_real_data.php

# 4. Run locally
php -S localhost:8000
```

Then open <http://localhost:8000>.

### Default admin accounts (created by `init_db.php`)

| Role        | Username   | Password      |
| ----------- | ---------- | ------------- |
| super_admin | `admin`    | `admin123`    |
| admin       | `officer1` | `officer123`  |

### Demo voters

`seed_real_data.php` pre-enrolls an approved electorate (registration is closed by design — see `register.php`). Every seeded voter's password is **`Voter@123`**.

## Configuration

External service credentials are currently set in the source files:

| What                | Where                              |
| ------------------- | ---------------------------------- |
| Gmail SMTP app password | `login.php`, `register.php` (`send_*_email*` functions) |
| Fast2SMS API key    | `login.php` (`send_login_sms_otp`) |

⚠️ **Before deploying anywhere:** the repo currently contains live SMTP credentials. Revoke/rotate them and move all secrets into environment variables or an untracked config file.

## Project structure

```
├── index.php                  # Constituency picker → portal landing
├── login.php                  # Passkey / credentials + 2FA OTP login
├── register.php               # Disabled (redirects to login); voters pre-enrolled
├── register_fingerprint.php   # Passkey enrollment
├── webauthn_options.php       # WebAuthn begin/finish endpoints
├── db.php                     # PDO SQLite connection + idempotent schema upgrades
├── init_db.php                # Schema bootstrap + default admins
├── seed_real_data.php         # Lok Sabha 2024 candidates + demo voters
├── schema.sql                 # Reference schema
├── admin/                     # Admin login, dashboard, voter approval, results
├── voters/                    # Voter dashboard, pre-vote workflow, face verify, vote
├── includes/                  # webauthn.php, party_symbols.php, connection.php
├── images/                    # Voter photos, candidate photos, party logos
├── scripts/                   # fetch_party_logos.php
├── bootstrap/, css/, js/      # Front-end assets
└── .github/workflows/php.yml  # CI: composer validate + install
```

## Security notes

- Passwords hashed with `password_hash()`; OTPs expire after 5 minutes
- Face match threshold (0.55 Euclidean distance) and verification state enforced **server-side**; no client-only "mark verified" path exists
- Every biometric attempt is logged (`biometric_logs`) with method, distance, snapshot, and IP
- Vote casting re-checks voter status inside a transaction to prevent double-voting races
- Known demo-grade gaps: credentials hardcoded in source, `login.php` accepts legacy plain-text passwords as a fallback, and SQLite is a single-file DB — all fine for a classroom demo, not for production
