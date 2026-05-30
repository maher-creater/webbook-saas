<?php
/**
 * 2030B P2P Pairing — Public landing page (PHP entry point).
 *
 * This thin wrapper boots the session, injects CSRF token + current user
 * (if any), then streams the static HTML landing page. The same JS engine
 * powers the page; if a user is signed in via PHP session, the JS will
 * also see them via /backend/api.php?op=user.me.
 */
require __DIR__ . '/inc/bootstrap.php';

$user = b30_current_user();
$csrf = b30_csrf_token();
$cfg  = b30_config();

// Stream the static landing page, replacing the </head> with our injected
// session metadata (so client-side JS can prefer server-known auth state).
$html = (string)file_get_contents(B30_PUBLIC . '/index.html');
$inject = '<script>window.B30_SERVER = ' . json_encode([
    'csrf'    => $csrf,
    'user_id' => $user['id'] ?? null,
    'email'   => $user['email'] ?? null,
    'level'   => $user['level'] ?? 0,
    'api'     => '/backend/api.php',
    'platform_name' => $cfg['platform']['name'] ?? '2030B P2P Pairing',
], JSON_UNESCAPED_SLASHES) . ';</script></head>';
echo str_replace('</head>', $inject, $html);
