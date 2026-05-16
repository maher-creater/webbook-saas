<?php
/**
 * 2030B P2P Pairing — One-shot setup script.
 *
 * Usage:  php backend/setup.php [admin_password]
 *
 * - Creates required directories
 * - Initialises the system SQLite database
 * - Seeds an admin user (optional — admin panel uses config.json password)
 */
require __DIR__ . '/inc/bootstrap.php';

echo "Initialising 2030B P2P Pairing...\n";
b30_pdo();
echo "  - system.sqlite ready.\n";

// Optional: create a real admin row (the admin panel only uses the config.json password,
// but a real admin user lets you future-proof for per-admin audits).
if (!empty($argv[1])) {
    $pdo = b30_pdo();
    $email = 'admin@2030b.io';
    $hash  = password_hash($argv[1], PASSWORD_BCRYPT);
    $exists = $pdo->prepare('SELECT id FROM users WHERE email=?');
    $exists->execute([$email]);
    if (!$exists->fetch()) {
        $pdo->prepare(
            'INSERT INTO users(email,password_hash,full_name,country_code,language,level,
                               total_credits,total_pairings,created_at,is_active,role)
             VALUES(?,?,?,?,?,4,0,0,?,1,"admin")'
        )->execute([$email, $hash, 'Admin', 'TN', 'en', time()]);
        echo "  - admin@2030b.io created with provided password.\n";
    } else {
        echo "  - admin@2030b.io already exists.\n";
    }
}

// Touch fx
echo "  - fetching FX rates...\n";
$rates = b30_exchange_rates(true);
echo "  - cached " . count($rates['rates']) . " currencies.\n";

echo "Done.\n";
