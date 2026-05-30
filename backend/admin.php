<?php
/**
 * 2030B P2P Pairing — Admin panel (PHP entry point).
 *
 * The admin sign-in form posts to /backend/api.php?op=admin.signin.
 * The page itself loads regardless of admin state; the JS hides/shows
 * gate vs panel based on the local admin session cookie, which is in
 * turn validated server-side on every admin.* API call.
 *
 * v3: the admin page is now a real .php file (pages/admin.php) for security.
 *     We render it via output-buffer and inject B30_SERVER as before.
 */
require __DIR__ . '/inc/bootstrap.php';
$csrf = b30_csrf_token();

// Render the admin page through PHP so its own header() block can run.
ob_start();
require B30_PUBLIC . '/pages/admin.php';
$html = (string)ob_get_clean();

$inject = '<script>window.B30_SERVER = ' . json_encode([
    'csrf'     => $csrf,
    'is_admin' => function_exists('b30_is_admin') ? b30_is_admin() : false,
    'api'      => '/backend/api.php',
], JSON_UNESCAPED_SLASHES) . ';</script></head>';
echo str_replace('</head>', $inject, $html);
