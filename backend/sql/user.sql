-- ===============================================================
-- 2030B P2P Pairing — Per-user database schema (user_{id}.sqlite)
-- One file per user, isolates trades and screenshots.
-- ===============================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS transactions (
    id                  TEXT PRIMARY KEY,
    platform            TEXT NOT NULL,                          -- dynamic provider code (see p2p-providers.json)
    type                TEXT NOT NULL CHECK (type IN ('buy','sell')),
    amount_usdt         REAL NOT NULL,
    amount_fiat         REAL NOT NULL,
    fiat_currency       TEXT NOT NULL,
    fee_percent         REAL NOT NULL DEFAULT 0,
    fee_amount          REAL NOT NULL DEFAULT 0,
    total_cost          REAL NOT NULL DEFAULT 0,
    transaction_date    INTEGER,
    screenshot_path     TEXT,                              -- relative path under uploads/
    screenshot_id       TEXT,
    status              TEXT NOT NULL DEFAULT 'pending'    -- pending | verified | rejected
                            CHECK (status IN ('pending','verified','rejected')),
    verification_notes  TEXT DEFAULT '',
    paired_with         TEXT,                              -- transaction id of the matching leg
    pairing_id          TEXT,                              -- groups the buy + sell legs
    created_at          INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_tx_pairing ON transactions(pairing_id);
CREATE INDEX IF NOT EXISTS idx_tx_status  ON transactions(status);

CREATE TABLE IF NOT EXISTS pairings (
    pairing_id      TEXT PRIMARY KEY,
    platform        TEXT NOT NULL,
    buy_tx_id       TEXT NOT NULL,
    sell_tx_id      TEXT NOT NULL,
    profit_usdt     REAL NOT NULL DEFAULT 0,
    profit_fiat     REAL NOT NULL DEFAULT 0,
    fiat_currency   TEXT NOT NULL,
    credits_earned  INTEGER NOT NULL DEFAULT 0,
    status          TEXT NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','verified','rejected')),
    pairing_date    INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS screenshots (
    id             TEXT PRIMARY KEY,
    transaction_id TEXT,
    original_name  TEXT,
    filename       TEXT NOT NULL,                          -- sanitized unique filename on disk
    mime           TEXT,
    size_bytes     INTEGER,
    upload_date    INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_shots_tx ON screenshots(transaction_id);
