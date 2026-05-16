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
function b30_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    if (!is_file(B30_CONFIG)) {
        $cfg = [];
        return $cfg;
    }
    $cfg = json_decode((string)file_get_contents(B30_CONFIG), true) ?: [];
    return $cfg;
}

function b30_save_config(array $cfg): bool {
    $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return (bool)file_put_contents(B30_CONFIG, $json, LOCK_EX);
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
    return $pdo;
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
    return !empty($_SESSION['is_admin']);
}
function b30_require_admin(): void {
    if (!b30_is_admin()) {
        http_response_code(403);
        if (b30_is_ajax()) { echo json_encode(['error' => 'admin_required']); exit; }
        header('Location: /admin.php'); exit;
    }
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
function b30_recompute_user(int $userId): array {
    $cfg  = b30_config();
    $pdo  = b30_pdo();
    $updo = b30_user_pdo($userId);
    $creditsPer = (int)($cfg['credits_per_pairing'] ?? 1);

    $txs   = $updo->query('SELECT * FROM transactions')->fetchAll();
    $pairs = $updo->query('SELECT * FROM pairings')->fetchAll();
    $rOps = $bOps = $rPairs = $bPairs = $totalPairs = $credits = 0;
    foreach ($txs as $t) {
        if ($t['status'] !== 'verified') continue;
        if ($t['platform'] === 'redotpay') $rOps++;
        elseif ($t['platform'] === 'binance') $bOps++;
    }
    foreach ($pairs as $p) {
        $legs = array_values(array_filter($txs, fn($x) => $x['pairing_id'] === $p['pairing_id']));
        $ok = count($legs) === 2 && !array_filter($legs, fn($x) => $x['status'] !== 'verified');
        $newStatus = $ok ? 'verified' : (array_filter($legs, fn($x) => $x['status'] === 'rejected') ? 'rejected' : 'pending');
        $earn = $ok ? $creditsPer : 0;
        $st = $updo->prepare('UPDATE pairings SET status=?, credits_earned=? WHERE pairing_id=?');
        $st->execute([$newStatus, $earn, $p['pairing_id']]);
        if ($ok) {
            $totalPairs++;
            $credits += $creditsPer;
            if ($p['platform'] === 'redotpay') $rPairs++;
            elseif ($p['platform'] === 'binance') $bPairs++;
        }
    }
    $level = 0;
    $L = $cfg['levels'] ?? [];
    if ($totalPairs >= (int)($L['1']['required_pairings'] ?? 1)) $level = 1;
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

    return [
        'level' => $level, 'total_credits' => $credits, 'total_pairings' => $totalPairs,
        'redotpay_ops_count' => $rOps, 'binance_ops_count' => $bOps,
        'redotpay_pairings_count' => $rPairs, 'binance_pairings_count' => $bPairs,
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
