<?php
/**
 * 2030B Auth API — JSON endpoint
 *
 * Routes (POST unless noted):
 *   GET  ?action=info                          -> { ok, ts, version }
 *   POST ?action=signin   { email, password, api_key, origin }
 *   POST ?action=signup   { email, password, full_name, api_key, origin }
 *   POST ?action=verify   { user_id, code, api_key }
 *   POST ?action=profile  { user_id, binance_id?, redotpay_id? }   (session-bound)
 *   GET  ?action=session                       -> { user|null }
 *   POST ?action=signout
 *
 *   Admin (requires admin session):
 *   GET  ?action=keys                          -> list all
 *   POST ?action=keys.create  { user_id?, label, scopes?, origins? }
 *   POST ?action=keys.toggle  { id, enabled }
 *   POST ?action=keys.delete  { id }
 *
 * CORS: this endpoint is intentionally CORS-permissive so it can be embedded
 * from any partner site. Origin & API-key validation are enforced inside.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../backend/inc/bootstrap.php';

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-API-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json; charset=utf-8');

function _json($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function _body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (is_array($j)) return $j;
    return $_POST;
}

$action = $_GET['action'] ?? 'info';

// API-key validation (where applicable)
function _check_api_key(?string $key, ?string $origin = null): array {
    if (!$key) return ['ok' => false, 'err' => 'Missing api_key'];
    if (!function_exists('b30_system_db')) return ['ok' => true, 'rec' => null]; // backend not fully wired
    try {
        $db = b30_system_db();
        $st = $db->prepare('SELECT * FROM api_keys WHERE key = :k AND enabled = 1');
        $st->execute([':k' => $key]);
        $rec = $st->fetch(PDO::FETCH_ASSOC);
        if (!$rec) return ['ok' => false, 'err' => 'Invalid api_key'];
        $origins = json_decode($rec['origins'] ?? '["*"]', true) ?: ['*'];
        if (!in_array('*', $origins, true) && $origin && !in_array($origin, $origins, true)) {
            return ['ok' => false, 'err' => 'Origin not allowed'];
        }
        // Touch last_used
        $db->prepare('UPDATE api_keys SET last_used = :t WHERE id = :id')
           ->execute([':t' => time(), ':id' => $rec['id']]);
        return ['ok' => true, 'rec' => $rec];
    } catch (Throwable $e) {
        return ['ok' => false, 'err' => 'API key check failed'];
    }
}

try {
    switch ($action) {
        case 'info':
            _json(['ok' => true, 'service' => '2030B-auth', 'version' => '1.0', 'ts' => time()]);

        case 'signin': {
            $b = _body();
            $check = _check_api_key($b['api_key'] ?? null, $b['origin'] ?? null);
            if (!$check['ok']) _json(['ok' => false, 'error' => $check['err']], 403);
            if (function_exists('b30_user_signin')) {
                $u = b30_user_signin($b['email'] ?? '', $b['password'] ?? '');
                _json(['ok' => true, 'user' => $u, 'token' => 'b30_' . ($u['id'] ?? '')]);
            }
            _json(['ok' => false, 'error' => 'backend not configured'], 501);
        }

        case 'signup': {
            $b = _body();
            $check = _check_api_key($b['api_key'] ?? null, $b['origin'] ?? null);
            if (!$check['ok']) _json(['ok' => false, 'error' => $check['err']], 403);
            if (function_exists('b30_user_register')) {
                $u = b30_user_register([
                    'email'     => $b['email'] ?? '',
                    'password'  => $b['password'] ?? '',
                    'full_name' => $b['full_name'] ?? ''
                ]);
                _json(['ok' => true, 'user' => $u, 'verify_required' => true]);
            }
            _json(['ok' => false, 'error' => 'backend not configured'], 501);
        }

        case 'verify': {
            $b = _body();
            if (function_exists('b30_user_verify_email')) {
                $u = b30_user_verify_email($b['user_id'] ?? '', $b['code'] ?? '');
                _json(['ok' => true, 'user' => $u]);
            }
            _json(['ok' => false, 'error' => 'backend not configured'], 501);
        }

        case 'profile': {
            $b = _body();
            if (function_exists('b30_user_update_profile')) {
                $u = b30_user_update_profile($b['user_id'] ?? '', $b);
                _json(['ok' => true, 'user' => $u]);
            }
            _json(['ok' => false, 'error' => 'backend not configured'], 501);
        }

        case 'session': {
            if (function_exists('b30_current_user')) {
                _json(['ok' => true, 'user' => b30_current_user()]);
            }
            _json(['ok' => true, 'user' => null]);
        }

        case 'signout': {
            if (function_exists('b30_signout')) b30_signout();
            _json(['ok' => true]);
        }

        // ---------------- ADMIN ----------------
        case 'keys': {
            if (!function_exists('b30_is_admin') || !b30_is_admin()) _json(['ok' => false, 'error' => 'forbidden'], 403);
            $db = b30_system_db();
            $rows = $db->query('SELECT * FROM api_keys ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
            _json(['ok' => true, 'keys' => $rows]);
        }
        case 'keys.create': {
            if (!function_exists('b30_is_admin') || !b30_is_admin()) _json(['ok' => false, 'error' => 'forbidden'], 403);
            $b = _body();
            $key = 'b30k_' . bin2hex(random_bytes(20));
            $id  = 'k_' . bin2hex(random_bytes(6));
            $db  = b30_system_db();
            $db->prepare('INSERT INTO api_keys (id,user_id,label,key,scopes,origins,created_at,enabled) VALUES (:id,:uid,:l,:k,:s,:o,:t,1)')
               ->execute([
                  ':id' => $id, ':uid' => $b['user_id'] ?? null,
                  ':l'  => $b['label'] ?? 'Untitled key',
                  ':k'  => $key,
                  ':s'  => json_encode($b['scopes']  ?? ['auth:read','auth:popup']),
                  ':o'  => json_encode($b['origins'] ?? ['*']),
                  ':t'  => time()
               ]);
            _json(['ok' => true, 'key' => $key, 'id' => $id]);
        }
        case 'keys.toggle': {
            if (!function_exists('b30_is_admin') || !b30_is_admin()) _json(['ok' => false, 'error' => 'forbidden'], 403);
            $b = _body();
            b30_system_db()->prepare('UPDATE api_keys SET enabled = :e WHERE id = :id')
                ->execute([':e' => !empty($b['enabled']) ? 1 : 0, ':id' => $b['id']]);
            _json(['ok' => true]);
        }
        case 'keys.delete': {
            if (!function_exists('b30_is_admin') || !b30_is_admin()) _json(['ok' => false, 'error' => 'forbidden'], 403);
            $b = _body();
            b30_system_db()->prepare('DELETE FROM api_keys WHERE id = :id')->execute([':id' => $b['id']]);
            _json(['ok' => true]);
        }

        default:
            _json(['ok' => false, 'error' => 'unknown action'], 404);
    }
} catch (Throwable $e) {
    _json(['ok' => false, 'error' => $e->getMessage()], 400);
}
