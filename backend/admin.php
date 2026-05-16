<?php
/**
 * 2030B P2P Pairing — Admin panel (PHP entry point).
 *
 * The admin sign-in form posts to /backend/api.php?op=admin.signin.
 * The page itself loads regardless of admin state; the JS hides/shows
 * gate vs panel based on the local admin session cookie, which is in
 * turn validated server-side on every admin.* API call.
 */
require __DIR__ . '/inc/bootstrap.php';
$csrf = b30_csrf_token();
$html = (string)file_get_contents(B30_PUBLIC . '/pages/admin.html');
$inject = '<script>window.B30_SERVER = ' . json_encode([
    'csrf'     => $csrf,
    'is_admin' => b30_is_admin(),
    'api'      => '/backend/api.php',
], JSON_UNESCAPED_SLASHES) . ';</script></head>';
echo str_replace('</head>', $inject, $html);
