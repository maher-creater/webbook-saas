<?php
/** 2030B P2P Pairing — Documentation page (PHP entry point). */
require __DIR__ . '/inc/bootstrap.php';
$csrf = b30_csrf_token();
$html = (string)file_get_contents(B30_PUBLIC . '/pages/docs.html');
$inject = '<script>window.B30_SERVER = ' . json_encode([
    'csrf' => $csrf, 'api' => '/backend/api.php',
], JSON_UNESCAPED_SLASHES) . ';</script></head>';
echo str_replace('</head>', $inject, $html);
