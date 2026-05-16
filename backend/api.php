<?php
/**
 * 2030B P2P Pairing — API endpoint
 *
 * All AJAX traffic goes here:
 *   POST /backend/api.php?op=register
 *   POST /backend/api.php?op=signin
 *   POST /backend/api.php?op=signout
 *   POST /backend/api.php?op=pairing.create     (multipart: buy_file, sell_file, ...)
 *   GET  /backend/api.php?op=pairing.list
 *   GET  /backend/api.php?op=user.me
 *   GET  /backend/api.php?op=fx
 *   POST /backend/api.php?op=admin.signin
 *   POST /backend/api.php?op=admin.signout
 *   GET  /backend/api.php?op=admin.users
 *   GET  /backend/api.php?op=admin.pending
 *   POST /backend/api.php?op=admin.review       (tx_id, status, notes)
 *   POST /backend/api.php?op=admin.config       (json blob)
 *   POST /backend/api.php?op=lvl4.request
 */
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$op = (string)($_GET['op'] ?? '');
$body = $_POST;
if (empty($body) && in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['POST','PUT','PATCH'])) {
    $raw = file_get_contents('php://input');
    $j = $raw ? json_decode($raw, true) : null;
    if (is_array($j)) $body = array_merge($body, $j);
}

if (!b30_rate_limit('api', 120, 60)) {
    b30_json(['error' => 'rate_limited'], 429);
}

// CSRF for state-changing endpoints
$mutating = preg_match('/(register|signin|signout|pairing\.create|admin\.|lvl4\.request)/', $op) === 1;
if ($mutating && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF'] ?? ($body['csrf'] ?? null);
    if (!b30_csrf_check($token)) b30_json(['error' => 'csrf'], 403);
}

