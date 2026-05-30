<?php
/**
 * 2030B P2P Pairing — API endpoint
 *
 * Conventions:
 *   GET  /backend/api.php?op=...      — read endpoints
 *   POST /backend/api.php?op=...      — mutating endpoints (require CSRF)
 *
 * Auth tiers:
 *   - public:           csrf, register, signin, fx, providers.list, security.public
 *   - user-session:     user.me, pairing.*, user.affiliate.*, user.keys.*
 *   - admin-session:    admin.* (each gated by a specific permission)
 *   - super-admin only: admin.admins.*, providers.save, security.save, penalties.cfg.save
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
// Expose JSON body to helpers (b30_request_api_key picks api_key from here too)
$GLOBALS['__b30_json_body'] = $body;

// ---- Auth-API gate ----------------------------------------------------------
// Auth endpoints (register/signin/admin.signin) require a valid X-API-Key when
// security.auth.api_key_required is true (default). The system_default key is
// seeded at install and managed by the super-admin.
$apiKeyRequiredOps = ['register', 'signin', 'admin.signin'];
if (in_array($op, $apiKeyRequiredOps, true)) {
    $providedKey = b30_request_api_key();
    $okKey = b30_check_api_key($providedKey, 'auth:public');
    if (!$okKey) {
        b30_json(['error' => 'api_key_invalid', 'hint' => 'Pass X-API-Key header with the system default or a valid user key'], 401);
    }
    // Stash for audit / scope checks
    $GLOBALS['__b30_api_key_row'] = $okKey;
}

// ---- Rate limit (per-bucket) -------------------------------------------------
$sec = b30_security();
$rl_api = (int)($sec['rate_limits']['api_per_minute']    ?? 120);
$rl_si  = (int)($sec['rate_limits']['signin_per_minute'] ?? 5);
$rl_su  = (int)($sec['rate_limits']['signup_per_minute'] ?? 3);
$rl_ad  = (int)($sec['rate_limits']['admin_per_minute']  ?? 30);
$bucket = 'api'; $max = $rl_api;
if (str_starts_with($op, 'admin.'))                       { $bucket = 'admin';  $max = $rl_ad; }
elseif ($op === 'signin' || $op === 'admin.signin')       { $bucket = 'signin'; $max = $rl_si; }
elseif ($op === 'register')                               { $bucket = 'signup'; $max = $rl_su; }
if (!b30_rate_limit($bucket, $max, 60)) {
    b30_json(['error' => 'rate_limited', 'bucket' => $bucket, 'max' => $max], 429);
}

// ---- CSRF for state-changing endpoints ---------------------------------------
// `install.*` is exempt from CSRF (no session yet) but is gated by an install token + .installed lock.
$mutating = preg_match('/(register|signin|signout|pairing\.|op\.|admin\.|lvl4\.|providers\.save|security\.save|penalties\.|user\.keys\.|user\.affiliate\.|user\.profile\.|secrets\.|subscription\.|tenant\.)/', $op) === 1;
$isInstall = str_starts_with($op, 'install.');
if ($mutating && !$isInstall && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF'] ?? ($body['csrf'] ?? null);
    if (!b30_csrf_check($token)) b30_json(['error' => 'csrf'], 403);
}

// ---- Schema validators (server-side) ----------------------------------------
function b30_validate_schema(array $data, array $schema): array {
    $errors = [];
    foreach ($schema as $field => $rules) {
        $v = $data[$field] ?? null;
        $required = $rules['required'] ?? false;
        if ($required && ($v === null || $v === '')) { $errors[$field] = 'required'; continue; }
        if ($v === null || $v === '') continue;
        $type = $rules['type'] ?? 'string';
        switch ($type) {
            case 'email':
                if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[$field] = 'email';
                break;
            case 'int':
                if (!is_numeric($v) || (int)$v != $v) $errors[$field] = 'int';
                break;
            case 'number':
                if (!is_numeric($v)) $errors[$field] = 'number';
                break;
            case 'bool':
                // accept truthy/falsy
                break;
            case 'array':
                if (!is_array($v)) $errors[$field] = 'array';
                break;
            case 'json':
                if (!is_array($v) && !is_object($v)) {
                    $decoded = is_string($v) ? json_decode($v, true) : null;
                    if (!is_array($decoded)) $errors[$field] = 'json';
                }
                break;
            case 'enum':
                if (!in_array((string)$v, $rules['values'] ?? [], true)) $errors[$field] = 'enum';
                break;
            case 'string':
            default:
                if (!is_string($v) && !is_numeric($v)) $errors[$field] = 'string';
                break;
        }
        if (!isset($errors[$field])) {
            $vLen = function_exists('mb_strlen') ? mb_strlen((string)$v) : strlen((string)$v);
            if (isset($rules['minLength']) && $vLen < (int)$rules['minLength']) $errors[$field] = 'minLength';
            if (isset($rules['maxLength']) && $vLen > (int)$rules['maxLength']) $errors[$field] = 'maxLength';
            if (isset($rules['min'])       && (float)$v < (float)$rules['min'])              $errors[$field] = 'min';
            if (isset($rules['max'])       && (float)$v > (float)$rules['max'])              $errors[$field] = 'max';
            if (isset($rules['regex'])     && !preg_match($rules['regex'], (string)$v))      $errors[$field] = 'regex';
        }
    }
    return $errors;
}

// ---- Helpers (local) ---------------------------------------------------------
function b30_strip_secret(array $u): array { unset($u['password_hash'], $u['email_verify_code']); return $u; }

try {
    switch ($op) {

        // =============================================================
        //                       PUBLIC
        // =============================================================
        case 'csrf':
            b30_json(['token' => b30_csrf_token()]);

        case 'config.public': {
            $cfg = b30_config();
            // Strip sensitive bits before returning to clients
            unset(
                $cfg['super_admin'],
                $cfg['admin_password'],
                $cfg['super_admin_email'],
                $cfg['super_admin_password'],
                $cfg['super_admin_name']
            );
            // Surface the system-default Auth API key so the front-end can pin
            // sign-in/sign-up requests with X-API-Key. The super-admin can
            // disable api_key_required in security.json if desired.
            $sec = b30_security();
            $needKey = (bool)($sec['auth']['api_key_required'] ?? true);
            $dk = b30_default_api_key();
            $cfg['auth_api'] = [
                'enabled'           => true,
                'api_key_required'  => $needKey,
                'default_key'       => ($needKey && $dk && (int)$dk['enabled'] === 1) ? $dk['key'] : null,
                'endpoint'          => '/backend/api.php',
            ];
            b30_json($cfg);
        }

        case 'providers.list':
            // Read-only public list (everyone needs to know enabled providers)
            b30_json(b30_providers());

        case 'security.public': {
            // Only expose the bits the client legitimately needs
            $s = b30_security();
            $pub = [
                'auth' => [
                    'password_min_length' => $s['auth']['password_min_length'] ?? 8,
                    'require_2fa_for_admin' => (bool)($s['auth']['require_2fa_for_admin'] ?? true),
                ],
                'upload' => [
                    'max_size_mb' => $s['upload']['max_size_mb'] ?? 5,
                    'allowed_mime' => $s['upload']['allowed_mime'] ?? ['image/jpeg','image/png','image/webp'],
                ],
                'csrf' => ['enabled' => (bool)($s['csrf']['enabled'] ?? true)],
            ];
            b30_json($pub);
        }

        case 'penalties.cfg.public':
            // Public rules list (for showing the user what NOT to do)
            b30_json(b30_penalties_cfg());

        case 'ecosystem':
            b30_json(b30_ecosystem_cfg());

        case 'fx':
            b30_json(b30_exchange_rates(!empty($_GET['force'])));

        // =============================================================
        //                       AUTH (user)
        // =============================================================
        case 'register': {
            $email = strtolower(trim((string)($body['email'] ?? '')));
            $pw    = (string)($body['password'] ?? '');
            $name  = trim((string)($body['full_name'] ?? ''));
            $minPw = (int)($sec['auth']['password_min_length'] ?? 8);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < $minPw) {
                b30_json(['error' => 'invalid', 'min_password' => $minPw], 400);
            }
            $pdo = b30_pdo();
            $st  = $pdo->prepare('SELECT id FROM users WHERE email=?');
            $st->execute([$email]);
            if ($st->fetch()) b30_json(['error' => 'exists'], 409);

            $cost = (int)($sec['auth']['bcrypt_cost'] ?? 12);
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => $cost]);
            $verifyCode = bin2hex(random_bytes(8));
            $st = $pdo->prepare(
                'INSERT INTO users(email,password_hash,full_name,country_code,language,phone,
                                   level,total_credits,total_pairings,created_at,last_login,is_active,role,
                                   email_verify_code,email_verified,auth_provider)
                 VALUES(?,?,?,?,?,?,0,0,0,?,?,1,"user",?,0,"password")'
            );
            $st->execute([
                $email, $hash, $name ?: explode('@',$email)[0],
                (string)($body['country_code'] ?? 'TN'),
                (string)($body['language'] ?? 'en'),
                (string)($body['phone'] ?? ''),
                time(), time(),
                $verifyCode,
            ]);
            $id = (int)$pdo->lastInsertId();
            // Assign a referral code to this new user
            $myCode = b30_gen_ref_code($id);
            $pdo->prepare('UPDATE users SET ref_code = ? WHERE id = ?')->execute([$myCode, $id]);

            // Honor inbound ref_code (if any)
            $refCode = trim((string)($body['ref_code'] ?? ''));
            if ($refCode) {
                $r = $pdo->prepare('SELECT id FROM users WHERE ref_code = ? AND id <> ?');
                $r->execute([$refCode, $id]);
                $referrer = $r->fetch();
                if ($referrer) {
                    $pdo->prepare('UPDATE users SET referred_by = ? WHERE id = ?')->execute([(int)$referrer['id'], $id]);
                    $pdo->prepare('INSERT OR IGNORE INTO referrals(referrer_id,referee_id,ref_code,created_at) VALUES(?,?,?,?)')
                        ->execute([(int)$referrer['id'], $id, $refCode, time()]);
                }
            }

            b30_user_pdo($id); // create per-user DB
            $_SESSION['user_id'] = $id;
            b30_audit('user:' . $id, 'register', $email);
            b30_json(['ok' => true, 'user_id' => $id, 'ref_code' => $myCode, 'verify_code' => $verifyCode]);
        }

        case 'signin': {
            $email = strtolower(trim((string)($body['email'] ?? '')));
            $pw    = (string)($body['password'] ?? '');
            $st = b30_pdo()->prepare('SELECT * FROM users WHERE email=?');
            $st->execute([$email]);
            $u = $st->fetch();
            if (!$u || !password_verify($pw, $u['password_hash'])) {
                b30_audit('anon', 'signin_fail', $email);
                b30_json(['error' => 'bad_credentials'], 401);
            }
            if (!($u['is_active'] ?? 1)) b30_json(['error' => 'disabled'], 403);
            $_SESSION['user_id'] = (int)$u['id'];
            b30_pdo()->prepare('UPDATE users SET last_login=? WHERE id=?')->execute([time(), (int)$u['id']]);
            b30_audit('user:' . $u['id'], 'signin', $email);
            b30_json(['ok' => true, 'user_id' => (int)$u['id']]);
        }

        case 'signout':
            $uid = $_SESSION['user_id'] ?? null;
            $_SESSION = []; session_destroy();
            if ($uid) b30_audit('user:' . $uid, 'signout');
            b30_json(['ok' => true]);

        case 'user.me': {
            $u = b30_require_user();
            b30_json(b30_strip_secret($u));
        }

        case 'user.profile.update': {
            $u = b30_require_user();
            $fields = ['full_name','country_code','language','phone','binance_id','redotpay_id'];
            $sets = []; $vals = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $body)) { $sets[] = "$f = ?"; $vals[] = trim((string)$body[$f]); }
            }
            if (!$sets) b30_json(['error' => 'nothing']);
            $vals[] = (int)$u['id'];
            b30_pdo()->prepare('UPDATE users SET ' . implode(',', $sets) . ', profile_completed = 1 WHERE id = ?')->execute($vals);
            b30_audit('user:' . $u['id'], 'profile_update');
            b30_json(['ok' => true]);
        }

        case 'user.verify_email': {
            $u = b30_require_user();
            $code = trim((string)($body['code'] ?? ''));
            if ($code && hash_equals((string)($u['email_verify_code'] ?? ''), $code)) {
                b30_pdo()->prepare('UPDATE users SET email_verified=1, email_verify_code=NULL WHERE id=?')->execute([(int)$u['id']]);
                b30_audit('user:' . $u['id'], 'email_verified');
                b30_json(['ok' => true]);
            }
            b30_json(['error' => 'bad_code'], 400);
        }

        // =============================================================
        //                       PAIRING / OPS
        // =============================================================
        case 'pairing.create': {
            $u = b30_require_user();
            $platform = strtolower((string)($body['platform'] ?? ''));
            if (!b30_provider_enabled($platform)) b30_json(['error' => 'platform_disabled', 'platform' => $platform], 400);

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
            $maxBytes  = (int)(($sec['upload']['max_size_mb'] ?? 5)) * 1024 * 1024;
            $allowMime = $sec['upload']['allowed_mime'] ?? ($cfg['screenshot_upload']['allowed_mime'] ?? ['image/jpeg','image/png','image/webp']);

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

            b30_audit('user:' . $u['id'], 'pairing.create', $pairingId, $platform);
            b30_json(['ok' => true, 'pairing_id' => $pairingId]);
        }

        case 'pairing.list': {
            $u = b30_require_user();
            $rows = b30_user_pdo((int)$u['id'])->query(
                'SELECT * FROM pairings ORDER BY pairing_date DESC'
            )->fetchAll();
            b30_json(['pairings' => $rows]);
        }

        case 'op.create': {
            $u = b30_require_user();
            $platform = strtolower((string)($body['platform'] ?? ''));
            $type     = (string)($body['type'] ?? '');
            if (!b30_provider_enabled($platform)) b30_json(['error' => 'platform_disabled'], 400);
            if (!in_array($type, ['buy','sell'], true)) b30_json(['error' => 'bad_type'], 400);

            $userUpDir = B30_UPLOADS . '/user_' . (int)$u['id'];
            if (!is_dir($userUpDir)) @mkdir($userUpDir, 0775, true);
            $maxBytes  = (int)(($sec['upload']['max_size_mb'] ?? 5)) * 1024 * 1024;
            $allowMime = $sec['upload']['allowed_mime'] ?? ['image/jpeg','image/png','image/webp'];
            $txId = b30_uid('t_');
            $shot = null;
            if (isset($_FILES['op_file']) && $_FILES['op_file']['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES['op_file'];
                if ($f['size'] > $maxBytes) b30_json(['error' => 'too_big'], 400);
                $info = @getimagesize($f['tmp_name']);
                if (!$info || !in_array($info['mime'] ?? '', $allowMime, true)) b30_json(['error' => 'bad_mime'], 400);
                $ext = match ($info['mime']) { 'image/png' => '.png', 'image/webp' => '.webp', default => '.jpg' };
                $shot = $txId . '_' . bin2hex(random_bytes(4)) . $ext;
                move_uploaded_file($f['tmp_name'], $userUpDir . '/' . $shot);
            }
            $up = b30_user_pdo((int)$u['id']);
            $up->prepare(
                'INSERT INTO transactions(id,platform,type,amount_usdt,amount_fiat,fiat_currency,
                   fee_percent,fee_amount,total_cost,transaction_date,screenshot_path,screenshot_id,
                   status,created_at)
                 VALUES(?,?,?,?,?,?,0,0,0,?,?,?,"pending",?)'
            )->execute([
                $txId, $platform, $type,
                (float)($body['amount_usdt'] ?? 0),
                (float)($body['amount_fiat'] ?? 0),
                (string)($body['fiat_currency'] ?? 'USD'),
                (int)($body['transaction_date'] ?? time()),
                $shot, $shot ? b30_uid('s_') : null,
                time()
            ]);
            b30_audit('user:' . $u['id'], 'op.create', $txId, $platform . '/' . $type);
            b30_json(['ok' => true, 'tx_id' => $txId]);
        }

        // =============================================================
        //                       USER · AFFILIATE
        // =============================================================
        case 'user.affiliate.my': {
            $u = b30_require_user();
            $pdo = b30_pdo();
            $refs = $pdo->prepare('SELECT r.*, u.email AS referee_email, u.full_name AS referee_name, u.created_at AS referee_joined FROM referrals r JOIN users u ON u.id = r.referee_id WHERE r.referrer_id = ? ORDER BY r.created_at DESC');
            $refs->execute([(int)$u['id']]);
            $rewards = $pdo->prepare('SELECT * FROM affiliate_rewards WHERE referrer_id = ? ORDER BY created_at DESC');
            $rewards->execute([(int)$u['id']]);
            b30_json([
                'ref_code'  => $u['ref_code'],
                'referrals' => $refs->fetchAll(),
                'rewards'   => $rewards->fetchAll(),
                'affiliate_credits' => (int)($u['affiliate_credits'] ?? 0),
            ]);
        }

        // =============================================================
        //                       USER · API KEYS
        // =============================================================
        case 'user.keys.list': {
            $u = b30_require_user();
            $st = b30_pdo()->prepare('SELECT id,label,key,scopes,origins,enabled,created_at,last_used FROM api_keys WHERE user_id = ? ORDER BY created_at DESC');
            $st->execute([(int)$u['id']]);
            $rows = $st->fetchAll();
            foreach ($rows as &$r) {
                $r['scopes']  = json_decode($r['scopes']  ?? '[]', true) ?: [];
                $r['origins'] = json_decode($r['origins'] ?? '[]', true) ?: [];
            }
            b30_json(['keys' => $rows]);
        }
        case 'user.keys.create': {
            $u = b30_require_user();
            $id    = b30_uid('k_');
            $key   = 'b30k_' . bin2hex(random_bytes(16));
            $label = trim((string)($body['label'] ?? 'My key'));
            $origins = $body['origins'] ?? ['*'];
            $scopes  = $body['scopes']  ?? ['auth:read','auth:popup'];
            if (is_string($origins)) $origins = array_map('trim', explode(',', $origins));
            if (is_string($scopes))  $scopes  = array_map('trim', explode(',', $scopes));
            b30_pdo()->prepare('INSERT INTO api_keys(id,user_id,label,key,scopes,origins,enabled,created_at) VALUES(?,?,?,?,?,?,1,?)')
                ->execute([$id, (int)$u['id'], $label, $key, json_encode($scopes), json_encode($origins), time()]);
            b30_audit('user:' . $u['id'], 'keys.create', $id);
            b30_json(['ok' => true, 'id' => $id, 'key' => $key]);
        }
        case 'user.keys.update': {
            $u = b30_require_user();
            $id = (string)($body['id'] ?? '');
            $st = b30_pdo()->prepare('SELECT * FROM api_keys WHERE id = ? AND user_id = ?');
            $st->execute([$id, (int)$u['id']]);
            if (!$st->fetch()) b30_json(['error' => 'not_found'], 404);
            $label = (string)($body['label'] ?? '');
            $enabled = (int)!empty($body['enabled']);
            $origins = $body['origins'] ?? null;
            if (is_string($origins)) $origins = array_map('trim', explode(',', $origins));
            $sets = ['label = ?', 'enabled = ?']; $vals = [$label, $enabled];
            if (is_array($origins)) { $sets[] = 'origins = ?'; $vals[] = json_encode($origins); }
            $vals[] = $id; $vals[] = (int)$u['id'];
            b30_pdo()->prepare('UPDATE api_keys SET ' . implode(',', $sets) . ' WHERE id = ? AND user_id = ?')->execute($vals);
            b30_audit('user:' . $u['id'], 'keys.update', $id);
            b30_json(['ok' => true]);
        }
        case 'user.keys.delete': {
            $u = b30_require_user();
            $id = (string)($body['id'] ?? '');
            b30_pdo()->prepare('DELETE FROM api_keys WHERE id = ? AND user_id = ?')->execute([$id, (int)$u['id']]);
            b30_audit('user:' . $u['id'], 'keys.delete', $id);
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN AUTH
        // =============================================================
        case 'admin.signin': {
            $email = strtolower(trim((string)($body['email'] ?? '')));
            $pw    = (string)($body['password'] ?? '');
            // Legacy fallback: bare config password (no email)
            if (!$email) {
                $cfg = b30_config();
                $expected = (string)($cfg['admin_password'] ?? '');
                if ($expected && hash_equals($expected, $pw)) {
                    $_SESSION['is_admin'] = true;
                    b30_audit('legacy_admin', 'signin');
                    b30_json(['ok' => true, 'legacy' => true]);
                }
                b30_json(['error' => 'wrong'], 401);
            }
            $st = b30_pdo()->prepare('SELECT * FROM admins WHERE email = ?');
            $st->execute([$email]);
            $a = $st->fetch();
            if (!$a || !password_verify($pw, $a['password_hash'])) {
                b30_audit('anon', 'admin.signin_fail', $email);
                b30_json(['error' => 'wrong'], 401);
            }
            if (!($a['enabled'] ?? 1)) b30_json(['error' => 'disabled'], 403);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['is_admin'] = true;
            b30_pdo()->prepare('UPDATE admins SET last_login = ? WHERE id = ?')->execute([time(), (int)$a['id']]);
            b30_audit('admin:' . $a['id'], 'signin', $email);
            $a['permissions'] = json_decode($a['permissions'] ?? '[]', true) ?: [];
            unset($a['password_hash']);
            b30_json(['ok' => true, 'admin' => $a]);
        }

        case 'admin.signout':
            $aid = $_SESSION['admin_id'] ?? null;
            $_SESSION['is_admin'] = false;
            unset($_SESSION['admin_id']);
            if ($aid) b30_audit('admin:' . $aid, 'signout');
            b30_json(['ok' => true]);

        case 'admin.me': {
            $a = b30_require_admin();
            unset($a['password_hash']);
            b30_json($a);
        }

        // =============================================================
        //                       ADMIN · USERS
        // =============================================================
        case 'admin.users': {
            b30_require_admin('manage_users');
            $rows = b30_pdo()->query(
                'SELECT id,email,full_name,country_code,language,level,total_credits,total_pairings,
                        redotpay_ops_count,binance_ops_count,redotpay_pairings_count,binance_pairings_count,
                        ref_code,referred_by,affiliate_credits,
                        created_at,last_login,is_active,role,email_verified FROM users ORDER BY created_at DESC'
            )->fetchAll();
            b30_json(['users' => $rows]);
        }

        case 'admin.user.toggle': {
            $a = b30_require_admin('manage_users');
            $uid = (int)($body['user_id'] ?? 0);
            if (!$uid) b30_json(['error' => 'invalid'], 400);
            b30_pdo()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
            b30_audit('admin:' . $a['id'], 'user.toggle', (string)$uid);
            b30_json(['ok' => true]);
        }

        case 'admin.pending': {
            b30_require_admin('verify_transactions');
            $out = [];
            $users = b30_pdo()->query('SELECT id,email,full_name FROM users')->fetchAll();
            foreach ($users as $u) {
                $up = b30_user_pdo((int)$u['id']);
                $rows = $up->query("SELECT * FROM transactions WHERE status='pending'")->fetchAll();
                foreach ($rows as $r) {
                    $r['user_id'] = (int)$u['id'];
                    $r['user_email'] = $u['email'];
                    $r['user_name']  = $u['full_name'];
                    $out[] = $r;
                }
            }
            b30_json(['pending' => $out]);
        }

        case 'admin.pairings.all': {
            b30_require_admin('verify_transactions');
            $out = [];
            $users = b30_pdo()->query('SELECT id,email,full_name FROM users')->fetchAll();
            foreach ($users as $u) {
                $up = b30_user_pdo((int)$u['id']);
                $pairs = $up->query('SELECT * FROM pairings ORDER BY pairing_date DESC')->fetchAll();
                $txs   = $up->query('SELECT * FROM transactions')->fetchAll();
                foreach ($pairs as $p) {
                    $p['user_id'] = (int)$u['id'];
                    $p['user_email'] = $u['email'];
                    $p['legs'] = array_values(array_filter($txs, fn($x) => $x['pairing_id'] === $p['pairing_id']));
                    $out[] = $p;
                }
            }
            b30_json(['pairings' => $out]);
        }

        case 'admin.review': {
            $a = b30_require_admin('verify_transactions');
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
            b30_audit('admin:' . $a['id'], 'review:' . $status, $txId, $notes);
            b30_json(['ok' => true, 'totals' => $totals]);
        }

        case 'admin.config': {
            b30_require_admin('edit_config');
            $raw = $body['config'] ?? null;
            if (!is_array($raw)) {
                $raw = json_decode((string)($body['json'] ?? ''), true);
                if (!is_array($raw)) b30_json(['error' => 'invalid_json'], 400);
            }
            b30_save_config($raw);
            b30_audit('admin', 'config.save');
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN · SUB-ADMINS CRUD (super only)
        // =============================================================
        case 'admin.admins.list': {
            b30_require_admin('manage_admins');
            $rows = b30_pdo()->query('SELECT id,email,full_name,role,permissions,parent_id,enabled,created_at,last_login FROM admins ORDER BY created_at ASC')->fetchAll();
            foreach ($rows as &$r) $r['permissions'] = json_decode($r['permissions'] ?? '[]', true) ?: [];
            b30_json(['admins' => $rows]);
        }
        case 'admin.admins.create': {
            $me = b30_require_admin('manage_admins');
            $email = strtolower(trim((string)($body['email'] ?? '')));
            $pw    = (string)($body['password'] ?? '');
            $name  = trim((string)($body['full_name'] ?? ''));
            $perms = $body['permissions'] ?? [];
            if (is_string($perms)) $perms = array_map('trim', explode(',', $perms));
            $minPw = (int)($sec['auth']['password_min_length'] ?? 8);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < $minPw || !$name) {
                b30_json(['error' => 'invalid'], 400);
            }
            $cost = (int)($sec['auth']['bcrypt_cost'] ?? 12);
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => $cost]);
            try {
                b30_pdo()->prepare('INSERT INTO admins(email,password_hash,full_name,role,permissions,parent_id,enabled,created_at) VALUES(?,?,?,?,?,?,1,?)')
                    ->execute([$email, $hash, $name, 'admin', json_encode($perms), (int)$me['id'], time()]);
            } catch (PDOException $e) {
                b30_json(['error' => 'exists'], 409);
            }
            b30_audit('admin:' . $me['id'], 'admins.create', $email);
            b30_json(['ok' => true]);
        }
        case 'admin.admins.update': {
            $me = b30_require_admin('manage_admins');
            $id    = (int)($body['id'] ?? 0);
            if (!$id) b30_json(['error' => 'invalid'], 400);
            $sets = []; $vals = [];
            if (isset($body['full_name'])) { $sets[]='full_name=?';   $vals[]=trim((string)$body['full_name']); }
            if (isset($body['permissions'])) {
                $p = $body['permissions']; if (is_string($p)) $p = array_map('trim', explode(',', $p));
                $sets[]='permissions=?'; $vals[]=json_encode($p);
            }
            if (isset($body['enabled']))  { $sets[]='enabled=?';     $vals[]=(int)!empty($body['enabled']); }
            if (!empty($body['password'])) {
                $cost = (int)($sec['auth']['bcrypt_cost'] ?? 12);
                $sets[]='password_hash=?'; $vals[]=password_hash((string)$body['password'], PASSWORD_BCRYPT, ['cost'=>$cost]);
            }
            if (!$sets) b30_json(['error' => 'nothing']);
            $vals[] = $id;
            b30_pdo()->prepare('UPDATE admins SET ' . implode(',', $sets) . ' WHERE id = ? AND role <> "super_admin"')->execute($vals);
            b30_audit('admin:' . $me['id'], 'admins.update', (string)$id);
            b30_json(['ok' => true]);
        }
        case 'admin.admins.delete': {
            $me = b30_require_admin('manage_admins');
            $id = (int)($body['id'] ?? 0);
            if (!$id) b30_json(['error' => 'invalid'], 400);
            b30_pdo()->prepare('DELETE FROM admins WHERE id = ? AND role <> "super_admin"')->execute([$id]);
            b30_audit('admin:' . $me['id'], 'admins.delete', (string)$id);
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN · PROVIDERS (super only)
        // =============================================================
        case 'admin.providers.save': {
            $a = b30_require_admin('manage_ecosystem');
            // require super_admin for global file write
            if (($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $next = $body['providers_file'] ?? null;
            if (!is_array($next)) b30_json(['error' => 'invalid'], 400);
            if (!b30_json_save(B30_PROVIDERS_FILE, $next)) b30_json(['error' => 'write_failed'], 500);
            b30_audit('admin:' . $a['id'], 'providers.save');
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN · SECURITY (super only)
        // =============================================================
        case 'admin.security.save': {
            $a = b30_require_admin('edit_config');
            if (($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $next = $body['security_file'] ?? null;
            if (!is_array($next)) b30_json(['error' => 'invalid'], 400);
            if (!b30_json_save(B30_SECURITY_FILE, $next)) b30_json(['error' => 'write_failed'], 500);
            b30_audit('admin:' . $a['id'], 'security.save');
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN · PENALTIES
        // =============================================================
        case 'admin.penalties.list': {
            b30_require_admin('manage_users');
            $rows = b30_pdo()->query('SELECT * FROM penalties ORDER BY created_at DESC')->fetchAll();
            b30_json(['penalties' => $rows]);
        }
        case 'admin.penalties.issue': {
            $a = b30_require_admin('manage_users');
            $type    = (string)($body['target_type'] ?? 'user');
            $tid     = (int)($body['target_id'] ?? 0);
            $rule    = (string)($body['rule_code'] ?? '');
            $reason  = (string)($body['reason'] ?? '');
            $evid    = (string)($body['evidence'] ?? '');
            if (!$tid || !$rule) b30_json(['error' => 'invalid'], 400);

            $cfg  = b30_penalties_cfg();
            $rdef = $cfg['rules'][$rule] ?? null;
            $sev  = (string)($body['severity'] ?? ($rdef['severity'] ?? 'minor'));
            $delta = (int)($body['credits_delta'] ?? ($rdef['credits_delta'] ?? 0));

            $pdo = b30_pdo();
            $pdo->prepare('INSERT INTO penalties(target_type,target_id,rule_code,severity,credits_delta,reason,evidence,status,issued_by,created_at) VALUES(?,?,?,?,?,?,?,"open",?,?)')
                ->execute([$type, $tid, $rule, $sev, $delta, $reason, $evid, 'admin:' . $a['id'], time()]);
            if ($type === 'user' && $delta < 0) {
                b30_adjust_user_credits($tid, $delta, 'penalty:' . $rule);
            }
            b30_audit('admin:' . $a['id'], 'penalty.issue', "$type:$tid", $rule);
            b30_json(['ok' => true]);
        }
        case 'admin.penalties.close': {
            $a = b30_require_admin('manage_users');
            $id = (int)($body['id'] ?? 0);
            $status = (string)($body['status'] ?? 'closed');
            if (!in_array($status, ['closed','reverted','appealed'], true)) b30_json(['error' => 'bad_status'], 400);
            b30_pdo()->prepare('UPDATE penalties SET status=?, closed_at=? WHERE id=?')
                ->execute([$status, time(), $id]);
            // If reverted, restore credits
            if ($status === 'reverted') {
                $row = b30_pdo()->prepare('SELECT * FROM penalties WHERE id=?');
                $row->execute([$id]);
                $p = $row->fetch();
                if ($p && $p['target_type'] === 'user' && (int)$p['credits_delta'] < 0) {
                    b30_adjust_user_credits((int)$p['target_id'], -(int)$p['credits_delta'], 'penalty.revert:' . $p['rule_code']);
                }
            }
            b30_audit('admin:' . $a['id'], 'penalty.' . $status, (string)$id);
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN · AFFILIATE
        // =============================================================
        case 'admin.affiliate': {
            b30_require_admin('manage_affiliate');
            $pdo = b30_pdo();
            $refs   = $pdo->query('SELECT * FROM referrals ORDER BY created_at DESC')->fetchAll();
            $rwd    = $pdo->query('SELECT * FROM affiliate_rewards ORDER BY created_at DESC')->fetchAll();
            b30_json(['referrals' => $refs, 'rewards' => $rwd]);
        }

        // =============================================================
        //                       ADMIN · KEYS (everyone's)
        // =============================================================
        case 'admin.keys.list': {
            b30_require_admin('manage_keys');
            $rows = b30_pdo()->query('SELECT k.id,k.user_id,k.label,k.key,k.scopes,k.origins,k.enabled,k.created_at,k.last_used, u.email AS user_email FROM api_keys k LEFT JOIN users u ON u.id = k.user_id ORDER BY k.created_at DESC')->fetchAll();
            foreach ($rows as &$r) {
                $r['scopes']  = json_decode($r['scopes']  ?? '[]', true) ?: [];
                $r['origins'] = json_decode($r['origins'] ?? '[]', true) ?: [];
            }
            b30_json(['keys' => $rows]);
        }
        case 'admin.keys.toggle': {
            $a = b30_require_admin('manage_keys');
            $id = (string)($body['id'] ?? '');
            b30_pdo()->prepare('UPDATE api_keys SET enabled = 1 - enabled WHERE id = ?')->execute([$id]);
            b30_audit('admin:' . $a['id'], 'keys.toggle', $id);
            b30_json(['ok' => true]);
        }
        case 'admin.keys.delete': {
            $a = b30_require_admin('manage_keys');
            $id = (string)($body['id'] ?? '');
            // Never let an admin delete the system_default key. They can revoke (toggle off) but not destroy.
            $row = b30_pdo()->prepare('SELECT label FROM api_keys WHERE id = ?');
            $row->execute([$id]); $row = $row->fetch();
            if ($row && ($row['label'] ?? '') === 'system_default') {
                b30_json(['error' => 'cannot_delete_default'], 400);
            }
            b30_pdo()->prepare('DELETE FROM api_keys WHERE id = ?')->execute([$id]);
            b30_audit('admin:' . $a['id'], 'keys.delete', $id);
            b30_json(['ok' => true]);
        }

        // -- Default Auth API key (super-admin only) ------------------
        case 'admin.keys.default': {
            b30_require_admin('manage_keys');
            $dk = b30_default_api_key();
            b30_json(['key' => $dk]);
        }
        case 'admin.keys.default.rotate': {
            $a = b30_require_admin('manage_keys');
            $pdo = b30_pdo();
            $new = 'b30_pk_' . bin2hex(random_bytes(24));
            $st  = $pdo->prepare("UPDATE api_keys SET key = ?, last_used = NULL WHERE label = 'system_default'");
            $st->execute([$new]);
            if ($st->rowCount() === 0) {
                // No default key yet → create it
                b30_ensure_default_api_key();
                $pdo->prepare("UPDATE api_keys SET key = ? WHERE label = 'system_default'")->execute([$new]);
            }
            b30_audit('admin:' . $a['id'], 'keys.default.rotate', 'system_default');
            b30_json(['ok' => true, 'key' => $new]);
        }
        case 'admin.keys.default.toggle': {
            $a = b30_require_admin('manage_keys');
            b30_pdo()->exec("UPDATE api_keys SET enabled = 1 - enabled WHERE label = 'system_default'");
            b30_audit('admin:' . $a['id'], 'keys.default.toggle', 'system_default');
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       ADMIN · AUDIT
        // =============================================================
        case 'admin.audit': {
            b30_require_admin('edit_config');
            $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
            $rows = b30_pdo()->query('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ' . $limit)->fetchAll();
            b30_json(['audit' => $rows]);
        }

        case 'admin.ecosystem.save': {
            $a = b30_require_admin('manage_ecosystem');
            $next = $body['ecosystem_file'] ?? null;
            if (!is_array($next)) b30_json(['error' => 'invalid'], 400);
            if (!b30_json_save(B30_ECOSYSTEM_FILE, $next)) b30_json(['error' => 'write_failed'], 500);
            b30_audit('admin:' . $a['id'], 'ecosystem.save');
            b30_json(['ok' => true]);
        }

        // =============================================================
        //          ADMIN · UNIFIED JSON LOADER + SAVER (super only)
        // =============================================================
        // Used by /pages/super-admin/*.php pages to round-trip any config file.
        // file: one of  config | providers | security | penalties | ecosystem | secrets
        case 'admin.json.load': {
            $a = b30_require_admin('edit_config');
            $file = (string)($_GET['file'] ?? $body['file'] ?? '');
            $map = [
                'config'      => B30_CONFIG,
                'providers'   => B30_PROVIDERS_FILE,
                'security'    => B30_SECURITY_FILE,
                'penalties'   => B30_PENALTIES_FILE,
                'ecosystem'   => B30_ECOSYSTEM_FILE,
                'secrets'     => B30_SECRETS_FILE,
            ];
            if (!isset($map[$file])) b30_json(['error' => 'unknown_file'], 400);
            if ($file === 'secrets' && ($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $data = b30_json_load($map[$file], []);
            // secrets: never round-trip plain-text passwords; surface only presence flags + email
            if ($file === 'secrets') {
                $data = [
                    'super_admin_email'        => $data['super_admin_email'] ?? '',
                    'super_admin_password_set' => !empty($data['super_admin_password']),
                    'admin_password_set'       => !empty($data['admin_password']),
                ];
            }
            // strip the cached config secret keys too
            if ($file === 'config') {
                unset($data['admin_password'], $data['super_admin_email'], $data['super_admin_password'], $data['super_admin_name'], $data['super_admin']);
            }
            b30_json(['file' => $file, 'data' => $data]);
        }

        case 'admin.penalties.cfg.save': {
            $a = b30_require_admin('edit_config');
            if (($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $next = $body['penalties_file'] ?? null;
            if (!is_array($next)) b30_json(['error' => 'invalid'], 400);
            if (!isset($next['rules']) || !is_array($next['rules'])) b30_json(['error' => 'missing_rules'], 400);
            if (!b30_json_save(B30_PENALTIES_FILE, $next)) b30_json(['error' => 'write_failed'], 500);
            b30_audit('admin:' . $a['id'], 'penalties.cfg.save');
            b30_json(['ok' => true]);
        }

        case 'admin.secrets.save': {
            $a = b30_require_admin('edit_config');
            if (($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $current = b30_secrets();
            $email = trim((string)($body['super_admin_email'] ?? $current['super_admin_email'] ?? ''));
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) b30_json(['error' => 'invalid_email'], 400);

            $sec   = b30_security();
            $minPw = (int)($sec['auth']['password_min_length'] ?? 8);

            $newSuperPw = (string)($body['super_admin_password'] ?? '');
            $newAdminPw = (string)($body['admin_password'] ?? '');
            $next = ['admin_password' => $current['admin_password'] ?? '',
                     'super_admin_email' => $email,
                     'super_admin_password' => $current['super_admin_password'] ?? ''];

            if ($newSuperPw !== '') {
                if (strlen($newSuperPw) < $minPw) b30_json(['error' => 'password_too_short', 'min' => $minPw], 400);
                $next['super_admin_password'] = $newSuperPw;
                // Also update the live DB password
                $cost = (int)($sec['auth']['bcrypt_cost'] ?? 12);
                $hash = password_hash($newSuperPw, PASSWORD_BCRYPT, ['cost' => $cost]);
                b30_pdo()->prepare("UPDATE admins SET password_hash = ? WHERE role = 'super_admin'")->execute([$hash]);
            }
            if ($newAdminPw !== '') {
                if (strlen($newAdminPw) < $minPw) b30_json(['error' => 'admin_password_too_short', 'min' => $minPw], 400);
                $next['admin_password'] = $newAdminPw;
            }
            if ($email !== '') {
                b30_pdo()->prepare("UPDATE admins SET email = ? WHERE role = 'super_admin'")->execute([$email]);
            }
            if (!b30_save_secrets($next)) b30_json(['error' => 'write_failed'], 500);
            b30_audit('admin:' . $a['id'], 'secrets.save', '', ($newSuperPw ? 'super_pw_changed ' : '') . ($newAdminPw ? 'admin_pw_changed ' : '') . ($email ? 'email_changed' : ''));
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                     SAAS · SUBSCRIPTION PACKS
        // =============================================================
        case 'subscription.packs': {
            // Public — lists available packs (used by install wizard + landing)
            $rows = b30_pdo()->query("SELECT * FROM subscription_packs WHERE enabled = 1 ORDER BY sort_order ASC")->fetchAll();
            b30_json(['packs' => $rows]);
        }
        case 'subscription.current': {
            b30_require_admin();
            $t = b30_current_tenant();
            if (!$t) b30_json(['error' => 'no_tenant'], 404);
            $st = b30_pdo()->prepare("SELECT * FROM subscription_packs WHERE code = ?");
            $st->execute([$t['pack_code']]);
            $pack = $st->fetch();
            $hist = b30_pdo()->query("SELECT * FROM subscription_history ORDER BY created_at DESC LIMIT 50")->fetchAll();
            b30_json(['tenant' => $t, 'pack' => $pack ?: null, 'history' => $hist]);
        }
        case 'subscription.change': {
            $a = b30_require_admin('edit_config');
            if (($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $errs = b30_validate_schema($body, [
                'pack_code'     => ['required' => true, 'type' => 'string', 'maxLength' => 50],
                'billing_cycle' => ['required' => true, 'type' => 'enum', 'values' => ['monthly','yearly']],
            ]);
            if ($errs) b30_json(['error' => 'invalid', 'fields' => $errs], 400);
            $pack = (string)$body['pack_code'];
            $cycle = (string)$body['billing_cycle'];
            $st = b30_pdo()->prepare("SELECT * FROM subscription_packs WHERE code = ? AND enabled = 1");
            $st->execute([$pack]);
            $row = $st->fetch();
            if (!$row) b30_json(['error' => 'unknown_pack'], 404);
            $amount = $cycle === 'yearly' ? (float)$row['price_yearly'] : (float)$row['price_monthly'];
            $now = time();
            $expires = $cycle === 'yearly' ? ($now + 365 * 86400) : ($now + 30 * 86400);
            $t = b30_current_tenant();
            if (!$t) b30_json(['error' => 'no_tenant'], 404);
            b30_pdo()->prepare("UPDATE tenants SET pack_code = ?, billing_cycle = ?, pack_started_at = ?, pack_expires_at = ?, status = 'active' WHERE id = ?")
                ->execute([$pack, $cycle, $now, $expires, (int)$t['id']]);
            b30_pdo()->prepare("INSERT INTO subscription_history(tenant_id,pack_code,billing_cycle,amount,currency,event,notes,created_at) VALUES(?,?,?,?,?,?,?,?)")
                ->execute([(int)$t['id'], $pack, $cycle, $amount, (string)$row['currency'], 'change', 'manual change by super-admin', $now]);
            b30_audit('admin:' . $a['id'], 'subscription.change', $pack, $cycle);
            b30_json(['ok' => true, 'pack' => $pack, 'cycle' => $cycle, 'expires_at' => $expires]);
        }
        case 'tenant.update': {
            $a = b30_require_admin('edit_config');
            if (($a['role'] ?? '') !== 'super_admin') b30_json(['error' => 'super_only'], 403);
            $errs = b30_validate_schema($body, [
                'site_name'   => ['required' => true, 'type' => 'string', 'minLength' => 2, 'maxLength' => 120],
                'owner_email' => ['required' => true, 'type' => 'email'],
                'owner_name'  => ['required' => true, 'type' => 'string', 'minLength' => 2, 'maxLength' => 80],
            ]);
            if ($errs) b30_json(['error' => 'invalid', 'fields' => $errs], 400);
            $t = b30_current_tenant();
            if (!$t) b30_json(['error' => 'no_tenant'], 404);
            b30_pdo()->prepare("UPDATE tenants SET site_name = ?, owner_email = ?, owner_name = ? WHERE id = ?")
                ->execute([(string)$body['site_name'], strtolower((string)$body['owner_email']), (string)$body['owner_name'], (int)$t['id']]);
            b30_audit('admin:' . $a['id'], 'tenant.update', (string)$t['id']);
            b30_json(['ok' => true]);
        }

        // =============================================================
        //                       INSTALL WIZARD
        // =============================================================
        // No CSRF (no session yet). Gated by .installed lockfile — once written,
        // any further install.* call returns {"error":"already_installed"}.
        case 'install.status': {
            b30_json([
                'installed' => b30_is_installed(),
                'php_version' => PHP_VERSION,
                'sqlite_ok'  => extension_loaded('pdo_sqlite'),
                'writable'   => is_writable(B30_PUBLIC) && is_writable(B30_DB_DIR ?: B30_PUBLIC),
            ]);
        }
        case 'install.run': {
            if (b30_is_installed()) b30_json(['error' => 'already_installed'], 409);
            $errs = b30_validate_schema($body, [
                'site_name'        => ['required' => true, 'type' => 'string', 'minLength' => 2, 'maxLength' => 120],
                'super_admin_email'=> ['required' => true, 'type' => 'email'],
                'super_admin_name' => ['required' => true, 'type' => 'string', 'minLength' => 2, 'maxLength' => 80],
                'super_admin_password' => ['required' => true, 'type' => 'string', 'minLength' => 8, 'maxLength' => 128],
                'pack_code'        => ['required' => true, 'type' => 'enum', 'values' => ['free','pro','enterprise']],
                'billing_cycle'    => ['type' => 'enum', 'values' => ['monthly','yearly']],
            ]);
            if ($errs) b30_json(['error' => 'invalid', 'fields' => $errs], 400);

            // 1) Update platform name in config.json
            $cfg = b30_config();
            $cfg['platform']['name'] = (string)$body['site_name'];
            // Strip cached secret keys before saving
            unset($cfg['admin_password'], $cfg['super_admin_email'], $cfg['super_admin_password'], $cfg['super_admin_name']);
            b30_save_config($cfg);

            // 2) Write secrets.json
            $secrets = [
                'admin_password'       => (string)($body['admin_password'] ?? bin2hex(random_bytes(8))),
                'super_admin_email'    => strtolower((string)$body['super_admin_email']),
                'super_admin_password' => (string)$body['super_admin_password'],
            ];
            b30_save_secrets($secrets);

            // 3) Apply provider toggles if any
            if (!empty($body['providers_enabled']) && is_array($body['providers_enabled'])) {
                $enabled = array_map('strtolower', $body['providers_enabled']);
                $pf = b30_providers();
                if (!empty($pf['providers']) && is_array($pf['providers'])) {
                    foreach ($pf['providers'] as &$p) {
                        $p['enabled'] = in_array(strtolower((string)($p['code'] ?? '')), $enabled, true);
                    }
                    b30_json_save(B30_PROVIDERS_FILE, $pf);
                }
            }

            // 4) Force-recreate super-admin with the new credentials
            $pdo = b30_pdo();
            $sec = b30_security();
            $cost = (int)($sec['auth']['bcrypt_cost'] ?? 12);
            $hash = password_hash((string)$body['super_admin_password'], PASSWORD_BCRYPT, ['cost' => $cost]);
            $pdo->prepare("DELETE FROM admins WHERE role = 'super_admin'")->execute();
            $pdo->prepare("INSERT INTO admins(email,password_hash,full_name,role,permissions,enabled,created_at) VALUES(?,?,?,?,?,1,?)")
                ->execute([strtolower((string)$body['super_admin_email']), $hash, (string)$body['super_admin_name'], 'super_admin', json_encode(['*']), time()]);

            // 5) Apply subscription pack
            $cycle = (string)($body['billing_cycle'] ?? 'monthly');
            $now = time();
            $expires = $cycle === 'yearly' ? ($now + 365 * 86400) : ($now + 30 * 86400);
            $pdo->prepare("UPDATE tenants SET site_name = ?, owner_email = ?, owner_name = ?, pack_code = ?, billing_cycle = ?, pack_started_at = ?, pack_expires_at = ? WHERE slug = 'default'")
                ->execute([(string)$body['site_name'], strtolower((string)$body['super_admin_email']), (string)$body['super_admin_name'], (string)$body['pack_code'], $cycle, $now, $expires]);
            $pdo->prepare("INSERT INTO subscription_history(tenant_id,pack_code,billing_cycle,amount,currency,event,notes,created_at) SELECT id, pack_code, billing_cycle, 0, 'USD', 'install', 'initial install', ? FROM tenants WHERE slug='default'")
                ->execute([$now]);

            // 6) Seed default API key (idempotent)
            b30_ensure_default_api_key();

            // 7) Lock further installs
            b30_mark_installed([
                'site_name' => (string)$body['site_name'],
                'pack_code' => (string)$body['pack_code'],
                'owner_email' => strtolower((string)$body['super_admin_email']),
            ]);
            b30_audit('install', 'install.run', '', (string)$body['site_name']);
            b30_json(['ok' => true, 'redirect' => '/pages/admin.php']);
        }

        // =============================================================
        //                       LEVEL-4 WAITLIST
        // =============================================================
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
            b30_audit('anon', 'lvl4.request', (string)($body['email'] ?? ''));
            b30_json(['ok' => true]);
        }

        default:
            b30_json(['error' => 'unknown_op', 'op' => $op], 404);
    }
} catch (Throwable $e) {
    b30_json(['error' => 'server', 'message' => $e->getMessage()], 500);
}
