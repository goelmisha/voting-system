-- SQLite Database Schema for Online Voting System

-- Table: admins
-- Stores administrative credentials for approving voters and managing elections
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: voters
-- Tracks registration status (pending, approved, rejected) and whether the ballot has been cast
CREATE TABLE IF NOT EXISTS voters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    voter_id_number TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    mobile TEXT,
    address TEXT,
    photo TEXT,
    document_proof TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
    has_voted INTEGER NOT NULL DEFAULT 0 CHECK(has_voted IN (0, 1)),
    fingerprint_credential TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: candidates
-- Holds information on candidates running in the election
CREATE TABLE IF NOT EXISTS candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    party TEXT NOT NULL,
    photo TEXT,
    votes_count INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: votes (Optional Audit Trail)
-- Anonymous ballot log linking a candidate vote without direct voter identification
CREATE TABLE IF NOT EXISTS votes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    candidate_id INTEGER NOT NULL,
    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
);

-- Indexes for optimized lookups
CREATE INDEX IF NOT EXISTS idx_voters_status ON voters(status);
CREATE INDEX IF NOT EXISTS idx_voters_voter_id ON voters(voter_id_number);