<?php
/**
 * 2030B P2P Pairing — Bootstrap (DB, session, CSRF, helpers)
 *
 * Place at backend/inc/bootstrap.php. All entry-point PHP files (index.php,
 * dashboard.php, api.php, admin.php, docs.php) require this file at the top.
 */

declare(strict_types=1);

// -- Paths -------------------------------------------------------
define('B30_ROOT',     dirname(__DIR__));              // /backend
define('B30_PUBLIC',   dirname(B30_ROOT));             // project root
define('B30_DB_DIR',   B30_ROOT . '/database');
define('B30_USER_DB_DIR', B30_ROOT . '/db_users');
define('B30_UPLOADS',  B30_ROOT . '/uploads');
define('B30_LANG_DIR', B30_PUBLIC . '/lang');
define('B30_CONFIG',   B30_PUBLIC . '/config.json');
define('B30_PROVIDERS_FILE', B30_PUBLIC . '/p2p-providers.json');
define('B30_SECURITY_FILE',  B30_PUBLIC . '/security.json');
define('B30_PENALTIES_FILE', B30_PUBLIC . '/penalties.json');
define('B30_ECOSYSTEM_FILE', B30_PUBLIC . '/ecosystem-currencies.json');
define('B30_SECRETS_FILE',   B30_PUBLIC . '/secrets.json');
define('B30_INSTALL_LOCK',   B30_PUBLIC . '/backend/database/.installed');

foreach ([B30_DB_DIR, B30_USER_DB_DIR, B30_UPLOADS] as $d) {
    if (!is_dir($d)) @mkdir($d, 0775, true);
}

// -- Session -----------------------------------------------------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('B30SESS');
session_start();

// 30-min absolute timeout
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// -- Config ------------------------------------------------------
// Loads config.json (public) and merges secrets.json (server-only) on top.
// secrets.json holds admin_password / super_admin_password / super_admin_email
// and MUST be blocked from the web via .htaccess (it is).
function b30_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = [];
    if (is_file(B30_CONFIG)) {
        $cfg = json_decode((string)file_get_contents(B30_CONFIG), true) ?: [];
    }
    $secretsFile = dirname(B30_CONFIG) . '/secrets.json';
    if (is_file($secretsFile)) {
        $sec = json_decode((string)file_get_contents($secretsFile), true);
        if (is_array($sec)) {
            // Secrets always win over config.json
            $cfg = array_merge($cfg, $sec);
        }
    }
    return $cfg;
}

function b30_save_config(array $cfg): bool {
    $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return (bool)file_put_contents(B30_CONFIG, $json, LOCK_EX);
}

// -- JSON file loaders + savers (providers, security, penalties, ecosystem) --
function b30_json_load(string $path, array $default = []): array {
    if (!is_file($path)) return $default;
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}
function b30_json_save(string $path, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return (bool)file_put_contents($path, $json, LOCK_EX);
}
function b30_providers(): array      { return b30_json_load(B30_PROVIDERS_FILE, ['providers' => []]); }
function b30_security(): array       { return b30_json_load(B30_SECURITY_FILE,  []); }
function b30_penalties_cfg(): array  { return b30_json_load(B30_PENALTIES_FILE, ['rules' => []]); }
function b30_ecosystem_cfg(): array  { return b30_json_load(B30_ECOSYSTEM_FILE, ['projects' => []]); }
function b30_secrets(): array        { return b30_json_load(B30_SECRETS_FILE,   []); }
function b30_save_secrets(array $sec): bool {
    // Always pretty-print + lock so the server-only file stays human-readable.
    return b30_json_save(B30_SECRETS_FILE, $sec);
}
function b30_is_installed(): bool { return is_file(B30_INSTALL_LOCK); }
function b30_mark_installed(array $meta = []): void {
    @file_put_contents(B30_INSTALL_LOCK, json_encode(array_merge($meta, ['at' => time()]), JSON_PRETTY_PRINT));
}

function b30_provider_codes(bool $enabled_only = false): array {
    $p = b30_providers()['providers'] ?? [];
    if ($enabled_only) $p = array_values(array_filter($p, fn($x) => ($x['enabled'] ?? true) !== false));
    return array_map(fn($x) => (string)($x['code'] ?? ''), $p);
}
function b30_provider_enabled(string $code): bool {
    $p = b30_providers()['providers'] ?? [];
    foreach ($p as $row) {
        if (strtolower((string)($row['code'] ?? '')) === strtolower($code)) {
            return ($row['enabled'] ?? true) !== false;
        }
    }
    return false;
}

