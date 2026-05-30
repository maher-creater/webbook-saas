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

-- ===============================================================
-- Profile completion + email verification + ecosystem currencies
-- ===============================================================
ALTER TABLE users ADD COLUMN total_operations    INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN binance_id          TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN redotpay_id         TEXT DEFAULT '';
ALTER TABLE users ADD COLUMN profile_completed   INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN email_verified      INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN email_verify_code   TEXT;
ALTER TABLE users ADD COLUMN auth_provider       TEXT NOT NULL DEFAULT 'password';

-- Auth API keys (for popup-embed on external domains)
CREATE TABLE IF NOT EXISTS api_keys (
    id          TEXT PRIMARY KEY,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    label       TEXT NOT NULL,
    key         TEXT NOT NULL UNIQUE,
    scopes      TEXT NOT NULL DEFAULT '["auth:read","auth:popup"]', -- JSON array
    origins     TEXT NOT NULL DEFAULT '["*"]',                       -- JSON array
    enabled     INTEGER NOT NULL DEFAULT 1,
    created_at  INTEGER NOT NULL,
    last_used   INTEGER
);
CREATE INDEX IF NOT EXISTS idx_keys_user ON api_keys(user_id);
CREATE INDEX IF NOT EXISTS idx_keys_key  ON api_keys(key);

-- Ecosystem currency ledger (per user, per project)
CREATE TABLE IF NOT EXISTS ecosystem_balances (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    project_code    TEXT NOT NULL,        -- e.g. 'BE_SMARTER'
    currency        TEXT NOT NULL,        -- e.g. 'CTC'
    balance         REAL NOT NULL DEFAULT 0,
    updated_at      INTEGER NOT NULL,
    UNIQUE (user_id, project_code)
);
CREATE INDEX IF NOT EXISTS idx_ebal_user ON ecosystem_balances(user_id);

-- Redemption history (credits -> currency)
CREATE TABLE IF NOT EXISTS ecosystem_redemptions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    project_code    TEXT NOT NULL,
    currency        TEXT NOT NULL,
    credits_spent   REAL NOT NULL,
    rate            REAL NOT NULL,
    amount_minted   REAL NOT NULL,
    created_at      INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_eredeem_user ON ecosystem_redemptions(user_id);

-- ===============================================================
-- Admin tree (super-admin + sub-admins with permissions)
-- ===============================================================
CREATE TABLE IF NOT EXISTS admins (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email           TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    full_name       TEXT NOT NULL,
    role            TEXT NOT NULL DEFAULT 'admin',          -- 'super_admin' | 'admin'
    permissions     TEXT NOT NULL DEFAULT '[]',             -- JSON array of perm strings
    parent_id       INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    enabled         INTEGER NOT NULL DEFAULT 1,
    created_at      INTEGER NOT NULL,
    last_login      INTEGER
);
CREATE INDEX IF NOT EXISTS idx_admins_email ON admins(email);
CREATE INDEX IF NOT EXISTS idx_admins_parent ON admins(parent_id);

-- ===============================================================
-- Referrals + affiliate rewards
-- ===============================================================
CREATE TABLE IF NOT EXISTS referrals (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    referrer_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referee_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ref_code        TEXT NOT NULL,
    created_at      INTEGER NOT NULL,
    UNIQUE (referee_id)
);
CREATE INDEX IF NOT EXISTS idx_ref_referrer ON referrals(referrer_id);

CREATE TABLE IF NOT EXISTS affiliate_rewards (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    referrer_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referee_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pairing_id      TEXT,
    credits         INTEGER NOT NULL DEFAULT 0,
    source          TEXT NOT NULL DEFAULT 'pairing',
    created_at      INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_aff_referrer ON affiliate_rewards(referrer_id);

-- ===============================================================
-- Penalties (user + admin discipline, rules in penalties.json)
-- ===============================================================
CREATE TABLE IF NOT EXISTS penalties (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type     TEXT NOT NULL,                          -- 'user' | 'admin'
    target_id       INTEGER NOT NULL,
    rule_code       TEXT NOT NULL,
    severity        TEXT NOT NULL DEFAULT 'minor',
    credits_delta   INTEGER NOT NULL DEFAULT 0,             -- negative = debit
    reason          TEXT,
    evidence        TEXT,                                   -- e.g. tx_id, pairing_id
    status          TEXT NOT NULL DEFAULT 'open',           -- 'open' | 'appealed' | 'closed' | 'reverted'
    issued_by       TEXT,
    created_at      INTEGER NOT NULL,
    closed_at       INTEGER
);
CREATE INDEX IF NOT EXISTS idx_pen_target ON penalties(target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_pen_status ON penalties(status);

-- Referral / affiliate columns on users
ALTER TABLE users ADD COLUMN ref_code           TEXT;
ALTER TABLE users ADD COLUMN referred_by        INTEGER;
ALTER TABLE users ADD COLUMN affiliate_credits  INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_users_refcode ON users(ref_code);