try {
    switch ($op) {

        case 'csrf':
            b30_json(['token' => b30_csrf_token()]);

        case 'register': {
            $email = strtolower(trim((string)($body['email'] ?? '')));
            $pw    = (string)($body['password'] ?? '');
            $name  = trim((string)($body['full_name'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) {
                b30_json(['error' => 'invalid'], 400);
            }
            $pdo = b30_pdo();
            $st  = $pdo->prepare('SELECT id FROM users WHERE email=?');
            $st->execute([$email]);
            if ($st->fetch()) b30_json(['error' => 'exists'], 409);
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $st = $pdo->prepare(
                'INSERT INTO users(email,password_hash,full_name,country_code,language,phone,
                                   level,total_credits,total_pairings,created_at,last_login,is_active,role)
                 VALUES(?,?,?,?,?,?,0,0,0,?,?,1,"user")'
            );
            $st->execute([
                $email, $hash, $name ?: explode('@',$email)[0],
                (string)($body['country_code'] ?? 'TN'),
                (string)($body['language'] ?? 'en'),
                (string)($body['phone'] ?? ''),
                time(), time(),
            ]);
            $id = (int)$pdo->lastInsertId();
            b30_user_pdo($id); // create per-user DB
            $_SESSION['user_id'] = $id;
            b30_json(['ok' => true, 'user_id' => $id]);
        }

        case 'signin': {
            $email = strtolower(trim((string)($body['email'] ?? '')));
            $pw    = (string)($body['password'] ?? '');
            $st = b30_pdo()->prepare('SELECT * FROM users WHERE email=?');
            $st->execute([$email]);
            $u = $st->fetch();
            if (!$u || !password_verify($pw, $u['password_hash'])) {
                b30_json(['error' => 'bad_credentials'], 401);
            }
            $_SESSION['user_id'] = (int)$u['id'];
            b30_pdo()->prepare('UPDATE users SET last_login=? WHERE id=?')->execute([time(), (int)$u['id']]);
            b30_json(['ok' => true, 'user_id' => (int)$u['id']]);
        }

        case 'signout':
            $_SESSION = [];
            session_destroy();
            b30_json(['ok' => true]);

        case 'user.me': {
            $u = b30_require_user();
            unset($u['password_hash']);
            b30_json($u);
        }

        case 'pairing.create': {
            $u = b30_require_user();
            $platform = (string)($body['platform'] ?? '');
            if (!in_array($platform, ['binance','redotpay'], true)) b30_json(['error' => 'platform'], 400);

            $cfg = b30_config();
            $buyFeePct  = (float)($cfg['fees']['buy_fee_percent']  ?? 3);
            $sellFeePct = (float)($cfg['fees']['sell_fee_percent'] ?? 0);

            $buyUsdt  = (float)($body['buy_usdt']  ?? 0);
            $buyFiat  = (float)($body['buy_fiat']  ?? 0);
            $sellUsdt = (float)($body['sell_usdt'] ?? 0);
            $sellFiat = (float)($body['sell_fiat'] ?? 0);
            $cur      = (string)($body['buy_currency'] ?? $cfg['default_fiat_currency'] ?? 'TND');
            if ($buyUsdt <= 0 || $buyFiat <= 0 || $sellUsdt <= 0 || $sellFiat <= 0) {
                b30_json(['error' => 'invalid_amounts'], 400);
            }

            // Screenshot uploads
            $userUpDir = B30_UPLOADS . '/user_' . (int)$u['id'];
            if (!is_dir($userUpDir)) @mkdir($userUpDir, 0775, true);
            $maxBytes  = (int)(($cfg['screenshot_upload']['max_size_mb'] ?? 5)) * 1024 * 1024;
            $allowMime = $cfg['screenshot_upload']['allowed_mime'] ?? ['image/jpeg','image/png','image/webp'];

            $saveShot = function(string $field, string $txId) use ($userUpDir, $maxBytes, $allowMime): array {
                if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return [null, null];
                $f = $_FILES[$field];
                if ($f['size'] > $maxBytes) throw new RuntimeException('Screenshot too large');
                $info = @getimagesize($f['tmp_name']);
                if (!$info) throw new RuntimeException('Not a valid image');
                $mime = $info['mime'] ?? '';
                if (!in_array($mime, $allowMime, true)) throw new RuntimeException('Unsupported image type');
                $ext = match ($mime) { 'image/png' => '.png', 'image/webp' => '.webp', default => '.jpg' };
                $name = $txId . '_' . bin2hex(random_bytes(4)) . $ext;
                $dest = $userUpDir . '/' . $name;
                if (!move_uploaded_file($f['tmp_name'], $dest)) throw new RuntimeException('Upload failed');
                return [$name, $mime];
            };

            $pairingId = b30_uid('p_');
            $buyId  = b30_uid('t_');
            $sellId = b30_uid('t_');

            [$buyShot,  $buyMime]  = $saveShot('buy_file',  $buyId);
            [$sellShot, $sellMime] = $saveShot('sell_file', $sellId);

            $upDb = b30_user_pdo((int)$u['id']);
            $upDb->beginTransaction();
            try {
                $insTx = $upDb->prepare(
                    'INSERT INTO transactions(id,platform,type,amount_usdt,amount_fiat,fiat_currency,
                       fee_percent,fee_amount,total_cost,transaction_date,screenshot_path,screenshot_id,
                       status,paired_with,pairing_id,created_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,"pending",?,?,?)'
                );
                $buyTotal  = $buyFiat * (1 + $buyFeePct / 100);
                $sellTotal = $sellFiat * (1 - $sellFeePct / 100);
                $now = time();
                $insTx->execute([$buyId, $platform, 'buy', $buyUsdt, $buyFiat, $cur,
                    $buyFeePct, $buyFiat * $buyFeePct / 100, $buyTotal,
                    (int)($body['buy_date_ts'] ?? $now), $buyShot, $buyShot ? b30_uid('s_') : null,
                    $sellId, $pairingId, $now]);
                $insTx->execute([$sellId, $platform, 'sell', $sellUsdt, $sellFiat, $cur,
                    $sellFeePct, $sellFiat * $sellFeePct / 100, $sellTotal,
                    (int)($body['sell_date_ts'] ?? $now), $sellShot, $sellShot ? b30_uid('s_') : null,
                    $buyId, $pairingId, $now]);
                $profitFiat = $sellTotal - $buyTotal;
                $profitUsdt = $sellUsdt > 0 ? $profitFiat / ($sellFiat / $sellUsdt) : 0;
                $insP = $upDb->prepare(
                    'INSERT INTO pairings(pairing_id,platform,buy_tx_id,sell_tx_id,
                                          profit_usdt,profit_fiat,fiat_currency,credits_earned,status,pairing_date)
                     VALUES(?,?,?,?,?,?,?,0,"pending",?)'
                );
                $insP->execute([$pairingId, $platform, $buyId, $sellId, $profitUsdt, $profitFiat, $cur, $now]);
                $upDb->commit();
            } catch (Throwable $e) {
                $upDb->rollBack();
                throw $e;
            }

            b30_json(['ok' => true, 'pairing_id' => $pairingId]);
        }

        case 'pairing.list': {
            $u = b30_require_user();
            $rows = b30_user_pdo((int)$u['id'])->query(
                'SELECT * FROM pairings ORDER BY pairing_date DESC'
            )->fetchAll();
            b30_json(['pairings' => $rows]);
        }

        case 'fx':
            b30_json(b30_exchange_rates(!empty($_GET['force'])));

        // ----- Admin -----
        case 'admin.signin': {
            $cfg = b30_config();
            $expected = (string)($cfg['admin_password'] ?? 'admin2030');
            if (!hash_equals($expected, (string)($body['password'] ?? ''))) {
                b30_json(['error' => 'wrong'], 401);
            }
            $_SESSION['is_admin'] = true;
            b30_json(['ok' => true]);
        }

        case 'admin.signout':
            $_SESSION['is_admin'] = false;
            b30_json(['ok' => true]);

        case 'admin.users': {
            b30_require_admin();
            $rows = b30_pdo()->query(
                'SELECT id,email,full_name,country_code,language,level,total_credits,total_pairings,
                        redotpay_ops_count,binance_ops_count,redotpay_pairings_count,binance_pairings_count,
                        created_at,last_login,is_active,role FROM users ORDER BY created_at DESC'
            )->fetchAll();
            b30_json(['users' => $rows]);
        }

        case 'admin.pending': {
            b30_require_admin();
            $out = [];
            $users = b30_pdo()->query('SELECT id,email FROM users')->fetchAll();
            foreach ($users as $u) {
                $up = b30_user_pdo((int)$u['id']);
                $rows = $up->query("SELECT * FROM transactions WHERE status='pending'")->fetchAll();
                foreach ($rows as $r) {
                    $r['user_id'] = (int)$u['id'];
                    $r['user_email'] = $u['email'];
                    $out[] = $r;
                }
            }
            b30_json(['pending' => $out]);
        }

        case 'admin.review': {
            b30_require_admin();
            $uid    = (int)($body['user_id'] ?? 0);
            $txId   = (string)($body['tx_id'] ?? '');
            $status = (string)($body['status'] ?? '');
            $notes  = (string)($body['notes'] ?? '');
            if (!$uid || !$txId || !in_array($status, ['verified','rejected'], true)) {
                b30_json(['error' => 'invalid'], 400);
            }
            $up = b30_user_pdo($uid);
            $up->prepare('UPDATE transactions SET status=?, verification_notes=? WHERE id=?')
               ->execute([$status, $notes, $txId]);
            $totals = b30_recompute_user($uid);

            b30_pdo()->prepare('INSERT INTO audit_log(actor,action,target,notes,created_at) VALUES(?,?,?,?,?)')
                ->execute(['admin', 'review:' . $status, $txId, $notes, time()]);
            b30_json(['ok' => true, 'totals' => $totals]);
        }

        case 'admin.config': {
            b30_require_admin();
            $raw = $body['config'] ?? null;
            if (!is_array($raw)) {
                $raw = json_decode((string)($body['json'] ?? ''), true);
                if (!is_array($raw)) b30_json(['error' => 'invalid_json'], 400);
            }
            b30_save_config($raw);
            b30_json(['ok' => true]);
        }

        case 'lvl4.request': {
            $st = b30_pdo()->prepare(
                'INSERT INTO lvl4_waitlist(email,full_name,reason,created_at) VALUES(?,?,?,?)'
            );
            $st->execute([
                strtolower(trim((string)($body['email'] ?? ''))),
                trim((string)($body['full_name'] ?? '')),
                trim((string)($body['reason'] ?? '')),
                time(),
            ]);
            b30_json(['ok' => true]);
        }

        default:
            b30_json(['error' => 'unknown_op'], 404);
    }
} catch (Throwable $e) {
    b30_json(['error' => 'server', 'message' => $e->getMessage()], 500);
}
