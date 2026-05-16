-- ===============================================================
-- 2030B P2P Pairing — System database schema (system.sqlite)
-- Stores users, sessions, global settings.
-- ===============================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    email                     TEXT NOT NULL UNIQUE,
    password_hash             TEXT NOT NULL,
    full_name                 TEXT NOT NULL,
    country_code              TEXT NOT NULL DEFAULT 'TN',
    language                  TEXT NOT NULL DEFAULT 'en',
    phone                     TEXT DEFAULT '',
    level                     INTEGER NOT NULL DEFAULT 0,
    total_credits             INTEGER NOT NULL DEFAULT 0,
    total_pairings            INTEGER NOT NULL DEFAULT 0,
    redotpay_ops_count        INTEGER NOT NULL DEFAULT 0,
    binance_ops_count         INTEGER NOT NULL DEFAULT 0,
    redotpay_pairings_count   INTEGER NOT NULL DEFAULT 0,
    binance_pairings_count    INTEGER NOT NULL DEFAULT 0,
    created_at                INTEGER NOT NULL,
    last_login                INTEGER,
    is_active                 INTEGER NOT NULL DEFAULT 1,
    role                      TEXT NOT NULL DEFAULT 'user'    -- 'user' | 'admin'
);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

CREATE TABLE IF NOT EXISTS sessions (
    session_id  TEXT PRIMARY KEY,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token       TEXT NOT NULL,
    expiry      INTEGER NOT NULL,
    created_at  INTEGER NOT NULL,
    ip          TEXT,
    user_agent  TEXT
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expiry ON sessions(expiry);

CREATE TABLE IF NOT EXISTS settings (
    key         TEXT PRIMARY KEY,
    value       TEXT,
    updated_at  INTEGER
);

-- Audit log for admin actions (approve/reject etc.)
CREATE TABLE IF NOT EXISTS audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    actor       TEXT,
    action      TEXT,
    target      TEXT,
    notes       TEXT,
    created_at  INTEGER NOT NULL
);

-- Level 4 waitlist
CREATE TABLE IF NOT EXISTS lvl4_waitlist (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    email       TEXT NOT NULL,
    full_name   TEXT,
    reason      TEXT,
    created_at  INTEGER NOT NULL
);