// -- System DB ---------------------------------------------------
function b30_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $file = B30_DB_DIR . '/system.sqlite';
    $needsInit = !is_file($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA foreign_keys = ON;');
    if ($needsInit) {
        $sql = (string)file_get_contents(B30_ROOT . '/sql/system.sql');
        $pdo->exec($sql);
    }
    // Always run light migrations (idempotent — both fresh and existing DBs).
    if (true) {
        // Add missing user columns (ignore "duplicate column" errors)
        $mig = [
            "ALTER TABLE users ADD COLUMN ref_code TEXT",
            "ALTER TABLE users ADD COLUMN referred_by INTEGER",
            "ALTER TABLE users ADD COLUMN affiliate_credits INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE users ADD COLUMN total_operations INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE users ADD COLUMN profile_completed INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE users ADD COLUMN email_verify_code TEXT",
            "ALTER TABLE users ADD COLUMN auth_provider TEXT NOT NULL DEFAULT 'password'",
        ];
        foreach ($mig as $q) { try { $pdo->exec($q); } catch (Throwable $e) {} }
        // Make sure new tables exist (run on both fresh and migrated DBs).
        $newTables = [
            "CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, full_name TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', permissions TEXT NOT NULL DEFAULT '[]', parent_id INTEGER, enabled INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL, last_login INTEGER)",
            "CREATE TABLE IF NOT EXISTS referrals (id INTEGER PRIMARY KEY AUTOINCREMENT, referrer_id INTEGER NOT NULL, referee_id INTEGER NOT NULL UNIQUE, ref_code TEXT NOT NULL, created_at INTEGER NOT NULL)",
            "CREATE TABLE IF NOT EXISTS affiliate_rewards (id INTEGER PRIMARY KEY AUTOINCREMENT, referrer_id INTEGER NOT NULL, referee_id INTEGER NOT NULL, pairing_id TEXT, credits INTEGER NOT NULL DEFAULT 0, source TEXT NOT NULL DEFAULT 'pairing', created_at INTEGER NOT NULL)",
            "CREATE TABLE IF NOT EXISTS penalties (id INTEGER PRIMARY KEY AUTOINCREMENT, target_type TEXT NOT NULL, target_id INTEGER NOT NULL, rule_code TEXT NOT NULL, severity TEXT NOT NULL DEFAULT 'minor', credits_delta INTEGER NOT NULL DEFAULT 0, reason TEXT, evidence TEXT, status TEXT NOT NULL DEFAULT 'open', issued_by TEXT, created_at INTEGER NOT NULL, closed_at INTEGER)",
            "CREATE TABLE IF NOT EXISTS api_keys (id TEXT PRIMARY KEY, user_id INTEGER, label TEXT NOT NULL, key TEXT NOT NULL UNIQUE, scopes TEXT NOT NULL DEFAULT '[\"auth:read\",\"auth:popup\"]', origins TEXT NOT NULL DEFAULT '[\"*\"]', enabled INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL, last_used INTEGER)",
            "CREATE TABLE IF NOT EXISTS ecosystem_balances (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, project_code TEXT NOT NULL, currency TEXT NOT NULL, balance REAL NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL, UNIQUE (user_id, project_code))",
            "CREATE TABLE IF NOT EXISTS ecosystem_redemptions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, project_code TEXT NOT NULL, currency TEXT NOT NULL, credits_spent REAL NOT NULL, rate REAL NOT NULL, amount_minted REAL NOT NULL, created_at INTEGER NOT NULL)",
        ];
        foreach ($newTables as $q) { try { $pdo->exec($q); } catch (Throwable $e) {} }
        // SaaS migrations — run unconditionally so old DBs catch up
        $saasTables = [
            // but we still model it as a table so multi-instance setups can later swap
            // file-storage for DB-backed tenancy.
            "CREATE TABLE IF NOT EXISTS tenants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                site_name TEXT NOT NULL,
                owner_email TEXT NOT NULL,
                owner_name TEXT NOT NULL,
                pack_code TEXT NOT NULL DEFAULT 'free',
                pack_started_at INTEGER NOT NULL,
                pack_expires_at INTEGER,
                billing_cycle TEXT NOT NULL DEFAULT 'monthly',
                status TEXT NOT NULL DEFAULT 'active',
                created_at INTEGER NOT NULL,
                meta TEXT NOT NULL DEFAULT '{}'
            )",
            "CREATE TABLE IF NOT EXISTS subscription_packs (
                code TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                price_monthly REAL NOT NULL DEFAULT 0,
                price_yearly  REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'USD',
                max_users INTEGER NOT NULL DEFAULT 0,
                max_providers INTEGER NOT NULL DEFAULT 2,
                max_admins INTEGER NOT NULL DEFAULT 0,
                feature_audit INTEGER NOT NULL DEFAULT 1,
                feature_affiliate INTEGER NOT NULL DEFAULT 1,
                feature_api_keys INTEGER NOT NULL DEFAULT 1,
                feature_white_label INTEGER NOT NULL DEFAULT 0,
                description TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 100,
                enabled INTEGER NOT NULL DEFAULT 1
            )",
            "CREATE TABLE IF NOT EXISTS subscription_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                pack_code TEXT NOT NULL,
                billing_cycle TEXT NOT NULL DEFAULT 'monthly',
                amount REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'USD',
                event TEXT NOT NULL,
                notes TEXT,
                created_at INTEGER NOT NULL
            )",
        ];
        foreach ($saasTables as $q) { try { $pdo->exec($q); } catch (Throwable $e) {} }
    }
    // Always ensure super-admin exists
    b30_ensure_super_admin();
    b30_ensure_default_api_key();
    b30_ensure_subscription_packs();
    b30_ensure_tenant();
    return $pdo;
}

