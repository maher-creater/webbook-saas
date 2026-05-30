<?php
/**
 * 2030B P2P Pairing — User dashboard (PHP entry point).
 * Requires an authenticated session.
 */
require __DIR__ . '/inc/bootstrap.php';
$user = b30_require_user();
$csrf = b30_csrf_token();

$html = (string)file_get_contents(B30_PUBLIC . '/pages/dashboard.html');
$inject = '<script>window.B30_SERVER = ' . json_encode([
    'csrf'    => $csrf,
    'user_id' => (int)$user['id'],
    'email'   => $user['email'],
    'level'   => (int)$user['level'],
    'api'     => '/backend/api.php',
], JSON_UNESCAPED_SLASHES) . ';</script></head>';
echo str_replace('</head>', $inject, $html);