/**
 * Seed the 3 default subscription packs (Free / Pro / Enterprise) once.
 * Idempotent: only inserts when the table is empty.
 */
function b30_ensure_subscription_packs(): void {
    try {
        $pdo = b30_pdo();
        $row = $pdo->query("SELECT COUNT(*) AS n FROM subscription_packs")->fetch();
        if ($row && (int)$row['n'] > 0) return;
        $packs = [
            ['free',       'Free',       0,     0,    'USD', 50,    2, 0, 1, 1, 1, 0, 'Starter pack: 2 P2P providers, no sub-admins, basic features.', 10],
            ['pro',        'Pro',        29,    290,  'USD', 1000,  5, 3, 1, 1, 1, 0, 'Growth pack: up to 5 providers, 3 sub-admins, affiliate kickbacks, audit log.', 20],
            ['enterprise', 'Enterprise', 99,    990,  'USD', 0,     99, 99, 1, 1, 1, 1, 'Unlimited everything + white-label branding.', 30],
        ];
        $st = $pdo->prepare("INSERT INTO subscription_packs(code,name,price_monthly,price_yearly,currency,max_users,max_providers,max_admins,feature_audit,feature_affiliate,feature_api_keys,feature_white_label,description,sort_order,enabled) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
        foreach ($packs as $p) $st->execute($p);
        b30_audit('system', 'subscription_packs.seeded', '', 'free/pro/enterprise');
    } catch (Throwable $e) { /* swallow on older DBs */ }
}

/**
 * Seed the single-instance tenant (the super-admin's own SaaS account).
 * Pulls site name + owner from config.json/secrets.json.
 */
function b30_ensure_tenant(): void {
    try {
        $pdo = b30_pdo();
        $row = $pdo->query("SELECT COUNT(*) AS n FROM tenants")->fetch();
        if ($row && (int)$row['n'] > 0) return;
        $cfg  = b30_config();
        $site = (string)($cfg['platform']['name'] ?? '2030B P2P Pairing');
        $sa   = $cfg['super_admin'] ?? [];
        $em   = strtolower((string)($sa['email']     ?? $cfg['super_admin_email'] ?? 'superadmin@2030b.local'));
        $nm   = (string)        ($sa['full_name']    ?? $cfg['super_admin_name']  ?? 'Maher Kaddoussi');
        $pdo->prepare("INSERT INTO tenants(slug,site_name,owner_email,owner_name,pack_code,pack_started_at,billing_cycle,status,created_at,meta) VALUES('default',?,?,?,'free',?,?,'active',?,'{}')")
            ->execute([$site, $em, $nm, time(), 'monthly', time()]);
        b30_audit('system', 'tenant.seeded', 'default');
    } catch (Throwable $e) { /* swallow */ }
}

function b30_current_tenant(): ?array {
    try {
        $row = b30_pdo()->query("SELECT * FROM tenants ORDER BY id ASC LIMIT 1")->fetch();
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

// -- Per-user DB -------------------------------------------------
function b30_user_pdo(int $userId): PDO {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    $file = B30_USER_DB_DIR . '/user_' . $userId . '.sqlite';
    $needsInit = !is_file($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    if ($needsInit) {
        $sql = (string)file_get_contents(B30_ROOT . '/sql/user.sql');
        $pdo->exec($sql);
    }
    $cache[$userId] = $pdo;
    return $pdo;
}

// -- CSRF --------------------------------------------------------
function b30_csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function b30_csrf_check(?string $token): bool {
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

// -- Auth helpers ------------------------------------------------
function b30_current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $st = b30_pdo()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}
function b30_require_user(): array {
    $u = b30_current_user();
    if (!$u) {
        http_response_code(401);
        if (b30_is_ajax()) { echo json_encode(['error' => 'auth_required']); exit; }
        header('Location: /index.php#join'); exit;
    }
    return $u;
}
function b30_is_admin(): bool {
    return !empty($_SESSION['admin_id']) || !empty($_SESSION['is_admin']);
}
function b30_current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) {
        if (!empty($_SESSION['is_admin'])) {
            return ['id' => 0, 'email' => 'legacy@admin', 'full_name' => 'Legacy super-admin', 'role' => 'super_admin', 'permissions' => ['*'], 'enabled' => 1];
        }
        return null;
    }
    $st = b30_pdo()->prepare('SELECT * FROM admins WHERE id = ?');
    $st->execute([(int)$_SESSION['admin_id']]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['permissions'] = json_decode($row['permissions'] ?? '[]', true) ?: [];
    return $row;
}
function b30_admin_has_perm(?array $admin, string $perm): bool {
    if (!$admin) return false;
    if (($admin['role'] ?? '') === 'super_admin') return true;
    $perms = $admin['permissions'] ?? [];
    return in_array('*', $perms, true) || in_array($perm, $perms, true);
}
function b30_require_admin(?string $perm = null): array {
    $a = b30_current_admin();
    if (!$a || !($a['enabled'] ?? 1)) {
        http_response_code(403);
        if (b30_is_ajax()) { echo json_encode(['error' => 'admin_required']); exit; }
        header('Location: /admin.php'); exit;
    }
    if ($perm && !b30_admin_has_perm($a, $perm)) {
        http_response_code(403);
        if (b30_is_ajax()) { echo json_encode(['error' => 'perm_denied', 'perm' => $perm]); exit; }
        header('Location: /admin.php'); exit;
    }
    return $a;
}

/**
 * Seed the super-admin from config.json on first run.
 * Reads {super_admin: {email, password, full_name}} → inserts into admins table.
 */
function b30_ensure_super_admin(): void {
    $pdo = b30_pdo();
    $row = $pdo->query("SELECT COUNT(*) AS n FROM admins WHERE role='super_admin'")->fetch();
    if ($row && (int)$row['n'] > 0) return;
    $cfg = b30_config();
    // Accept both layouts:
    //   { "super_admin": {"email":"...","password":"..."} }
    //   { "super_admin_email": "...", "super_admin_password": "..." }
    $sa  = $cfg['super_admin'] ?? [];
    $email = strtolower((string)($sa['email']     ?? $cfg['super_admin_email']    ?? 'superadmin@2030b.local'));
    $pw    = (string)        ($sa['password']  ?? $cfg['super_admin_password'] ?? 'change-me-now');
    $name  = (string)        ($sa['full_name'] ?? $cfg['super_admin_name']     ?? 'Maher Kaddoussi');
    $hash  = password_hash($pw, PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO admins(email,password_hash,full_name,role,permissions,enabled,created_at) VALUES(?,?,?,?,?,1,?)')
        ->execute([$email, $hash, $name, 'super_admin', json_encode(['*']), time()]);
}

/**
 * Seed a "system_default" API key — used by the Auth API for the public
 * sign-in/sign-up popup flows. Super-admin can rotate or revoke this key
 * from the admin panel (Keys tab).
 *
 * The key is stored in the api_keys table with user_id=NULL (system-scope),
 * scopes = auth:public (register + signin only), origins = ["*"].
 */
function b30_ensure_default_api_key(): void {
    try {
        $pdo = b30_pdo();
        $row = $pdo->query("SELECT id,key FROM api_keys WHERE label='system_default' LIMIT 1")->fetch();
        if ($row) return;
        $id   = 'sysk_' . bin2hex(random_bytes(6));
        $key  = 'b30_pk_' . bin2hex(random_bytes(24));
        $now  = time();
        $pdo->prepare(
            "INSERT INTO api_keys(id,user_id,label,key,scopes,origins,enabled,created_at)
             VALUES(?, NULL, 'system_default', ?, ?, ?, 1, ?)"
        )->execute([$id, $key, json_encode(['auth:public']), json_encode(['*']), $now]);
        b30_audit('system', 'default_api_key.seeded', $id, 'system_default created');
    } catch (Throwable $e) { /* swallow on fresh DBs that lack api_keys */ }
}

/**
 * Return the system_default API key row (or null if missing). Used by the
 * public config endpoint so the browser can pin requests with X-API-Key.
 */
function b30_default_api_key(): ?array {
    try {
        $st = b30_pdo()->prepare("SELECT id,key,enabled FROM api_keys WHERE label='system_default' LIMIT 1");
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/**
 * Verify an incoming X-API-Key header against the api_keys table. Returns the
 * row on success, or null on failure. Updates last_used on hit.
 * If the security policy turns API gating off, this returns ['ok'=>true].
 */
function b30_check_api_key(?string $key, string $requiredScope = 'auth:public'): ?array {
    $sec = b30_security();
    $required = $sec['auth']['api_key_required'] ?? true;
    if (!$required) return ['id' => null, 'key' => null, 'scopes' => ['*'], 'enabled' => 1];
    if (!$key) return null;
    try {
        $st = b30_pdo()->prepare('SELECT * FROM api_keys WHERE key = ? AND enabled = 1 LIMIT 1');
        $st->execute([$key]);
        $row = $st->fetch();
        if (!$row) return null;
        $row['scopes']  = json_decode($row['scopes']  ?? '[]', true) ?: [];
        $row['origins'] = json_decode($row['origins'] ?? '[]', true) ?: ['*'];
        $scopes = $row['scopes'];
        if (!in_array('*', $scopes, true) && !in_array($requiredScope, $scopes, true)) {
            // Allow auth:popup to also satisfy auth:public for backward-compat
            if ($requiredScope === 'auth:public' && in_array('auth:popup', $scopes, true)) {
                // accept
            } else {
                return null;
            }
        }
        b30_pdo()->prepare('UPDATE api_keys SET last_used = ? WHERE id = ?')->execute([time(), $row['id']]);
        return $row;
    } catch (Throwable $e) { return null; }
}

/**
 * Extract the API key from a request: header `X-API-Key`, query `api_key`,
 * or POST/JSON `api_key` field — in that order.
 */
function b30_request_api_key(): ?string {
    $h = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($h !== '') return trim($h);
    if (!empty($_GET['api_key']))  return (string)$_GET['api_key'];
    if (!empty($_POST['api_key'])) return (string)$_POST['api_key'];
    if (!empty($GLOBALS['__b30_json_body']['api_key'])) return (string)$GLOBALS['__b30_json_body']['api_key'];
    return null;
}

/**
 * Append to audit_log (admin actions, logins, uploads, etc.)
 */
function b30_audit(string $actor, string $action, string $target = '', string $notes = ''): void {
    try {
        b30_pdo()->prepare('INSERT INTO audit_log(actor,action,target,notes,created_at) VALUES(?,?,?,?,?)')
            ->execute([$actor, $action, $target, $notes, time()]);
    } catch (Throwable $e) { /* swallow */ }
}

/**
 * Generate a referral code from user id + random.
 */
function b30_gen_ref_code(int $userId): string {
    return strtoupper(substr(base_convert((string)$userId, 10, 36), 0, 4)) . bin2hex(random_bytes(2));
}

/**
 * Kickback credits to the referrer when one of their referees finalizes a pairing.
 * Uses config.affiliate_program.{enabled, percent_of_pairing_credits, max_per_pairing}.
 */
function b30_award_affiliate(int $refereeId, string $pairingId, int $creditsEarned): void {
    if ($creditsEarned <= 0) return;
    $pdo = b30_pdo();
    $st = $pdo->prepare('SELECT * FROM referrals WHERE referee_id = ?');
    $st->execute([$refereeId]);
    $r = $st->fetch();
    if (!$r) return;
    // Already awarded for this pairing?
    $chk = $pdo->prepare('SELECT 1 FROM affiliate_rewards WHERE pairing_id = ? AND referrer_id = ?');
    $chk->execute([$pairingId, (int)$r['referrer_id']]);
    if ($chk->fetch()) return;

    $cfg = b30_config();
    $ap  = $cfg['affiliate_program'] ?? [];
    if (!($ap['enabled'] ?? true)) return;
    $pct = (float)($ap['percent_of_pairing_credits'] ?? 10);
    $max = (int)($ap['max_per_pairing'] ?? 5);
    $kick = (int)max(0, min($max, floor($creditsEarned * $pct / 100)));
    if ($kick <= 0) return;

    $pdo->prepare('INSERT INTO affiliate_rewards(referrer_id,referee_id,pairing_id,credits,source,created_at) VALUES(?,?,?,?,?,?)')
        ->execute([(int)$r['referrer_id'], $refereeId, $pairingId, $kick, 'pairing', time()]);
    $pdo->prepare('UPDATE users SET affiliate_credits = affiliate_credits + ?, total_credits = total_credits + ? WHERE id = ?')
        ->execute([$kick, $kick, (int)$r['referrer_id']]);
}

/**
 * Apply a credits delta to a user (used by penalties).
 */
function b30_adjust_user_credits(int $userId, int $delta, string $reason = ''): void {
    $pdo = b30_pdo();
    $pdo->prepare('UPDATE users SET total_credits = MAX(0, total_credits + ?) WHERE id = ?')
        ->execute([$delta, $userId]);
    b30_audit('system', 'credits_adjust', (string)$userId, $reason . ' (' . $delta . ')');
}

// -- Misc --------------------------------------------------------
function b30_is_ajax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
function b30_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function b30_uid(string $prefix = ''): string { return $prefix . bin2hex(random_bytes(6)); }

function b30_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function b30_rate_limit(string $bucket, int $max = 30, int $window = 60): bool {
    $key = '_rl_' . $bucket;
    $now = time();
    $hits = $_SESSION[$key] ?? [];
    $hits = array_values(array_filter($hits, fn($t) => $t > $now - $window));
    if (count($hits) >= $max) { $_SESSION[$key] = $hits; return false; }
    $hits[] = $now; $_SESSION[$key] = $hits; return true;
}

// Re-evaluate user's level/credits after any transaction status flip
// Provider-agnostic: counts ops + pairings per platform code dynamically.
function b30_recompute_user(int $userId): array {
    $cfg  = b30_config();
    $pdo  = b30_pdo();
    $updo = b30_user_pdo($userId);
    $creditsPer = (int)($cfg['credits_per_pairing'] ?? 1);

    $txs   = $updo->query('SELECT * FROM transactions')->fetchAll();
    $pairs = $updo->query('SELECT * FROM pairings')->fetchAll();

    $opsBy = [];   // platform => verified-op count
    $pairsBy = [];
    foreach ($txs as $t) {
        if ($t['status'] !== 'verified') continue;
        $pl = (string)$t['platform'];
        $opsBy[$pl] = ($opsBy[$pl] ?? 0) + 1;
    }

    $totalPairs = 0; $credits = 0;
    $newly_verified = []; // pairings that transitioned to verified now
    foreach ($pairs as $p) {
        $legs = array_values(array_filter($txs, fn($x) => $x['pairing_id'] === $p['pairing_id']));
        $ok = count($legs) === 2 && !array_filter($legs, fn($x) => $x['status'] !== 'verified');
        $newStatus = $ok ? 'verified' : (array_filter($legs, fn($x) => $x['status'] === 'rejected') ? 'rejected' : 'pending');
        $earn = $ok ? $creditsPer : 0;
        if ($ok && $p['status'] !== 'verified') $newly_verified[] = ['id' => $p['pairing_id'], 'credits' => $earn];
        $st = $updo->prepare('UPDATE pairings SET status=?, credits_earned=? WHERE pairing_id=?');
        $st->execute([$newStatus, $earn, $p['pairing_id']]);
        if ($ok) {
            $totalPairs++;
            $credits += $creditsPer;
            $pl = (string)$p['platform'];
            $pairsBy[$pl] = ($pairsBy[$pl] ?? 0) + 1;
        }
    }

    // Level resolution (legacy keys still respected for binance/redotpay):
    $level = 0;
    $L = $cfg['levels'] ?? [];
    if ($totalPairs >= (int)($L['1']['required_pairings'] ?? 1)) $level = 1;
    $rOps = $opsBy['redotpay'] ?? 0; $bOps = $opsBy['binance'] ?? 0;
    $rPairs = $pairsBy['redotpay'] ?? 0; $bPairs = $pairsBy['binance'] ?? 0;
    if ($level >= 1 &&
        $rOps   >= (int)($L['2']['required_redotpay_operations'] ?? 3) &&
        $rPairs >= (int)($L['2']['required_redotpay_pairings'] ?? 1))    $level = 2;
    if ($level >= 2 &&
        $bOps   >= (int)($L['3']['required_binance_operations'] ?? 20) &&
        $bPairs >= (int)($L['3']['required_binance_pairings'] ?? 1))     $level = 3;
    if ($level >= 3 &&
        $totalPairs >= (int)($L['4']['required_pairings'] ?? 100))       $level = 4;

    $st = $pdo->prepare(
        'UPDATE users SET level=?, total_credits=?, total_pairings=?,
                          redotpay_ops_count=?, binance_ops_count=?,
                          redotpay_pairings_count=?, binance_pairings_count=?
         WHERE id=?'
    );
    $st->execute([$level, $credits, $totalPairs, $rOps, $bOps, $rPairs, $bPairs, $userId]);

    // Affiliate kickback for any newly-verified pairing
    foreach ($newly_verified as $nv) {
        b30_award_affiliate($userId, (string)$nv['id'], (int)$nv['credits']);
    }

    return [
        'level' => $level, 'total_credits' => $credits, 'total_pairings' => $totalPairs,
        'redotpay_ops_count' => $rOps, 'binance_ops_count' => $bOps,
        'redotpay_pairings_count' => $rPairs, 'binance_pairings_count' => $bPairs,
        'ops_by_platform'      => $opsBy,
        'pairings_by_platform' => $pairsBy,
    ];
}

// --- Exchange rate cache + fetch ---
function b30_exchange_rates(bool $force = false): array {
    $pdo = b30_pdo();
    $row = $pdo->query("SELECT value, updated_at FROM settings WHERE key='fx_rates'")->fetch();
    if (!$force && $row && (time() - (int)$row['updated_at']) < 86400) {
        return json_decode((string)$row['value'], true) ?: [];
    }
    $cfg = b30_config();
    $base = 'USD';
    $url = ($cfg['exchange_rate_api'] ?? 'https://api.exchangerate-api.com/v4/latest/') . $base;
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $body = @file_get_contents($url, false, $ctx);
    $data = $body ? json_decode($body, true) : null;
    $rates = ($data && !empty($data['rates'])) ? $data['rates'] : [];
    if (!$rates) {
        $rates = ['USD'=>1,'EUR'=>0.92,'GBP'=>0.78,'TND'=>3.13,'CAD'=>1.36,'AUD'=>1.51,
                  'JPY'=>151,'CNY'=>7.23,'INR'=>83.4,'BRL'=>5.07,'ZAR'=>18.7,'NGN'=>1450];
    }
    $payload = ['base' => $base, 'rates' => $rates, 'updated_at' => time()];
    $st = $pdo->prepare("INSERT INTO settings(key,value,updated_at) VALUES('fx_rates',?,?)
                         ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at");
    $st->execute([json_encode($payload), time()]);
    return $payload;
}
